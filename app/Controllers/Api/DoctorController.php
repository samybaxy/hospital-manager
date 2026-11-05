<?php

namespace HospitalManager\Controllers\Api;

use \HospitalManager\Services\AuditLogger;
use \HospitalManager\Services\DoctorService;
use \HospitalManager\Models\Doctor;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;

class DoctorController extends BaseController
{
    public function register_routes()
    {
        // Get all doctors (any signed-in user)
        register_rest_route($this->namespace, '/doctors', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctors'],
                'permission_callback' => [$this, 'check_signed_in']
            ]
        ]);

        // Create doctor (admin only)
        register_rest_route($this->namespace, '/doctors', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_doctor'],
                'permission_callback' => [$this, 'check_manage_doctors']
            ]
        ]);

        // Get doctor specialties (any signed-in user)
        register_rest_route($this->namespace, '/doctors/specialties', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_specialties'],
                'permission_callback' => [$this, 'check_signed_in']
            ]
        ]);

        // Get single doctor (any signed-in user)
        register_rest_route($this->namespace, '/doctors/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctor'],
                'permission_callback' => [$this, 'check_signed_in']
            ]
        ]);

        // Get doctor's patients
        register_rest_route($this->namespace, '/doctors/(?P<ID>\d+)/patients', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_doctor_patients'],
                'permission_callback' => [$this, 'check_view_doctor_patients']
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
                'permission_callback' => [$this, 'check_manage_doctors']
            ]
        ]);
    }

    /**
     * Get all doctors with optional search and pagination
     */
    public function get_doctors(WP_REST_Request $request) {
        try {
            // Extract parameters from request
            $params = $request->get_params()['params'] ?? [];
            
            // Use DoctorService to search doctors
            $results = DoctorService::searchDoctors($params);
            
            if (!$results) {
                return $this->error_response('Failed to fetch doctors', 500);
            }
            
            // Convert Doctor model instances to arrays
            $doctors_array = [];
            foreach ($results['data'] as $doctor) {
                if ($doctor instanceof \HospitalManager\Models\Doctor) {
                    $doctors_array[] = $doctor->toArray();
                } else {
                    $doctors_array[] = $doctor;
                }
            }
            
            $response_data = [
                'success' => true,
                'message' => 'Doctors retrieved successfully',
                'data' => [
                    'doctors' => [
                        'items' => $doctors_array,
                        'currentPage' => $results['current_page'],
                        'lastPage' => $results['last_page'],
                        'perPage' => $results['per_page'],
                        'total' => $results['total']
                    ]
                ],
                'status' => 200,
                'version' => 'v1'
            ];
            
            error_log('Hospital Manager API Response: GET /hospital-manager/v1/doctors - Status: 200 - Found: ' . count($results['data']) . ' doctors');
            
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
            // Get unique specialties from DoctorService
            $specialties = DoctorService::getSpecialties();
            
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
                'email' => $user ? $user->user_email : '',
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
            
            // Validate data using DoctorService
            $validation = DoctorService::validateDoctorData($data, false);
            if ($validation !== true) {
                return new WP_Error(
                    'validation_failed',
                    implode(', ', $validation),
                    ['status' => 400]
                );
            }
            
            // Create new doctor instance
            // Optional login for the doctor (same rules as patients): a doctor
            // without an email is created without a portal account (user_id = 0).
            $user_id = 0;
            if (!empty($data['email'])) {
                $email = sanitize_email($data['email']);
                if (!is_email($email)) {
                    return new WP_Error('invalid_email', 'Invalid email address', ['status' => 400]);
                }
                if (email_exists($email)) {
                    return new WP_Error('email_exists', 'A user with this email address already exists', ['status' => 400]);
                }

                $login = sanitize_user(strstr($email, '@', true), true) ?: 'doctor_' . wp_generate_password(6, false);
                $base = $login;
                for ($n = 1; username_exists($login); $n++) {
                    $login = $base . $n;
                }

                $user_id = wp_insert_user([
                    'user_login' => $login,
                    'user_email' => $email,
                    'user_pass' => wp_generate_password(),
                    'first_name' => sanitize_text_field($data['first_name']),
                    'last_name' => sanitize_text_field($data['last_name']),
                    'display_name' => 'Dr. ' . trim(sanitize_text_field($data['first_name']) . ' ' . sanitize_text_field($data['last_name'])),
                    'role' => 'doctor',
                ]);
                if (is_wp_error($user_id)) {
                    return new WP_Error('user_create_failed', 'Could not create a login for this doctor: ' . $user_id->get_error_message(), ['status' => 500]);
                }

                // The generated password is never shown to anyone, so email the
                // doctor WordPress's standard set-password link.
                wp_new_user_notification($user_id, null, 'user');
            }

            $doctor = new Doctor();
            $doctor->user_id = (int) $user_id;
            
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
            
            // The column is board_certification ("certification" was silently dropped)
            if (isset($data['board_certification']) || isset($data['certification'])) {
                $doctor->board_certification = sanitize_text_field($data['board_certification'] ?? $data['certification']);
            }
            
            if (isset($data['office'])) {
                $doctor->office = sanitize_text_field($data['office']);
            }
            
            // Handle appointment_availability using DoctorService
            if (isset($data['appointment_availability'])) {
                try {
                    $doctor->appointment_availability = DoctorService::formatAppointmentAvailability($data['appointment_availability']);
                } catch (\Exception $e) {
                    return new WP_Error(
                        'invalid_availability_format',
                        $e->getMessage(),
                        ['status' => 400]
                    );
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
            
            // Validate data using DoctorService
            $validation = DoctorService::validateDoctorData($data, true);
            if ($validation !== true) {
                return new WP_Error(
                    'validation_failed',
                    implode(', ', $validation),
                    ['status' => 400]
                );
            }
            
            // Update the doctor fields with new data
            if (isset($data['first_name'])) {
                $doctor->first_name = sanitize_text_field($data['first_name']);
            }
            if (isset($data['last_name'])) {
                $doctor->last_name = sanitize_text_field($data['last_name']);
            }
            if (isset($data['phone'])) {
                $doctor->phone = sanitize_text_field($data['phone']);
            }
            if (isset($data['specialty'])) {
                $doctor->specialty = sanitize_text_field($data['specialty']);
            }
            if (isset($data['license_number']) && \HospitalManager\Services\AccessPolicy::allowsAll(wp_get_current_user(), 'manage_doctors')) {
                $doctor->license_number = sanitize_text_field($data['license_number']);
            }
            // Only administrators change a doctor's status or licence; a doctor
            // editing their own profile can't (re)activate themselves.
            $is_admin = \HospitalManager\Services\AccessPolicy::allowsAll(wp_get_current_user(), 'manage_doctors');
            if (!$is_admin) {
                unset($data['status']);
            }
            if (isset($data['status'])) {
                $doctor->status = sanitize_text_field($data['status']);
            }
            
            // Optional fields
            if (isset($data['years_experience'])) {
                $doctor->years_experience = sanitize_text_field($data['years_experience']);
            }
            
            if (isset($data['education'])) {
                $doctor->education = sanitize_text_field($data['education']);
            }
            
            // The column is board_certification ("certification" was silently dropped)
            if (isset($data['board_certification']) || isset($data['certification'])) {
                $doctor->board_certification = sanitize_text_field($data['board_certification'] ?? $data['certification']);
            }
            
            if (isset($data['office'])) {
                $doctor->office = sanitize_text_field($data['office']);
            }
            
            // Handle appointment_availability using DoctorService
            if (isset($data['appointment_availability'])) {
                try {
                    $doctor->appointment_availability = DoctorService::formatAppointmentAvailability($data['appointment_availability']);
                } catch (\Exception $e) {
                    return new WP_Error(
                        'invalid_availability_format',
                        $e->getMessage(),
                        ['status' => 400]
                    );
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
            error_log('Hospital Manager: error updating doctor: ' . $e->getMessage());
            return new WP_Error(
                'doctor_update_failed',
                'Failed to update doctor.',
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
            
            // Check if it's safe to delete using DoctorService
            $has_appointments = DoctorService::hasAppointments($doctor_id);
                
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
     * Get patients associated with a specific doctor
     * 
     * @param \WP_REST_Request $request The request object containing the doctor ID
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_doctor_patients($request)
    {
        try {
            $doctor_id = $request->get_param('ID');
            
            // Get params for pagination if provided
            $params = $request->get_params();
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $per_page = isset($params['per_page']) ? intval($params['per_page']) : 20;

            // Get patients from DoctorService
            $results = DoctorService::getDoctorPatients($doctor_id, $page, $per_page);
            
            if ($results === null) {
                return new WP_Error(
                    'not_found',
                    'Doctor not found or inactive',
                    ['status' => 404]
                );
            }
            
            // Get additional patient statistics from DoctorService
            $statistics = DoctorService::getDoctorPatientStatistics($doctor_id);
            
            if (!$results || empty($results['data'])) {
                return new WP_REST_Response([
                    'data' => [],
                    'meta' => [
                        'total' => $statistics['total_patients'],
                        'active' => $statistics['active_patients'],
                        'recent_visits' => $statistics['recent_visits'],
                        'per_page' => $per_page,
                        'current_page' => $page,
                        'last_page' => 0
                    ]
                ]);
            }
            
            // Format the response data
            $patients = array_map(function($patient) {
                return [
                    'ID' => $patient['ID'],
                    'first_name' => $patient['first_name'],
                    'last_name' => $patient['last_name'],
                    'phone' => $patient['phone'],
                    'last_visit_date' => $patient['last_visit_date'],
                    'last_visit_time' => $patient['last_visit_time']
                ];
            }, $results['data']);
            
            return new WP_REST_Response([
                'data' => $patients,
                'meta' => [
                    'total' => $statistics['total_patients'],
                    'active' => $statistics['active_patients'],
                    'recent_visits' => $statistics['recent_visits'],
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
     * Doctor directory, specialties and profiles: any signed-in user (patients
     * need them to book). They used to be public, contact details included.
     */
    public function check_signed_in()
    {
        if (!is_user_logged_in()) {
            return new \WP_Error('rest_forbidden', 'You must be logged in to access this endpoint.', ['status' => 401]);
        }
        return true;
    }

    /**
     * A doctor's patient list (names, phones, last visit): staff who may look
     * patients up. It used to be public.
     */
    public function check_view_doctor_patients()
    {
        $signed_in = $this->check_signed_in();
        if (is_wp_error($signed_in)) {
            return $signed_in;
        }
        if (!\HospitalManager\Services\AccessPolicy::allowsAll(wp_get_current_user(), 'view_patients')) {
            return new \WP_Error('rest_forbidden', 'You do not have permission to view these patients.', ['status' => 403]);
        }
        return true;
    }

    /** Create and delete doctors: administrators only. */
    public function check_manage_doctors()
    {
        $signed_in = $this->check_signed_in();
        if (is_wp_error($signed_in)) {
            return $signed_in;
        }
        if (!\HospitalManager\Services\AccessPolicy::allowsAll(wp_get_current_user(), 'manage_doctors')) {
            return new \WP_Error('rest_forbidden', 'Only administrators can add or remove doctors.', ['status' => 403]);
        }
        return true;
    }

    /**
     * Edit a doctor: administrators, or a doctor editing their own record. Any
     * doctor used to be able to edit, deactivate or delete every other doctor.
     *
     * @return bool|\WP_Error
     */
    public function check_doctor_permission($request = null)
    {
        $signed_in = $this->check_signed_in();
        if (is_wp_error($signed_in)) {
            return $signed_in;
        }

        $user = wp_get_current_user();
        if (\HospitalManager\Services\AccessPolicy::allowsAll($user, 'manage_doctors')) {
            return true;
        }

        $id = $request ? (int) $request->get_param('ID') : 0;
        if ($id && in_array('doctor', (array) $user->roles, true)
            && \HospitalManager\Services\AccessPolicy::doctorRecordId($user->ID) === $id) {
            return true;
        }

        return new \WP_Error(
            'rest_forbidden',
            __('You can only edit your own doctor profile.'),
            ['status' => 403]
        );
    }
}
