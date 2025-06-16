<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use Exception;
use HospitalManager\Models\Inventory;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\RoleService;
use HospitalManager\Services\InventoryService;

class InventoryController extends InventoryBaseController
{
    private $transactionController;
    private $alertController;
    private $supplierController;
    private $reorderController;

    public function __construct()
    {
        // Set default namespace for inventory routes
        $this->namespace = 'hospital-manager/v1';
        
        // Initialize sub-controllers
        $this->transactionController = new InventoryTransactionController();
        $this->alertController = new InventoryAlertController();
        $this->supplierController = new InventorySupplierController();
        $this->reorderController = new InventoryReorderController();
    }

    /**
     * Register all routes for the Inventory API
     * 
     * @return void
     */
    public function register_routes()
    {
        // Register inventory item routes
        $this->register_item_routes();
        
        // Register dashboard and reporting routes
        $this->register_dashboard_routes();
        
        // Delegate to specialized controllers
        $this->transactionController->register_routes();
        $this->alertController->register_routes();
        $this->supplierController->register_routes();
        $this->reorderController->register_routes();
    }

    /**
     * Register inventory item related routes
     */
    private function register_item_routes()
    {
        // Route for listing and creating inventory items
        register_rest_route($this->namespace, '/inventory', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_inventory'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                },
                'args' => [
                    'category' => [
                        'description' => 'Filter by category',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'status' => [
                        'description' => 'Filter by status',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'location' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'search' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'low_stock' => [
                        'default' => false
                    ],
                    'expiring' => [
                        'default' => false
                    ],
                    'sort_by' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'sort_direction' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'limit' => [
                        'default' => 50
                    ],
                    'offset' => [
                        'default' => 0
                    ],
                    'page' => [
                        'default' => 1
                    ],
                    'per_page' => []
                ]
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_inventory_item'],
                'permission_callback' => function($request) {
                    return RoleService::canCreateInventory();
                },
                'args' => [
                    'item_name' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'category' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'quantity' => [
                        'default' => 0
                    ],
                    'unit' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'reorder_level' => [
                        'default' => 10
                    ],
                    'expiry_date' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'location' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'cost' => []
                ]
            ]
        ]);

        // Routes for individual inventory item operations
        register_rest_route($this->namespace, '/inventory/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_inventory_item'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                }
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_inventory_item'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_inventory_item'],
                'permission_callback' => function($request) {
                    return RoleService::canDeleteInventory();
                }
            ]
        ]);

        // Route for critical inventory items
        register_rest_route($this->namespace, '/inventory/critical', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_critical_inventory'],
                'permission_callback' => function($request) {
                    return RoleService::canViewCriticalItems();
                }
            ]
        ]);

        // Route for expiring inventory items
        register_rest_route($this->namespace, '/inventory/expiring', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_expiring_inventory'],
                'permission_callback' => function($request) {
                    return RoleService::canViewCriticalItems();
                },
                'args' => [
                    'days' => []
                ]
            ]
        ]);

        // Route for inventory summary/stats
        register_rest_route($this->namespace, '/inventory/summary', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_inventory_summary'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                }
            ]
        ]);

        // Route for category summary
        register_rest_route($this->namespace, '/inventory/categories', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_category_summary'],
                'permission_callback' => function($request) {
                    return RoleService::canViewReports();
                }
            ]
        ]);

        // Route for updating inventory status
        register_rest_route($this->namespace, '/inventory/(?P<ID>\d+)/status', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_inventory_status'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ]
        ]);

        // Route for bulk operations
        register_rest_route($this->namespace, '/inventory/bulk', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'bulk_update_inventory'],
                'permission_callback' => function($request) {
                    return RoleService::canPerformBulkOperations();
                },
                'args' => [
                    'action' => [
                        'enum' => ['update_status', 'delete', 'update_location']
                    ],
                    'items' => []
                ]
            ]
        ]);

        // Route for export
        register_rest_route($this->namespace, '/inventory/export', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'export_inventory'],
                'permission_callback' => function($request) {
                    return RoleService::canExportInventory();
                },
                'args' => [
                    'format' => []
                ]
            ]
        ]);

        // Route for user permissions
        register_rest_route($this->namespace, '/inventory/permissions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_user_permissions'],
                'permission_callback' => function($request) {
                    return is_user_logged_in();
                }
            ]
        ]);
    }

    /**
     * Register dashboard and reporting routes
     */
    private function register_dashboard_routes()
    {
        // Route for dashboard data
        register_rest_route($this->namespace, '/inventory/dashboard', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_data'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                }
            ]
        ]);
    }

    /**
     * Get inventory items with filtering
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_inventory($request)
    {
        try {
            // Use centralized pagination handling
            $pagination = $this->handlePagination($request, 20);

            $filters = [
                'category' => $request->get_param('category'),
                'status' => $request->get_param('status'),
                'location' => $request->get_param('location'),
                'search' => $request->get_param('search'),
                'low_stock' => $request->get_param('low_stock'),
                'expiring' => $request->get_param('expiring'),
                'sort_by' => $request->get_param('sort_by'),
                'sort_direction' => $request->get_param('sort_direction'),
                'limit' => $pagination['limit'],
                'offset' => $pagination['offset']
            ];

            // Remove null values except for limit and offset
            $filters = array_filter($filters, function($value, $key) {
                if (in_array($key, ['limit', 'offset'])) {
                    return true; // Keep limit and offset even if 0
                }
                return $value !== null && $value !== '';
            }, ARRAY_FILTER_USE_BOTH);

            // Get total count first (without limit/offset)
            $count_filters = $filters;
            unset($count_filters['limit']);
            unset($count_filters['offset']);
            $total_items = Inventory::getFilteredCount($count_filters);

            // Get paginated items
            $items = Inventory::getFiltered($filters);

            // Calculate pagination metadata
            $total_pages = $pagination['per_page'] > 0 ? ceil($total_items / $pagination['per_page']) : 1;

            $response_data = [
                'data' => $items,
                'pagination' => [
                    'current_page' => intval($pagination['page']),
                    'per_page' => intval($pagination['per_page']),
                    'total_items' => intval($total_items),
                    'total_pages' => intval($total_pages),
                    'has_next' => $pagination['page'] < $total_pages,
                    'has_prev' => $pagination['page'] > 1
                ]
            ];

            return new WP_REST_Response(
                ApiService::formatResponse($response_data, 'Inventory items retrieved successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_fetch_error',
                'Failed to fetch inventory items: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get a single inventory item
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_inventory_item($request)
    {
        return $this->createResponse(
            function() use ($request) {
                $id = intval($request['ID']);
                $item = Inventory::findOne($id);

                if (!$item) {
                    throw new \Exception('Inventory item not found', 404);
                }

                return $item;
            },
            'Inventory item retrieved successfully',
            'inventory_fetch_error',
            'Failed to fetch inventory item'
        );
    }

    /**
     * Create a new inventory item
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_inventory_item($request)
    {
        try {
            $data = [
                'item_name' => $request->get_param('item_name'),
                'category' => $request->get_param('category'),
                'quantity' => intval($request->get_param('quantity')),
                'unit' => $request->get_param('unit'),
                'reorder_level' => intval($request->get_param('reorder_level')),
                'location' => $request->get_param('location'),
                'cost' => $request->get_param('cost')
            ];

            $expiry_date = $request->get_param('expiry_date');
            if (!empty($expiry_date)) {
                $data['expiry_date'] = $expiry_date;
            }

            $item_id = Inventory::create($data);

            if (!$item_id) {
                return new WP_Error(
                    'inventory_create_error',
                    'Failed to create inventory item',
                    ['status' => 500]
                );
            }

            $item = Inventory::findOne($item_id);

            return new WP_REST_Response(
                ApiService::formatResponse($item, 'Inventory item created successfully'),
                201
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_create_error',
                'Failed to create inventory item: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update an inventory item
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_inventory_item($request)
    {
        try {
            $id = intval($request['ID']);
            
            // Check if item exists
            $existing_item = Inventory::findOne($id);
            if (!$existing_item) {
                return new WP_Error(
                    'inventory_not_found',
                    'Inventory item not found',
                    ['status' => 404]
                );
            }

            $data = [];
            $updateable_fields = ['item_name', 'category', 'quantity', 'unit', 'reorder_level', 'expiry_date', 'location', 'cost'];

            foreach ($updateable_fields as $field) {
                $value = $request->get_param($field);
                if ($value !== null) {
                    if (in_array($field, ['quantity', 'reorder_level'])) {
                        $data[$field] = intval($value);
                    } else {
                        $data[$field] = $value;
                    }
                }
            }

            if (empty($data)) {
                return new WP_Error(
                    'no_data_to_update',
                    'No valid data provided for update',
                    ['status' => 400]
                );
            }

            $success = Inventory::updateItem($id, $data);

            if (!$success) {
                return new WP_Error(
                    'inventory_update_error',
                    'Failed to update inventory item',
                    ['status' => 500]
                );
            }

            $item = Inventory::findOne($id);

            return new WP_REST_Response(
                ApiService::formatResponse($item, 'Inventory item updated successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_update_error',
                'Failed to update inventory item: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete an inventory item
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_inventory_item($request)
    {
        try {
            $id = intval($request['ID']);
            
            // Check if item exists
            $existing_item = Inventory::findOne($id);
            if (!$existing_item) {
                return new WP_Error(
                    'inventory_not_found',
                    'Inventory item not found',
                    ['status' => 404]
                );
            }

            $success = Inventory::deleteItem($id);

            if (!$success) {
                return new WP_Error(
                    'inventory_delete_error',
                    'Failed to delete inventory item',
                    ['status' => 500]
                );
            }

            return new WP_REST_Response(
                ApiService::formatResponse(null, 'Inventory item deleted successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_delete_error',
                'Failed to delete inventory item: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get critical inventory items
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_critical_inventory($request)
    {
        return $this->createResponse(
            function() {
                return Inventory::getCritical();
            },
            'Critical inventory items retrieved successfully',
            'inventory_fetch_error',
            'Failed to fetch critical inventory items'
        );
    }

    /**
     * Get expiring inventory items
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_expiring_inventory($request)
    {
        return $this->createResponse(
            function() use ($request) {
                $days = intval($request->get_param('days'));
                return Inventory::getExpiringSoon($days);
            },
            'Expiring inventory items retrieved successfully',
            'inventory_fetch_error',
            'Failed to fetch expiring inventory items'
        );
    }

    /**
     * Get inventory summary statistics
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_inventory_summary($request)
    {
        return $this->createResponse(
            function() {
                return Inventory::getSummary();
            },
            'Inventory summary retrieved successfully',
            'inventory_fetch_error',
            'Failed to fetch inventory summary'
        );
    }

    /**
     * Get category summary
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_category_summary($request)
    {
        return $this->createResponse(
            function() {
                return Inventory::getCategorySummary();
            },
            'Category summary retrieved successfully',
            'inventory_fetch_error',
            'Failed to fetch category summary'
        );
    }

    /**
     * Update inventory item status
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_inventory_status($request)
    {
        try {
            $id = intval($request['ID']);
            $success = Inventory::updateStatus($id);

            if (!$success) {
                return new WP_Error(
                    'inventory_status_update_error',
                    'Failed to update inventory status',
                    ['status' => 500]
                );
            }

            $item = Inventory::findOne($id);

            return new WP_REST_Response(
                ApiService::formatResponse($item, 'Inventory status updated successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_status_update_error',
                'Failed to update inventory status: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Bulk update inventory items
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulk_update_inventory($request)
    {
        try {
            $action = $request->get_param('action');
            $items = $request->get_param('items');

            if (empty($items) || !is_array($items)) {
                return new WP_Error(
                    'invalid_items',
                    'Invalid items array provided',
                    ['status' => 400]
                );
            }

            $results = [];

            foreach ($items as $item_data) {
                $id = intval($item_data['id']);
                
                switch ($action) {
                    case 'update_status':
                        break;
                    case 'delete':
                        break;
                    case 'update_location':
                }
            }

            return new WP_REST_Response(
                ApiService::formatResponse($results, 'Bulk operation completed'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'bulk_operation_error',
                'Failed to perform bulk operation: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Export inventory data
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function export_inventory($request)
    {
        try {
            $format = $request->get_param('format');
            $items = Inventory::getFiltered();

            if ($format === 'csv') {
                $csv_data = $this->generate_csv($items);
                
                return new WP_REST_Response([
                    'success' => true,
                    'data' => $csv_data,
                    'filename' => 'inventory_export_' . date('Y-m-d_H-i-s') . '.csv',
                    'content_type' => 'text/csv'
                ], 200);
            }

            return new WP_REST_Response(
                ApiService::formatResponse($items, 'Inventory data exported successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'export_error',
                'Failed to export inventory data: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Generate CSV data from inventory items
     *
     * @param array $items
     * @return string
     */
    private function generate_csv($items)
    {
        $csv = "ID,Item Name,Category,Quantity,Unit,Reorder Level,Status,Location,Cost,Expiry Date,Created At\n";
        
        foreach ($items as $item) {
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",%d,\"%s\",%d,\"%s\",\"%s\",%.2f,\"%s\",\"%s\"\n",
                $item->ID,
                str_replace('"', '""', $item->item_name),
                str_replace('"', '""', $item->category),
                $item->quantity,
                str_replace('"', '""', $item->unit),
                $item->reorder_level,
                str_replace('"', '""', $item->status),
                str_replace('"', '""', $item->location ?: ''),
                floatval($item->cost ?: 0),
                $item->expiry_date ?: '',
                $item->created_at
            );
        }
        
        return $csv;
    }

    /**
     * Get current user's inventory permissions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_user_permissions($request)
    {
        try {
            $permissions = RoleService::getUserInventoryPermissions();
            $user_role = RoleService::getUserRoleDisplayName();

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'permissions' => $permissions,
                    'role' => $user_role,
                    'user_id' => get_current_user_id()
                ]
            ], 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to get user permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard data with comprehensive statistics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_dashboard_data($request)
    {
        try {
            $dashboard_data = InventoryService::getDashboardData();

            return new WP_REST_Response(
                ApiService::formatResponse($dashboard_data, 'Dashboard data retrieved successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'dashboard_data_error',
                'Failed to retrieve dashboard data: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
