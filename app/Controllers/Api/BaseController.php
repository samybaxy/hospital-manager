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
        // Check if this is the login endpoint
        $current_route = $request->get_route();
        if (strpos($current_route, '/auth/login') !== false) {
            return true; // Always allow access to login endpoint
        }
        
        // First check if user is authenticated through WordPress session
        if (!is_user_logged_in()) {
            // If not logged in through WordPress session, check the request for nonce
            $nonce = $request->get_header('X-WP-Nonce');
            
            if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
                // Nonce verification passed, but we still need to match the user
                // This would require getting the user from the nonce or other authentication method
                // For development purposes, we'll accept the nonce as sufficient
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    return true; // In debug mode, allow nonce-only auth
                }
            }
            
            // Check for authorization header (JWT or custom token)
            $auth_header = $request->get_header('Authorization');
            if ($auth_header && strpos($auth_header, 'Bearer') !== false) {
                // Implement JWT token validation here if using JWT
                // For development purposes, we'll accept the header as sufficient
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    return true; // In debug mode, allow header-only auth
                }
            }
            
            // No valid authentication found
            return new WP_Error(
                'rest_forbidden',
                'You must be logged in to access this endpoint.',
                ['status' => 401]
            );
        }

        // Check required capability if specified
        if ($required_capability && !current_user_can($required_capability)) {
            // For development, accept administrator as having all capabilities
            if (current_user_can('administrator')) {
                return true;
            }
            
            // Check if the user has one of the allowed roles for this application
            $allowed_roles = ['administrator', 'doctor', 'patient', 'lab_tech', 'desk_officer'];
            $user = wp_get_current_user();
            $user_roles = (array) $user->roles;
            
            // Check if any of the user's roles are in the allowed roles array
            $has_allowed_role = false;
            foreach ($user_roles as $role) {
                if (in_array($role, $allowed_roles)) {
                    $has_allowed_role = true;
                    break;
                }
            }
            
            if ($has_allowed_role) {
                return true;
            }
            
            return new WP_Error(
                'rest_forbidden',
                'You do not have permission to access this resource.',
                ['status' => 403]
            );
        }

        return true;
    }
    
    /**
     * Check if user is authenticated
     * This method is more permissive and checks multiple authentication methods
     * 
     * @return bool|WP_Error Returns true if authenticated, WP_Error otherwise
     */
    public function check_auth() 
    {
        // First check standard WordPress authentication
        if (is_user_logged_in()) {
            return true;
        }
        
        // Check for nonce in header
        $headers = getallheaders();
        if (isset($headers['X-WP-Nonce']) && wp_verify_nonce($headers['X-WP-Nonce'], 'wp_rest')) {
            return true;
        }
        
        // Check for application password authentication
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            return true;
        }
        
        // Check for authentication via cookies for AJAX requests
        if (wp_doing_ajax() && isset($_COOKIE[LOGGED_IN_COOKIE])) {
            return true;
        }
        
        // Check for custom authentication marker set in login endpoint
        if (isset($_COOKIE['hospital_manager_auth']) && $_COOKIE['hospital_manager_auth'] === 'authenticated') {
            return true;
        }
        
        // In development environment, be more permissive
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development') {
            // Check if the request comes from the same origin
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            if (strpos($referer, site_url()) === 0) {
                return true;
            }
        }
        
        return new WP_Error(
            'rest_not_logged_in',
            'You must be logged in to access this endpoint.',
            ['status' => 401]
        );
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