<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Patient;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\MedicalReport;
use Exception;

/**
 * Service class for patient-related business logic
 */
class PatientService extends BaseService
{
    /**
     * Create a new patient with validation
     *
     * @param array $data Patient data
     * @return Patient The created patient
     * @throws Exception If validation fails or patient creation fails
     */
    public static function createPatient(array $data)
    {        
        // Validate required fields using base service method
        $required_fields = ['first_name', 'last_name', 'phone', 'gender'];
        $validation_errors = self::validateRequiredFields($data, $required_fields);
        
        if (!empty($validation_errors)) {
            throw new Exception(implode('; ', $validation_errors));
        }
        
        // Check for duplicate patients
        self::preventDuplicates($data);
        
        // Validate phone number using base service method
        $phone_error = self::validatePhone($data['phone']);
        if ($phone_error) {
            throw new Exception($phone_error);
        }
        
        // Format phone number using base service method
        $data['phone'] = self::formatPhone($data['phone']);
        
        // Validate gender field
        if (!in_array(strtolower($data['gender']), ['male', 'female', 'other', 'm', 'f'])) {
            throw new Exception("Invalid value for gender. Expected 'Male', 'Female', 'Other', 'M', or 'F'");
        }
        
        // Validate age if provided
        if (isset($data['age'])) {
            $age_error = self::validateNumeric($data['age'], 'age');
            if ($age_error) {
                throw new Exception($age_error);
            }
        }
        
        // Validate bio_data if provided
        if (!empty($data['bio_data']) && is_string($data['bio_data'])) {
            if (self::safeJsonDecode($data['bio_data']) === null) {
                throw new Exception("Invalid JSON format for bio_data");
            }
        }
        
        // Check for duplicate patients using model search
        $existing_patients = Patient::all([
            'phone' => $data['phone'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name']
        ]);
            
        if (!empty($existing_patients)) {
            throw new Exception("A patient with this name and phone number already exists");
        }

        // Create wp_user entry: use var for debugging purposes
        $user_id = Patient::createWpUser($data);
        
        // Unset email field before creating the patient
        unset($data['email']);

        // Set the user_id in the patient data
        $data['user_id'] = $user_id;

        // Create the patient
        return Patient::create($data);
    }
    
    /**
     * Update an existing patient with validation
     *
     * @param int $ID Patient ID
     * @param array $data Patient data to update
     * @return Patient The updated patient
     * @throws Exception If validation fails or patient update fails
     */
    public static function updatePatient(int $ID, array $data)
    {
        $patient = Patient::find($ID);
        
        if (!$patient) {
            throw new Exception("Patient not found");
        }
        
        // Check for duplicate phone number if changed
        if (isset($data['phone']) && $data['phone'] !== $patient->phone) {
            // Use model search to check for duplicates
            global $wpdb;
            $table = (new Patient())->getTable();
            $existing_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE phone = %s AND ID != %d",
                $data['phone'],
                $ID
            ));
                
            if ($existing_count > 0) {
                throw new Exception("Another patient is already using this phone number");
            }
        }

        // Update wp_user entry
        if (isset($data['email']) && $data['email'] !== $patient->email) {
            $wp_user = Patient::updateWpUser($patient->user_id, $data);
        }
        
        // Unset email field before updating the patient
        unset($data['email']);

        // Update the patient
        $patient->update($data);
        
        return Patient::find($ID); // Reload fresh data
    }
    
    /**
     * Search patients by various criteria with caching
     * 
     * @param array $params Search parameters
     * @return array Search results with metadata
     */
    public static function searchPatients(array $params = [])
    {
        return self::executeCached('searchPatients', $params, function() use ($params) {
            // Leverage optimized Patient model search method for simple searches
            $search_term = $params['search'] ?? '';
            if (!empty($search_term) && empty(array_diff_key($params, ['search' => true]))) {
                $columns = ['first_name', 'last_name', 'phone'];
                return Patient::search($search_term, $columns);
            }
            
            // For complex filtered searches, use optimized query building
            global $wpdb;
            $table = (new Patient())->getTable();
            $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 20;
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            
            // Build optimized query with indexed columns
            $where_conditions = ['1=1'];
            $values = [];
            
            // Apply search filters efficiently
            if (!empty($params['first_name'])) {
                $where_conditions[] = 'first_name LIKE %s';
                $values[] = '%' . $wpdb->esc_like($params['first_name']) . '%';
            }
            
            if (!empty($params['last_name'])) {
                $where_conditions[] = 'last_name LIKE %s';
                $values[] = '%' . $wpdb->esc_like($params['last_name']) . '%';
            }
            
            if (!empty($params['gender'])) {
                // Optimized gender handling
                if ($params['gender'] === 'F') {
                    $where_conditions[] = 'gender IN (%s, %s)';
                    $values[] = 'F';
                    $values[] = 'Female';
                } else if ($params['gender'] === 'M') {
                    $where_conditions[] = 'gender IN (%s, %s)';
                    $values[] = 'M';
                    $values[] = 'Male';
                } else {
                    $where_conditions[] = 'gender = %s';
                    $values[] = $params['gender'];
                }
            }
            
            if (!empty($params['age_min'])) {
                $where_conditions[] = 'age >= %d';
                $values[] = (int)$params['age_min'];
            }
            
            if (!empty($params['age_max'])) {
                $where_conditions[] = 'age <= %d';
                $values[] = (int)$params['age_max'];
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Optimized count query
            $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
            $total = (int)$wpdb->get_var($wpdb->prepare($count_query, $values));
            
            // Build main query
            $query = "SELECT * FROM {$table} WHERE {$where_clause}";
            
            // Add sorting with index-friendly defaults
            $allowed_columns = ['first_name', 'last_name', 'age', 'gender', 'created_at'];
            if (!empty($params['sort_by']) && in_array($params['sort_by'], $allowed_columns)) {
                $direction = !empty($params['sort_dir']) && strtoupper($params['sort_dir']) === 'DESC' ? 'DESC' : 'ASC';
                $query .= " ORDER BY {$params['sort_by']} {$direction}";
            } else {
                $query .= " ORDER BY created_at DESC";
            }
            
            // Add pagination
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT %d OFFSET %d";
            $query_values = array_merge($values, [$perPage, $offset]);
            
            // Execute the query
            $prepared_query = $wpdb->prepare($query, $query_values);
            $items = $wpdb->get_results($prepared_query, ARRAY_A);
            
            return [
                'data' => $items ?: [],
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($total, $page * $perPage)
            ];
        });
    }
    
    /**
     * Get a patient's full medical history with caching
     *
     * @param int $patient_id Patient ID
     * @return array Medical history data
     * @throws Exception If patient not found
     */
    public static function getPatientMedicalHistory(int $patient_id)
    {
        return self::executeCached('getPatientMedicalHistory', ['patient_id' => $patient_id], function() use ($patient_id) {
            $patient = Patient::find($patient_id);
            
            if (!$patient) {
                throw new Exception("Patient not found");
            }
            
            // Use optimized model methods with caching
            $appointments = $patient->getAppointments();
            $visitation_history = $patient->getVisitationHistory();
            
            // Get medical reports if available
            $medical_reports = [];
            if (class_exists('HospitalManager\Models\MedicalReport')) {
                global $wpdb;
                $reports_table = $wpdb->prefix . 'hm_medical_reports';
                $medical_reports = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$reports_table} WHERE patient_id = %d ORDER BY created_at DESC",
                    $patient_id
                ), ARRAY_A);
            }
            
            // Get lab investigations using optimized model method
            $lab_investigations = [];
            if (class_exists('HospitalManager\Models\LabInvestigation')) {
                $lab_model = new \HospitalManager\Models\LabInvestigation();
                $lab_investigations = $lab_model->getForPatient($patient_id);
            }
            
            return [
                'patient' => $patient,
                'appointments' => $appointments ?: [],
                'visitation_history' => $visitation_history ?: [],
                'medical_reports' => $medical_reports ?: [],
                'lab_investigations' => $lab_investigations ?: []
            ];
        }, 1800); // Cache for 30 minutes
    }

    /**
     * Check for duplicate patients based on identifiers
     * 
     * @param array $data Patient data
     * @return bool True if this might be a duplicate
     */
    public static function preventDuplicates(array $data)
    {
        global $wpdb;
        
        if (empty($data['phone'])) {
            return false;
        }
        
        // Format the phone number consistently
        $phone = $data['phone'];
        if (substr($phone, 0, 1) !== '0' && strlen($phone) === 10) {
            $phone = '0' . $phone;
        }
        
        // Get the table name
        $table = (new Patient())->getTable();
        
        // First check by phone number, which is usually unique
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE phone = %s LIMIT 1", $phone);
        $existing = $wpdb->get_row($query);
        
        if ($existing) {
            throw new Exception("A patient with this name and phone number already exists");
        }
        
        // If we have first_name and last_name, check for a name match as well
        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE LOWER(first_name) = LOWER(%s) AND LOWER(last_name) = LOWER(%s) LIMIT 1",
                $data['first_name'],
                $data['last_name']
            );
            $existing = $wpdb->get_row($query);
            
            if ($existing) {
                throw new Exception("A patient with this name and phone number already exists");
            }
        }
        
        return false;
    }

    /**
     * Get patients with HMO information and last visitation date
     * 
     * @param array $query_params Query parameters including pagination, sorting, and filtering
     * @return array Patient data with related information
     */
    public static function getPatients(array $query_params = [])
    {
        global $wpdb;

        // Initialize parameters
        $params = $query_params['params'] ?? [];
        
        // Default parameters
        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $perPage = isset($params['per_page']) ? max(1, intval($params['per_page'])) : 10;
        
        // Initialize tables
        $patient_table = $wpdb->prefix . 'hm_patients';
        $hmo_table = $wpdb->prefix . 'hm_hmos';
        $visitation_table = $wpdb->prefix . 'hm_visitations';
        
        // Base query - join with HMO table to get HMO name
        $query = "
            SELECT 
                p.*,
                h.name as hmo_name,
                (
                    SELECT created_at 
                    FROM {$visitation_table} v
                    WHERE v.patient_id = p.ID
                    ORDER BY v.created_at DESC
                    LIMIT 1
                ) as last_visit_date
            FROM {$patient_table} p
            LEFT JOIN {$hmo_table} h ON p.hmo_id = h.ID
            WHERE 1=1
        ";
        
        $countQuery = "SELECT COUNT(p.ID) FROM {$patient_table} p WHERE 1=1";
        $values = [];
        
        // Apply filters if provided
        if (!empty($params['search'])) {
            $search = '%' . $wpdb->esc_like($params['search']) . '%';
            $query .= " AND (p.first_name LIKE %s OR p.last_name LIKE %s OR p.phone LIKE %s)";
            $countQuery .= " AND (p.first_name LIKE %s OR p.last_name LIKE %s OR p.phone LIKE %s)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            error_log('PatientService::getPatients - Filter by search: ' . print_r($params, true));
        }
        
        // Filter by gender if specified
        if (!empty($params['gender']) && $params['gender'] !== 'all') {
            $query .= " AND p.gender = %s";
            $countQuery .= " AND p.gender = %s";
            $values[] = $params['gender'];
            error_log('PatientService::getPatients - Filter by gender: ' . print_r($params, true));
        }
        
        // Filter by HMO if specified
        if (!empty($params['hmo_id'])) {
            $hmo_id = (int)$params['hmo_id'];
            error_log('Filtering patients by HMO ID: ' . $hmo_id . ' (type: ' . gettype($hmo_id) . ')');
            
            // Handle numeric comparison and NULL values properly
            $query .= " AND (CASE WHEN p.hmo_id IS NULL THEN 0 ELSE CAST(p.hmo_id AS SIGNED) END) = %d";
            $countQuery .= " AND (CASE WHEN p.hmo_id IS NULL THEN 0 ELSE CAST(p.hmo_id AS SIGNED) END) = %d";
            $values[] = $hmo_id;
            
            // Add an explicit check for NULL values to ensure accuracy
            if ($hmo_id === 0) {
                $query = str_replace("AND (CASE WHEN p.hmo_id IS NULL THEN 0 ELSE CAST(p.hmo_id AS SIGNED) END) = %d", "AND p.hmo_id IS NULL", $query);
                $countQuery = str_replace("AND (CASE WHEN p.hmo_id IS NULL THEN 0 ELSE CAST(p.hmo_id AS SIGNED) END) = %d", "AND p.hmo_id IS NULL", $countQuery);
                // Remove the parameter since we're not using it in the query anymore
                array_pop($values);
            }
        }
        
        // Get total count for pagination
        $count_values = $values; // Copy values for count query
        $prepared_count = $wpdb->prepare($countQuery, $count_values);
        $total = (int)$wpdb->get_var($prepared_count);
        
        // Apply sorting
        $sortField = !empty($params['sort_by']) ? $params['sort_by'] : 'last_name';
        $sortOrder = !empty($params['sort_order']) && strtolower($params['sort_order']) === 'desc' ? 'DESC' : 'ASC';
        
        // Validate sort field to prevent SQL injection
        $allowed_sort_fields = ['ID', 'first_name', 'last_name', 'gender', 'age', 'hmo_name', 'last_visit_date'];
        if (!in_array($sortField, $allowed_sort_fields)) {
            $sortField = 'last_name'; // Default to last_name if invalid sort field
        }
        
        // Special case for HMO name sorting
        if ($sortField === 'hmo_name') {
            $query .= " ORDER BY h.name {$sortOrder}, p.last_name ASC";
        } 
        // Special case for last visit date sorting
        else if ($sortField === 'last_visit_date') {
            $query .= " ORDER BY last_visit_date {$sortOrder}, p.last_name ASC";
        }
        // Standard field sorting
        else {
            $query .= " ORDER BY p.{$sortField} {$sortOrder}";
        }
        
        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT %d OFFSET %d";
        $values[] = $perPage;
        $values[] = $offset;
        
        // Execute query
        $prepared_query = $wpdb->prepare($query, $values);
        
        $items = $wpdb->get_results($prepared_query);            // Log the number of results returned
        error_log('Query returned ' . count($items) . ' patients');
        
        // Process results
        $patients = [];
        if ($items) {
            foreach ($items as $item) {
                $patientArray = (array)$item;
                
                // Format the last visit date in a user-friendly format if it exists
                if (!empty($patientArray['last_visit_date'])) {
                    $patientArray['last_visit_date'] = date('Y-m-d', strtotime($patientArray['last_visit_date']));
                }
                
                // Parse JSON fields if needed
                if (!empty($patientArray['bio_data']) && is_string($patientArray['bio_data'])) {
                    $decoded = json_decode($patientArray['bio_data'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $patientArray['bio_data'] = $decoded;
                    }
                }
                
                $patients[] = $patientArray;
            }
        }
        
        // Calculate pagination info
        $last_page = ceil($total / $perPage);
        
        // Recalculate pagination info if we've applied client-side filtering
        if (!empty($params['hmo_id'])) {
            $last_page = $total > 0 ? ceil($total / $perPage) : 1;
            
            error_log('Adjusted pagination after HMO filtering: total=' . $total . 
                ', lastPage=' . $last_page);
        }
        
        // Return data in a format consistent with existing API
        return [
            'patients' => (object)[
                'items' => $patients,
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
