<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Controller;
use WP_Error;

class BaseController extends WP_REST_Controller 
{
    protected $namespace = 'hospital-manager/v1';

    protected function check_permission($request, $required_capability) 
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                'You must be logged in to access this endpoint.',
                ['status' => 401]
            );
        }

        if (!current_user_can($required_capability)) {
            return new WP_Error(
                'rest_forbidden',
                'You do not have permission to access this resource.',
                ['status' => 403]
            );
        }

        return true;
    }
}