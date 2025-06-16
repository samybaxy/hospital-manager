<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use Exception;
use HospitalManager\Models\InventoryAlert;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\RoleService;
use HospitalManager\Services\InventoryService;

class InventoryAlertController extends InventoryBaseController
{
    public function __construct()
    {
        $this->namespace = 'hospital-manager/v1';
    }

    /**
     * Register alert routes
     */
    public function register_routes()
    {
        // Routes for inventory alerts
        register_rest_route($this->namespace, '/inventory/alerts', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_alerts'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                },
                'args' => [
                    'type' => [
                        'description' => 'Filter by alert type',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'severity' => [
                        'description' => 'Filter by severity level',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'status' => [
                        'description' => 'Filter by alert status',
                        'type' => 'string',
                        'default' => 'active',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'limit' => [
                        'description' => 'Number of alerts to return',
                        'type' => 'integer',
                        'default' => 50
                    ]
                ]
            ]
        ]);

        // Route for acknowledging alerts
        register_rest_route($this->namespace, '/inventory/alerts/(?P<ID>\d+)/acknowledge', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'acknowledge_alert'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ]
        ]);

        // Route for resolving alerts
        register_rest_route($this->namespace, '/inventory/alerts/(?P<ID>\d+)/resolve', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'resolve_alert'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ]
        ]);

        // Route for generating alerts
        register_rest_route($this->namespace, '/inventory/alerts/generate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_alerts'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ]
        ]);
    }

    /**
     * Get inventory alerts with filtering
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_alerts($request)
    {
        try {
            // Use centralized pagination handling
            $pagination = $this->handlePagination($request, 50);

            $filters = [
                'type' => $request->get_param('type'),
                'severity' => $request->get_param('severity'),
                'status' => $request->get_param('status'),
                'limit' => $pagination['limit'],
                'offset' => $pagination['offset']
            ];

            // Remove null values except for limit and offset
            $filters = array_filter($filters, function($value, $key) {
                if (in_array($key, ['limit', 'offset'])) {
                    return true;
                }
                return $value !== null && $value !== '';
            }, ARRAY_FILTER_USE_BOTH);

            $alerts = InventoryAlert::getFiltered($filters);
            $total_count = InventoryAlert::getFilteredCount($filters);
            
            // Calculate pagination metadata
            $total_pages = $pagination['per_page'] > 0 ? ceil($total_count / $pagination['per_page']) : 1;

            $response_data = [
                'data' => $alerts,
                'pagination' => [
                    'current_page' => $pagination['page'],
                    'per_page' => $pagination['per_page'],
                    'total' => $total_count,
                    'pages' => $total_pages
                ]
            ];

            return new WP_REST_Response(
                ApiService::formatResponse($response_data, 'Alerts retrieved successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'alerts_fetch_error',
                'Failed to fetch alerts: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Acknowledge an alert
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function acknowledge_alert($request)
    {
        return $this->createResponse(
            function() use ($request) {
                $alert_id = $request->get_param('ID');
                return InventoryService::acknowledgeAlert($alert_id, get_current_user_id());
            },
            'Alert acknowledged successfully',
            'alert_acknowledge_error',
            'Failed to acknowledge alert'
        );
    }

    /**
     * Resolve an alert
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function resolve_alert($request)
    {
        return $this->createResponse(
            function() use ($request) {
                $alert_id = $request->get_param('ID');
                return InventoryService::resolveAlert($alert_id, get_current_user_id());
            },
            'Alert resolved successfully',
            'alert_resolve_error',
            'Failed to resolve alert'
        );
    }

    /**
     * Generate automatic alerts
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function generate_alerts($request)
    {
        try {
            $alerts = InventoryService::generateAlerts();

            return new WP_REST_Response([
                'success' => true,
                'data' => $alerts,
                'message' => count($alerts) . ' alerts generated successfully'
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'alerts_generate_error',
                'Failed to generate alerts: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
