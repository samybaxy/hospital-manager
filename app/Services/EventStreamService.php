<?php

namespace HospitalManager\Services;

use HospitalManager\Config;

class EventStreamService {
    private static $connections = [];
    
    public static function initEndpoints() {
        add_action('rest_api_init', function () {
            register_rest_route('hospital-manager/v1', '/events', [
                'methods' => 'GET',
                'callback' => [self::class, 'handleSSEConnection'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                }
            ]);
        });
    }

    public static function handleSSEConnection() {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        $user_id = get_current_user_id();
        self::$connections[$user_id] = true;

        // Send initial retry interval
        echo "retry: " . Config::SSE_RETRY_INTERVAL . "\n\n";
        flush();

        // Keep connection alive and check for new messages
        while (true) {
            if (connection_aborted()) {
                unset(self::$connections[$user_id]);
                exit();
            }

            // Check message queue for new messages
            $messages = MessageQueueService::getPendingMessages($user_id);
            
            foreach ($messages as $message) {
                echo "event: message\n";
                echo "data: " . json_encode($message) . "\n\n";
                flush();
            }

            // Sleep briefly to prevent CPU overuse
            sleep(1);
        }
    }

    public static function broadcastEvent($event, $data, $user_ids = null) {
        if ($user_ids === null) {
            // Broadcast to all connected users
            foreach (array_keys(self::$connections) as $user_id) {
                MessageQueueService::queueMessage($user_id, [
                    'event' => $event,
                    'data' => $data
                ]);
            }
        } else {
            // Broadcast to specific users
            foreach ((array)$user_ids as $user_id) {
                MessageQueueService::queueMessage($user_id, [
                    'event' => $event,
                    'data' => $data
                ]);
            }
        }
    }
}
