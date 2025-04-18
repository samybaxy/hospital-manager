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
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/notifications/(?P<id>\d+)/read', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'mark_as_read'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
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
        $notification_id = $request->get_param('id');
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
}
