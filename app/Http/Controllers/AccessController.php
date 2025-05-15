<?php

namespace HospitalManager\Http\Controllers;

use HospitalManager\Services\RoleManager;
use WPMVC\MVC\Controller as Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class AccessController extends Controller
{
    /**
     * Constructor
     * 
     * @param object $view View object
     */
    public function __construct($view)
    {
        parent::__construct($view);
    }
    
    /**
     * Get user route access permissions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getUserRouteAccess(WP_REST_Request $request)
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return new WP_Error('unauthorized', 'You must be logged in to access this endpoint.', ['status' => 401]);
        }

        // Get current user
        $user = wp_get_current_user();
        
        // User has no role
        if (empty($user->roles)) {
            return new WP_Error('no_role', 'User has no assigned role.', ['status' => 403]);
        }
        
        // Get the first role (primary role)
        $role = $user->roles[0];
        
        // Get route access map for this role
        $access_map = RoleManager::getRouteAccessMap($role);
        
        // Return response
        return new WP_REST_Response([
            'success' => true,
            'role' => $role,
            'access' => $access_map
        ], 200);
    }
}
