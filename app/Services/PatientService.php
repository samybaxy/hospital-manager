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
        
        // Validate phone number format
        if (!preg_match('/^\d{10,15}$/', $data['phone'])) {
            throw new Exception("Invalid phone number format. Phone number should contain 10-15 digits only");
        }
        
        // Validate gender field
        if (!in_array(strtolower($data['gender']), ['male', 'female', 'other'])) {
            throw new Exception("Invalid value for gender. Expected 'Male', 'Female', or 'Other'");
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
     * Search for patients with advanced filtering
     *
     * @param array $params Search parameters
     * @return array Paginated patients with metadata
     */
    public static function searchPatients(array $params)
    {
        // Always use direct database query to get all patients to avoid ORM issues
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $query = "SELECT * FROM {$table}";
        $where_clauses = [];
        $query_params = [];
        
        // Build where clauses for filtering
        if (!empty($params['first_name'])) {
            $where_clauses[] = "first_name LIKE %s";
            $query_params[] = '%' . $wpdb->esc_like($params['first_name']) . '%';
        }
        
        if (!empty($params['last_name'])) {
            $where_clauses[] = "last_name LIKE %s";
            $query_params[] = '%' . $wpdb->esc_like($params['last_name']) . '%';
        }
        
        if (!empty($params['hmo_id'])) {
            $where_clauses[] = "hmo_id = %s";
            $query_params[] = $params['hmo_id'];
        }
        
        if (!empty($params['gender'])) {
            $where_clauses[] = "gender = %s";
            $query_params[] = $params['gender'];
        }
        
        // Apply age range filter if provided
        if (!empty($params['age_min']) && is_numeric($params['age_min'])) {
            $where_clauses[] = "age >= %d";
            $query_params[] = (int)$params['age_min'];
        }
        
        if (!empty($params['age_max']) && is_numeric($params['age_max'])) {
            $where_clauses[] = "age <= %d";
            $query_params[] = (int)$params['age_max'];
        }
        
        // Add WHERE clause to the query if we have conditions
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
            $query = $wpdb->prepare($query, $query_params);
        }
        
        // Add pagination if specified
        $per_page = !empty($params['per_page']) ? (int) $params['per_page'] : 10;
        $page = !empty($params['page']) ? (int) $params['page'] : 1;
        $offset = ($page - 1) * $per_page;
        
        // Get total count for pagination metadata
        $total_query = "SELECT COUNT(*) FROM ({$query}) as total_count";
        $total = $wpdb->get_var($total_query);
        
        // Add pagination to the main query
        $query .= " LIMIT %d OFFSET %d";
        $query = $wpdb->prepare($query, $per_page, $offset);
        
        // Execute the query
        $patients_data = $wpdb->get_results($query, ARRAY_A);
        
        $patients = [];
        foreach ($patients_data as $patient_data) {
            // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
            if (isset($patient_data['id']) && !isset($patient_data['ID'])) {
                $patient_data['ID'] = $patient_data['id'];
            }
            $patients[] = new Patient($patient_data);
        }
        
        return [
            'patients' => $patients,
            'meta' => [
                'total' => (int)$total,
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil((int)$total / $per_page)
            ]
        ];
    }
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
}
