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
        // Create a view object for the controller
        $view = new \stdClass();
        $accessController = new AccessController($view);
        
        // Register route for getting user access permissions
        register_rest_route('hospital-manager/v1', '/access', [
            'methods' => 'GET',
            'callback' => [$accessController, 'getUserRouteAccess'],
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ]);
    }
}
