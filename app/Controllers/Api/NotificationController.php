<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Notification;
use HospitalManager\Services\NotificationService;

class NotificationController extends BaseController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/notifications', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_notifications'],
                'permission_callback' => [$this, 'check_auth']
            ]
        ]);

        register_rest_route($this->namespace, '/notifications/unread', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_unread_count'],
                'permission_callback' => [$this, 'check_auth']
            ]
        ]);

        register_rest_route($this->namespace, '/notifications/(?P<ID>\d+)/read', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'mark_as_read'],
                'permission_callback' => [$this, 'check_auth']
            ]
        ]);
    }

    public function get_notifications($request)
    {
        $user_id = get_current_user_id();
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;

        $result = NotificationService::getUserNotifications($user_id, $page, $per_page);

        return new WP_REST_Response([
            'data' => $result['items'],
            'meta' => [
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total']
            ]
        ]);
    }

    public function mark_as_read($request)
    {
        $notification_id = $request->get_param('ID');
        $user_id = get_current_user_id();

        // Verify notification belongs to user
        $notification = Notification::find($notification_id);
        if (!$notification || $notification->user_id != $user_id) {
            return new WP_Error(
                'invalid_notification',
                'Notification not found or does not belong to user',
                ['status' => 404]
            );
        }

        if (NotificationService::markAsRead($notification_id)) {
            return new WP_REST_Response(['success' => true]);
        }

        return new WP_Error(
            'update_failed',
            'Failed to mark notification as read',
            ['status' => 500]
        );
    }
    
    /**
     * Check if user is authenticated properly for notifications
     * 
     * @return bool|\WP_Error
     */
    public function check_auth()
    {
        // Check if user is logged in through standard WordPress authentication
        if (is_user_logged_in()) {
            return true;
        }
        
        // Check if user is authenticated through JWT or other means
        // This depends on how authentication is implemented in the frontend
        $headers = getallheaders();
        
        // If using Authorization header with Bearer token
        if (isset($headers['Authorization'])) {
            $auth_header = $headers['Authorization'];
            
            if (strpos($auth_header, 'Bearer') !== false) {
                // For JWT validation, you would validate the token here
                // This is a placeholder and would need to be implemented based on your authentication strategy
                return true;
            }
        }
        
        // Handle AJAX requests from same origin
        if (wp_doing_ajax() && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // Check if the request is coming from our own site
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
            if (strpos($referer, site_url()) === 0) {
                // Request is from our site, now check for WordPress cookie authentication
                if (isset($_COOKIE[LOGGED_IN_COOKIE])) {
                    return true;
                }
            }
        }
        
        // User is not authenticated
        return new \WP_Error(
            'rest_not_logged_in',
            __('You are not currently logged in.'),
            ['status' => 401]
        );
    }
    
    /**
     * Get count of unread notifications for the current user
     *
     * @param \WP_REST_Request $request The request object
     * @return WP_REST_Response|\WP_Error
     */
    public function get_unread_count($request)
    {
        $user_id = get_current_user_id();
        $count = NotificationService::getUnreadCount($user_id);
        
        return new WP_REST_Response([
            'count' => $count
        ]);
    }
}
