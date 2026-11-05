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

        // Mark every notification of the signed-in user as read
        register_rest_route($this->namespace, '/notifications/mark-all-read', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'mark_all_as_read'],
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
            // Notification models serialise to "{}" unless converted
            'data' => \HospitalManager\Services\ApiService::serializeModels($result['items']),
            'meta' => [
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total']
            ]
        ]);
    }

    public function mark_all_as_read($request)
    {
        $updated = NotificationService::markAllAsRead(get_current_user_id());
        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to mark notifications as read', ['status' => 500]);
        }
        return new WP_REST_Response(['success' => true, 'updated' => (int) $updated]);
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
     * Notifications are only for a signed-in user (WordPress cookie + nonce, or
     * the app's own cookie, both resolved before this runs).
     *
     * This used to also accept any request carrying "Authorization: Bearer <anything>"
     * (an unimplemented JWT placeholder) and any AJAX-looking request that merely had
     * a login cookie present, without validating either. Both let anonymous callers
     * through, so they were removed.
     *
     * @return bool|\WP_Error
     */
    public function check_auth()
    {
        if (is_user_logged_in()) {
            return true;
        }

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
