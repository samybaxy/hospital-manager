<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use Exception;
use HospitalManager\Models\Inventory;
use HospitalManager\Models\InventoryTransaction;
use HospitalManager\Models\InventoryAlert;
use HospitalManager\Models\InventorySupplier;
use HospitalManager\Models\InventoryReorder;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\RoleService;
use HospitalManager\Services\InventoryService;

class InventoryController extends BaseController
{
    public function __construct()
    {
        // Set default namespace for inventory routes
        $this->namespace = 'hospital-manager/v1';
    }
    /**
     * Register all routes for the Inventory API
     * 
     * @return void
     */
    public function register_routes()
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
                        'description' => 'Filter by location',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'search' => [
                        'description' => 'Search in item name, category, or location',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'sort_by' => [
                        'description' => 'Sort by field',
                        'type' => 'string',
                        'default' => 'created_at',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'sort_direction' => [
                        'description' => 'Sort direction',
                        'type' => 'string',
                        'default' => 'desc',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'limit' => [
                        'description' => 'Number of items to return',
                        'type' => 'integer',
                        'default' => 50
                    ],
                    'offset' => [
                        'description' => 'Number of items to skip',
                        'type' => 'integer',
                        'default' => 0
                    ]
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
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'category' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'quantity' => [
                        'type' => 'integer',
                        'default' => 0
                    ],
                    'unit' => [
                        'type' => 'string',
                        'default' => 'units',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'reorder_level' => [
                        'type' => 'integer',
                        'default' => 10
                    ],
                    'expiry_date' => [
                        'type' => 'string',
                        'format' => 'date',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'location' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'cost' => [
                        'type' => 'number'
                    ]
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
                    'days' => [
                        'description' => 'Number of days to look ahead',
                        'type' => 'integer',
                        'default' => 30
                    ]
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
                        'required' => true,
                        'type' => 'string',
                        'enum' => ['update_status', 'delete', 'update_location']
                    ],
                    'items' => [
                        'required' => true,
                        'type' => 'array'
                    ]
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
                    'format' => [
                        'type' => 'string',
                        'default' => 'csv',
                        'enum' => ['csv', 'json']
                    ]
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

        // Routes for inventory transactions
        register_rest_route($this->namespace, '/inventory/transactions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_transactions'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                },
                'args' => [
                    'item_id' => [
                        'description' => 'Filter by inventory item ID',
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'type' => [
                        'description' => 'Filter by transaction type',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'date_from' => [
                        'description' => 'Filter from date (Y-m-d format)',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'date_to' => [
                        'description' => 'Filter to date (Y-m-d format)',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'limit' => [
                        'description' => 'Number of transactions to return',
                        'type' => 'integer',
                        'default' => 50
                    ],
                    'offset' => [
                        'description' => 'Number of transactions to skip',
                        'type' => 'integer',
                        'default' => 0
                    ]
                ]
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_transaction'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                },
                'args' => [
                    'item_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'type' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => ['stock_in', 'stock_out', 'adjustment', 'transfer', 'expired', 'damaged', 'returned']
                    ],
                    'quantity' => [
                        'required' => true,
                        'type' => 'integer'
                    ],
                    'reference' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'notes' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ]
                ]
            ]
        ]);

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

        // Routes for suppliers
        register_rest_route($this->namespace, '/inventory/suppliers', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_suppliers'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                }
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_supplier'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                },
                'args' => [
                    'name' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'contact_person' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'email' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_email'
                    ],
                    'phone' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'address' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'payment_terms' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        ]);

        // Routes for individual supplier operations
        register_rest_route($this->namespace, '/inventory/suppliers/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_supplier'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                }
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_supplier'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_supplier'],
                'permission_callback' => function($request) {
                    return RoleService::canDeleteInventory();
                }
            ]
        ]);

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

        // Route for generating automatic alerts
        register_rest_route($this->namespace, '/inventory/alerts/generate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_alerts'],
                'permission_callback' => function($request) {
                    return RoleService::canEditInventory();
                }
            ]
        ]);

        // Route for generating reorder suggestions
        register_rest_route($this->namespace, '/inventory/reorders/suggestions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_reorder_suggestions'],
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
            $filters = [
                'category' => $request->get_param('category'),
                'status' => $request->get_param('status'),
                'location' => $request->get_param('location'),
                'search' => $request->get_param('search'),
                'sort_by' => $request->get_param('sort_by'),
                'sort_direction' => $request->get_param('sort_direction'),
                'limit' => $request->get_param('limit'),
                'offset' => $request->get_param('offset')
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $items = Inventory::getFiltered($filters);

            return new WP_REST_Response(
                ApiService::formatResponse($items, 'Inventory items retrieved successfully'),
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
        try {
            $id = intval($request['ID']);
            $item = Inventory::findOne($id);

            if (!$item) {
                return new WP_Error(
                    'inventory_not_found',
                    'Inventory item not found',
                    ['status' => 404]
                );
            }

            return new WP_REST_Response(
                ApiService::formatResponse($item, 'Inventory item retrieved successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_fetch_error',
                'Failed to fetch inventory item: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
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
        try {
            $items = Inventory::getCritical();

            return new WP_REST_Response(
                ApiService::formatResponse($items, 'Critical inventory items retrieved successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_fetch_error',
                'Failed to fetch critical inventory items: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get expiring inventory items
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_expiring_inventory($request)
    {
        try {
            $days = intval($request->get_param('days'));
            $items = Inventory::getExpiringSoon($days);

            return new WP_REST_Response(
                ApiService::formatResponse($items, 'Expiring inventory items retrieved successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_fetch_error',
                'Failed to fetch expiring inventory items: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get inventory summary statistics
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_inventory_summary($request)
    {
        try {
            $summary = Inventory::getSummary();

            return new WP_REST_Response(
                ApiService::formatResponse($summary, 'Inventory summary retrieved successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_fetch_error',
                'Failed to fetch inventory summary: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get category summary
     *
     * @param \WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_category_summary($request)
    {
        try {
            $categories = Inventory::getCategorySummary();

            return new WP_REST_Response(
                ApiService::formatResponse($categories, 'Category summary retrieved successfully'),
                200
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_fetch_error',
                'Failed to fetch category summary: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
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
                        $results[$id] = Inventory::updateStatus($id);
                        break;
                    case 'delete':
                        $results[$id] = Inventory::deleteItem($id);
                        break;
                    case 'update_location':
                        if (isset($item_data['location'])) {
                            $results[$id] = Inventory::updateItem($id, ['location' => $item_data['location']]);
                        }
                        break;
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

    // ==============================================
    // TRANSACTION MANAGEMENT METHODS
    // ==============================================

    /**
     * Get inventory transactions with filtering
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_transactions($request)
    {
        try {
            $filters = [
                'item_id' => $request->get_param('item_id'),
                'type' => $request->get_param('type'),
                'date_from' => $request->get_param('date_from'),
                'date_to' => $request->get_param('date_to'),
                'limit' => $request->get_param('limit'),
                'offset' => $request->get_param('offset')
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $transactions = InventoryTransaction::getFiltered($filters);

            return new WP_REST_Response(
                ApiService::formatResponse($transactions, 'Transactions retrieved successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'transactions_fetch_error',
                'Failed to fetch transactions: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Create a new inventory transaction
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_transaction($request)
    {
        try {
            $data = [
                'item_id' => $request->get_param('item_id'),
                'type' => $request->get_param('type'),
                'quantity' => $request->get_param('quantity'),
                'reference' => $request->get_param('reference'),
                'notes' => $request->get_param('notes'),
                'user_id' => get_current_user_id()
            ];

            $transaction = InventoryService::recordTransaction($data);

            return new WP_REST_Response(
                ApiService::formatResponse($transaction, 'Transaction created successfully'),
                201
            );

        } catch (Exception $e) {
            return new WP_Error(
                'transaction_create_error',
                'Failed to create transaction: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // ==============================================
    // ALERT MANAGEMENT METHODS
    // ==============================================

    /**
     * Get inventory alerts with filtering
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_alerts($request)
    {
        try {
            $filters = [
                'type' => $request->get_param('type'),
                'severity' => $request->get_param('severity'),
                'status' => $request->get_param('status'),
                'limit' => $request->get_param('limit')
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $alerts = InventoryAlert::getFiltered($filters);

            return new WP_REST_Response(
                ApiService::formatResponse($alerts, 'Alerts retrieved successfully'),
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
        try {
            $alert_id = $request->get_param('ID');
            $result = InventoryService::acknowledgeAlert($alert_id, get_current_user_id());

            return new WP_REST_Response(
                ApiService::formatResponse($result, 'Alert acknowledged successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'alert_acknowledge_error',
                'Failed to acknowledge alert: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Resolve an alert
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function resolve_alert($request)
    {
        try {
            $alert_id = $request->get_param('ID');
            $result = InventoryService::resolveAlert($alert_id, get_current_user_id());

            return new WP_REST_Response(
                ApiService::formatResponse($result, 'Alert resolved successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'alert_resolve_error',
                'Failed to resolve alert: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
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

    // ==============================================
    // SUPPLIER MANAGEMENT METHODS
    // ==============================================

    /**
     * Get all suppliers
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_suppliers($request)
    {
        try {
            $suppliers = InventorySupplier::getAll();

            return new WP_REST_Response(
                ApiService::formatResponse($suppliers, 'Suppliers retrieved successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'suppliers_fetch_error',
                'Failed to fetch suppliers: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get a single supplier
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_supplier($request)
    {
        try {
            $supplier_id = $request->get_param('ID');
            $supplier = InventorySupplier::findById($supplier_id);

            if (!$supplier) {
                return new WP_Error(
                    'supplier_not_found',
                    'Supplier not found',
                    ['status' => 404]
                );
            }

            return new WP_REST_Response(
                ApiService::formatResponse($supplier, 'Supplier retrieved successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'supplier_fetch_error',
                'Failed to fetch supplier: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Create a new supplier
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_supplier($request)
    {
        try {
            $data = [
                'name' => $request->get_param('name'),
                'contact_person' => $request->get_param('contact_person'),
                'email' => $request->get_param('email'),
                'phone' => $request->get_param('phone'),
                'address' => $request->get_param('address'),
                'payment_terms' => $request->get_param('payment_terms')
            ];

            $supplier = InventorySupplier::create($data);

            return new WP_REST_Response(
                ApiService::formatResponse($supplier, 'Supplier created successfully'),
                201
            );

        } catch (Exception $e) {
            return new WP_Error(
                'supplier_create_error',
                'Failed to create supplier: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update a supplier
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_supplier($request)
    {
        try {
            $supplier_id = $request->get_param('ID');
            $data = [];

            // Get all parameters that were sent in the request
            $params = $request->get_params();
            $allowed_fields = ['name', 'contact_person', 'email', 'phone', 'address', 'payment_terms'];

            foreach ($allowed_fields as $field) {
                if (isset($params[$field])) {
                    $data[$field] = $params[$field];
                }
            }

            $supplier = InventorySupplier::updateById($supplier_id, $data);

            return new WP_REST_Response(
                ApiService::formatResponse($supplier, 'Supplier updated successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'supplier_update_error',
                'Failed to update supplier: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a supplier
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_supplier($request)
    {
        try {
            $supplier_id = $request->get_param('ID');
            $result = InventorySupplier::deleteById($supplier_id);

            return new WP_REST_Response(
                ApiService::formatResponse($result, 'Supplier deleted successfully'),
                200
            );

        } catch (Exception $e) {
            return new WP_Error(
                'supplier_delete_error',
                'Failed to delete supplier: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // ==============================================
    // REORDER MANAGEMENT METHODS
    // ==============================================

    /**
     * Get reorders with filtering
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_reorders($request)
    {
        try {
            $filters = [
                'status' => $request->get_param('status'),
                'priority' => $request->get_param('priority')
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $reorders = InventoryReorder::getFiltered($filters);

            return new WP_REST_Response(
                ApiService::formatResponse($reorders, 'Reorders retrieved successfully'),
                200
            );

        } catch (Exception $e) {
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
            $suggestions = InventoryService::generateReorderSuggestions();

            return new WP_REST_Response([
                'success' => true,
                'data' => $suggestions,
                'message' => count($suggestions) . ' reorder suggestions generated'
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'reorder_suggestions_error',
                'Failed to generate reorder suggestions: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // ==============================================
    // DASHBOARD AND REPORTING METHODS
    // ==============================================

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
