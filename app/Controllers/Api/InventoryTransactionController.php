<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use Exception;
use HospitalManager\Models\InventoryTransaction;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\RoleService;
use HospitalManager\Services\InventoryService;

class InventoryTransactionController extends InventoryBaseController
{
    public function __construct()
    {
        $this->namespace = 'hospital-manager/v1';
    }

    /**
     * Register transaction routes
     */
    public function register_routes()
    {
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
    }

    /**
     * Get inventory transactions with filtering
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_transactions($request)
    {
        try {
            // Use centralized pagination handling
            $pagination = $this->handlePagination($request, 50);

            $filters = [
                'item_id' => $request->get_param('item_id'),
                'type' => $request->get_param('type'),
                'date_from' => $request->get_param('date_from'),
                'date_to' => $request->get_param('date_to'),
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

            $transactions = InventoryTransaction::getFiltered($filters);
            $total_count = InventoryTransaction::getFilteredCount($filters);
            
            // Calculate pagination metadata
            $total_pages = $pagination['per_page'] > 0 ? ceil($total_count / $pagination['per_page']) : 1;

            $response_data = [
                'data' => $transactions,
                'pagination' => [
                    'current_page' => $pagination['page'],
                    'per_page' => $pagination['per_page'],
                    'total' => $total_count,
                    'pages' => $total_pages
                ]
            ];

            return new WP_REST_Response(
                ApiService::formatResponse($response_data, 'Transactions retrieved successfully'),
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
}
