<?php

namespace HospitalManager\Services;

class WebSocketService
{
    private static $redis;
    private static $transient_prefix = 'hm_ws_';
    private static $message_ttl = 300; // 5 minutes

    /**
     * Initialize the WebSocket service
     */
    public static function init()
    {
        add_action('rest_api_init', function () {
            // (A Server-Sent Events endpoint, /ws/events, was removed: no client used
            // it, it held a PHP worker ~5 s per connection and echoed any Origin back
            // with credentials. Real-time messages are delivered by polling below,
            // and every chat/lab/appointment message is also stored as a notification.)

            // Pending real-time messages for the signed-in user (cleared once read)
            register_rest_route('hospital-manager/v1', '/ws/poll', [
                'methods' => 'GET',
                'callback' => [self::class, 'handlePollingRequest'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]);
        });
    }

    /**
     * Send a message to a specific user
     *
     * @param bool $notify Also store a notification for chat/appointment/lab_results
     *                     channels. Pass false when the caller already created a
     *                     (more specific) notification, to avoid duplicates.
     */
    public static function sendMessage($channel, $data, $user_id, $notify = true)
    {
        $message = [
            'ID' => uniqid(),
            'channel' => $channel,
            'data' => $data,
            'timestamp' => time()
        ];

        $messages = get_transient(self::$transient_prefix . $user_id) ?: [];
        $messages[] = $message;
        
        set_transient(self::$transient_prefix . $user_id, $messages, self::$message_ttl);

        // Also create a notification for certain message types
        if ($notify && in_array($channel, ['chat', 'appointment', 'lab_results'])) {
            NotificationService::create(
                $user_id,
                $channel . '_notification',
                self::getNotificationTitle($channel, $data),
                self::getNotificationMessage($channel, $data),
                $data
            );
        }

        return true;
    }

    /**
     * Get pending messages for a user
     */
    private static function getMessages($user_id)
    {
        $messages = get_transient(self::$transient_prefix . $user_id) ?: [];
        
        // Filter out expired messages
        $messages = array_filter($messages, function($message) {
            return (time() - $message['timestamp']) < self::$message_ttl;
        });

        return $messages;
    }

    /**
     * Delete a processed message
     */
    private static function deleteMessage($user_id, $message_id)
    {
        $messages = get_transient(self::$transient_prefix . $user_id) ?: [];
        
        $messages = array_filter($messages, function($message) use ($message_id) {
            return $message['ID'] !== $message_id;
        });

        set_transient(self::$transient_prefix . $user_id, $messages, self::$message_ttl);
    }

    /**
     * Get notification title based on channel and data
     */
    private static function getNotificationTitle($channel, $data)
    {
        switch ($channel) {
            case 'chat':
                return 'New Message';
            case 'appointment':
                return 'Appointment Update';
            case 'lab_results':
                return 'Lab Results Available';
            default:
                return 'New Notification';
        }
    }

    /**
     * Get notification message based on channel and data
     */
    private static function getNotificationMessage($channel, $data)
    {
        switch ($channel) {
            case 'chat':
                $sender = get_userdata($data['message']['sender_id'])->display_name;
                return "New message from {$sender}";
            case 'appointment':
                return "Your appointment status has been updated to: {$data['status']}";
            case 'lab_results':
                return "New lab results are available for review";
            default:
                return "You have a new notification";
        }
    }
    
    /**
     * Handle polling requests for browsers that don't support SSE
     * 
     * @return \WP_REST_Response
     */
    public static function handlePollingRequest() 
    {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new \WP_REST_Response([
                'error' => 'User not authenticated',
                'code' => 'not_authenticated'
            ], 401);
        }
        
        $messages = self::getMessages($user_id);
        $response = [
            'status' => 'connected_polling',
            'messages' => $messages
        ];
        
        // Delete processed messages
        foreach ($messages as $message) {
            self::deleteMessage($user_id, $message['ID']);
        }
        
        return new \WP_REST_Response($response);
    }
    
    /**
     * Check if user is authenticated
     * More permissive authentication check
     * 
     * @return bool
     */
    public static function checkAuthentication() 
    {
        // Signed-in users only. This used to accept a bare nonce, any login cookie,
        // a cookie with the literal value "authenticated", and on development/local
        // sites any request with a site Referer or a localhost Host header.
        return is_user_logged_in();
    }
}
