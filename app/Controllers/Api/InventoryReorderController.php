<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use Exception;
use HospitalManager\Models\InventoryReorder;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\RoleService;
use HospitalManager\Services\InventoryService;

class InventoryReorderController extends InventoryBaseController
{
    public function __construct()
    {
        $this->namespace = 'hospital-manager/v1';
    }

    /**
     * Register reorder routes
     */
    public function register_routes()
    {
        // Routes for reorders
        register_rest_route($this->namespace, '/inventory/reorders', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_reorders'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                },
                'args' => [
                    'status' => [
                        'description' => 'Filter by reorder status',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'priority' => [
                        'description' => 'Filter by priority level',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'item_id' => [
                        'description' => 'Filter by item ID',
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'supplier_id' => [
                        'description' => 'Filter by supplier ID',
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'page' => [
                        'description' => 'Page number for pagination',
                        'type' => 'integer',
                        'default' => 1,
                        'sanitize_callback' => 'absint'
                    ],
                    'per_page' => [
                        'description' => 'Number of items per page',
                        'type' => 'integer',
                        'default' => 10,
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_reorder'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                },
                'args' => [
                    'item_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'supplier_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'quantity' => [
                        'required' => true,
                        'type' => 'integer'
                    ],
                    'priority' => [
                        'type' => 'string',
                        'default' => 'medium',
                        'enum' => ['low', 'medium', 'high', 'urgent']
                    ],
                    'notes' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ]
                ]
            ]
        ]);

        // Route for approving reorders
        register_rest_route($this->namespace, '/inventory/reorders/(?P<ID>\d+)/approve', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'approve_reorder'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ]
        ]);

        // Route for completing reorders
        register_rest_route($this->namespace, '/inventory/reorders/(?P<ID>\d+)/complete', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'complete_reorder'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                },
                'args' => [
                    'received_quantity' => [
                        'required' => true,
                        'type' => 'integer'
                    ],
                    'notes' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ]
                ]
            ]
        ]);

        // Route for generating reorder suggestions
        register_rest_route($this->namespace, '/inventory/reorders/suggestions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_reorder_suggestions'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                },
                'args' => [
                    'page' => [
                        'description' => 'Page number for pagination',
                        'type' => 'integer',
                        'default' => 1,
                        'sanitize_callback' => 'absint'
                    ],
                    'per_page' => [
                        'description' => 'Number of items per page',
                        'type' => 'integer',
                        'default' => 10,
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get reorders with filtering
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_reorders($request)
    {
        try {
            // Use centralized pagination handling
            $pagination = $this->handlePagination($request, 10);
            
            $filters = [
                'status' => $request->get_param('status'),
                'priority' => $request->get_param('priority'),
                'item_id' => $request->get_param('item_id'),
                'supplier_id' => $request->get_param('supplier_id')
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $result = InventoryReorder::getFiltered($filters, $pagination['page'], $pagination['per_page']);

            return new WP_REST_Response([
                'success' => true,
                'data' => $result,
                'message' => count($result['data']) . ' reorders retrieved (page ' . $pagination['page'] . ' of ' . $result['pagination']['total_pages'] . ')'
            ], 200);

        } catch (Exception $e) {
            // Log the error for debugging
            error_log('Reorders API Error: ' . $e->getMessage());
            error_log('Reorders API Trace: ' . $e->getTraceAsString());
            
            return new WP_Error(
                'reorders_fetch_error',
                'Failed to fetch reorders: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Create a new reorder
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_reorder($request)
    {
        try {
            $data = [
                'item_id' => $request->get_param('item_id'),
                'supplier_id' => $request->get_param('supplier_id'),
                'quantity' => $request->get_param('quantity'),
                'priority' => $request->get_param('priority'),
                'notes' => $request->get_param('notes'),
                'requested_by' => get_current_user_id()
            ];

            $reorder = InventoryReorder::create($data);

            return new WP_REST_Response(
                ApiService::formatResponse($reorder, 'Reorder created successfully'),
                201
            );

        } catch (Exception $e) {
            return new WP_Error(
                'reorder_create_error',
                'Failed to create reorder: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Approve a reorder
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function approve_reorder($request)
    {
        try {
            $reorder_id = $request->get_param('ID');
            $result = InventoryService::approveReorder($reorder_id, get_current_user_id());

            return new WP_REST_Response(
                ApiService::formatResponse($result, 'Reorder approved successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'reorder_approve_error',
                'Failed to approve reorder: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Complete a reorder
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function complete_reorder($request)
    {
        try {
            $reorder_id = $request->get_param('ID');
            $received_quantity = $request->get_param('received_quantity');
            $notes = $request->get_param('notes');

            $result = InventoryService::completeReorder($reorder_id, $received_quantity, $notes, get_current_user_id());

            return new WP_REST_Response(
                ApiService::formatResponse($result, 'Reorder completed successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'reorder_complete_error',
                'Failed to complete reorder: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get reorder suggestions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_reorder_suggestions($request)
    {
        try {
            // Get pagination parameters
            $page = $request->get_param('page') ?: 1;
            $per_page = $request->get_param('per_page') ?: 10;

            // Validate pagination parameters
            $page = max(1, intval($page));
            $per_page = max(1, min(100, intval($per_page))); // Limit to max 100 items per page

            // Get paginated suggestions
            $result = InventoryService::generateReorderSuggestionsPaginated($page, $per_page);

            return new WP_REST_Response([
                'success' => true,
                'data' => $result,
                'message' => count($result['data']) . ' reorder suggestions generated (page ' . $page . ' of ' . $result['pagination']['total_pages'] . ')'
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'reorder_suggestions_error',
                'Failed to generate reorder suggestions: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
