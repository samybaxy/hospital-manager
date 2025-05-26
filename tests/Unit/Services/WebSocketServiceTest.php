<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use HospitalManager\Tests\Mocks\Services\MockWebSocketService;
use HospitalManager\Tests\Mocks\Services\MockNotificationService;
use Mockery;

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
        
        // Add test users
        \HospitalManager\Tests\Mocks\Services\MockUser::reset();
        \HospitalManager\Tests\Mocks\Services\MockUser::addUser(1, [
            'display_name' => 'Test User 1',
            'user_email' => 'user1@example.com'
        ]);
        \HospitalManager\Tests\Mocks\Services\MockUser::addUser(2, [
            'display_name' => 'Test User 2',
            'user_email' => 'user2@example.com' 
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
        
        $message_id = $messages[0]['ID'];
        
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
            'ID' => uniqid(),
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
