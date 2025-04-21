<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Controller;
use WP_Error;
use WP_REST_Response;
use HospitalManager\Services\ApiService;

class BaseController extends WP_REST_Controller 
{
    protected $namespace = 'hospital-manager/v1';

    /**
     * Check if the user has the required permissions
     * 
     * @param \WP_REST_Request $request The request object
     * @param string $required_capability The capability required to access this endpoint
     * @return true|\WP_Error True if the user has permission, WP_Error otherwise
     */
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
    
    /**
     * Format a successful response
     *
     * @param mixed $data The data to return
     * @param string $message Success message
     * @param int $status HTTP status code
     * @return WP_REST_Response
     */
    protected function success_response($data = null, $message = 'Success', $status = 200)
    {
        $response = new WP_REST_Response(
            ApiService::formatResponse($data, $message, $status)
        );
        $response->set_status($status);
        
        return $response;
    }
    
    /**
     * Format an error response
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param mixed $errors Additional error details
     * @return WP_REST_Response
     */
    protected function error_response($message = 'An error occurred', $status = 400, $errors = null)
    {
        $response = new WP_REST_Response(
            ApiService::formatErrorResponse($message, $status, $errors)
        );
        $response->set_status($status);
        
        return $response;
    }
}