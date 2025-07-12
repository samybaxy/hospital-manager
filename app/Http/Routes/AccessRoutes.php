<?php

namespace HospitalManager\Http\Routes;

use HospitalManager\Http\Controllers\AccessController;

class AccessRoutes
{
    /**
     * Register the access routes for the API
     */
    public function register()
    {
        // Create controller instance
        $accessController = new AccessController();
        
        // Register route for getting user access permissions
        register_rest_route('hospital-manager/v1', '/access', [
            'methods' => 'GET',
            'callback' => [$accessController, 'getUserRouteAccess'],
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ]);

        // Temporary route to sync roles - only for admins
        register_rest_route('hospital-manager/v1', '/sync-roles', [
            'methods' => 'POST',
            'callback' => [$accessController, 'syncRoles'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }
}
