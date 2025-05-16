<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\HMO;

class HMOController extends BaseController
{
    /**
     * Register all routes for the HMO API
     * 
     * @return void
     */
    public function register_routes() 
    {
        // Route for listing all HMOs
        register_rest_route($this->namespace, '/hmos', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_hmos'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patients');
                },
            ]
        ]);
    }

    /**
     * Get all HMOs
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response
     */
    public function get_hmos($request) 
    {
        try {
            global $wpdb;
            
            // Get HMO table
            $hmo = new HMO();
            $table = $hmo->getTable();
            
            // Query all HMOs
            $query = "SELECT * FROM {$table} ORDER BY name ASC";
            $hmos = $wpdb->get_results($query);
            
            // Convert to array format with proper type casting
            $formatted_hmos = [];
            foreach ($hmos as $hmo) {
                // Force the ID to be an integer to ensure proper type comparison in filtering
                $formatted_hmos[] = [
                    'id' => (int)$hmo->id, // Cast to integer to ensure numeric comparison works
                    'name' => $hmo->name,
                ];
            }
            
            // Make sure we have at least one HMO for testing
            if (empty($formatted_hmos)) {
                error_log('Warning: No HMOs found in the database');
            }
            
            return $this->success_response(
                ['hmos' => $formatted_hmos],
                'HMOs retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving HMOs: ' . $e->getMessage(), 
                500
            );
        }
    }
}
