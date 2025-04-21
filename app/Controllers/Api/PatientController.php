<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\Patient;
use HospitalManager\Services\PatientService;

class PatientController extends BaseController 
{
    /**
     * Register all routes for the Patient API
     * 
     * @return void
     */
    public function register_routes() 
    {
        // Route for listing and creating patients
        register_rest_route($this->namespace, '/patients', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_patients'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_patient_records');
                },
            ]
        ]);

        // Routes for individual patient operations
        register_rest_route($this->namespace, '/patients/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_patient_records');
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_patient_records');
                },
            ]
        ]);

        // Route for patient search
        register_rest_route($this->namespace, '/patients/search', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'search_patients'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ]
        ]);
    }

    /**
     * Get all patients
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function get_patients($request) 
    {
        try {
            // Use the service to handle pagination and filtering
            $params = $request->get_params();
            $result = PatientService::searchPatients($params);
            
            return $this->success_response(
                $result, 
                'Patients retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving patients: ' . $e->getMessage(), 
                500
            );
        }
    }

    /**
     * Get a single patient by ID
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function get_patient($request) 
    {
        try {
            $patient_id = $request['id'];
            
            if (!empty($request['include_medical_history'])) {
                // Get patient with full medical history
                $data = PatientService::getPatientMedicalHistory($patient_id);
                return $this->success_response(
                    $data,
                    'Patient with medical history retrieved successfully'
                );
            } else {
                // Get just the patient data
                $patient = Patient::find($patient_id);
                if (!$patient) {
                    return $this->error_response('Patient not found', 404);
                }
                return $this->success_response(
                    $patient,
                    'Patient retrieved successfully'
                );
            }
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving patient: ' . $e->getMessage(), 
                500
            );
        }
    }

    /**
     * Create a new patient
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function create_patient($request) 
    {
        try {
            $patient = PatientService::createPatient($request->get_params());
            return $this->success_response(
                $patient, 
                'Patient created successfully', 
                201
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error creating patient: ' . $e->getMessage(), 
                400
            );
        }
    }

    /**
     * Update an existing patient
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function update_patient($request) 
    {
        try {
            $patient_id = (int) $request['id'];
            $params = $request->get_params();
            unset($params['id']); // Remove id from parameters to update
            
            $updated_patient = PatientService::updatePatient($patient_id, $params);
            
            return $this->success_response(
                $updated_patient, 
                'Patient updated successfully'
            );
        } catch (\Exception $e) {
            $status = $e->getMessage() === 'Patient not found' ? 404 : 400;
            return $this->error_response(
                'Error updating patient: ' . $e->getMessage(), 
                $status
            );
        }
    }
    
    /**
     * Delete a patient
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function delete_patient($request) 
    {
        try {
            $patient_id = (int) $request['id'];
            $patient = Patient::find($patient_id);
            
            if (!$patient) {
                return $this->error_response('Patient not found', 404);
            }
            
            // Check if it's safe to delete (could add this to PatientService)
            $has_appointments = method_exists($patient, 'appointments') && 
                               $patient->appointments()->count() > 0;
                
            if ($has_appointments) {
                return $this->error_response(
                    'Cannot delete patient with existing appointments. Archive instead.', 
                    400
                );
            }
            
            $deleted = $patient->delete();
            
            if (!$deleted) {
                return $this->error_response('Failed to delete patient', 500);
            }
            
            return $this->success_response(
                null, 
                'Patient deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error deleting patient: ' . $e->getMessage(), 
                500
            );
        }
    }
    
    /**
     * Search for patients based on query parameters
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function search_patients($request) 
    {
        try {
            $params = $request->get_params();
            $result = PatientService::searchPatients($params);
            
            return $this->success_response($result, 'Patients retrieved successfully');
        } catch (\Exception $e) {
            return $this->error_response(
                'Error searching patients: ' . $e->getMessage(), 
                500
            );
        }
    }
}