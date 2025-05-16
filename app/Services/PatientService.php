<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Patient;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\MedicalReport;
use Exception;

/**
 * Service class for patient-related business logic
 */
class PatientService
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
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'phone', 'gender'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check for duplicate patients
        self::preventDuplicates($data);
        
        // Validate phone number format
        if (!preg_match('/^\d{10,15}$/', $data['phone'])) {
            throw new Exception("Invalid phone number format. Phone number should contain 10-15 digits only");
        }
        
        // Ensure phone number format consistency
        if (substr($data['phone'], 0, 1) !== '0' && strlen($data['phone']) === 10) {
            $data['phone'] = '0' . $data['phone'];
        }
        
        // Validate gender field
        if (!in_array(strtolower($data['gender']), ['male', 'female', 'other', 'm', 'f'])) {
            throw new Exception("Invalid value for gender. Expected 'Male', 'Female', 'Other', 'M', or 'F'");
        }
        
        // Validate age if provided
        if (isset($data['age']) && !is_numeric($data['age'])) {
            throw new Exception("Invalid age value. Age must be a number");
        }
        
        // Validate bio_data if provided
        if (!empty($data['bio_data']) && is_string($data['bio_data'])) {
            $decoded = json_decode($data['bio_data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON format for bio_data");
            }
        }
        
        // Check for duplicate patients
        $query = new Patient();
        $existing = $query->where('phone', $data['phone'])
            ->where('first_name', $data['first_name'])
            ->where('last_name', $data['last_name'])
            ->get();
            
        if (!empty($existing)) {
            throw new Exception("A patient with this name and phone number already exists");
        }
        
        // Create the patient
        return Patient::create($data);
    }
    
    /**
     * Update an existing patient with validation
     *
     * @param int $id Patient ID
     * @param array $data Patient data to update
     * @return Patient The updated patient
     * @throws Exception If validation fails or patient update fails
     */
    public static function updatePatient(int $id, array $data)
    {
        $patient = Patient::find($id);
        
        if (!$patient) {
            throw new Exception("Patient not found");
        }
        
        // Check for duplicate phone number if changed
        if (isset($data['phone']) && $data['phone'] !== $patient->phone) {
            $query = new Patient();
            $existing = $query->where('phone', $data['phone'])
                ->where('id', '!=', $id)
                ->get();
                
            if (!empty($existing)) {
                throw new Exception("Another patient is already using this phone number");
            }
        }
        
        // Update the patient
        $patient->update($data);
        
        return Patient::find($id); // Reload fresh data
    }
    
    /**
     * Search patients by various criteria
     * 
     * @param array $params Search parameters
     * @return array Search results with metadata
     */
    public static function searchPatients(array $params = [])
    {
        global $wpdb;
        $table = (new Patient())->getTable();
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 20;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        
        // Build the query directly for better control
        $query = "SELECT * FROM {$table} WHERE 1=1";
        $count_query = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
        $values = [];
        
        // Apply search filters
        if (!empty($params['first_name'])) {
            $query .= " AND first_name LIKE %s";
            $count_query .= " AND first_name LIKE %s";
            $values[] = '%' . $wpdb->esc_like($params['first_name']) . '%';
        }
        
        if (!empty($params['last_name'])) {
            $query .= " AND last_name LIKE %s";
            $count_query .= " AND last_name LIKE %s";
            $values[] = '%' . $wpdb->esc_like($params['last_name']) . '%';
        }
        
        if (!empty($params['gender'])) {
            // Special handling for gender
            if ($params['gender'] === 'F') {
                $query .= " AND (gender = %s OR gender = %s)";
                $count_query .= " AND (gender = %s OR gender = %s)";
                $values[] = 'F';
                $values[] = 'Female';
            } else if ($params['gender'] === 'M') {
                $query .= " AND (gender = %s OR gender = %s)";
                $count_query .= " AND (gender = %s OR gender = %s)";
                $values[] = 'M';
                $values[] = 'Male';
            } else {
                $query .= " AND gender = %s";
                $count_query .= " AND gender = %s";
                $values[] = $params['gender'];
            }
        }
        
        if (!empty($params['age_min'])) {
            $query .= " AND age >= %d";
            $count_query .= " AND age >= %d";
            $values[] = (int)$params['age_min'];
        }
        
        if (!empty($params['age_max'])) {
            $query .= " AND age <= %d";
            $count_query .= " AND age <= %d";
            $values[] = (int)$params['age_max'];
        }
        
        // Count total results
        $count_values = $values; // Copy values for count query
        $count_sql = $wpdb->prepare($count_query, $count_values);
        $total = (int)$wpdb->get_var($count_sql);
        
        // Add sorting
        if (!empty($params['sort_by'])) {
            $direction = !empty($params['sort_dir']) ? $params['sort_dir'] : 'ASC';
            $allowed_columns = ['first_name', 'last_name', 'age', 'gender', 'created_at'];
            $sort_column = in_array($params['sort_by'], $allowed_columns) ? $params['sort_by'] : 'created_at';
            $query .= " ORDER BY {$sort_column} " . ($direction === 'DESC' ? 'DESC' : 'ASC');
        } else {
            // Default sorting
            $query .= " ORDER BY created_at DESC";
        }
        
        // Add pagination
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT %d OFFSET %d";
        $values[] = $perPage;
        $values[] = $offset;
        
        // Execute the query
        $prepared_query = $wpdb->prepare($query, $values);
        $items = $wpdb->get_results($prepared_query);
        
        // Convert results directly to plain arrays with accessible properties
        $patients = [];
        if ($items) {
            foreach ($items as $item) {
                // Convert the database row directly to an array
                $patientArray = (array)$item;
                
                // Make sure we have consistent ID fields
                if (isset($patientArray['id']) && !isset($patientArray['ID'])) {
                    $patientArray['ID'] = $patientArray['id'];
                } elseif (isset($patientArray['ID']) && !isset($patientArray['id'])) {
                    $patientArray['id'] = $patientArray['ID'];
                }
                
                // Parse JSON fields if needed
                if (!empty($patientArray['bio_data']) && is_string($patientArray['bio_data'])) {
                    $decoded = json_decode($patientArray['bio_data'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $patientArray['bio_data'] = $decoded;
                    }
                }
                
                // Add the patient array to our collection
                $patients[] = $patientArray;
            }
        }
        
        // Calculate pagination info
        $last_page = ceil($total / $perPage);
        
        return [
            'patients' => (object)[
                'items' => $patients,
                'currentPage' => $page,
                'lastPage' => $last_page,
                'perPage' => $perPage,
                'total' => $total
            ],
            'meta' => [
                'current_page' => $page,
                'last_page' => $last_page,
                'per_page' => $perPage,
                'total' => $total
            ]
        ];
    }
    
    /**
     * Get a patient's full medical history
     *
     * @param int $patient_id Patient ID
     * @return array Medical history data
     * @throws Exception If patient not found
     */
    public static function getPatientMedicalHistory(int $patient_id)
    {
        $patient = Patient::find($patient_id);
        
        if (!$patient) {
            throw new Exception("Patient not found");
        }
        
        // Get associated records
        $appointmentQuery = new Appointment();
        $appointments = $appointmentQuery->where('patient_id', $patient_id)
            ->orderBy('appointment_date', 'DESC')
            ->get();
            
        $medicalReportQuery = new MedicalReport();
        $medical_reports = $medicalReportQuery->where('patient_id', $patient_id)
            ->orderBy('created_at', 'DESC')
            ->get();
        
        // Get related lab investigations - assuming there's a relationship in the patient model
        $lab_investigations = [];
        if (method_exists($patient, 'labInvestigations')) {
            $lab_investigations = $patient->labInvestigations()->orderBy('created_at', 'DESC')->get();
        }
        
        return [
            'patient' => $patient,
            'appointments' => $appointments,
            'medical_reports' => $medical_reports,
            'lab_investigations' => $lab_investigations
        ];
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
                    WHERE v.patient_id = p.id
                    ORDER BY v.created_at DESC
                    LIMIT 1
                ) as last_visit_date
            FROM {$patient_table} p
            LEFT JOIN {$hmo_table} h ON p.hmo_id = h.id
            WHERE 1=1
        ";
        
        $countQuery = "SELECT COUNT(p.id) FROM {$patient_table} p WHERE 1=1";
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
        $allowed_sort_fields = ['id', 'first_name', 'last_name', 'gender', 'age', 'hmo_name', 'last_visit_date'];
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
                
                // Make sure we have consistent ID fields
                if (isset($patientArray['id']) && !isset($patientArray['ID'])) {
                    $patientArray['ID'] = $patientArray['id'];
                } elseif (isset($patientArray['ID']) && !isset($patientArray['id'])) {
                    $patientArray['id'] = $patientArray['ID'];
                }
                
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
