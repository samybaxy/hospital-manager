<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\WebSocketService;
use HospitalManager\Services\NotificationService;
use Brain\Monkey\Functions;
use Mockery;

class WebSocketServiceTest extends TestCase
{
    /**
     * @var int Mock user ID for testing
     */
    private $test_user_id = 1;
    
    /**
     * @var string The transient prefix used by WebSocketService
     */
    private $transient_prefix = 'hm_ws_';
    
    /**
     * @var array Mock storage for transients
     */
    private $mock_transients = [];
    
    /**
     * @var bool Flag to track if notification was created
     */
    private $notification_created = false;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset our test data
        $this->mock_transients = [];
        $this->notification_created = false;
        
        // Mock WordPress transient functions
        Functions\when('get_transient')->alias(function($key) {
            return isset($this->mock_transients[$key]) ? $this->mock_transients[$key] : false;
        });
        
        Functions\when('set_transient')->alias(function($key, $value, $expiration) {
            $this->mock_transients[$key] = $value;
            return true;
        });
        
        Functions\when('delete_transient')->alias(function($key) {
            if (isset($this->mock_transients[$key])) {
                unset($this->mock_transients[$key]);
            }
            return true;
        });
        
        // Mock other WordPress functions
        Functions\when('get_current_user_id')->justReturn($this->test_user_id);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('uniqid')->justReturn('mock_message_id');
        Functions\when('current_time')->justReturn('2025-04-22 10:30:00');
        
        // Set up mock for NotificationService using WordPress-MVC patterns
        Functions\when('apply_filters')->alias(function($tag, $value = '', ...$args) {
            if ($tag === 'pre_notification_create') {
                $this->notification_created = true;
                return (object)[
                    'id' => 999,
                    'user_id' => $args[0],
                    'type' => $args[1],
                    'title' => $args[2],
                    'message' => $args[3],
                    'data' => $args[4] ?? null
                ];
            }
            return $value;
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
     * Test sending a message to a user
     */
    public function testSendMessage()
    {
        $channel = 'test_channel';
        $data = ['key' => 'value'];
        
        // Call the method
        $result = WebSocketService::sendMessage($channel, $data, $this->test_user_id);
        
        // Assert success
        $this->assertTrue($result, "WebSocketService::sendMessage should return true on success");
        
        // Verify message was stored in transient
        $transient_key = $this->transient_prefix . $this->test_user_id;
        $this->assertArrayHasKey($transient_key, $this->mock_transients, "Message should be stored in transient with key: $transient_key");
        
        $messages = $this->mock_transients[$transient_key];
        $this->assertIsArray($messages, "Stored messages should be an array");
        $this->assertCount(1, $messages, "There should be exactly one message stored");
        $this->assertEquals($channel, $messages[0]['channel'], "Channel should match what was sent");
        $this->assertEquals($data, $messages[0]['data'], "Data should match what was sent");
        $this->assertEquals('mock_message_id', $messages[0]['id'], "Message ID should be set");
        $this->assertArrayHasKey('timestamp', $messages[0], "Message should have a timestamp");
    }
    
    /**
     * Test sending multiple messages to a user
     */
    public function testSendMultipleMessages()
    {
        // Send first message
        WebSocketService::sendMessage('channel1', ['test' => 1], $this->test_user_id);
        
        // Send second message
        WebSocketService::sendMessage('channel2', ['test' => 2], $this->test_user_id);
        
        // Verify both messages are in the transient
        $transient_key = $this->transient_prefix . $this->test_user_id;
        $this->assertArrayHasKey($transient_key, $this->mock_transients);
        
        $messages = $this->mock_transients[$transient_key];
        $this->assertCount(2, $messages, "There should be two messages stored");
        $this->assertEquals('channel1', $messages[0]['channel']);
        $this->assertEquals('channel2', $messages[1]['channel']);
    }
    
    /**
     * Test sending a message that triggers a notification
     */
    public function testSendMessageWithNotification()
    {
        // These channels should trigger notifications
        $channels_with_notifications = [
            'chat' => ['message' => 'Hello', 'sender' => 'Dr. Smith'],
            'appointment' => ['id' => 123, 'time' => '14:30'],
            'lab_results' => ['test' => 'Blood Test', 'result' => 'Normal']
        ];
        
        foreach ($channels_with_notifications as $channel => $test_data) {
            // Reset notification flag
            $this->notification_created = false;
            
            // Send the message
            WebSocketService::sendMessage($channel, $test_data, $this->test_user_id);
            
            // Assert that a notification was created
            $this->assertTrue(
                $this->notification_created, 
                "Channel '$channel' should create a notification"
            );
        }
        
        // Test a channel that doesn't trigger notifications
        $this->notification_created = false;
        WebSocketService::sendMessage('custom_channel', ['data' => 'test'], $this->test_user_id);
        $this->assertFalse($this->notification_created, "Custom channel should not create a notification");
    }
    
    /**
     * Test getting pending messages for a user
     */
    public function testGetPendingMessages()
    {
        // Setup: Store some messages
        $messages = [
            [
                'id' => 'msg1',
                'channel' => 'test',
                'data' => ['test' => 1],
                'timestamp' => '2025-04-22 10:00:00'
            ],
            [
                'id' => 'msg2',
                'channel' => 'test',
                'data' => ['test' => 2],
                'timestamp' => '2025-04-22 10:15:00'
            ]
        ];
        
        $this->mock_transients[$this->transient_prefix . $this->test_user_id] = $messages;
        
        // Get the messages
        $pending_messages = WebSocketService::getPendingMessages();
        
        // Verify we got the messages
        $this->assertIsArray($pending_messages);
        $this->assertCount(2, $pending_messages);
        $this->assertEquals($messages, $pending_messages);
        
        // Verify the messages were cleared after retrieval
        $this->assertArrayNotHasKey($this->transient_prefix . $this->test_user_id, $this->mock_transients);
    }
    
    /**
     * Test case when no messages are pending
     */
    public function testGetPendingMessagesEmpty()
    {
        // Get messages when none exist
        $pending_messages = WebSocketService::getPendingMessages();
        
        // Should return an empty array
        $this->assertIsArray($pending_messages);
        $this->assertEmpty($pending_messages);
    }
}
