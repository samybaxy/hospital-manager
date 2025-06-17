<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Doctor;

class DoctorService extends BaseService
{
    /**
     * Get comprehensive patient statistics for a doctor with caching
     * 
     * @param int $doctor_id Doctor ID
     * @return array Patient statistics
     */
    public static function getDoctorPatientStatistics($doctor_id)
    {
        return self::executeCached('getDoctorPatientStatistics', ['doctor_id' => $doctor_id], function() use ($doctor_id) {
            // Ensure doctor exists and is active using optimized model method
            $doctor = Doctor::find($doctor_id);
            if (!$doctor || $doctor->status !== 'active') {
                return null;
            }
            
            global $wpdb;
            $visitations_table = $wpdb->prefix . 'hm_visitations';
            
            // Optimized single query to get all statistics
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(DISTINCT patient_id) as total_patients,
                    COUNT(CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_visits,
                    COUNT(DISTINCT CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN patient_id END) as active_patients
                FROM $visitations_table 
                WHERE doctor_id = %d", 
                $doctor_id
            ), ARRAY_A);
            
            return [
                'total_patients' => (int) ($stats['total_patients'] ?: 0),
                'recent_visits' => (int) ($stats['recent_visits'] ?: 0),
                'active_patients' => (int) ($stats['active_patients'] ?: 0)
            ];
        }, 3600); // Cache for 1 hour
    }
    
    /**
     * Get patients for a doctor with pagination and caching
     * 
     * @param int $doctor_id Doctor ID
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array|null Paginated patients data or null if doctor not found
     */
    public static function getDoctorPatients($doctor_id, $page = 1, $per_page = 20)
    {
        return self::executeCached('getDoctorPatients', [
            'doctor_id' => $doctor_id,
            'page' => $page,
            'per_page' => $per_page
        ], function() use ($doctor_id, $page, $per_page) {
            // Validate doctor exists and is active
            $doctor = Doctor::find($doctor_id);
            if (!$doctor || $doctor->status !== 'active') {
                return null;
            }
            
            global $wpdb;
            $visitations_table = $wpdb->prefix . 'hm_visitations';
            $patients_table = $wpdb->prefix . 'hm_patients';
            $offset = ($page - 1) * $per_page;
            
            // Optimized count query
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT patient_id) FROM $visitations_table WHERE doctor_id = %d",
                $doctor_id
            ));
            
            // Optimized paginated query with better indexing
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, v.last_visit_date, v.visit_count, v.last_visit_time
                FROM $patients_table p
                INNER JOIN (
                    SELECT patient_id, 
                           MAX(date) as last_visit_date,
                           MAX(time) as last_visit_time,
                           COUNT(*) as visit_count
                    FROM $visitations_table 
                    WHERE doctor_id = %d 
                    GROUP BY patient_id
                    ORDER BY last_visit_date DESC
                    LIMIT %d OFFSET %d
                ) v ON p.ID = v.patient_id
                ORDER BY v.last_visit_date DESC",
                $doctor_id, $per_page, $offset
            ), ARRAY_A);
            
            return [
                'data' => $results ?: [],
                'total' => (int) ($total ?: 0),
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil(($total ?: 0) / $per_page)
            ];
        }, 900); // Cache for 15 minutes
    }
    
    /**
     * Search and paginate doctors using optimized model caching
     * 
     * @param array $params Search parameters
     * @return array Paginated doctors with metadata
     */
    public static function searchDoctors($params = [])
    {
        return self::executeCached('searchDoctors', $params, function() use ($params) {
            // Extract parameters with defaults
            $search = $params['search'] ?? '';
            $per_page = (int) ($params['per_page'] ?? 10);
            $page = (int) ($params['page'] ?? 1);
            $orderby = $params['orderby'] ?? 'last_name';
            $order = $params['order'] ?? 'asc';
            $specialty = $params['specialty'] ?? '';
            $status = $params['status'] ?? 'active';
            
            // Handle 'all' specialty as empty string
            if ($specialty === 'all') {
                $specialty = '';
            }
            
            // Use the Doctor model's cached searchAndPaginate method
            return Doctor::searchAndPaginate(
                $search,
                $page,
                $per_page,
                $status,
                $specialty,
                $orderby,
                $order
            );
        }, 1800); // Cache for 30 minutes
    }
    
    /**
     * Get all available doctor specialties with caching
     * 
     * @return array List of unique specialties
     */
    public static function getSpecialties()
    {
        return self::executeCached('getSpecialties', [], function() {
            return Doctor::getSpecialties();
        }, 7200); // Cache for 2 hours (specialties rarely change)
    }
    
    /**
     * Validate doctor data before creation/update
     * 
     * @param array $data Doctor data
     * @param bool $is_update Whether this is an update operation
     * @return array|true Returns validation errors array or true if valid
     */
    public static function validateDoctorData($data, $is_update = false)
    {
        $errors = [];
        
        // Required fields for creation
        if (!$is_update) {
            $required_fields = ['first_name', 'last_name', 'phone', 'specialty', 'license_number'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    $errors[] = "Missing required field: {$field}";
                }
            }
        }
        
        // Validate appointment_availability JSON if provided
        if (isset($data['appointment_availability'])) {
            if (is_string($data['appointment_availability'])) {
                $decoded = json_decode($data['appointment_availability'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'Invalid appointment availability format';
                }
            }
        }
        
        // Validate license number uniqueness for new doctors
        if (!$is_update && !empty($data['license_number'])) {
            global $wpdb;
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $doctors_table WHERE license_number = %s",
                $data['license_number']
            ));
            if ($existing > 0) {
                $errors[] = 'License number already exists';
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Format appointment availability data
     * 
     * @param mixed $availability Availability data (string or array)
     * @return string JSON encoded availability
     */
    public static function formatAppointmentAvailability($availability)
    {
        if (is_string($availability)) {
            // Validate JSON
            $decoded = json_decode($availability, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $availability;
            } else {
                throw new \Exception('Invalid appointment availability JSON format');
            }
        } else {
            // Convert array to JSON string
            return json_encode($availability);
        }
    }
    
    /**
     * Check if a doctor has existing appointments
     * 
     * @param int $doctor_id Doctor ID
     * @return bool True if doctor has appointments
     */
    public static function hasAppointments($doctor_id)
    {
        global $wpdb;
        
        // Check if appointments table exists
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$appointments_table'");
        
        if (!$table_exists) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE doctor_id = %d",
            $doctor_id
        ));
        
        return (int) $count > 0;
    }
}
