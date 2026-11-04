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
        
        // Only a signed-in user (WordPress cookie + nonce, or the app's own cookie)
        // gets past this point. Nonce-only and Bearer-only requests used to be
        // accepted on WP_DEBUG local sites when Origin/Referer matched the site,
        // skipping the capability check below; both headers are easily forged and
        // logged-out nonces are public.
        if (!is_user_logged_in()) {
            // No valid authentication found
            return new WP_Error(
                'rest_forbidden',
                'You must be logged in to access this endpoint.',
                ['status' => 401]
            );
        }

        // Role-based access, decided in one place. This used to let any hospital
        // role through whenever the capability was missing, and no role had these
        // capabilities, so every check passed for everyone (patients included).
        return \HospitalManager\Services\AccessPolicy::authorize($request, $required_capability);
    }
    
    /**
     * Check if user is authenticated
     * This method is more permissive and checks multiple authentication methods
     * 
     * @return bool|WP_Error Returns true if authenticated, WP_Error otherwise
     */
    public function check_auth() 
    {
        // Signed-in users only. This used to also accept a valid nonce on its own
        // (logged-out nonces are public), the mere presence of a login or Basic-auth
        // header/cookie, a cookie with the literal value "authenticated", and on
        // WP_DEBUG local sites any request with a site Origin/Referer.
        if (is_user_logged_in()) {
            return true;
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