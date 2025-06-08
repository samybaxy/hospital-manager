<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use HospitalManager\Models\Patient;
use HospitalManager\Services\PatientService;
use WP_REST_Server;

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
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_patients'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_patient_records');
                },
            ]
        ]);

        // Routes for individual patient operations
        register_rest_route($this->namespace, '/patients/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_patient_records');
                },
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_patient_records');
                },
            ]
        ]);

        // Route for patient search
        register_rest_route($this->namespace, '/patients/search', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'search_patients'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ]
        ]);
        
        // Route for patients to view their own records
        register_rest_route($this->namespace, '/patients/me', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_own_patient_record'],
                'permission_callback' => function($request) {
                    // Only needs to be logged in (no special capability required)
                    return is_user_logged_in();
                },
            ]
        ]);
        
        // Route for a patient to view their own record
        register_rest_route($this->namespace, '/patients/me', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_own_patient_record'],
                'permission_callback' => function($request) {
                    // Only logged-in users can access their own records
                    return is_user_logged_in();
                },
            ]
        ]);

        // Route for patient visitation history
        register_rest_route($this->namespace, '/patients/(?P<ID>\d+)/visitations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_patient_visitations'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ]
        ]);

        // Get current patient profile
        register_rest_route($this->namespace, '/patient/profile', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_my_profile'],
                'permission_callback' => [$this, 'check_patient_permission']
            ]
        ]);

        // Update current patient profile
        register_rest_route($this->namespace, '/patient/profile', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_my_profile'],
                'permission_callback' => [$this, 'check_patient_permission']
            ]
        ]);
    }

    /**
     * Get all patients with HMO information and last visitation date
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function get_patients($request) 
    {
        try {
            // Use the enhanced service to handle pagination, filtering, and relationships
            $params = $request->get_params();
            
            // Log the incoming parameters for debugging
            // error_log('Patient API request params: ' . print_r($params, true));
            
            // Check explicitly for HMO filter and ensure it's an integer
            if (isset($params['hmo_id'])) {
                $params['hmo_id'] = (int)$params['hmo_id']; // Force integer type
            }
            
            $result = PatientService::getPatients($params);
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
            $patient_id = $request['ID'];
            $patient_model = Patient::find($patient_id);

            if (!$patient_model) {
                return $this->error_response('Patient not found', 404);
            }
            
            $patient = $patient_model->toArray();
            
            // Additional authorization check
            $current_user_id = get_current_user_id();
            
            // 1. If user is a patient, they can only view their own record
            if (current_user_can('patient') && !current_user_can('administrator') && !current_user_can('doctor')) {
                // Check if the patient record belongs to the current user
                if ($patient['user_id'] != $current_user_id) {
                    return $this->error_response('You do not have permission to view this patient record', 403);
                }
            }
            
            if (!empty($request['include_medical_history'])) {
                // Get patient with full medical history
                $data = PatientService::getPatientMedicalHistory($patient_id);
                return $this->success_response(
                    $data,
                    'Patient with medical history retrieved successfully'
                );
            } else {
                // Get the user's email from wp_users table
                if (isset($patient['user_id'])) {
                    $user = get_user_by('ID', $patient['user_id']);
                    if ($user) {
                        $patient['email'] = $user->user_email;
                    }
                }
                
                // Get the patient's last visitation date
                $last_visit = Patient::get_last_visitation_date($patient_id);
                
                if ($last_visit) {
                    $patient['last_visit_date'] = $last_visit;
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
                $patient->toArray(),
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
            $patient_id = (int) $request['ID'];
            $params = $request->get_params();
            unset($params['ID']); // Remove ID from parameters to update
            
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
            $patient_id = (int) $request['ID'];
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
    
    /**
     * Get the patient record for the currently logged-in user
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function get_own_patient_record($request) 
    {
        try {
            $current_user_id = get_current_user_id();
            
            if (!$current_user_id) {
                return $this->error_response('Not logged in', 401);
            }
            
            // Find the patient record associated with the current user
            global $wpdb;
            $table = $wpdb->prefix . 'hm_patients';
            $patient_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d", 
                $current_user_id
            ), ARRAY_A);
            
            if (!$patient_data) {
                return $this->error_response('No patient record found for this user', 404);
            }
            
            $patient = new Patient($patient_data);
            
            return $this->success_response(
                $patient->toArray(),
                'Patient record retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving patient record: ' . $e->getMessage(), 
                500
            );
        }
    }

    /**
     * Get patient visitation history
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function get_patient_visitations($request) 
    {
        try {
            $patient_id = $request['ID'];
            
            // Verify patient exists
            $patient = Patient::find($patient_id);
            if (!$patient) {
                return $this->error_response('Patient not found', 404);
            }
            
            // Get patient visitations using the model method
            $visitations = $patient->getVisitationHistory();
            
            return $this->success_response(
                $visitations,
                'Patient visitation history retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving patient visitation history: ' . $e->getMessage(), 
                500
            );
        }
    }
    
    /**
     * Check if current user has patient permissions
     */
    public function check_patient_permission($request) 
    {
        $user = wp_get_current_user();
        
        if (!$user || !$user->ID) {
            return false;
        }
        
        // Check if user has patient role
        return in_array('patient', $user->roles);
    }

    /**
     * Get current patient's profile
     */
    public function get_my_profile($request)
    {
        try {
            $user_id = get_current_user_id();
            
            // Find patient by user_id using the Patient model
            $patients = Patient::query()->where('user_id', $user_id)->get();
            
            if (empty($patients)) {
                return new WP_Error(
                    'profile_not_found',
                    'Patient profile not found',
                    ['status' => 404]
                );
            }
            
            $patient = $patients[0];
            
            // Get user information
            $user = get_userdata($user_id);
            $response = $patient->attributes;
            $response['email'] = $user->user_email;
            $response['user_registered'] = $user->user_registered;
            
            return new WP_REST_Response($response);
        } catch (\Exception $e) {
            error_log('Error getting patient profile: ' . $e->getMessage());
            return new WP_Error(
                'server_error',
                'Failed to retrieve profile information',
                ['status' => 500]
            );
        }
    }

    /**
     * Update current patient's profile
     */
    public function update_my_profile($request)
    {
        try {
            $user_id = get_current_user_id();
            
            // Find patient by user_id
            $patients = Patient::query()->where('user_id', $user_id)->get();
            
            if (empty($patients)) {
                return new WP_Error(
                    'profile_not_found',
                    'Patient profile not found',
                    ['status' => 404]
                );
            }
            
            $patient = $patients[0];
            $params = $request->get_params();
            
            // Fields that a patient can update about themselves
            if (isset($params['first_name'])) {
                $patient->first_name = sanitize_text_field($params['first_name']);
            }
            
            if (isset($params['last_name'])) {
                $patient->last_name = sanitize_text_field($params['last_name']);
            }
            
            if (isset($params['phone'])) {
                $patient->phone = sanitize_text_field($params['phone']);
            }
            
            // Save patient record
            $patient->save();
            
            // Return the updated profile
            $updated_patient = Patient::find($patient->ID);
            $user = get_userdata($user_id);
            $response = $updated_patient->attributes;
            $response['email'] = $user->user_email;
            $response['user_registered'] = $user->user_registered;
            
            return new WP_REST_Response($response);
        } catch (\Exception $e) {
            error_log('Error updating patient profile: ' . $e->getMessage());
            return new WP_Error(
                'update_failed',
                'Failed to update profile: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}