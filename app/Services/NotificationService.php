<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Notification;
use WP_Error;

class NotificationService
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
            error_log("NotificationService::create - Invalid user ID: " . print_r($user_id, true));
            return new WP_Error('invalid_user_id', 'Invalid user ID');
        }

        try {
            // Verify user exists
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                error_log("NotificationService::create - User not found: $user_id");
                return new WP_Error('user_not_found', 'User not found');
            }

            // Create notification post
            $notification_data = [
                'post_type' => 'hm_notification',
                'post_title' => $title,
                'post_content' => $message,
                'post_status' => 'publish',
                'post_author' => $user_id,
                'meta_input' => array_merge([
                    'type' => $type,
                    'is_read' => false,
                    'user_id' => $user_id,
                ], $meta)
            ];

            // Insert the post directly using WordPress function
            $post_id = wp_insert_post($notification_data, true);
            
            // Check if post was created successfully
            if (is_wp_error($post_id)) {
                error_log("NotificationService::create - Error creating notification: " . $post_id->get_error_message());
                return $post_id; // Return the WP_Error
            }

            // Return created notification using our model
            $notification = new Notification([
                'ID' => $post_id,
                'post_title' => $title,
                'post_content' => $message,
                'post_type' => 'hm_notification',
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);

            error_log("NotificationService::create - Successfully created notification ID: $post_id for user: $user_id");
            return $notification;
            
        } catch (\Exception $e) {
            error_log("NotificationService::create - Exception: " . $e->getMessage());
            error_log("NotificationService::create - Trace: " . $e->getTraceAsString());
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
                WHERE user_id = %d AND read = 0",
                $userId
            )
        );
        
        return (int)$count;
    }
}
