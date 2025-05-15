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
        
        // Get the first role (primary role)
        $role = $user->roles[0];
        
        // Get route access map for this role
        $access_map = RoleManager::getRouteAccessMap($role);
        
        // For administrators, ensure all routes are accessible regardless of what's in the database
        if ($role === 'administrator') {
            $all_routes = [
                'patients' => true,
                'doctors' => true,
                'appointments' => true,
                'departments' => true,
                'visitations' => true,
                'chat' => true,
                'notifications' => true,
                'audit_log' => true,
                'billing' => true,
                'inventory' => true,
                'reports' => true,
                'statistics' => true,
                'settings' => true,
                'lab_dashboard' => true
            ];
            
            // Merge with existing permissions, prioritizing 'true' values
            $access_map = array_merge($access_map, $all_routes);
        }
        
        // Return response using BaseController's success_response method
        return $this->success_response([
            'role' => $role,
            'access' => $access_map
        ], 'Access permissions retrieved successfully');
    }
}
