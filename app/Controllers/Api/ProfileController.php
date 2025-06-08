<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\HMO;

class ProfileController extends BaseController 
{
    /**
     * Register all routes for the Profile API
     */
    public function register_routes() 
    {
        // Get current user's profile
        register_rest_route($this->namespace, '/profile', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_my_profile'],
                'permission_callback' => function($request) {
                    return is_user_logged_in();
                }
            ]
        ]);

        // Update current user's profile
        register_rest_route($this->namespace, '/profile', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_my_profile'],
                'permission_callback' => function($request) {
                    return is_user_logged_in();
                }
            ]
        ]);
    }

    /**
     * Get current user's profile (patient or doctor)
     */
    public function get_my_profile($request)
    {
        try {
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            
            if (!$user) {
                return new WP_Error(
                    'user_not_found',
                    'User not found',
                    ['status' => 404]
                );
            }

            $response = [];

            // Check if user is a patient
            if (in_array('patient', $user->roles)) {
                $patients = Patient::query()->where('user_id', $user_id)->get();
                
                if (!empty($patients)) {
                    $patient = $patients[0];
                    
                    // Get HMO name if patient has HMO
                    $hmo_name = '';
                    if ($patient->hmo_id) {
                        $hmo = HMO::find($patient->hmo_id);
                        $hmo_name = $hmo ? $hmo->name : '';
                    }
                    
                    $response = [
                        'ID' => $patient->ID ?? null,
                        'first_name' => $patient->first_name ?? '',
                        'last_name' => $patient->last_name ?? '',
                        'phone' => $patient->phone ?? '',
                        'age' => $patient->age ?? '',
                        'gender' => $patient->gender ?? '',
                        'marital_status' => $patient->marital_status ?? '',
                        'city' => $patient->city ?? '',
                        'state' => $patient->state ?? '',
                        'address' => $patient->address ?? '',
                        'hmo_id' => $patient->hmo_id ?? '',
                        'hmo_name' => $hmo_name,
                        'hmo_designated_id' => $patient->hmo_designated_id ?? '',
                        'email' => $user->user_email,
                        'user_registered' => $user->user_registered,
                        'role' => 'patient'
                    ];
                    
                    return new WP_REST_Response([
                        'success' => true,
                        'data' => $response
                    ]);
                }
            }
            
            // Check if user is a doctor
            if (in_array('doctor', $user->roles)) {
                $doctors = Doctor::where('user_id', $user_id);
                
                if (!empty($doctors)) {
                    $doctor = $doctors[0];
                    $response = [
                        'ID' => $doctor->ID ?? null,
                        'first_name' => $doctor->first_name ?? '',
                        'last_name' => $doctor->last_name ?? '',
                        'phone' => $doctor->phone ?? '',
                        'specialty' => $doctor->specialty ?? '',
                        'status' => $doctor->status ?? '',
                        'office' => $doctor->office ?? '',
                        'board_certification' => $doctor->board_certification ?? '',
                        'education' => $doctor->education ?? '',
                        'years_experience' => $doctor->years_experience ?? '',
                        'license_number' => $doctor->license_number ?? '',
                        'appointment_availability' => $doctor->appointment_availability ?? '',
                        'email' => $user->user_email,
                        'user_registered' => $user->user_registered,
                        'role' => 'doctor'
                    ];
                    
                    return new WP_REST_Response([
                        'success' => true,
                        'data' => $response
                    ]);
                }
            }
            
            // If no patient or doctor record found, return basic user info
            $response = [
                'ID' => $user->ID,
                'first_name' => get_user_meta($user_id, 'first_name', true),
                'last_name' => get_user_meta($user_id, 'last_name', true),
                'email' => $user->user_email,
                'user_registered' => $user->user_registered,
                'role' => 'user',
                'phone' => get_user_meta($user_id, 'phone', true)
            ];
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $response
            ]);
            
        } catch (\Exception $e) {
            error_log('Error getting user profile: ' . $e->getMessage());
            return new WP_Error(
                'server_error',
                'Failed to retrieve profile information',
                ['status' => 500]
            );
        }
    }

    /**
     * Update current user's profile (patient or doctor)
     */
    public function update_my_profile($request)
    {
        try {
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            $params = $request->get_params();
            
            if (!$user) {
                return new WP_Error(
                    'user_not_found',
                    'User not found',
                    ['status' => 404]
                );
            }

            $updated = false;

            // Check if user is a patient
            if (in_array('patient', $user->roles)) {
                $patients = Patient::query()->where('user_id', $user_id)->get();
                
                if (!empty($patients)) {
                    $patient = $patients[0];
                    
                    // Fields that a patient can update
                    if (isset($params['first_name'])) {
                        $patient->first_name = sanitize_text_field($params['first_name']);
                        $updated = true;
                    }
                    
                    if (isset($params['last_name'])) {
                        $patient->last_name = sanitize_text_field($params['last_name']);
                        $updated = true;
                    }
                    
                    if (isset($params['phone'])) {
                        $patient->phone = sanitize_text_field($params['phone']);
                        $updated = true;
                    }
                    
                    if ($updated) {
                        $patient->save();
                    }
                    
                    // Return updated patient profile
                    $updated_patient = Patient::find($patient->ID);
                    
                    // Get HMO name if patient has HMO
                    $hmo_name = '';
                    if ($updated_patient->hmo_id) {
                        $hmo = HMO::find($updated_patient->hmo_id);
                        $hmo_name = $hmo ? $hmo->name : '';
                    }
                    
                    $response = [
                        'ID' => $updated_patient->ID ?? null,
                        'first_name' => $updated_patient->first_name ?? '',
                        'last_name' => $updated_patient->last_name ?? '',
                        'phone' => $updated_patient->phone ?? '',
                        'age' => $updated_patient->age ?? '',
                        'gender' => $updated_patient->gender ?? '',
                        'marital_status' => $updated_patient->marital_status ?? '',
                        'city' => $updated_patient->city ?? '',
                        'state' => $updated_patient->state ?? '',
                        'address' => $updated_patient->address ?? '',
                        'hmo_id' => $updated_patient->hmo_id ?? '',
                        'hmo_name' => $hmo_name,
                        'hmo_designated_id' => $updated_patient->hmo_designated_id ?? '',
                        'email' => $user->user_email,
                        'user_registered' => $user->user_registered,
                        'role' => 'patient'
                    ];
                    
                    return new WP_REST_Response([
                        'success' => true,
                        'message' => 'Profile updated successfully',
                        'data' => $response
                    ]);
                }
            }
            
            // Check if user is a doctor
            if (in_array('doctor', $user->roles)) {
                $doctors = Doctor::where('user_id', $user_id);
                
                if (!empty($doctors)) {
                    $doctor = $doctors[0];
                    
                    // Fields that a doctor can update
                    if (isset($params['first_name'])) {
                        $doctor->first_name = sanitize_text_field($params['first_name']);
                        $updated = true;
                    }
                    
                    if (isset($params['last_name'])) {
                        $doctor->last_name = sanitize_text_field($params['last_name']);
                        $updated = true;
                    }
                    
                    if (isset($params['phone'])) {
                        $doctor->phone = sanitize_text_field($params['phone']);
                        $updated = true;
                    }
                    
                    if ($updated) {
                        $doctor->save();
                    }
                    
                    // Return updated doctor profile
                    $updated_doctor = Doctor::find($doctor->ID);
                    $response = [
                        'ID' => $updated_doctor->ID ?? null,
                        'first_name' => $updated_doctor->first_name ?? '',
                        'last_name' => $updated_doctor->last_name ?? '',
                        'phone' => $updated_doctor->phone ?? '',
                        'specialty' => $updated_doctor->specialty ?? '',
                        'status' => $updated_doctor->status ?? '',
                        'office' => $updated_doctor->office ?? '',
                        'board_certification' => $updated_doctor->board_certification ?? '',
                        'education' => $updated_doctor->education ?? '',
                        'years_experience' => $updated_doctor->years_experience ?? '',
                        'license_number' => $updated_doctor->license_number ?? '',
                        'appointment_availability' => $updated_doctor->appointment_availability ?? '',
                        'email' => $user->user_email,
                        'user_registered' => $user->user_registered,
                        'role' => 'doctor'
                    ];
                    
                    return new WP_REST_Response([
                        'success' => true,
                        'message' => 'Profile updated successfully',
                        'data' => $response
                    ]);
                }
            }
            
            // If no patient or doctor record, update user meta
            if (isset($params['first_name'])) {
                update_user_meta($user_id, 'first_name', sanitize_text_field($params['first_name']));
                $updated = true;
            }
            
            if (isset($params['last_name'])) {
                update_user_meta($user_id, 'last_name', sanitize_text_field($params['last_name']));
                $updated = true;
            }
            
            if (isset($params['phone'])) {
                update_user_meta($user_id, 'phone', sanitize_text_field($params['phone']));
                $updated = true;
            }
            
            if (!$updated) {
                return new WP_Error(
                    'no_updates',
                    'No valid fields provided for update',
                    ['status' => 400]
                );
            }
            
            // Return updated user profile
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'ID' => $user->ID,
                    'first_name' => get_user_meta($user_id, 'first_name', true),
                    'last_name' => get_user_meta($user_id, 'last_name', true),
                    'email' => $user->user_email,
                    'user_registered' => $user->user_registered,
                    'role' => 'user',
                    'phone' => get_user_meta($user_id, 'phone', true)
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log('Error updating user profile: ' . $e->getMessage());
            return new WP_Error(
                'update_failed',
                'Failed to update profile: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
