<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Doctor;

class DoctorService
{
    /**
     * Get comprehensive patient statistics for a doctor
     * 
     * @param int $doctor_id Doctor ID
     * @return array Patient statistics
     */
    public static function getDoctorPatientStatistics($doctor_id)
    {
        global $wpdb;
        
        // Ensure doctor exists and is active
        $doctor = Doctor::find($doctor_id);
        if (!$doctor || $doctor->status !== 'active') {
            return null;
        }
        
        $visitations_table = $wpdb->prefix . 'hm_visitations';
        
        // Get total number of unique patients this doctor has seen
        $total_patients = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT patient_id) FROM $visitations_table WHERE doctor_id = %d", 
            $doctor_id
        ));
        
        // Count recent visits (last 30 days)
        $recent_visits = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $visitations_table 
            WHERE doctor_id = %d AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            $doctor_id
        ));
        
        // Count active patients (had a visit in the last 90 days)
        $active_patients = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT patient_id) FROM $visitations_table 
            WHERE doctor_id = %d AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
            $doctor_id
        ));
        
        return [
            'total_patients' => (int) ($total_patients ?: 0),
            'recent_visits' => (int) ($recent_visits ?: 0),
            'active_patients' => (int) ($active_patients ?: 0)
        ];
    }
    
    /**
     * Get patients for a doctor with pagination
     * 
     * @param int $doctor_id Doctor ID
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array|null Paginated patients data or null if doctor not found
     */
    public static function getDoctorPatients($doctor_id, $page = 1, $per_page = 20)
    {
        // Validate doctor exists and is active
        $doctor = Doctor::find($doctor_id);
        if (!$doctor || $doctor->status !== 'active') {
            return null;
        }
        
        // Use the Doctor model's getPatients method
        return Doctor::getPatients($doctor_id, $page, $per_page);
    }
    
    /**
     * Search and paginate doctors using the model
     * 
     * @param array $params Search parameters
     * @return array Paginated doctors with metadata
     */
    public static function searchDoctors($params = [])
    {
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
        
        // Use the Doctor model's searchAndPaginate method
        return Doctor::searchAndPaginate(
            $search,
            $page,
            $per_page,
            $status,
            $specialty,
            $orderby,
            $order
        );
    }
    
    /**
     * Get all available doctor specialties
     * 
     * @return array List of unique specialties
     */
    public static function getSpecialties()
    {
        // return Doctor::getUniqueSpecialties();
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
            $existing = Doctor::where('license_number', $data['license_number']);
            if (!empty($existing)) {
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
