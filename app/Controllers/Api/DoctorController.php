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
            $search = $request->get_param('search');
            $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
            $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 20;
            $specialty = $request->get_param('specialty') !== 'all' ? $request->get_param('specialty') : null;
            $orderby = $request->get_param('orderby') ? $request->get_param('orderby') : 'last_name';
            $order = $request->get_param('order') ? $request->get_param('order') : 'asc';
            
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
            
            // Format the response
            $response = [
                'id' => $doctor->id,
                'first_name' => $doctor->first_name,
                'last_name' => $doctor->last_name,
                'fullName' => $doctor->first_name . ' ' . $doctor->last_name,
                'specialty' => $doctor->specialty,
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
