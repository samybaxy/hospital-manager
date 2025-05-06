<?php

namespace HospitalManager\Tests\Mocks\Services;

use HospitalManager\Tests\Mocks\Services\MockNotification;
use HospitalManager\Tests\Mocks\Services\MockUser;
use HospitalManager\Tests\Mocks\Services\MockWebSocketService;

/**
 * Mock Notification Service
 */
class MockNotificationService 
{
    /**
     * @var array Array of notifications (for testing assertions)
     */
    public static $notifications = [];
    /**
     * Create a new notification
     */
    public static function create($userId, $type, $title, $message, $data = null) 
    {
        // Check if user exists
        $user = MockUser::getUser('ID', $userId);
        if (!$user) {
            return false;
        }
        
        // Create notification
        $notification = new MockNotification([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Save the notification
        $notification->save();
        
        // Store in static array for test assertions
        self::$notifications[] = [
            'id' => $notification->id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ];
        
        // Send real-time notification via WebSocket
        MockWebSocketService::sendMessage(
            'notification',
            [
                'id' => $notification->id,
                'type' => $type,
                'title' => $title,
                'message' => $message
            ],
            $userId
        );
        
        return $notification;
    }
    
    /**
     * Mark a notification as read
     */
    public static function markAsRead($notificationId) 
    {
        $notification = MockNotification::find($notificationId);
        if (!$notification) {
            return false;
        }
        
        $notification->read = true;
        return $notification->save();
    }
    
    /**
     * Get user notifications
     */
    public static function getUserNotifications($userId, $page = 1, $limit = 20) 
    {
        return MockNotification::where('user_id', $userId);
    }
    
    /**
     * Get unread count
     */
    public static function getUnreadCount($userId) 
    {
        return MockNotification::count(['user_id' => $userId, 'read' => false]);
    }

    /**
     * Reset for testing
     */
    public static function reset()
    {
        self::$notifications = [];
        MockNotification::reset();
    }
}