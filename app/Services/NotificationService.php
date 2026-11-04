<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Notification;
use WP_Error;

class NotificationService extends BaseService
{
    /**
     * Create a new notification
     *
     * @param int $user_id User ID to notify
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $meta Additional metadata
     * 
     * @return Notification|WP_Error
     */
    public static function create($user_id, $type, $title, $message, $meta = [])
    {
        if (empty($user_id) || !is_numeric($user_id)) {
            self::logError('NotificationService', 'create', 'Invalid user ID', $user_id);
            return new WP_Error('invalid_user_id', 'Invalid user ID');
        }

        try {
            // Verify user exists
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                self::logError('NotificationService', 'create', "User not found: $user_id");
                return new WP_Error('user_not_found', 'User not found');
            }

            // Store in hm_notifications — the table every reader uses (list, unread
            // count, mark-as-read, delete). This used to wp_insert_post() an
            // 'hm_notification' post instead, so app-generated notifications (lab
            // results, lab assignments, ...) never appeared for anyone.
            global $wpdb;
            $reference_id = null;
            foreach (['reference_id', 'lab_result_id', 'lab_id', 'appointment_id', 'visitation_id', 'report_id', 'chat_id'] as $key) {
                if (!empty($meta[$key]) && is_numeric($meta[$key])) {
                    $reference_id = (int) $meta[$key];
                    break;
                }
            }

            $row = [
                'user_id' => (int) $user_id,
                'type' => sanitize_key($type),
                'title' => $title,
                'message' => $message,
                'reference_id' => $reference_id,
                'read' => 0,
                'created_at' => current_time('mysql'),
            ];

            if ($wpdb->insert($wpdb->prefix . 'hm_notifications', $row) === false) {
                self::logError('NotificationService', 'create', 'Error creating notification: ' . $wpdb->last_error);
                return new WP_Error('notification_error', 'Failed to create notification');
            }

            $row['ID'] = $wpdb->insert_id;
            return new Notification($row);
            
        } catch (\Exception $e) {
            self::logError('NotificationService', 'create', 'Exception: ' . $e->getMessage(), $e->getTraceAsString());
            return new WP_Error('notification_error', 'Failed to create notification: ' . $e->getMessage());
        }
    }

    /**
     * Mark a notification as read
     *
     * @param int $notificationId ID of the notification
     * @return bool Whether the operation was successful
     */
    public static function markAsRead($notificationId)
    {
        $notification = Notification::find($notificationId);
        if (!$notification) {
            return false;
        }

        $notification->read = true;
        return $notification->save();
    }

    /**
     * Mark all of a user's notifications as read.
     *
     * @return int|false Number of notifications updated
     */
    public static function markAllAsRead($userId)
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}hm_notifications SET `read` = 1 WHERE user_id = %d AND `read` = 0",
            $userId
        ));
    }

    /**
     * Get unread notifications for a user
     *
     * @param int $userId User ID
     * @param int $page   Page number
     * @param int $limit  Number of notifications per page
     * @return array Paginated notifications
     */
    public static function getUnreadNotifications($userId, $page = 1, $limit = 20)
    {
        global $wpdb;
        $page = max(1, (int) $page);
        $limit = min(100, max(1, (int) $limit)); // per_page=0 used to divide by zero (500)
        $offset = ($page - 1) * $limit;
        
        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hm_notifications 
                WHERE user_id = %d AND `read` = 0 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d",
                $userId,
                $limit,
                $offset
            )
        );

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_notifications 
                WHERE user_id = %d AND `read` = 0",
                $userId
            )
        );

        return [
            'items' => array_map(function($data) {
                return new Notification((array)$data);
            }, $notifications),
            'total' => (int)$total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => ceil($total / $limit)
        ];
    }

    /**
     * Get all notifications for a user
     *
     * @param int $userId User ID
     * @param int $page   Page number
     * @param int $limit  Number of notifications per page
     * @return array Paginated notifications
     */
    public static function getUserNotifications($userId, $page = 1, $limit = 20)
    {
        global $wpdb;
        $page = max(1, (int) $page);
        $limit = min(100, max(1, (int) $limit)); // per_page=0 used to divide by zero (500)
        $offset = ($page - 1) * $limit;
        
        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hm_notifications 
                WHERE user_id = %d 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d",
                $userId,
                $limit,
                $offset
            )
        );

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_notifications 
                WHERE user_id = %d",
                $userId
            )
        );

        return [
            'items' => array_map(function($data) {
                return new Notification((array)$data);
            }, $notifications),
            'total' => (int)$total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => ceil($total / $limit)
        ];
    }

    /**
     * Delete notifications for a user
     *
     * @param int   $userId User ID
     * @param array $types  Optional array of notification types to delete
     * @return bool Whether the operation was successful
     */
    public static function deleteUserNotifications($userId, $types = [])
    {
        global $wpdb;
        
        $query = "DELETE FROM {$wpdb->prefix}hm_notifications WHERE user_id = %d";
        $params = [$userId];
        
        if (!empty($types)) {
            $placeholders = array_fill(0, count($types), '%s');
            $query .= " AND type IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $types);
        }
        
        return $wpdb->query($wpdb->prepare($query, $params));
    }
    
    /**
     * Get count of unread notifications for a user
     *
     * @param int $userId User ID
     * @return int Number of unread notifications
     */
    public static function getUnreadCount($userId)
    {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_notifications 
                WHERE user_id = %d AND `read` = 0",
                $userId
            )
        );
        
        return (int)$count;
    }
}
