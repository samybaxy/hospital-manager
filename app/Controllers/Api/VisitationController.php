<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\Visitation;

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
        $user = wp_get_current_user();
        $params = $request->get_params();

        if (in_array('patient', $user->roles)) {
            // Patients can only see their own visitations
            $params['patient_id'] = $user->ID;
        }

        $visitations = Visitation::where($params)->get();
        return new WP_REST_Response($visitations, 200);
    }

    public function create_visitation($request) 
    {
        $visitation = Visitation::create($request->get_params());
        return new WP_REST_Response($visitation, 201);
    }
}