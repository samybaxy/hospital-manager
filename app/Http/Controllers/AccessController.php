<?php

namespace HospitalManager\Http\Controllers;

use HospitalManager\Services\RoleManager;
use HospitalManager\Controllers\Api\BaseController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class AccessController extends BaseController
{
    
    /**
     * Get user route access permissions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getUserRouteAccess(WP_REST_Request $request)
    {
        // Check permissions using the BaseController method
        $permission_check = $this->check_permission($request, 'read');
        if (is_wp_error($permission_check)) {
            return $permission_check;
        }

        // Get current user
        $user = wp_get_current_user();
        
        // User has no role
        if (empty($user->roles)) {
            return $this->error_response('User has no assigned role', 403, ['error_code' => 'no_role']);
        }

        // Debug user information
        error_log('DEBUG USER ROLES: ' . print_r($user->roles, true));
        error_log('DEBUG USER ID: ' . $user->ID);
        error_log('DEBUG USER CAPS: ' . print_r($user->allcaps, true));
        
        // Get the first role (primary role)
        $role = $user->roles[0];
        
        // Get route access map for this role
        $access_map = RoleManager::getRouteAccessMap($role);
        error_log('DEBUG ACCESS MAP: ' . print_r($access_map, true));
        
        // Return response using BaseController's success_response method
        return $this->success_response([
            'role' => $role,
            'access' => $access_map
        ], 'Access permissions retrieved successfully');
    }
}
