<?php

namespace HospitalManager\Tests\Mocks\Services;

use HospitalManager\Tests\Mocks\Services\MockNotificationService;

/**
 * Mock WebSocketService for testing
 */
class MockWebSocketService
{
    /**
     * @var array Array of messages sent via WebSocket (for testing assertions)
     */
    public static $messages = [];
    
    public static $transient_storage = [];
    public static $message_ttl = 300;
    public static $transient_prefix = 'hm_ws_';
    
    /**
     * Initialize the service
     */
    public static function init()
    {
        // In a real environment, this would register REST API routes
        // For testing, we just return true
        return true;
    }
    
    /**
     * Reset state for testing
     */
    public static function reset()
    {
        self::$messages = [];
        self::$transient_storage = [];
    }
    
    /**
     * Mock implementation of set_transient
     */
    public static function set_transient($key, $value, $ttl)
    {
        self::$transient_storage[$key] = [
            'value' => $value,
            'expiry' => time() + $ttl
        ];
        
        return true;
    }
    
    /**
     * Mock implementation of get_transient
     */
    public static function get_transient($key)
    {
        if (!isset(self::$transient_storage[$key])) {
            return false;
        }
        
        $data = self::$transient_storage[$key];
        
        // Check if expired
        if (time() > $data['expiry']) {
            unset(self::$transient_storage[$key]);
            return false;
        }
        
        return $data['value'];
    }
    
    /**
     * Send a message to a user
     */
    public static function sendMessage($channel, $data, $user_id)
    {
        $message = [
            'ID' => uniqid(),
            'channel' => $channel,
            'data' => $data,
            'timestamp' => time(),
            'user_id' => $user_id
        ];
        
        // Store in static messages array for test assertions
        self::$messages[] = $message;
        
        // Also store in transient storage for persistence
        $key = self::$transient_prefix . $user_id;
        $messages = self::get_transient($key) ?: [];
        $messages[] = $message;
        
        self::set_transient($key, $messages, self::$message_ttl);
        
        // We'll create notifications only for specific channels that require them
        // In this case, only the 'chat' channel needs notifications for the WebSocketServiceTest
        if ($channel === 'chat') {
            MockNotificationService::create(
                $user_id,
                $channel . '_notification',
                self::getNotificationTitle($channel, $data),
                self::getNotificationMessage($channel, $data),
                $data
            );
        }
        // Other channels like 'lab_results' are handled by their own services
        
        return true;
    }
    
    /**
     * Get messages for a user
     */
    public static function getMessages($user_id)
    {
        $messages = self::get_transient(self::$transient_prefix . $user_id) ?: [];
        
        // Filter out expired messages
        $messages = array_filter($messages, function($message) {
            return (time() - $message['timestamp']) < self::$message_ttl;
        });
        
        return $messages;
    }
    
    /**
     * Delete a message
     */
    public static function deleteMessage($user_id, $message_id)
    {
        $key = self::$transient_prefix . $user_id;
        $messages = self::get_transient($key) ?: [];
        
        $messages = array_filter($messages, function($message) use ($message_id) {
            return $message['ID'] !== $message_id;
        });
        
        self::set_transient($key, $messages, self::$message_ttl);
    }
    
    /**
     * Get notification title
     */
    public static function getNotificationTitle($channel, $data)
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
     * Get notification message
     */
    public static function getNotificationMessage($channel, $data)
    {
        switch ($channel) {
            case 'chat':
                // In our mock, we'll simplify this
                return "New message from User";
            case 'appointment':
                return "Your appointment status has been updated to: {$data['status']}";
            case 'lab_results':
                return "New lab results are available for review";
            default:
                return "You have a new notification";
        }
    }
}