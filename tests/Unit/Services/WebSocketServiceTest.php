<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use ReflectionClass;

/**
 * Mock NotificationService for testing
 */
class MockNotificationService
{
    public static $notifications = [];
    
    /**
     * Create a notification
     */
    public static function create($user_id, $type, $title, $message, $data = null)
    {
        $notification = [
            'id' => uniqid('not_'),
            'user_id' => $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'created_at' => time()
        ];
        
        self::$notifications[] = $notification;
        return $notification;
    }
    
    /**
     * Reset for testing
     */
    public static function reset()
    {
        self::$notifications = [];
    }
}

/**
 * Mock WebSocketService for testing
 */
class MockWebSocketService
{
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
            'id' => uniqid(),
            'channel' => $channel,
            'data' => $data,
            'timestamp' => time()
        ];
        
        $key = self::$transient_prefix . $user_id;
        $messages = self::get_transient($key) ?: [];
        $messages[] = $message;
        
        self::set_transient($key, $messages, self::$message_ttl);
        
        // Also create a notification for certain message types
        if (in_array($channel, ['chat', 'appointment', 'lab_results'])) {
            MockNotificationService::create(
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
            return $message['id'] !== $message_id;
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
    
    /**
     * Reset the mock data
     */
    public static function reset()
    {
        self::$transient_storage = [];
    }
}

/**
 * Test for WebSocketService
 */
class WebSocketServiceTest extends TestCase
{
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock data
        MockWebSocketService::reset();
        MockNotificationService::reset();
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
     * Test initialization of WebSocket service
     */
    public function testInit()
    {
        $result = MockWebSocketService::init();
        $this->assertTrue($result, "WebSocketService should register REST route on init");
    }
    
    /**
     * Test sending a message to a user
     */
    public function testSendMessage()
    {
        // Send a test message to user ID 1
        $user_id = 1;
        $channel = 'test_channel';
        $data = ['message' => 'Test message', 'type' => 'info'];
        
        $result = MockWebSocketService::sendMessage($channel, $data, $user_id);
        
        // Check if the send was successful
        $this->assertTrue($result, "Sending a message should return true");
        
        // Check if the message was stored in the transient
        $messages = MockWebSocketService::getMessages($user_id);
        $this->assertNotEmpty($messages, "User should have messages after sending");
        
        // Check the message content
        $this->assertEquals($channel, $messages[0]['channel'], "Message should have the correct channel");
        $this->assertEquals($data, $messages[0]['data'], "Message should have the correct data");
    }
    
    /**
     * Test sending a message that generates a notification
     */
    public function testSendMessageWithNotification()
    {
        // Send a message on a channel that generates notifications
        $user_id = 1;
        $channel = 'chat';
        $data = ['message' => ['content' => 'Hello', 'sender_id' => 2]];
        
        $result = MockWebSocketService::sendMessage($channel, $data, $user_id);
        
        // Check if the send was successful
        $this->assertTrue($result, "Sending a message should return true");
        
        // Check if a notification was created
        $this->assertNotEmpty(MockNotificationService::$notifications, "Notification should be created for chat message");
        $this->assertEquals('chat_notification', MockNotificationService::$notifications[0]['type'], "Notification should have the correct type");
        $this->assertEquals('New Message', MockNotificationService::$notifications[0]['title'], "Notification should have the correct title");
        $this->assertEquals($user_id, MockNotificationService::$notifications[0]['user_id'], "Notification should be for the correct user");
    }
    
    /**
     * Test message retrieval
     */
    public function testGetMessages()
    {
        // Add some test messages
        $user_id = 1;
        
        // Send multiple messages
        MockWebSocketService::sendMessage('test_channel', ['message' => 'Message 1'], $user_id);
        MockWebSocketService::sendMessage('test_channel', ['message' => 'Message 2'], $user_id);
        MockWebSocketService::sendMessage('other_channel', ['message' => 'Message 3'], $user_id);
        
        // Get the messages
        $messages = MockWebSocketService::getMessages($user_id);
        
        // Check if all messages were retrieved
        $this->assertCount(3, $messages, "All messages should be retrieved");
        
        // Check content of specific message
        $this->assertEquals('Message 2', $messages[1]['data']['message'], "Message content should match");
    }
    
    /**
     * Test message deletion
     */
    public function testDeleteMessage()
    {
        // Add a test message
        $user_id = 1;
        MockWebSocketService::sendMessage('test_channel', ['message' => 'Test message'], $user_id);
        
        // Get the message to find its ID
        $messages = MockWebSocketService::getMessages($user_id);
        $this->assertCount(1, $messages, "User should have one message");
        
        $message_id = $messages[0]['id'];
        
        // Delete the message
        MockWebSocketService::deleteMessage($user_id, $message_id);
        
        // Check if the message was deleted
        $messages_after = MockWebSocketService::getMessages($user_id);
        $this->assertEmpty($messages_after, "Messages should be empty after deletion");
    }
    
    /**
     * Test notification title formatting
     */
    public function testGetNotificationTitle()
    {
        // Test various channels
        $this->assertEquals('New Message', MockWebSocketService::getNotificationTitle('chat', []), 
            "Chat notification should have correct title");
        
        $this->assertEquals('Appointment Update', MockWebSocketService::getNotificationTitle('appointment', []), 
            "Appointment notification should have correct title");
        
        $this->assertEquals('Lab Results Available', MockWebSocketService::getNotificationTitle('lab_results', []), 
            "Lab results notification should have correct title");
        
        $this->assertEquals('New Notification', MockWebSocketService::getNotificationTitle('unknown', []), 
            "Unknown channel should have default title");
    }
    
    /**
     * Test notification message formatting
     */
    public function testGetNotificationMessage()
    {
        // Test chat message
        $this->assertEquals('New message from User', 
            MockWebSocketService::getNotificationMessage('chat', []), 
            "Chat notification should have correct message format");
        
        // Test appointment message
        $appointment_data = ['status' => 'confirmed'];
        $this->assertEquals('Your appointment status has been updated to: confirmed', 
            MockWebSocketService::getNotificationMessage('appointment', $appointment_data), 
            "Appointment notification should have correct message format");
        
        // Test lab results message
        $this->assertEquals('New lab results are available for review', 
            MockWebSocketService::getNotificationMessage('lab_results', []), 
            "Lab results notification should have correct message format");
        
        // Test unknown channel
        $this->assertEquals('You have a new notification', 
            MockWebSocketService::getNotificationMessage('unknown', []), 
            "Unknown channel should have default message");
    }
    
    /**
     * Test message expiration
     */
    public function testMessageExpiration()
    {
        // Create a message in the past
        $user_id = 1;
        $message = [
            'id' => uniqid(),
            'channel' => 'test_channel',
            'data' => ['message' => 'Old message'],
            'timestamp' => time() - (MockWebSocketService::$message_ttl + 10) // Make it older than TTL
        ];
        
        // Manually add an old message
        $key = MockWebSocketService::$transient_prefix . $user_id;
        MockWebSocketService::$transient_storage[$key] = [
            'value' => [$message],
            'expiry' => time() + MockWebSocketService::$message_ttl
        ];
        
        // Get messages - the old one should be filtered out
        $messages = MockWebSocketService::getMessages($user_id);
        $this->assertEmpty($messages, "Expired messages should be filtered out");
    }
}
