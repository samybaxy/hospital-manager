<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use HospitalManager\Services\AuditLogger;

class DoctorController extends BaseController
{
    public function register_routes()
    {
        // Get doctor's patients
        register_rest_route($this->namespace, '/doctor/patients', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_patients'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);

        // Get doctor's upcoming visitations
        register_rest_route($this->namespace, '/doctor/visitations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_visitations'],
                'permission_callback' => [$this, 'check_doctor_permission']
            ]
        ]);

        // Create new visitation
        register_rest_route($this->namespace, '/doctor/visitations', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_visitation'],
                'permission_callback' => function() {
                    return current_user_can('doctor');
                }
            ]
        ]);

        // Update patient biodata
        register_rest_route($this->namespace, '/doctor/patients/(?P<id>\d+)/biodata', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_patient_biodata'],
                'permission_callback' => function() {
                    return current_user_can('doctor');
                }
            ]
        ]);
    }

    public function get_patients($request)
    {
        global $wpdb;
        try {
            $search = $request->get_param('search');
            $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
            $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 20;
            $offset = ($page - 1) * $per_page;
            
            // Get database prefix
            $table_name = $wpdb->prefix . 'hm_patients';
            
            // Direct SQL query for better error control
            $where_clause = '';
            if ($search) {
                $search_param = '%' . $wpdb->esc_like($search) . '%';
                $where_clause = $wpdb->prepare(
                    " WHERE first_name LIKE %s OR last_name LIKE %s OR hmo_designated_id LIKE %s",
                    $search_param,
                    $search_param,
                    $search_param
                );
            }
            
            // Get count for pagination
            $count_query = "SELECT COUNT(*) FROM $table_name" . $where_clause;
            $total = $wpdb->get_var($count_query);
            
            // Main query
            $query = "SELECT * FROM $table_name" . $where_clause . " LIMIT %d OFFSET %d";
            $prepared_query = $wpdb->prepare($query, $per_page, $offset);
            $patients = $wpdb->get_results($prepared_query, ARRAY_A);
            
            // Calculate pagination info
            $last_page = ceil($total / $per_page);
            
            // Map each patient to include fullName for convenience
            $patients = array_map(function($patient) {
                $patient['fullName'] = $patient['first_name'] . ' ' . $patient['last_name'];
                return $patient;
            }, $patients);
            
            return new WP_REST_Response([
                'data' => $patients,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $last_page,
                    'per_page' => $per_page,
                    'total' => intval($total)
                ]
            ]);
        } catch (\Exception $e) {
            // Log the error and return a friendly response
            error_log('Patient query error: ' . $e->getMessage());
            return new WP_REST_Response([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $per_page,
                    'total' => 0
                ],
                'error' => 'There was an error loading patients data. Please try again later.'
            ], 200); // Return 200 with empty data instead of 500
        }
    }

    public function get_visitations($request)
    {
        try {
            $doctor_id = get_current_user_id();
            $date = $request->get_param('date') ?? date('Y-m-d');
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d'); // Default to today if format is invalid
            }

            // Use direct SQL for better error handling
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_visitations';
            $patients_table = $wpdb->prefix . 'hm_patients';
            
            $query = $wpdb->prepare(
                "SELECT v.*, 
                 CONCAT(p.first_name, ' ', p.last_name) as patientName
                 FROM $table_name v
                 LEFT JOIN $patients_table p ON v.patient_id = p.id
                 WHERE v.doctor_id = %d AND v.date = %s
                 ORDER BY v.time ASC",
                $doctor_id,
                $date
            );
            
            $results = $wpdb->get_results($query, ARRAY_A);
            
            // Process visitations to ensure proper time format and IDs
            $visitations = array_map(function($item) {
                // Ensure each result has an ID
                if (!isset($item['id'])) {
                    $item['id'] = uniqid('temp-');
                }
                
                // Format the time value consistently
                if (isset($item['time'])) {
                    // Make sure time is in valid HH:MM:SS or HH:MM format
                    if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $item['time'], $matches)) {
                        $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                        $minute = $matches[2];
                        $item['time'] = $hour . ':' . $minute;
                    } else {
                        // Default time if invalid format
                        $item['time'] = '00:00';
                    }
                } else {
                    $item['time'] = '00:00';
                }
                
                return $item;
            }, $results ?: []);
            
            return new WP_REST_Response($visitations);
        } catch (\Exception $e) {
            // Log error and return empty array
            error_log('Error fetching visitations: ' . $e->getMessage());
            return new WP_REST_Response([], 200);
        }
    }

    public function create_visitation($request)
    {
        $doctor_id = get_current_user_id();
        $patient_id = $request->get_param('patient_id');
        $date = $request->get_param('date');
        $time = $request->get_param('time');
        $diagnosis = $request->get_param('diagnosis');
        $treatment = $request->get_param('treatment');
        $medical_history = $request->get_param('medical_history');

        // Validate required fields
        if (!$patient_id || !$date || !$time) {
            return new WP_Error(
                'missing_required_fields',
                'Missing required fields',
                ['status' => 400]
            );
        }

        // Create visitation
        $visitation = Visitation::create([
            'doctor_id' => $doctor_id,
            'patient_id' => $patient_id,
            'date' => $date,
            'time' => $time,
            'diagnosis' => $diagnosis,
            'treatment' => $treatment,
            'medical_history' => $medical_history
        ]);

        // Log the action
        AuditLogger::log(
            'create_visitation',
            'visitation',
            $visitation->id,
            [
                'doctor_id' => $doctor_id,
                'patient_id' => $patient_id,
                'date' => $date,
                'time' => $time
            ]
        );

        return new WP_REST_Response($visitation, 201);
    }

    public function update_patient_biodata($request)
    {
        $patient_id = $request->get_param('id');
        $biodata = $request->get_param('biodata');
        
        if (!$biodata || !is_array($biodata)) {
            return new WP_Error(
                'invalid_biodata',
                'Invalid biodata format',
                ['status' => 400]
            );
        }

        $patient = Patient::find($patient_id);
        if (!$patient) {
            return new WP_Error(
                'patient_not_found',
                'Patient not found',
                ['status' => 404]
            );
        }

        $old_biodata = $patient->bio_data;
        $patient->bio_data = $biodata;
        $patient->save();

        // Log the biodata update
        AuditLogger::log(
            'update_patient_biodata',
            'patient',
            $patient_id,
            [
                'old_biodata' => $old_biodata,
                'new_biodata' => $biodata,
                'updated_by' => get_current_user_id()
            ]
        );

        return new WP_REST_Response($patient);
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
