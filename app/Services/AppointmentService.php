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
    
    /**
     * Get booking data for appointment booking form
     * 
     * @param int $doctor_id Doctor ID
     * @return array Doctor information and available dates
     * @throws Exception If doctor not found
     */
    public static function getBookingData($doctor_id)
    {
        global $wpdb;
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        
        $doctor = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$doctors_table} WHERE ID = %d",
                $doctor_id
            ),
            ARRAY_A
        );
        
        if (!$doctor) {
            throw new Exception('Doctor not found');
        }
        
        // Get available dates
        $available_dates = self::getAvailableDates($doctor_id);
        
        return [
            'doctor' => $doctor,
            'available_dates' => $available_dates['dates'] ?? [],
            'doctor_id' => $doctor_id
        ];
    }
    
    /**
     * Get available time slots for a doctor on a specific date
     * 
     * @param int $doctor_id Doctor ID
     * @param string $date Date in Y-m-d format
     * @return array Available time slots
     */
    public static function getAvailability($doctor_id, $date = null)
    {
        if (!$date) {
            return self::getAvailableDates($doctor_id);
        }
        
        global $wpdb;
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        
        // Get the day of the week from the date
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        // Get doctor's availability from the database
        $doctor_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT appointment_availability FROM {$doctor_table} WHERE ID = %d",
                $doctor_id
            )
        );
        
        // Default working hours if no custom availability set
        $working_hours = [
            'start' => '09:00:00',
            'end' => '17:00:00',
            'slot_duration' => 30 // minutes
        ];
        
        $available_slots = [];
        
        // If doctor has custom availability
        if ($doctor_data && !empty($doctor_data->appointment_availability)) {
            $availability = json_decode($doctor_data->appointment_availability, true);
            
            // Check if doctor works on this day
            if (isset($availability[$day_of_week]) && !empty($availability[$day_of_week])) {
                foreach ($availability[$day_of_week] as $time_slot) {
                    // Generate 30-minute slots between start and end times
                    $start_time = self::ensureTimeFormat($time_slot['start']);
                    $end_time = self::ensureTimeFormat($time_slot['end']);
                    
                    for ($time = strtotime($start_time); $time < strtotime($end_time); $time += 30 * 60) {
                        $available_slots[] = date('H:i:s', $time);
                    }
                }
            } else {
                // Doctor doesn't work on this day
                return [
                    'date' => $date,
                    'available_slots' => [],
                    'message' => 'The doctor is not available on this day.'
                ];
            }
        } else {
            // Use default working hours
            $start_time = strtotime($working_hours['start']);
            $end_time = strtotime($working_hours['end']);
            
            for ($time = $start_time; $time < $end_time; $time += $working_hours['slot_duration'] * 60) {
                $available_slots[] = date('H:i:s', $time);
            }
        }
        
        // Get existing appointments for this doctor on this date
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        $booked_times = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT appointment_time FROM {$appointments_table} 
                WHERE doctor_id = %d AND appointment_date = %s AND status != 'cancelled'",
                $doctor_id, $date
            )
        );
        
        // Remove already booked slots
        $available_slots = array_filter($available_slots, function($slot) use ($booked_times) {
            return !in_array($slot, $booked_times);
        });
        
        return [
            'date' => $date,
            'available_slots' => array_values($available_slots) // Reset array indexes
        ];
    }
    
    /**
     * Check if a time slot is available for booking
     * 
     * @param int $doctor_id Doctor ID
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i:s format
     * @return bool True if slot is available, false otherwise
     */
    public static function isSlotAvailable($doctor_id, $date, $time)
    {
        global $wpdb;
        
        // Check if requested time is within doctor's availability hours
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        // Get doctor's availability
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        $doctor_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT appointment_availability FROM {$doctor_table} WHERE ID = %d",
                $doctor_id
            )
        );
        
        // First check if the doctor works on this day/time
        $is_available = false;
        
        if ($doctor_data && !empty($doctor_data->appointment_availability)) {
            $availability = json_decode($doctor_data->appointment_availability, true);
            
            if (isset($availability[$day_of_week]) && !empty($availability[$day_of_week])) {
                $request_time = strtotime($time);
                
                // Check each time slot for this day
                foreach ($availability[$day_of_week] as $time_slot) {
                    $start_time = strtotime(self::ensureTimeFormat($time_slot['start']));
                    $end_time = strtotime(self::ensureTimeFormat($time_slot['end']));
                    
                    if ($request_time >= $start_time && $request_time < $end_time) {
                        $is_available = true;
                        break;
                    }
                }
            }
        } else {
            // Default working hours if no custom availability (9 AM to 5 PM)
            $request_hour = (int)date('H', strtotime($time));
            if ($request_hour >= 9 && $request_hour < 17) {
                $is_available = true;
            }
        }
        
        // If not available based on schedule, return false immediately
        if (!$is_available) {
            return false;
        }
        
        // Check if the slot is already booked
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        $existing_appointment = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$appointments_table} 
                WHERE doctor_id = %d 
                AND appointment_date = %s 
                AND appointment_time = %s 
                AND status != 'cancelled'",
                $doctor_id, $date, $time
            )
        );
        
        return $existing_appointment == 0;
    }
    
    /**
     * Get appointment statistics for a doctor
     * 
     * @param int $doctor_id Doctor ID
     * @return array Appointment statistics
     * @throws Exception If doctor not found or database error
     */
    public static function getAppointmentStats($doctor_id)
    {
        global $wpdb;
        
        // Verify doctor exists first
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        $doctor_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$doctor_table} WHERE ID = %d",
            $doctor_id
        ));
        
        if (!$doctor_exists) {
            throw new Exception('Doctor not found');
        }
        
        $table_name = $wpdb->prefix . 'hm_appointments';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if (!$table_exists) {
            throw new Exception('Appointments table not found');
        }
        
        // Get all appointments count for this doctor (excluding cancelled)
        $total_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND (status != 'cancelled' OR status IS NULL OR status = '')",
            $doctor_id
        ));
        
        // Get appointments by status
        $pending_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'pending'",
            $doctor_id
        ));
        
        $confirmed_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'confirmed'",
            $doctor_id
        ));
        
        $completed_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'completed'",
            $doctor_id
        ));
        
        $cancelled_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'cancelled'",
            $doctor_id
        ));
        
        // Get upcoming appointments (confirmed + pending for future dates)
        $today = date('Y-m-d');
        $upcoming_appointments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE doctor_id = %d 
             AND (status = 'pending' OR status = 'confirmed') 
             AND appointment_date >= %s",
            $doctor_id, $today
        ));
        
        return [
            'totalAppointments' => (int) ($total_appointments ?? 0),
            'pendingAppointments' => (int) ($pending_appointments ?? 0),
            'confirmedAppointments' => (int) ($confirmed_appointments ?? 0),
            'completedAppointments' => (int) ($completed_appointments ?? 0),
            'cancelledAppointments' => (int) ($cancelled_appointments ?? 0),
            'upcomingAppointments' => (int) ($upcoming_appointments ?? 0)
        ];
    }
    
    /**
     * Get available dates for a doctor (next 30 days)
     * 
     * @param int $doctor_id Doctor ID
     * @return array Available dates
     */
    public static function getAvailableDates($doctor_id)
    {
        global $wpdb;
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        
        // Get doctor's availability settings
        $doctor_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT appointment_availability FROM {$doctor_table} WHERE ID = %d",
                $doctor_id
            )
        );
        
        $availability = [];
        if ($doctor_data && !empty($doctor_data->appointment_availability)) {
            $availability = json_decode($doctor_data->appointment_availability, true);
        }
        
        // Default working days if no custom availability
        $default_working_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        
        $available_dates = [];
        $start_date = strtotime('today');
        $end_date = strtotime('+30 days');
        
        for ($date = $start_date; $date <= $end_date; $date += 86400) { // 86400 seconds = 1 day
            $day_of_week = strtolower(date('l', $date));
            $date_string = date('Y-m-d', $date);
            
            // Skip past dates
            if ($date < strtotime('today')) {
                continue;
            }
            
            $is_available = false;
            
            // Check if doctor works on this day
            if (!empty($availability)) {
                $is_available = isset($availability[$day_of_week]) && !empty($availability[$day_of_week]);
            } else {
                // Use default working days
                $is_available = in_array($day_of_week, $default_working_days);
            }
            
            $available_dates[] = [
                'date' => $date_string,
                'day' => ucfirst($day_of_week),
                'available' => $is_available
            ];
        }
        
        return [
            'dates' => $available_dates,
            'doctor_id' => $doctor_id
        ];
    }
    
    /**
     * Ensure time is in HH:MM:SS format
     * 
     * @param string $time Time string
     * @return string Formatted time
     */
    private static function ensureTimeFormat($time) 
    {
        // If time is in HH:MM format, convert to HH:MM:SS
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time . ':00';
        }
        return $time;
    }
}