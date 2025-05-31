<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\Visitation;
use HospitalManager\Services\VisitationService;

class VisitationController extends BaseController 
{
    public function register_routes() 
    {
        register_rest_route($this->namespace, '/visitations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_visitations'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_visitation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'add_visitation');
                },
            ]
        ]);

        // Register route for getting a single visitation by ID
        register_rest_route($this->namespace, '/visitations/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_visitation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'view_patient_records');
                },
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_visitation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'edit_visitation');
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_visitation'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'delete_visitation');
                },
            ]
        ]);
    }

    public function get_visitations($request) 
    {
        try {
            // Extract parameters from request
            $params = $request->get_params();
            
            // Use the service to handle all business logic
            $result = VisitationService::getVisitations($params);
            
            return $this->success_response(
                $result, 
                'Visitations retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving visitations: ' . $e->getMessage(), 
                500
            );
        }
    }

    public function create_visitation($request) 
    {
        try {
            $params = $request->get_params();
            
            // Validate required fields
            if (empty($params['patient_id']) || empty($params['doctor_id']) || 
                empty($params['date']) || empty($params['time'])) {
                return $this->error_response(
                    'Missing required fields: patient_id, doctor_id, date, time',
                    400
                );
            }
            
            // Create visitation directly with Visitation model
            $visitation = Visitation::create($params);
            
            if (!$visitation) {
                throw new \Exception('Failed to create visitation');
            }
            
            return $this->success_response(
                $visitation,
                'Visitation created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error creating visitation: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Get a single visitation record by ID
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response Response containing visitation data or error
     */
    public function get_visitation($request) 
    {
        try {
            $id = $request['id'];
            $result = VisitationService::getVisitation($id);
            
            if (!$result) {
                return $this->error_response(
                    'Visitation not found',
                    404
                );
            }
            
            return $this->success_response(
                $result, 
                'Visitation retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving visitation: ' . $e->getMessage(), 
                500
            );
        }
    }
    
    /**
     * Update a visitation record
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response Response containing updated visitation data or error
     */
    public function update_visitation($request) 
    {
        try {
            $id = $request['id'];
            $params = $request->get_params();
            
            // Remove the ID from params to avoid conflicts
            unset($params['id']);
            unset($params['ID']);
            
            // Remove any non-table fields that might cause SQL errors
            $allowed_fields = ['patient_id', 'doctor_id', 'appointment_id', 'date', 'time', 
                              'medical_history', 'diagnosis', 'treatment', 'complaint'];
            
            $filtered_params = array_intersect_key($params, array_flip($allowed_fields));
            
            // Convert empty strings to null for nullable fields
            if (isset($filtered_params['appointment_id']) && $filtered_params['appointment_id'] === '') {
                $filtered_params['appointment_id'] = null;
            }
            
            // Validate required fields
            if (empty($filtered_params['patient_id']) || empty($filtered_params['doctor_id']) || 
                empty($filtered_params['date']) || empty($filtered_params['time'])) {
                return $this->error_response(
                    'Missing required fields: patient_id, doctor_id, date, time',
                    400
                );
            }
            
            // Check if visitation exists first
            $existing = VisitationService::getVisitation($id);
            if (!$existing) {
                return $this->error_response(
                    'Visitation not found',
                    404
                );
            }
            
            // Use a method that we know exists to update the record
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_visitations';
            
            // Debug the query being executed
            error_log("Updating visitation ID {$id} in table {$table_name}");
            error_log("Update data: " . json_encode($params));
            
            $result = $wpdb->update(
                $table_name,
                $filtered_params,
                ['ID' => $id],
                null,
                ['%d']
            );
            
            if ($result === false) {
                error_log("Database error: " . $wpdb->last_error);
                throw new \Exception('Failed to update visitation: ' . $wpdb->last_error);
            }
            
            // Get the updated record
            $updated = VisitationService::getVisitation($id);
            
            return $this->success_response(
                $updated,
                'Visitation updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error updating visitation: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Delete a visitation record
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response Response indicating success or failure
     */
    public function delete_visitation($request) 
    {
        try {
            $id = $request['id'];
            
            // Check if visitation exists first
            $existing = VisitationService::getVisitation($id);
            if (!$existing) {
                return $this->error_response(
                    'Visitation not found',
                    404
                );
            }
            
            // Delete the record using wpdb
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_visitations';
            
            $result = $wpdb->delete(
                $table_name,
                ['ID' => $id],
                ['%d']
            );
            
            if ($result === false) {
                throw new \Exception('Failed to delete visitation');
            }
            
            return $this->success_response(
                ['id' => $id, 'deleted' => true],
                'Visitation deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error deleting visitation: ' . $e->getMessage(),
                500
            );
        }
    }
}