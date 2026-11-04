<?php

namespace HospitalManager\Http\Controllers;

use HospitalManager\Services\RoleService;
use HospitalManager\Controllers\Api\BaseController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Exception;

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
        // Any signed-in user may ask what they can access. This used to require the
        // WordPress "read" capability, which pharmacy_staff and inventory_manager
        // don't have, so their menus failed to load (403).
        $permission_check = $this->check_permission($request, '');
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
        $access_map = RoleService::getRouteAccessMap($role);

        // Patient, visit and lab flags come from the same map the API enforces
        // (AccessPolicy), so menus and buttons match what requests will be allowed.
        $access_map = array_merge($access_map, \HospitalManager\Services\AccessPolicy::accessFlags($user));
        
        // Return response using BaseController's success_response method
        return $this->success_response([
            'role' => $role,
            'access' => $access_map,
            'user_id' => $user->ID,
            // hm_doctors.ID (visits store this, not the WordPress user ID); null if not a doctor
            'doctor_id' => \HospitalManager\Services\AccessPolicy::doctorRecordId($user->ID),
        ], 'Access permissions retrieved successfully');
    }

    /**
     * Sync role capabilities (temporary endpoint for testing)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function syncRoles(WP_REST_Request $request)
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return $this->error_response('Insufficient permissions', 403, ['error_code' => 'insufficient_permissions']);
        }

        try {
            // Sync role capabilities
            RoleService::syncRoleCapabilities();
            
            return $this->success_response([
                'message' => 'Role capabilities synchronized successfully'
            ], 'Roles synchronized');

        } catch (Exception $e) {
            return $this->error_response('Failed to sync roles: ' . $e->getMessage(), 500, [
                'error_code' => 'sync_failed'
            ]);
        }
    }
}
