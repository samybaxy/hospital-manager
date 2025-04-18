<?php

namespace HospitalManager\Services;

class MessageQueueService
{
    private static $transient_prefix = 'hm_msg_queue_';
    private static $message_ttl = 86400; // 24 hours

    /**
     * Queue a message for delivery
     */
    public static function queueMessage($userId, $message)
    {
        $queue = get_transient(self::$transient_prefix . $userId) ?: [];
        $queue[] = [
            'id' => uniqid(),
            'message' => $message,
            'timestamp' => time()
        ];
        
        // Keep only messages from last 24 hours
        $queue = array_filter($queue, function($item) {
            return (time() - $item['timestamp']) < self::$message_ttl;
        });

        set_transient(self::$transient_prefix . $userId, $queue, self::$message_ttl);
    }

    /**
     * Get pending messages for a user
     */
    public static function getPendingMessages($userId)
    {
        $queue = get_transient(self::$transient_prefix . $userId) ?: [];
        
        // Clear the queue after retrieving
        delete_transient(self::$transient_prefix . $userId);
        
        return $queue;
    }

    /**
     * Handle message delivery status
     */
    public static function handleDeliveryStatus($userId, $messageId, $status)
    {
        $queue = get_transient(self::$transient_prefix . $userId) ?: [];
        
        // Remove delivered message
        $queue = array_filter($queue, function($item) use ($messageId) {
            return $item['id'] !== $messageId;
        });

        if (!empty($queue)) {
            set_transient(self::$transient_prefix . $userId, $queue, self::$message_ttl);
        }

        // Log delivery status if needed
        if ($status === 'failed') {
            error_log("Message delivery failed for user $userId: $messageId");
        }
    }

    /**
     * Clean up expired messages
     */
    public static function cleanupExpiredMessages()
    {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE %s
            AND option_value REGEXP %s
        ", 
            '_transient_' . self::$transient_prefix . '%',
            '"timestamp":[0-9]+' // Match timestamp values
        ));
    }
}
