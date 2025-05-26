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
     * Modified to use a shorter timeout to avoid browser hanging
     */
    public static function handleSSEConnection()
    {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new \WP_Error(
                'not_logged_in',
                'User must be logged in',
                ['status' => 401]
            );
        }

        // Set time limit to avoid PHP timeouts
        set_time_limit(30);
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
        
        // Add CORS headers if needed
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
        }

        // Send initial connection message
        echo "event: connection\n";
        echo "data: " . json_encode(['status' => 'connected', 'timestamp' => time()]) . "\n\n";
        flush();

        // Initialize variables for connection management
        $last_check = time();
        $check_interval = 1; // Check every 1 second
        $max_execution_time = 5; // Limit execution to just 5 seconds to prevent browser hanging
        $start_time = time();

        // Set up error handling
        set_error_handler(function($errno, $errstr) {
            echo "event: error\n";
            echo "data: " . json_encode(['error' => 'Server error']) . "\n\n";
            flush();
            return true;
        });

        try {
            // Keep connection open for limited time
            while ((time() - $start_time) < $max_execution_time) {
                if ((time() - $last_check) >= $check_interval) {
                    $messages = self::getMessages($user_id);
                    
                    if (!empty($messages)) {
                        foreach ($messages as $message) {
                            echo "event: message\n";
                            echo "data: " . json_encode($message) . "\n\n";
                            flush();
                            
                            // Delete processed message
                            self::deleteMessage($user_id, $message['ID']);
                        }
                    } else {
                        // Send a ping to keep connection alive
                        echo "event: ping\n";
                        echo "data: " . json_encode(['time' => time()]) . "\n\n";
                        flush();
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
            
            // Send end connection message when time limit reached
            echo "event: end\n";
            echo "data: " . json_encode(['reason' => 'time_limit']) . "\n\n";
            flush();
            
        } catch (\Exception $e) {
            // Handle any exceptions
            echo "event: error\n";
            echo "data: " . json_encode(['error' => 'Connection error']) . "\n\n";
            flush();
        }
        
        // Restore error handler
        restore_error_handler();
        
        exit(0); // Ensure clean exit
    }

    /**
     * Send a message to a specific user
     */
    public static function sendMessage($channel, $data, $user_id)
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
