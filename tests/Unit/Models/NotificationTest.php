<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Notification;
use HospitalManager\Models\Patient;

class NotificationTest extends TestCase
{
    /**
     * @var int Test user ID
     */
    protected $user_id;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test user for notifications
        $this->user_id = $this->createUserWithRole('patient');
    }
    
    /**
     * Test notification creation
     */
    public function testCreateNotification()
    {
        $data = [
            'user_id' => $this->user_id,
            'type' => 'appointment',
            'title' => 'Appointment Reminder',
            'message' => 'Your appointment is scheduled for tomorrow at 10:00 AM.',
            'read' => 0,
            'created_at' => current_time('mysql')
        ];

        $notification = Notification::create($data);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user_id, $notification->user_id);
        $this->assertEquals('appointment', $notification->type);
        $this->assertEquals('Appointment Reminder', $notification->title);
        $this->assertEquals('Your appointment is scheduled for tomorrow at 10:00 AM.', $notification->message);
        $this->assertEquals(0, $notification->read);
    }

    /**
     * Test finding a notification by ID
     */
    public function testFindNotification()
    {
        // Create a test notification
        $notification = $this->createTestNotification([
            'user_id' => $this->user_id
        ]);
        
        // Find the notification by ID
        $found_notification = Notification::find($notification->id);
        
        $this->assertInstanceOf(Notification::class, $found_notification);
        $this->assertEquals($notification->id, $found_notification->id);
        $this->assertEquals($notification->user_id, $found_notification->user_id);
        $this->assertEquals($notification->title, $found_notification->title);
        $this->assertEquals($notification->message, $found_notification->message);
    }

    /**
     * Test getting the user associated with a notification
     */
    public function testGetUser()
    {
        // Create a test notification
        $notification = $this->createTestNotification([
            'user_id' => $this->user_id
        ]);
        
        // Get the associated user
        $user = $notification->getUser();
        
        $this->assertNotNull($user);
        $this->assertEquals($this->user_id, $user->ID);
    }

    /**
     * Test marking a notification as read
     */
    public function testMarkAsRead()
    {
        // Create a test notification (unread by default)
        $notification = $this->createTestNotification([
            'user_id' => $this->user_id,
            'read' => 0
        ]);
        
        // Mark as read
        $notification->read = 1;
        $notification->save();
        
        // Retrieve the notification again
        $updated = Notification::find($notification->id);
        
        $this->assertEquals(1, $updated->read);
    }

    /**
     * Test default created_at timestamp
     */
    public function testDefaultCreatedAt()
    {
        $data = [
            'user_id' => $this->user_id,
            'type' => 'system',
            'title' => 'System Notification',
            'message' => 'This is a system notification'
            // No created_at provided
        ];

        $notification = Notification::create($data);
        
        $this->assertNotNull($notification->created_at);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $notification->created_at);
    }

    /**
     * Test deleting a notification
     */
    public function testDeleteNotification()
    {
        // Create a test notification
        $notification = $this->createTestNotification([
            'user_id' => $this->user_id
        ]);
        
        $notification_id = $notification->id;
        
        // Delete the notification
        $notification->delete();
        
        // Try to find the deleted notification
        $deleted = Notification::find($notification_id);
        
        $this->assertNull($deleted);
    }
}
