<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\Visitation;
use HospitalManager\Services\VisitationService;

class VisitationController extends BaseController 
{
    public function register_routes() 
    {
        register_rest_route($this->namespace, '/visitations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_visitations'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_visitation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'add_visitation');
                },
            ]
        ]);
    }

    public function get_visitations($request) 
    {
        try {
            // Extract parameters from request
            $params = $request->get_params();
            
            // Use the service to handle all business logic
            $result = VisitationService::getVisitations($params);
            
            return $this->success_response(
                $result, 
                'Visitations retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving visitations: ' . $e->getMessage(), 
                500
            );
        }
    }

    public function create_visitation($request) 
    {
        $visitation = Visitation::create($request->get_params());
        return new WP_REST_Response($visitation, 201);
    }
}