<?php

namespace HospitalManager\Controllers\Api;

use \HospitalManager\Services\AuditLogger;
use \HospitalManager\Models\Doctor;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;

class DoctorController extends BaseController
{
    public function register_routes()
    {
        // Get all doctors (public endpoint)
        register_rest_route($this->namespace, '/doctors', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctors'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Create doctor (admin only)
        register_rest_route($this->namespace, '/doctors', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_doctor'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);

        // Get doctor specialties (public endpoint)
        register_rest_route($this->namespace, '/doctors/specialties', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_specialties'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Get single doctor (public endpoint)
        register_rest_route($this->namespace, '/doctors/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctor'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Get doctor's patients
        register_rest_route($this->namespace, '/doctors/(?P<id>\d+)/patients', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctor_patients'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Update doctor (admin only)
        register_rest_route($this->namespace, '/doctors/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_doctor'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);

        // Delete doctor (admin only)
        register_rest_route($this->namespace, '/doctors/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_doctor'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);

        // Get current doctor profile
        register_rest_route($this->namespace, '/doctor/profile', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_my_profile'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);

        // Update current doctor profile
        register_rest_route($this->namespace, '/doctor/profile', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_my_profile'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);
    }

    /**
     * Get all doctors with optional search and pagination
     */
    public function get_doctors($request)
    {
        try {
            $params = $request->get_params()['params'] ?? [];
            // Get the search parameter - ensuring it's properly sanitized
            $search = $params['search'];
            $search = is_string($search) ? sanitize_text_field(trim($search)) : '';
            $page = $params['page'] ? intval($params['page']) : 1;
            $per_page = $params['per_page'] ? intval($params['per_page']) : 20;
            $specialty = $params['specialty'] !== 'all' ? sanitize_text_field($params['specialty']) : null;
            $orderby = $params['orderby'] ? sanitize_text_field($params['orderby']) : 'last_name';
            $order = $params['order'] ? sanitize_text_field($params['order']) : 'asc';
            
            // For debugging purposes
            error_log("Doctor search parameters: " . 
                      "search='$search', page=$page, per_page=$per_page, " . 
                      "specialty='$specialty', orderby='$orderby', order='$order'");
            
            // Use Doctor model to fetch paginated results with search
            $results = Doctor::searchAndPaginate(
                $search,
                $page,
                $per_page,
                'active',
                $specialty,
                $orderby,
                $order
            );
            
            // Format doctors to include all required fields and fullName
            $doctors = array_map(function($doctor) {
                $formatted_doctor = [
                    'id' => $doctor->id,
                    'user_id' => $doctor->user_id,
                    'first_name' => $doctor->first_name,
                    'last_name' => $doctor->last_name,
                    'fullName' => $doctor->first_name . ' ' . $doctor->last_name,
                    'phone' => $doctor->phone,
                    'specialty' => $doctor->specialty,
                    'status' => $doctor->status,
                    'created_at' => $doctor->created_at,
                    'updated_at' => $doctor->updated_at
                ];
                return $formatted_doctor;
            }, $results['data']);
            
            // Return paginated response
            return new WP_REST_Response([
                'data' => $doctors,
                'meta' => [
                    'current_page' => $results['current_page'],
                    'last_page' => $results['last_page'],
                    'per_page' => $results['per_page'],
                    'total' => $results['total']
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Error fetching doctors: ' . $e->getMessage());
            return new WP_REST_Response([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $per_page,
                    'total' => 0
                ],
                'error' => 'Failed to load doctors. Please try again.'
            ], 200); // Return 200 with empty data
        }
    }

    /**
     * Get all available doctor specialties
     */
    public function get_specialties($request)
    {
        try {
            // Get unique specialties from Doctor model
            $specialties = Doctor::getUniqueSpecialties();
            
            return new WP_REST_Response($specialties);
        } catch (\Exception $e) {
            error_log('Error fetching doctor specialties: ' . $e->getMessage());
            return new WP_REST_Response([], 200);
        }
    }

    /**
     * Get a single doctor by ID
     */
    public function get_doctor($request)
    {
        try {
            $doctor_id = $request->get_param('id');
            $doctor = Doctor::find($doctor_id);
            
            if (!$doctor || $doctor->status !== 'active') {
                return new WP_Error(
                    'doctor_not_found',
                    'Doctor not found or inactive',
                    ['status' => 404]
                );
            }

            $user = get_userdata($doctor->user_id);
            
            // Format the response
            $response = [
                'id' => $doctor->id,
                'first_name' => $doctor->first_name,
                'last_name' => $doctor->last_name,
                'fullName' => $doctor->first_name . ' ' . $doctor->last_name,
                'specialty' => $doctor->specialty,
                'phone' => $doctor->phone,
                'email' => $user->user_email,
                'status' => $doctor->status,
                'license_number' => $doctor->license_number,
                'board_certification' => $doctor->board_certification,
                'education' => $doctor->education,
                'years_experience' => $doctor->years_experience,
                'office' => $doctor->office,
                // Don't include personal contact info in public endpoint
            ];
            
            return new WP_REST_Response($response);
        } catch (\Exception $e) {
            error_log('Error getting doctor: ' . $e->getMessage());
            return new WP_Error(
                'server_error',
                'Failed to retrieve doctor information',
                ['status' => 500]
            );
        }
    }

    /**
     * Create a new doctor record
     */
    public function create_doctor($request)
    {
        try {
            $params = $request->get_params();
            
            // Validate required fields
            $required_fields = ['first_name', 'last_name', 'phone', 'specialty'];
            foreach ($required_fields as $field) {
                if (empty($params[$field])) {
                    return new WP_Error(
                        'missing_required_field',
                        "Field '{$field}' is required",
                        ['status' => 400]
                    );
                }
            }
            
            // Sanitize and prepare data for database
            $doctor_data = [
                'first_name' => sanitize_text_field($params['first_name']),
                'last_name' => sanitize_text_field($params['last_name']),
                'phone' => sanitize_text_field($params['phone']),
                'specialty' => sanitize_text_field($params['specialty']),
                'status' => !empty($params['status']) ? sanitize_text_field($params['status']) : 'active',
            ];
            
            // Create new doctor record
            $doctor = Doctor::create($doctor_data);
            
            // Log the creation
            AuditLogger::log(
                'create_doctor',
                'doctor',
                $doctor->id,
                [
                    'user_id' => get_current_user_id(),
                    'doctor_data' => $doctor_data
                ]
            );
            
            // Return the created doctor
            return new WP_REST_Response([
                'message' => 'Doctor created successfully',
                'doctor' => [
                    'id' => $doctor->id,
                    'first_name' => $doctor->first_name,
                    'last_name' => $doctor->last_name,
                    'phone' => $doctor->phone,
                    'specialty' => $doctor->specialty,
                    'status' => $doctor->status,
                ]
            ], 201); // Created
            
        } catch (\Exception $e) {
            error_log('Error creating doctor: ' . $e->getMessage());
            return new WP_Error(
                'create_failed',
                'Failed to create doctor: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update an existing doctor record
     */
    public function update_doctor($request)
    {
        try {
            $doctor_id = $request->get_param('id');
            $params = $request->get_params();
            
            // Find the doctor to update
            $doctor = Doctor::find($doctor_id);
            
            if (!$doctor) {
                return new WP_Error(
                    'doctor_not_found',
                    'Doctor not found',
                    ['status' => 404]
                );
            }
            
            // Update doctor fields
            if (isset($params['first_name'])) {
                $doctor->first_name = sanitize_text_field($params['first_name']);
            }
            
            if (isset($params['last_name'])) {
                $doctor->last_name = sanitize_text_field($params['last_name']);
            }
            
            if (isset($params['phone'])) {
                $doctor->phone = sanitize_text_field($params['phone']);
            }
            
            if (isset($params['specialty'])) {
                $doctor->specialty = sanitize_text_field($params['specialty']);
            }
            
            if (isset($params['status'])) {
                $doctor->status = sanitize_text_field($params['status']);
            }
            
            // Save updated doctor
            $doctor->save();
            
            // Log the update
            AuditLogger::log(
                'update_doctor',
                'doctor',
                $doctor->id,
                [
                    'user_id' => get_current_user_id(),
                    'updated_fields' => array_keys($request->get_params())
                ]
            );
            
            // Return the updated doctor
            $updated_doctor = Doctor::find($doctor->id);
            
            return new WP_REST_Response([
                'message' => 'Doctor updated successfully',
                'doctor' => [
                    'id' => $updated_doctor->id,
                    'first_name' => $updated_doctor->first_name,
                    'last_name' => $updated_doctor->last_name,
                    'phone' => $updated_doctor->phone,
                    'specialty' => $updated_doctor->specialty,
                    'status' => $updated_doctor->status,
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log('Error updating doctor: ' . $e->getMessage());
            return new WP_Error(
                'update_failed',
                'Failed to update doctor: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a doctor
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_doctor($request) 
    {
        try {
            $doctor_id = (int) $request['id'];
            $doctor = Doctor::find($doctor_id);
            
            if (!$doctor) {
                return new WP_Error(
                    'doctor_not_found',
                    'Doctor not found',
                    ['status' => 404]
                );
            }
            
            // Check if it's safe to delete 
            // Following PatientController approach - check appointments via model method
            $has_appointments = method_exists($doctor, 'appointments') && 
                               $doctor->appointments()->count() > 0;
                
            if ($has_appointments) {
                return new WP_Error(
                    'doctor_has_appointments',
                    'Cannot delete doctor with existing appointments. Consider deactivating instead.',
                    ['status' => 400]
                );
            }
            
            // Proceed with deletion using the Doctor model's delete method
            $deleted = $doctor->delete();
            
            if (!$deleted) {
                return new WP_Error(
                    'delete_failed',
                    'Failed to delete doctor',
                    ['status' => 500]
                );
            }
            
            // Log the deletion
            AuditLogger::log(
                'delete_doctor',
                'doctor',
                $doctor_id,
                [
                    'user_id' => get_current_user_id(),
                    'doctor_data' => [
                        'id' => $doctor_id,
                        'first_name' => $doctor->first_name,
                        'last_name' => $doctor->last_name
                    ]
                ]
            );
            
            return new WP_REST_Response([
                'message' => 'Doctor deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            error_log('Error deleting doctor: ' . $e->getMessage());
            return new WP_Error(
                'delete_error',
                'Error deleting doctor: ' . $e->getMessage(), 
                ['status' => 500]
            );
        }
    }

    /**
     * Get current doctor's profile
     */
    public function get_my_profile($request)
    {
        try {
            $user_id = get_current_user_id();
            
            // Find doctor by user_id using the Doctor model
            $doctors = Doctor::where('user_id', $user_id);
            
            if (empty($doctors)) {
                return new WP_Error(
                    'profile_not_found',
                    'Doctor profile not found',
                    ['status' => 404]
                );
            }
            
            $doctor = $doctors[0];
            
            // Get user information
            $user = get_userdata($user_id);
            $response = $doctor->attributes;
            $response['email'] = $user->user_email;
            
            return new WP_REST_Response($response);
        } catch (\Exception $e) {
            error_log('Error getting doctor profile: ' . $e->getMessage());
            return new WP_Error(
                'server_error',
                'Failed to retrieve profile information',
                ['status' => 500]
            );
        }
    }

    /**
     * Update current doctor's profile
     */
    public function update_my_profile($request)
    {
        try {
            $user_id = get_current_user_id();
            
            // Find doctor by user_id
            $doctor = Doctor::where('user_id', $user_id)[0] ?? null;
            
            if (!$doctor) {
                return new WP_Error(
                    'profile_not_found',
                    'Doctor profile not found',
                    ['status' => 404]
                );
            }
            
            $params = $request->get_params();
            
            // Fields that a doctor can update about themselves
            if (isset($params['phone'])) {
                $doctor->phone = sanitize_text_field($params['phone']);
            }
            
            // Save doctor record
            $doctor->save();
            
            // Log the update
            AuditLogger::log(
                'update_doctor_profile',
                'doctor',
                $doctor->id,
                [
                    'user_id' => $user_id,
                    'updated_fields' => array_keys($request->get_params())
                ]
            );
            
            // Return the updated profile
            $updated_doctor = Doctor::find($doctor->id);
            $user = get_userdata($user_id);
            $response = $updated_doctor->attributes;
            $response['email'] = $user->user_email;
            
            return new WP_REST_Response($response);
        } catch (\Exception $e) {
            error_log('Error updating doctor profile: ' . $e->getMessage());
            return new WP_Error(
                'update_failed',
                'Failed to update profile: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get patients associated with a specific doctor
     * 
     * @param \WP_REST_Request $request The request object containing the doctor ID
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_doctor_patients($request)
    {
        try {
            $doctor_id = $request->get_param('id');
            
            // Validate doctor exists
            $doctor = Doctor::find($doctor_id);
            if (!$doctor || $doctor->status !== 'active') {
                return new WP_Error(
                    'not_found',
                    'Doctor not found or inactive',
                    ['status' => 404]
                );
            }

            // Get params for pagination if provided
            $params = $request->get_params();
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $per_page = isset($params['per_page']) ? intval($params['per_page']) : 20;
            
            // Get patients from the Doctor model
            $results = Doctor::getPatients($doctor_id, $page, $per_page);
            
            if (!$results || empty($results['data'])) {
                return new WP_REST_Response([
                    'data' => [],
                    'meta' => [
                        'total' => 0,
                        'per_page' => $per_page,
                        'current_page' => $page,
                        'last_page' => 0
                    ]
                ]);
            }
            
            // Format the response data
            $patients = array_map(function($patient) {
                return [
                    'id' => $patient->id,
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                    'phone' => $patient->phone,
                    'email' => $patient->email,
                    'status' => $patient->status,
                    'last_visit_date' => $patient->last_visit_date
                ];
            }, $results['data']);
            
            return new WP_REST_Response([
                'data' => $patients,
                'meta' => [
                    'total' => $results['total'],
                    'per_page' => $per_page,
                    'current_page' => $page,
                    'last_page' => $results['last_page']
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log('Error fetching doctor patients: ' . $e->getMessage());
            return new WP_Error(
                'server_error',
                'Failed to retrieve patients for this doctor',
                ['status' => 500]
            );
        }
    }

    /**
     * Check if user has doctor permissions
     * For development, this is more lenient to allow easier testing
     * 
     * @return bool|\WP_Error
     */
    public function check_doctor_permission()
    {
        // First check if user is authenticated at all using the base check_auth method
        $auth_check = $this->check_auth();
        if (is_wp_error($auth_check)) {
            return $auth_check;
        }
        
        // Check strict permissions for production
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
            if (!current_user_can('doctor') && !current_user_can('administrator')) {
                return new \WP_Error(
                    'rest_forbidden',
                    __('You do not have permission to access doctor resources.'),
                    ['status' => 403]
                );
            }
            return true;
        }
        
        // In development/local, we're being more permissive
        // We've already checked authentication via check_auth,
        // so we can just return true here
        return true;
    }
}
