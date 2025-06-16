<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use HospitalManager\Services\ApiService;

abstract class InventoryBaseController extends BaseController
{
    /**
     * Handle standard pagination parameters from request
     * 
     * @param WP_REST_Request $request
     * @param int $default_per_page
     * @return array Pagination parameters
     */
    protected function handlePagination($request, $default_per_page = 20)
    {
        // Handle both limit/offset and page/per_page formats
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');

        // Convert page/per_page to limit/offset if needed
        if ($page && $per_page) {
            $limit = intval($per_page);
            $offset = (intval($page) - 1) * $limit;
        } else {
            $limit = $limit ? intval($limit) : $default_per_page;
            $offset = $offset ? intval($offset) : 0;
            $page = $offset > 0 ? floor($offset / $limit) + 1 : 1;
            $per_page = $limit;
        }

        return [
            'page' => $page,
            'per_page' => $per_page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Create standardized API response with error handling
     *
     * @param callable $callback Function that returns response data
     * @param string $success_message Message for successful operation
     * @param string $error_code Error code for failed operation
     * @param string $error_message_prefix Prefix for error message
     * @return WP_REST_Response|WP_Error
     */
    protected function createResponse($callback, $success_message, $error_code, $error_message_prefix)
    {
        try {
            $data = call_user_func($callback);
            return new WP_REST_Response(
                ApiService::formatResponse($data, $success_message),
                200
            );
        } catch (\Exception $e) {
            return new WP_Error(
                $error_code,
                $error_message_prefix . ': ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
