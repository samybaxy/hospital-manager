<?php

namespace HospitalManager\Controllers\Api;

use \HospitalManager\Services\AuditLogger;
use \HospitalManager\Models\Doctor;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;

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
        register_rest_route($this->namespace, '/doctors/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctor'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Get doctor's patients
        register_rest_route($this->namespace, '/doctors/(?P<ID>\d+)/patients', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctor_patients'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Update doctor (admin only)
        register_rest_route($this->namespace, '/doctors/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_doctor'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);

        // Delete doctor (admin only)
        register_rest_route($this->namespace, '/doctors/(?P<ID>\d+)', [
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
    /**
     * Get all doctors with optional search and pagination
     */
    public function get_doctors(WP_REST_Request $request) {
        try {
            error_log('Hospital Manager API Request: GET /hospital-manager/v1/doctors - Params: ' . print_r($request->get_params(), true));
            // Extract parameters from request
            $params = $request->get_params()['params'] ?? [];

            // Safely get parameters with default values
            $search = $params['search'] ?? '';
            $per_page = (int) ($params['per_page'] ?? 10);
            $page = (int) ($params['page'] ?? 1);
            $orderby = $params['orderby'] ?? 'last_name';
            $order = $params['order'] ?? 'asc';
            $specialty = $params['specialty'] ?? '';
            if ($specialty === 'all') {
                $specialty = ''; // Treat 'all' as empty string to not filter by specialty
            }
            $status = $params['status'] ?? 'active';
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_doctors';
            
            // Build the WHERE clause
            $where_conditions = [];
            $where_values = [];
            
            if (!empty($search)) {
                $where_conditions[] = "(first_name LIKE %s OR last_name LIKE %s OR specialty LIKE %s)";
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                error_log("Search term: " . print_r($search_term, true));
            }

            if (!empty($specialty)) {
                $where_conditions[] = "specialty = %s";
                $where_values[] = $specialty;
            }
            
            if (!empty($status)) {
                $where_conditions[] = "status = %s";
                $where_values[] = $status;
            }
            
            $where_clause = '';
            if (!empty($where_conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            }
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
            if (!empty($where_values)) {
                $count_query = $wpdb->prepare($count_query, ...$where_values);
            }
            $total = (int) $wpdb->get_var($count_query);
            
            // Calculate pagination
            $offset = ($page - 1) * $per_page;
            $last_page = ceil($total / $per_page);
            
            // Get doctors with proper ordering based on request parameters
            $query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, [$per_page, $offset]);
            $prepared_query = $wpdb->prepare($query, ...$query_values);
            error_log("Doctor query: {$prepared_query}");
            $doctors = $wpdb->get_results($prepared_query);
            
            $response_data = [
                'success' => true,
                'message' => 'Doctors retrieved successfully',
                'data' => [
                    'doctors' => [
                        'items' => $doctors,
                        'currentPage' => $page,
                        'lastPage' => $last_page,
                        'perPage' => $per_page,
                        'total' => $total
                    ]
                ],
                'status' => 200,
                'version' => 'v1'
            ];
            
            error_log('Hospital Manager API Response: GET /hospital-manager/v1/doctors - Status: 200 - Found: ' . count($doctors) . ' doctors');
            
            return rest_ensure_response($response_data);
            
        } catch (\Exception $e) {
            error_log('Error fetching doctors: ' . $e->getMessage());
            return $this->error_response('Failed to fetch doctors: ' . $e->getMessage(), 500);
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
            $doctor_id = $request->get_param('ID');
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
                'ID' => $doctor->ID,
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
                'appointment_availability' => $doctor->appointment_availability,
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
            // Get request data
            $data = $request->get_json_params() ?: $request->get_params();
            
            // Validate required fields
            $required_fields = ['first_name', 'last_name', 'phone', 'specialty', 'license_number'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    return new WP_Error(
                        'missing_required_field',
                        "Missing required field: {$field}",
                        ['status' => 400]
                    );
                }
            }
            
            // Create new doctor instance
            $doctor = new Doctor();
            
            // Set required fields
            $doctor->first_name = sanitize_text_field($data['first_name']);
            $doctor->last_name = sanitize_text_field($data['last_name']);
            $doctor->phone = sanitize_text_field($data['phone']);
            $doctor->specialty = sanitize_text_field($data['specialty']);
            $doctor->license_number = sanitize_text_field($data['license_number']);
            $doctor->status = isset($data['status']) ? sanitize_text_field($data['status']) : 'active';
            
            // Set optional fields
            if (isset($data['years_experience'])) {
                $doctor->years_experience = sanitize_text_field($data['years_experience']);
            }
            
            if (isset($data['education'])) {
                $doctor->education = sanitize_text_field($data['education']);
            }
            
            if (isset($data['certification'])) {
                $doctor->certification = sanitize_text_field($data['certification']);
            }
            
            if (isset($data['office'])) {
                $doctor->office = sanitize_text_field($data['office']);
            }
            
            if (isset($data['department'])) {
                $doctor->department = sanitize_text_field($data['department']);
            }
            
            // Handle appointment_availability - it comes as JSON string from frontend
            if (isset($data['appointment_availability'])) {
                if (is_string($data['appointment_availability'])) {
                    // Validate JSON
                    $availability = json_decode($data['appointment_availability'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $doctor->appointment_availability = $data['appointment_availability'];
                    } else {
                        error_log('Invalid JSON in appointment_availability: ' . $data['appointment_availability']);
                        return new WP_Error(
                            'invalid_availability_format',
                            'Invalid appointment availability format',
                            ['status' => 400]
                        );
                    }
                } else {
                    // Convert array to JSON string
                    $doctor->appointment_availability = json_encode($data['appointment_availability']);
                    error_log('Converted appointment_availability to JSON: ' . $doctor->appointment_availability);
                }
            }
            
            // Set timestamps
            $doctor->created_at = current_time('mysql');
            $doctor->updated_at = current_time('mysql');
            
            // Save the doctor
            $saved = $doctor->save();
            
            if (!$saved) {
                throw new \Exception('Failed to save doctor to database');
            }
            
            error_log("Doctor created successfully with ID: {$doctor->ID}");
            
            return new WP_REST_Response([
                'message' => 'Doctor created successfully',
                'data' => $doctor->toArray()
            ], 201);
            
        } catch (\Exception $e) {
            error_log('Error creating doctor: ' . $e->getMessage());
            return new WP_Error(
                'doctor_creation_failed',
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
        $ID = $request->get_param('ID');
        
        if (!$ID) {
            return new WP_Error(
                'missing_doctor_id',
                'Doctor ID is required',
                ['status' => 400]
            );
        }
        
        try {
            $doctor = Doctor::find($ID);
            
            if (!$doctor) {
                return new WP_Error(
                    'doctor_not_found',
                    'Doctor not found',
                    ['status' => 404]
                );
            }
            
            // Get the request data
            $data = $request->get_json_params() ?: $request->get_params();
            
            // Validate required fields
            $required_fields = ['first_name', 'last_name', 'phone', 'specialty', 'license_number'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    return new WP_Error(
                        'missing_required_field',
                        "Missing required field: {$field}",
                        ['status' => 400]
                    );
                }
            }
            
            // Update the doctor fields with new data
            $doctor->first_name = sanitize_text_field($data['first_name']);
            $doctor->last_name = sanitize_text_field($data['last_name']);
            $doctor->phone = sanitize_text_field($data['phone']);
            $doctor->specialty = sanitize_text_field($data['specialty']);
            $doctor->license_number = sanitize_text_field($data['license_number']);
            $doctor->status = isset($data['status']) ? sanitize_text_field($data['status']) : 'active';
            
            // Optional fields
            if (isset($data['years_experience'])) {
                $doctor->years_experience = sanitize_text_field($data['years_experience']);
            }
            
            if (isset($data['education'])) {
                $doctor->education = sanitize_text_field($data['education']);
            }
            
            if (isset($data['certification'])) {
                $doctor->certification = sanitize_text_field($data['certification']);
            }
            
            if (isset($data['office'])) {
                $doctor->office = sanitize_text_field($data['office']);
            }
            
            if (isset($data['department'])) {
                $doctor->department = sanitize_text_field($data['department']);
            }
            
            // Handle appointment_availability - it comes as JSON string from frontend
            if (isset($data['appointment_availability'])) {
                if (is_string($data['appointment_availability'])) {
                    // Validate JSON
                    $availability = json_decode($data['appointment_availability'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $doctor->appointment_availability = $data['appointment_availability'];
                        error_log('Setting appointment_availability to: ' . $data['appointment_availability']);
                    } else {
                        error_log('Invalid JSON in appointment_availability: ' . $data['appointment_availability']);
                        return new WP_Error(
                            'invalid_availability_format',
                            'Invalid appointment availability format',
                            ['status' => 400]
                        );
                    }
                } else {
                    // Convert array to JSON string
                    $doctor->appointment_availability = json_encode($data['appointment_availability']);
                    error_log('Converted appointment_availability to JSON: ' . $doctor->appointment_availability);
                }
            }
            
            // Save the doctor with updated data
            $saved = $doctor->save();
            
            if (!$saved) {
                throw new \Exception('Failed to save doctor to database');
            }
            
            // Log successful update
            error_log("Doctor {$ID} updated successfully");
            
            return new WP_REST_Response([
                'message' => 'Doctor updated successfully',
                'data' => $doctor->toArray()
            ], 200);
            
        } catch (\Exception $e) {
            error_log('Error updating doctor: ' . $e->getMessage());
            return new WP_Error(
                'doctor_update_failed',
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
            $doctor_id = (int) $request['ID'];
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
                        'ID' => $doctor_id,
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
            $response['user_registered'] = $user->user_registered;
            
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
            if (isset($params['first_name'])) {
                $doctor->first_name = sanitize_text_field($params['first_name']);
            }
            
            if (isset($params['last_name'])) {
                $doctor->last_name = sanitize_text_field($params['last_name']);
            }
            
            if (isset($params['phone'])) {
                $doctor->phone = sanitize_text_field($params['phone']);
            }
            
            // Save doctor record
            $doctor->save();
            
            // Log the update
            AuditLogger::log(
                'update_doctor_profile',
                'doctor',
                $doctor->ID,
                [
                    'user_id' => $user_id,
                    'updated_fields' => array_keys($request->get_params())
                ]
            );
            
            // Return the updated profile
            $updated_doctor = Doctor::find($doctor->ID);
            $user = get_userdata($user_id);
            $response = $updated_doctor->attributes;
            $response['email'] = $user->user_email;
            $response['user_registered'] = $user->user_registered;
            
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
            $doctor_id = $request->get_param('ID');
            
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

            // Get patients from the Doctor model - now based on visitations
            $results = Doctor::getPatients($doctor_id, $page, $per_page);
            
            // Get additional patient statistics
            global $wpdb;
            $visitations_table = $wpdb->prefix . 'hm_visitations';
            $patients_table = $wpdb->prefix . 'hm_patients';
            
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
            
            if (!$results || empty($results['data'])) {
                return new WP_REST_Response([
                    'data' => [],
                    'meta' => [
                        'total' => $total_patients ? (int)$total_patients : 0,
                        'active' => $active_patients ? (int)$active_patients : 0,
                        'recent_visits' => $recent_visits ? (int)$recent_visits : 0,
                        'per_page' => $per_page,
                        'current_page' => $page,
                        'last_page' => 0
                    ]
                ]);
            }
            
            // Format the response data
            $patients = array_map(function($patient) {
                return [
                    'ID' => $patient->ID,
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                    'phone' => $patient->phone,
                    'email' => $patient->email,
                    'last_visit_date' => $patient->last_visit_date,
                    'last_visit_time' => $patient->last_visit_time
                ];
            }, $results['data']);
            
            return new WP_REST_Response([
                'data' => $patients,
                'meta' => [
                    'total' => $total_patients ? (int)$total_patients : (int)$results['total'],
                    'active' => $active_patients ? (int)$active_patients : 0,
                    'recent_visits' => $recent_visits ? (int)$recent_visits : 0,
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
