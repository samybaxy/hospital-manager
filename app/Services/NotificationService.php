<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Notification;

class NotificationService
{
    /**
     * Create a new notification
     *
     * @param int    $userId      User ID to notify
     * @param string $type        Type of notification (e.g., 'lab_results', 'appointment')
     * @param string $title       Notification title
     * @param string $message     Notification message
     * @param array  $data        Additional data for the notification
     * @return Notification|false Created notification or false on failure
     */
    public static function create($userId, $type, $title, $message, $data = [])
    {
        $notification = new Notification([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => maybe_serialize($data),
            'read' => false,
            'created_at' => current_time('mysql')
        ]);
        
        if ($notification->save()) {
            return $notification;
        }
        
        return false;
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
        $offset = ($page - 1) * $limit;
        
        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hm_notifications 
                WHERE user_id = %d AND read = 0 
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
                WHERE user_id = %d AND read = 0",
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
}
