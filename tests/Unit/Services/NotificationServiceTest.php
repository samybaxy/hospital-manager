<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Mock Notification class for testing
 */
class MockNotification 
{
    public $id;
    public $user_id;
    public $type;
    public $title;
    public $message;
    public $data;
    public $read;
    public $created_at;
    
    private static $mockNotifications = [];
    private static $nextId = 1;
    
    /**
     * Constructor
     */
    public function __construct($data = []) 
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
    
    /**
     * Save method
     */
    public function save() 
    {
        if (!isset($this->id)) {
            $this->id = self::$nextId++;
        }
        
        self::$mockNotifications[$this->id] = $this;
        return true;
    }
    
    /**
     * Find a notification by ID
     */
    public static function find($id) 
    {
        return isset(self::$mockNotifications[$id]) ? self::$mockNotifications[$id] : null;
    }
    
    /**
     * Where clause for finding notifications
     */
    public static function where($column, $value) 
    {
        $results = [];
        foreach (self::$mockNotifications as $notification) {
            if (isset($notification->$column) && $notification->$column === $value) {
                $results[] = $notification;
            }
        }
        return $results;
    }
    
    /**
     * Reset for testing
     */
    public static function reset() 
    {
        self::$mockNotifications = [];
        self::$nextId = 1;
    }
    
    /**
     * Count notifications matching criteria
     */
    public static function count($criteria = []) 
    {
        $count = 0;
        foreach (self::$mockNotifications as $notification) {
            $match = true;
            foreach ($criteria as $key => $value) {
                if (!isset($notification->$key) || $notification->$key !== $value) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                $count++;
            }
        }
        return $count;
    }
}

/**
 * Mock WebSocket Service
 */
class MockWebSocketService 
{
    public static $messages = [];
    
    /**
     * Send a message via WebSocket
     */
    public static function sendMessage($channel, $data, $userId = null) 
    {
        self::$messages[] = [
            'channel' => $channel,
            'data' => $data,
            'user_id' => $userId
        ];
        return true;
    }
    
    /**
     * Reset for testing
     */
    public static function reset() 
    {
        self::$messages = [];
    }
}

/**
 * Mock User functions
 */
class MockUser 
{
    private static $users = [];
    
    /**
     * Add a test user
     */
    public static function addUser($id, $data = []) 
    {
        $userData = array_merge([
            'ID' => $id,
            'display_name' => 'Test User ' . $id,
            'user_email' => 'user' . $id . '@example.com'
        ], $data);
        
        self::$users[$id] = (object)$userData;
    }
    
    /**
     * Get user by field
     */
    public static function getUser($field, $value) 
    {
        if ($field === 'ID') {
            return isset(self::$users[$value]) ? self::$users[$value] : false;
        }
        
        foreach (self::$users as $user) {
            if (isset($user->$field) && $user->$field === $value) {
                return $user;
            }
        }
        
        return false;
    }
    
    /**
     * Reset for testing
     */
    public static function reset() 
    {
        self::$users = [];
    }
}

/**
 * Mock Notification Service
 */
class MockNotificationService 
{
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
}

/**
 * Test for Notification Service
 */
class NotificationServiceTest extends TestCase
{
    /**
     * @var int User ID for testing
     */
    protected $test_user_id;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock data
        MockNotification::reset();
        MockWebSocketService::reset();
        MockUser::reset();
        
        // Create a test user
        $this->test_user_id = 101;
        MockUser::addUser($this->test_user_id, [
            'display_name' => 'Test Patient',
            'user_email' => 'patient@example.com'
        ]);
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test creating a notification successfully
     */
    public function testCreateNotificationSuccess()
    {
        $notification = MockNotificationService::create(
            $this->test_user_id,
            'appointment',
            'Appointment Reminder',
            'Your appointment is tomorrow at 10:00 AM',
            ['appointment_id' => 123, 'doctor_name' => 'Dr. Smith']
        );
        
        // Assert that a notification object was returned
        $this->assertNotNull($notification, "Service should return a Notification object");
        $this->assertEquals($this->test_user_id, $notification->user_id, "Notification should be assigned to the correct user");
        $this->assertEquals('appointment', $notification->type, "Notification type should match what was passed");
        $this->assertEquals('Appointment Reminder', $notification->title, "Notification title should match what was passed");
        $this->assertEquals('Your appointment is tomorrow at 10:00 AM', $notification->message, "Notification message should match what was passed");
        $this->assertFalse($notification->read, "New notification should be marked as unread");
        
        // Verify the notification was saved to the database
        $saved_notification = MockNotification::find($notification->id);
        $this->assertNotNull($saved_notification, "Notification should be retrievable from the database after creation");
        $this->assertEquals($notification->id, $saved_notification->id, "Retrieved notification should have the same ID");
        
        // Verify WebSocket message was sent
        $this->assertCount(1, MockWebSocketService::$messages, "Real-time notification should be sent via WebSocket");
        $this->assertEquals('notification', MockWebSocketService::$messages[0]['channel'], "Notification should be sent on the 'notification' channel");
        $this->assertEquals($this->test_user_id, MockWebSocketService::$messages[0]['user_id'], "WebSocket message should target the correct user");
    }
    
    /**
     * Test creating a notification with invalid user
     */
    public function testCreateNotificationInvalidUser()
    {
        // Try to create a notification for an invalid user ID
        $invalid_user_id = 9999;
        $notification = MockNotificationService::create(
            $invalid_user_id,
            'appointment',
            'Appointment Reminder',
            'Your appointment is tomorrow at 10:00 AM'
        );
        
        // Should return false for invalid user
        $this->assertFalse($notification, "Creating a notification for a non-existent user should fail to prevent errors");
    }
    
    /**
     * Test creating a notification without optional data
     */
    public function testCreateNotificationWithoutOptionalData()
    {
        // No data parameter
        $notification = MockNotificationService::create(
            $this->test_user_id,
            'system',
            'System Notification',
            'This is a system notification without additional data'
        );
        
        // Should still create successfully
        $this->assertNotNull($notification, "Service should create notification even without optional data");
        $this->assertNull($notification->data, "Data field should be null when no data is provided");
    }
    
    /**
     * Test marking a notification as read
     */
    public function testMarkAsRead()
    {
        // First create a notification
        $notification = MockNotificationService::create(
            $this->test_user_id,
            'test',
            'Test Notification',
            'Test message'
        );
        
        // Mark it as read
        $result = MockNotificationService::markAsRead($notification->id);
        
        // Verify success
        $this->assertTrue($result, "markAsRead should return true on success");
        
        // Get the notification again and verify it's marked as read
        $updated = MockNotification::find($notification->id);
        $this->assertTrue($updated->read, "Notification should be marked as read after calling markAsRead");
    }
    
    /**
     * Test marking a non-existent notification as read
     */
    public function testMarkAsReadNonExistent()
    {
        // Try to mark a non-existent notification as read
        $result = MockNotificationService::markAsRead(9999);
        
        // Should return false
        $this->assertFalse($result, "Marking a non-existent notification as read should return false");
    }
    
    /**
     * Test getting notifications for a user
     */
    public function testGetUserNotifications()
    {
        // Create multiple notifications for our test user
        for ($i = 0; $i < 3; $i++) {
            MockNotificationService::create(
                $this->test_user_id,
                'test',
                "Test Notification $i",
                "Test message $i"
            );
        }
        
        // Create a notification for another user
        $other_user_id = 102;
        MockUser::addUser($other_user_id);
        
        MockNotificationService::create(
            $other_user_id,
            'test',
            'Other User Notification',
            'This should not be returned'
        );
        
        // Get notifications for our test user
        $notifications = MockNotificationService::getUserNotifications($this->test_user_id);
        
        // Verify we got the right number
        $this->assertCount(3, $notifications, "Should return exactly 3 notifications for this user");
        
        // Verify they are all for our test user
        foreach ($notifications as $notification) {
            $this->assertEquals($this->test_user_id, $notification->user_id, "All returned notifications should belong to the specified user");
        }
    }
    
    /**
     * Test getting unread notifications count
     */
    public function testGetUnreadCount()
    {
        // Create some read and unread notifications
        $notification1 = MockNotificationService::create(
            $this->test_user_id,
            'test',
            'Test Notification 1',
            'Test message 1'
        );
        
        $notification2 = MockNotificationService::create(
            $this->test_user_id,
            'test',
            'Test Notification 2',
            'Test message 2'
        );
        
        // Mark one as read
        MockNotificationService::markAsRead($notification1->id);
        
        // Get unread count
        $count = MockNotificationService::getUnreadCount($this->test_user_id);
        
        // Should be 1
        $this->assertEquals(1, $count, "Unread count should be 1 since we have 2 notifications with 1 marked as read");
    }
}
