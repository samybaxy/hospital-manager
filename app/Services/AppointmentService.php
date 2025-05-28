<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Appointment;
use Exception;

/**
 * Service class for appointment-related business logic
 */
class AppointmentService
{
    /**
     * Get appointments with enhanced filtering and pagination
     * 
     * @param array $query_params Query parameters including pagination, sorting, and filtering
     * @return array Appointment data with related information
     */
    public static function getAppointments(array $query_params = [])
    {
        global $wpdb;

        // Initialize parameters
        $params = $query_params['params'] ?? $query_params;
        
        // Default parameters
        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $perPage = isset($params['per_page']) ? max(1, intval($params['per_page'])) : 10;
        
        // Initialize tables
        $appointment_table = $wpdb->prefix . 'hm_appointments';
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        $patient_table = $wpdb->prefix . 'hm_patients';
        
        // Base query - join with doctor and patient tables to get names
        $query = "
            SELECT 
                a.*,
                COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'Unknown Doctor') as doctor_name,
                COALESCE(d.specialty, '') as doctor_specialty,
                COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Patient') as patient_name,
                p.phone as patient_phone
            FROM {$appointment_table} a
            LEFT JOIN {$doctor_table} d ON a.doctor_id = d.ID
            LEFT JOIN {$patient_table} p ON a.patient_id = p.ID
            WHERE 1=1
        ";
        
        $countQuery = "SELECT COUNT(a.ID) FROM {$appointment_table} a WHERE 1=1";
        $values = [];
        
        // Apply role-based restrictions
        $user = wp_get_current_user();
        if (in_array('patient', $user->roles)) {
            // Patients can only see their own appointments
            $query .= " AND a.patient_id = %d";
            $countQuery .= " AND a.patient_id = %d";
            $values[] = $user->ID;
            error_log('AppointmentService::getAppointments - Role restriction: patient can only see own appointments');
        } elseif (in_array('doctor', $user->roles)) {
            // Doctors can only see their own appointments if doctor_id not specified
            if (empty($params['doctor_id'])) {
                // Get doctor ID from users table
                $doctor_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$doctor_table} WHERE user_id = %d",
                    $user->ID
                ));
                if ($doctor_id) {
                    $query .= " AND a.doctor_id = %d";
                    $countQuery .= " AND a.doctor_id = %d";
                    $values[] = $doctor_id;
                    error_log('AppointmentService::getAppointments - Role restriction: doctor can only see own appointments');
                }
            }
        }
        
        // Apply filters if provided
        if (!empty($params['search'])) {
            $search = '%' . $wpdb->esc_like($params['search']) . '%';
            $query .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE %s OR CONCAT(d.first_name, ' ', d.last_name) LIKE %s OR a.reason LIKE %s)";
            $countQuery .= " AND (a.patient_id IN (SELECT ID FROM {$patient_table} WHERE CONCAT(first_name, ' ', last_name) LIKE %s) OR a.doctor_id IN (SELECT ID FROM {$doctor_table} WHERE CONCAT(first_name, ' ', last_name) LIKE %s) OR a.reason LIKE %s)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            error_log('AppointmentService::getAppointments - Filter by search: ' . print_r($params, true));
        }
        
        // Filter by doctor if specified
        if (!empty($params['doctor_id'])) {
            $query .= " AND a.doctor_id = %d";
            $countQuery .= " AND a.doctor_id = %d";
            $values[] = intval($params['doctor_id']);
            error_log('AppointmentService::getAppointments - Filter by doctor: ' . $params['doctor_id']);
        }
        
        // Filter by patient if specified
        if (!empty($params['patient_id'])) {
            $query .= " AND a.patient_id = %d";
            $countQuery .= " AND a.patient_id = %d";
            $values[] = intval($params['patient_id']);
            error_log('AppointmentService::getAppointments - Filter by patient: ' . $params['patient_id']);
        }
        
        // Filter by status if specified
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $query .= " AND a.status = %s";
            $countQuery .= " AND a.status = %s";
            $values[] = sanitize_text_field($params['status']);
            error_log('AppointmentService::getAppointments - Filter by status: ' . $params['status']);
        }
        
        // Handle upcoming filter
        if (!empty($params['upcoming']) && ($params['upcoming'] === 'true' || $params['upcoming'] === true)) {
            $today = date('Y-m-d');
            $query .= " AND (a.status = 'pending' OR a.status = 'confirmed') AND a.appointment_date >= %s";
            $countQuery .= " AND (a.status = 'pending' OR a.status = 'confirmed') AND a.appointment_date >= %s";
            $values[] = $today;
            error_log('AppointmentService::getAppointments - Filter by upcoming appointments');
        }
        
        // Handle date range filtering
        if (!empty($params['date_from'])) {
            $query .= " AND a.appointment_date >= %s";
            $countQuery .= " AND a.appointment_date >= %s";
            $values[] = sanitize_text_field($params['date_from']);
        }
        
        if (!empty($params['date_to'])) {
            $query .= " AND a.appointment_date <= %s";
            $countQuery .= " AND a.appointment_date <= %s";
            $values[] = sanitize_text_field($params['date_to']);
        }
        
        // Get total count for pagination
        $count_values = $values; // Copy values for count query
        $prepared_count = $wpdb->prepare($countQuery, $count_values);
        $total = (int)$wpdb->get_var($prepared_count);
        
        // Apply sorting
        $sortField = !empty($params['sort_by']) ? $params['sort_by'] : 'appointment_date';
        $sortOrder = !empty($params['sort_order']) && strtolower($params['sort_order']) === 'asc' ? 'ASC' : 'DESC';
        
        // Validate sort field to prevent SQL injection
        $allowed_sort_fields = ['ID', 'appointment_date', 'appointment_time', 'patient_name', 'doctor_name', 'status', 'reason'];
        if (!in_array($sortField, $allowed_sort_fields)) {
            $sortField = 'appointment_date'; // Default to appointment_date if invalid sort field
        }
        
        // Special case for patient name sorting
        if ($sortField === 'patient_name') {
            $query .= " ORDER BY patient_name {$sortOrder}, a.appointment_date DESC";
        } 
        // Special case for doctor name sorting
        else if ($sortField === 'doctor_name') {
            $query .= " ORDER BY doctor_name {$sortOrder}, a.appointment_date DESC";
        }
        // Standard field sorting
        else {
            $query .= " ORDER BY a.{$sortField} {$sortOrder}";
            // If sorting by date, add time as secondary sort
            if ($sortField === 'appointment_date') {
                $query .= ", a.appointment_time {$sortOrder}";
            }
        }
        
        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT %d OFFSET %d";
        $values[] = $perPage;
        $values[] = $offset;
        
        // Execute query
        $prepared_query = $wpdb->prepare($query, $values);
        
        $items = $wpdb->get_results($prepared_query);
        error_log('Query returned ' . count($items) . ' appointments');
        
        // Process results
        $appointments = [];
        if ($items) {
            foreach ($items as $item) {
                $appointmentArray = (array)$item;
                
                // Format the appointment data consistently
                $appointmentArray['date'] = $appointmentArray['appointment_date'];
                $appointmentArray['time'] = $appointmentArray['appointment_time'];
                
                // Ensure status has a default value
                if (empty($appointmentArray['status'])) {
                    $appointmentArray['status'] = 'pending';
                }
                
                // Ensure reason and notes have default values
                if (empty($appointmentArray['reason'])) {
                    $appointmentArray['reason'] = '';
                }
                
                if (empty($appointmentArray['notes'])) {
                    $appointmentArray['notes'] = '';
                }
                
                $appointments[] = $appointmentArray;
            }
        }
        
        // Calculate pagination info
        $last_page = ceil($total / $perPage);
        
        // Recalculate pagination info if we've applied client-side filtering
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $last_page = $total > 0 ? ceil($total / $perPage) : 1;
            
            error_log('Adjusted pagination after status filtering: total=' . $total . 
                ', lastPage=' . $last_page);
        }
        
        // Return data in a format consistent with existing API
        return [
            'appointments' => (object)[
                'items' => $appointments,
                'currentPage' => (int)$page,
                'lastPage' => $last_page,
                'perPage' => (int)$perPage,
                'total' => (int)$total
            ],
            'meta' => [
                'current_page' => (int)$page,
                'last_page' => $last_page,
                'per_page' => (int)$perPage,
                'total' => (int)$total
            ]
        ];
    }
}