<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\NotificationService;
use HospitalManager\Models\Notification;
use Brain\Monkey\Functions;
use Mockery;

class NotificationServiceTest extends TestCase
{
    /**
     * @var int User ID for testing
     */
    protected $test_user_id;

    /**
     * @var array Mock database for notifications
     */
    protected $mock_notifications = [];

    /**
     * @var int Next ID for mock notifications
     */
    protected $next_notification_id = 1;
    
    /**
     * @var bool Flag to track WebSocket messages
     */
    protected $websocket_message_sent = false;
    
    /**
     * @var array WebSocket message data for assertions
     */
    protected $websocket_message_data = [];

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock data
        $this->mock_notifications = [];
        $this->next_notification_id = 1;
        $this->websocket_message_sent = false;
        $this->websocket_message_data = [];
        
        // Create a test user
        $this->test_user_id = $this->createUserWithRole('patient');
        
        // Mock WordPress functions
        Functions\when('current_time')->justReturn('2025-04-22 10:30:00');
        
        // Mock user existence check
        Functions\when('get_user_by')->alias(function($field, $value) {
            if ($field === 'ID' && $value === $this->test_user_id) {
                return (object)[
                    'ID' => $this->test_user_id,
                    'display_name' => 'Test Patient',
                    'user_email' => 'patient@example.com'
                ];
            }
            return false;
        });
        
        // Mock the notification creation in WordPress-MVC pattern
        // This uses the proper filter hooks that WordPress-MVC would use
        Functions\when('apply_filters')->alias(function($tag, $value = '', ...$args) {
            if ($tag === 'pre_notification_create') {
                // Allow pre-filtering of notification data
                return $value;
            } elseif ($tag === 'after_notification_create') {
                // Handle post-creation filtering
                return $args[0];
            }
            return $value;
        });
        
        // Mock Notification::create to use our mock storage
        Functions\when('Notification::create')->alias(function($data) {
            $id = $this->next_notification_id++;
            $this->mock_notifications[$id] = array_merge(['id' => $id], $data);
            return $this->createMockNotification($this->mock_notifications[$id]);
        });
        
        // Mock Notification::find
        Functions\when('Notification::find')->alias(function($id) {
            if (isset($this->mock_notifications[$id])) {
                return $this->createMockNotification($this->mock_notifications[$id]);
            }
            return null;
        });
        
        // Mock Notification::where with callback
        Functions\when('Notification::where')->alias(function($column, $value) {
            $results = [];
            foreach ($this->mock_notifications as $notification) {
                if (isset($notification[$column]) && $notification[$column] === $value) {
                    $results[] = $this->createMockNotification($notification);
                }
            }
            return $results;
        });
        
        // Mock WebSocketService::sendMessage
        Functions\when('WebSocketService::sendMessage')->alias(function($channel, $data, $user_id) {
            $this->websocket_message_sent = true;
            $this->websocket_message_data = [
                'channel' => $channel,
                'data' => $data,
                'user_id' => $user_id
            ];
            return true;
        });
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
     * Create a mock notification object from data
     */
    private function createMockNotification($data)
    {
        // Create a mock that acts like a Notification model
        $notification = Mockery::mock(Notification::class);
        
        // Set up properties
        foreach ($data as $key => $value) {
            $notification->{$key} = $value;
        }
        
        // Allow saving
        $notification->shouldReceive('save')
            ->andReturnUsing(function() use ($notification) {
                $this->mock_notifications[$notification->id] = (array)$notification;
                return true;
            });
            
        return $notification;
    }

    /**
     * Test creating a notification successfully
     */
    public function testCreateNotificationSuccess()
    {
        $notification = NotificationService::create(
            $this->test_user_id,
            'appointment',
            'Appointment Reminder',
            'Your appointment is tomorrow at 10:00 AM',
            ['appointment_id' => 123, 'doctor_name' => 'Dr. Smith']
        );
        
        // Assert that a notification object was returned
        $this->assertInstanceOf(Notification::class, $notification, "Service should return a Notification object");
        $this->assertEquals($this->test_user_id, $notification->user_id, "Notification should be assigned to the correct user");
        $this->assertEquals('appointment', $notification->type, "Notification type should match what was passed");
        $this->assertEquals('Appointment Reminder', $notification->title, "Notification title should match what was passed");
        $this->assertEquals('Your appointment is tomorrow at 10:00 AM', $notification->message, "Notification message should match what was passed");
        $this->assertFalse((bool)$notification->read, "New notification should be marked as unread");
        
        // Verify the notification was saved to the database
        $saved_notification = Notification::find($notification->id);
        $this->assertNotNull($saved_notification, "Notification should be retrievable from the database after creation");
        $this->assertEquals($notification->id, $saved_notification->id, "Retrieved notification should have the same ID");
        
        // Verify WebSocket message was sent
        $this->assertTrue($this->websocket_message_sent, "Real-time notification should be sent via WebSocket");
        $this->assertEquals('notification', $this->websocket_message_data['channel'], "Notification should be sent on the 'notification' channel");
        $this->assertEquals($this->test_user_id, $this->websocket_message_data['user_id'], "WebSocket message should target the correct user");
    }
    
    /**
     * Test creating a notification with invalid user
     */
    public function testCreateNotificationInvalidUser()
    {
        // Try to create a notification for an invalid user ID
        $invalid_user_id = 9999;
        $notification = NotificationService::create(
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
        $notification = NotificationService::create(
            $this->test_user_id,
            'system',
            'System Notification',
            'This is a system notification without additional data'
        );
        
        // Should still create successfully
        $this->assertInstanceOf(Notification::class, $notification, "Service should create notification even without optional data");
        $this->assertNull($notification->data, "Data field should be null when no data is provided");
    }
    
    /**
     * Test marking a notification as read
     */
    public function testMarkAsRead()
    {
        // First create a notification
        $notification = NotificationService::create(
            $this->test_user_id,
            'test',
            'Test Notification',
            'Test message'
        );
        
        // Mark it as read
        $result = NotificationService::markAsRead($notification->id);
        
        // Verify success
        $this->assertTrue($result, "markAsRead should return true on success");
        
        // Get the notification again and verify it's marked as read
        $updated = Notification::find($notification->id);
        $this->assertTrue((bool)$updated->read, "Notification should be marked as read after calling markAsRead");
    }
    
    /**
     * Test marking a non-existent notification as read
     */
    public function testMarkAsReadNonExistent()
    {
        // Try to mark a non-existent notification as read
        $result = NotificationService::markAsRead(9999);
        
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
            NotificationService::create(
                $this->test_user_id,
                'test',
                "Test Notification $i",
                "Test message $i"
            );
        }
        
        // Create a notification for another user
        $other_user_id = $this->createUserWithRole('patient');
        NotificationService::create(
            $other_user_id,
            'test',
            'Other User Notification',
            'This should not be returned'
        );
        
        // Get notifications for our test user
        $notifications = NotificationService::getUserNotifications($this->test_user_id);
        
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
        $notification1 = NotificationService::create(
            $this->test_user_id,
            'test',
            'Test Notification 1',
            'Test message 1'
        );
        
        $notification2 = NotificationService::create(
            $this->test_user_id,
            'test',
            'Test Notification 2',
            'Test message 2'
        );
        
        // Mark one as read
        NotificationService::markAsRead($notification1->id);
        
        // Get unread count
        $count = NotificationService::getUnreadCount($this->test_user_id);
        
        // Should be 1
        $this->assertEquals(1, $count, "Unread count should be 1 since we have 2 notifications with 1 marked as read");
    }
}
