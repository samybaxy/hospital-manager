<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\LabInvestigation;

class LabInvestigationController extends BaseController 
{
    public function register_routes() 
    {
        register_rest_route($this->namespace, '/lab-investigations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_investigations'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_investigation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_lab_investigations');
                },
            ]
        ]);

        register_rest_route($this->namespace, '/lab-investigations/(?P<ID>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_investigation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'update_lab_results');
                },
            ]
        ]);
    }

    public function get_investigations($request) 
    {
        $investigations = LabInvestigation::where($request->get_params())->get();
        return new WP_REST_Response($investigations, 200);
    }

    public function create_investigation($request) 
    {
        $investigation = LabInvestigation::create($request->get_params());
        return new WP_REST_Response($investigation, 201);
    }

    public function update_investigation($request) 
    {
        $investigation = LabInvestigation::find($request['ID']);
        if (!$investigation) {
            return new WP_REST_Response(['error' => 'Investigation not found'], 404);
        }

        $investigation->update($request->get_params());
        return new WP_REST_Response($investigation, 200);
    }
}