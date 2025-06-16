<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use Exception;
use HospitalManager\Models\InventorySupplier;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\RoleService;

class InventorySupplierController extends InventoryBaseController
{
    public function __construct()
    {
        $this->namespace = 'hospital-manager/v1';
    }

    /**
     * Register supplier routes
     */
    public function register_routes()
    {
        // Routes for suppliers
        register_rest_route($this->namespace, '/inventory/suppliers', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_suppliers'],
                'permission_callback' => function($request) {
                    return RoleService::canViewInventory();
                },
                'args' => [
                    'search' => [
                        'description' => 'Search suppliers by name or contact person',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'status' => [
                        'description' => 'Filter by supplier status',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'page' => [
                        'description' => 'Page number for pagination',
                        'type' => 'integer',
                        'default' => 1
                    ],
                    'per_page' => [
                        'description' => 'Number of items per page',
                        'type' => 'integer',
                        'default' => 10
                    ]
                ]
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
    }

    /**
     * Get all suppliers
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_suppliers($request)
    {
        try {
            // Use centralized pagination handling
            $pagination = $this->handlePagination($request, 10);

            $filters = [
                'search' => $request->get_param('search'),
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

            $suppliers = InventorySupplier::getFiltered($filters);
            $total_count = InventorySupplier::getFilteredCount($filters);
            
            // Calculate pagination metadata
            $total_pages = $pagination['per_page'] > 0 ? ceil($total_count / $pagination['per_page']) : 1;

            $response_data = [
                'data' => $suppliers,
                'pagination' => [
                    'current_page' => $pagination['page'],
                    'per_page' => $pagination['per_page'],
                    'total' => $total_count,
                    'pages' => $total_pages
                ]
            ];

            return new WP_REST_Response(
                ApiService::formatResponse($response_data, 'Suppliers retrieved successfully'),
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
}
