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
        $search = $request->get_param('search');
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;

        $query = Patient::query();
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('hmo_designated_id', 'LIKE', "%{$search}%");
            });
        }

        $patients = $query->paginate($per_page, ['*'], 'page', $page);

        return new WP_REST_Response([
            'data' => $patients->items(),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total()
            ]
        ]);
    }

    public function get_visitations($request)
    {
        $doctor_id = get_current_user_id();
        $date = $request->get_param('date') ?? date('Y-m-d');

        $visitations = Visitation::where('doctor_id', $doctor_id)
            ->where('date', $date)
            ->with(['patient'])
            ->orderBy('time', 'ASC')
            ->get();

        return new WP_REST_Response($visitations);
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
