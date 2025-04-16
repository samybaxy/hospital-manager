<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\Patient;

class PatientController extends BaseController 
{
    public function register_routes() 
    {
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

        register_rest_route($this->namespace, '/patients/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_patient'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ]
        ]);
    }

    public function get_patients($request) 
    {
        $patients = Patient::all();
        return new WP_REST_Response($patients, 200);
    }

    public function get_patient($request) 
    {
        $patient = Patient::find($request['id']);
        if (!$patient) {
            return new WP_REST_Response(['error' => 'Patient not found'], 404);
        }
        return new WP_REST_Response($patient, 200);
    }

    public function create_patient($request) 
    {
        $patient = Patient::create($request->get_params());
        return new WP_REST_Response($patient, 201);
    }
}