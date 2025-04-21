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
        $required_fields = ['first_name', 'last_name', 'phone_number', 'sex'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check for duplicate patients
        $query = new Patient();
        $existing = $query->where('phone_number', $data['phone_number'])
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
        if (isset($data['phone_number']) && $data['phone_number'] !== $patient->phone_number) {
            $query = new Patient();
            $existing = $query->where('phone_number', $data['phone_number'])
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
        $query = new Patient();
        
        // Apply text search filters
        foreach (['first_name', 'last_name'] as $field) {
            if (!empty($params[$field])) {
                $query = $query->where($field, 'LIKE', '%' . $params[$field] . '%');
            }
        }
        
        // Apply exact match filters
        foreach (['hmo_id', 'sex'] as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $query = $query->where($field, $params[$field]);
            }
        }
        
        // Apply age range filter if provided
        if (!empty($params['age_min']) && is_numeric($params['age_min'])) {
            $query = $query->where('age', '>=', (int) $params['age_min']);
        }
        
        if (!empty($params['age_max']) && is_numeric($params['age_max'])) {
            $query = $query->where('age', '<=', (int) $params['age_max']);
        }
        
        // Add pagination
        $per_page = !empty($params['per_page']) ? (int) $params['per_page'] : 10;
        $page = !empty($params['page']) ? (int) $params['page'] : 1;
        
        $total = $query->count();
        $patients = $query->paginate($per_page, ['*'], 'page', $page);
        
        return [
            'patients' => $patients,
            'meta' => [
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil($total / $per_page)
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
}
