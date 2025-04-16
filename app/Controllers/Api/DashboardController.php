<?php
<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;

class DashboardController extends WP_REST_Controller 
{
    public function register_routes() 
    {
        register_rest_route('hospital-manager/v1', '/dashboard', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_data'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        ]);
    }

    public function check_permissions() 
    {
        return is_user_logged_in();
    }

    public function get_dashboard_data($request) 
    {
        $user = wp_get_current_user();
        $role = $user->roles[0];
        
        switch ($role) {
            case 'patient':
                return $this->get_patient_dashboard($user->ID);
            case 'doctor':
                return $this->get_doctor_dashboard($user->ID);
            case 'lab_tech':
                return $this->get_lab_dashboard($user->ID);
            default:
                return new WP_REST_Response(['error' => 'Invalid role'], 403);
        }
    }
}