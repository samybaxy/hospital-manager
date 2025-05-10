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
            // Server-Sent Events endpoint
            register_rest_route('hospital-manager/v1', '/ws/events', [
                'methods' => 'GET',
                'callback' => [self::class, 'handleSSEConnection'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]);
            
            // Polling fallback endpoint for browsers that don't support SSE
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
     * Handle Server-Sent Events connection
     */
    public static function handleSSEConnection()
    {
        $user_id = get_current_user_id();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // Send initial connection message
        echo "event: connection\n";
        echo "data: " . json_encode(['status' => 'connected']) . "\n\n";
        flush();

        $last_check = time();
        $check_interval = 2; // Check every 2 seconds

        while (true) {
            if ((time() - $last_check) >= $check_interval) {
                $messages = self::getMessages($user_id);
                
                foreach ($messages as $message) {
                    echo "event: message\n";
                    echo "data: " . json_encode($message) . "\n\n";
                    flush();
                    
                    // Delete processed message
                    self::deleteMessage($user_id, $message['id']);
                }
                
                $last_check = time();
            }

            // Check if client is still connected
            if (connection_aborted()) {
                break;
            }

            // Sleep to prevent excessive CPU usage
            usleep(500000); // 0.5 seconds
        }
    }

    /**
     * Send a message to a specific user
     */
    public static function sendMessage($channel, $data, $user_id)
    {
        $message = [
            'id' => uniqid(),
            'channel' => $channel,
            'data' => $data,
            'timestamp' => time()
        ];

        $messages = get_transient(self::$transient_prefix . $user_id) ?: [];
        $messages[] = $message;
        
        set_transient(self::$transient_prefix . $user_id, $messages, self::$message_ttl);

        // Also create a notification for certain message types
        if (in_array($channel, ['chat', 'appointment', 'lab_results'])) {
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
            return $message['id'] !== $message_id;
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
            self::deleteMessage($user_id, $message['id']);
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
        // First check standard WordPress authentication
        if (is_user_logged_in()) {
            return true;
        }
        
        // Check for nonce in header
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (isset($headers['X-WP-Nonce']) && wp_verify_nonce($headers['X-WP-Nonce'], 'wp_rest')) {
            return true;
        }
        
        // Check for authentication via cookies for AJAX requests
        if (isset($_COOKIE[LOGGED_IN_COOKIE])) {
            return true;
        }
        
        // Check for custom authentication marker set in login endpoint
        if (isset($_COOKIE['hospital_manager_auth']) && $_COOKIE['hospital_manager_auth'] === 'authenticated') {
            return true;
        }
        
        // In development environment, be more permissive
        if (defined('WP_ENVIRONMENT_TYPE') && (WP_ENVIRONMENT_TYPE === 'development' || WP_ENVIRONMENT_TYPE === 'local')) {
            // Check if the request comes from the same origin
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            
            if (strpos($referer, site_url()) === 0 || strpos($origin, site_url()) === 0) {
                return true;
            }
            
            // For local development, be extra permissive
            if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                return true;
            }
        }
        
        return false;
    }
}
