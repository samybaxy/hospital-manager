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
            
            // Convert empty strings to null for nullable fields
            if (isset($params['appointment_id']) && $params['appointment_id'] === '') {
                $params['appointment_id'] = null;
            }
            
            // Validate required fields
            if (empty($params['patient_id']) || empty($params['doctor_id']) || 
                empty($params['date']) || empty($params['time'])) {
                return $this->error_response(
                    'Missing required fields: patient_id, doctor_id, date, time',
                    400
                );
            }
            
            // Use the Visitation model to update the record
            $updated = Visitation::updateById($id, $params);
            
            if (!$updated) {
                return $this->error_response(
                    'Visitation not found or failed to update',
                    404
                );
            }
            
            return $this->success_response(
                $updated->toArray(),
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
            
            // Use the Visitation model to delete the record
            $deleted = Visitation::deleteById($id);
            
            if (!$deleted) {
                return $this->error_response(
                    'Visitation not found or failed to delete',
                    404
                );
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