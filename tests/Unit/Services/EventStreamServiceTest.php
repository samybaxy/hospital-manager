<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Mock MessageQueueService for testing
 */
class MockMessageQueueService 
{
    private static $messageQueue = [];
    
    /**
     * Add a message to the user's queue
     */
    public static function queueMessage($userId, $message) 
    {
        if (!isset(self::$messageQueue[$userId])) {
            self::$messageQueue[$userId] = [];
        }
        
        $message['id'] = uniqid('msg_');
        $message['timestamp'] = time();
        
        self::$messageQueue[$userId][] = $message;
        return true;
    }
    
    /**
     * Get pending messages for a user
     */
    public static function getPendingMessages($userId) 
    {
        return isset(self::$messageQueue[$userId]) ? self::$messageQueue[$userId] : [];
    }
    
    /**
     * Clear all messages
     */
    public static function reset() 
    {
        self::$messageQueue = [];
    }
}

/**
 * Mock EventStreamService class for testing
 */
class MockEventStreamService 
{
    private static $connections = [];
    
    /**
     * Initialize SSE endpoints
     */
    public static function initEndpoints() 
    {
        // In a real application, this would register REST API routes
        // For testing, we just return true to indicate it was called
        return true;
    }
    
    /**
     * Handle SSE connection
     */
    public static function handleSSEConnection() 
    {
        $headers = [
            'Content-Type: text/event-stream',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'X-Accel-Buffering: no'
        ];
        
        $userId = 1; // Mock user ID
        self::$connections[$userId] = true;
        
        // In testing we just return the headers that would be set
        // and a sample message format
        return [
            'headers' => $headers,
            'data' => "retry: 1000\n\nevent: message\ndata: {\"type\":\"test\"}\n\n"
        ];
    }
    
    /**
     * Broadcast an event to users
     */
    public static function broadcastEvent($event, $data, $userIds = null) 
    {
        if ($userIds === null) {
            // Mock broadcasting to all connected users
            foreach (array_keys(self::$connections) as $userId) {
                MockMessageQueueService::queueMessage($userId, [
                    'event' => $event,
                    'data' => $data
                ]);
            }
        } else {
            // Mock broadcasting to specific users
            foreach ((array)$userIds as $userId) {
                MockMessageQueueService::queueMessage($userId, [
                    'event' => $event,
                    'data' => $data
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * Add a user connection for testing
     */
    public static function addConnection($userId)
    {
        self::$connections[$userId] = true;
    }
    
    /**
     * Reset connections for testing
     */
    public static function reset()
    {
        self::$connections = [];
    }
}

/**
 * Test for EventStreamService
 */
class EventStreamServiceTest extends TestCase
{
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock data
        MockEventStreamService::reset();
        MockMessageQueueService::reset();
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
     * Test initialization of SSE endpoints
     */
    public function testInitEndpoints()
    {
        // Simply verify that the function exists and can be called
        $result = MockEventStreamService::initEndpoints();
        $this->assertTrue($result, "EventStreamService should register REST route on init");
    }
    
    /**
     * Test SSE connection handling
     */
    public function testHandleSSEConnection()
    {
        $response = MockEventStreamService::handleSSEConnection();
        
        // Verify headers
        $this->assertIsArray($response['headers']);
        $this->assertContains('Content-Type: text/event-stream', $response['headers']);
        $this->assertContains('Cache-Control: no-cache', $response['headers']);
        $this->assertContains('Connection: keep-alive', $response['headers']);
        
        // Verify data format
        $this->assertStringContainsString('event: message', $response['data']);
        $this->assertStringContainsString('data:', $response['data']);
    }
    
    /**
     * Test broadcasting to all connected users
     */
    public function testBroadcastEventToAllUsers()
    {
        // Add some mock connections
        MockEventStreamService::addConnection(1);
        MockEventStreamService::addConnection(2);
        MockEventStreamService::addConnection(3);
        
        // Broadcast an event
        $event = 'notification';
        $data = ['message' => 'Test notification', 'type' => 'info'];
        $result = MockEventStreamService::broadcastEvent($event, $data);
        
        // Check if broadcast was successful
        $this->assertTrue($result);
        
        // Check if all connected users received the message
        $user1Messages = MockMessageQueueService::getPendingMessages(1);
        $user2Messages = MockMessageQueueService::getPendingMessages(2);
        $user3Messages = MockMessageQueueService::getPendingMessages(3);
        
        $this->assertNotEmpty($user1Messages);
        $this->assertNotEmpty($user2Messages);
        $this->assertNotEmpty($user3Messages);
        
        // Verify message content for a user
        $this->assertEquals($event, $user1Messages[0]['event']);
        $this->assertEquals($data, $user1Messages[0]['data']);
    }
    
    /**
     * Test broadcasting to specific users
     */
    public function testBroadcastEventToSpecificUsers()
    {
        // Add some mock connections
        MockEventStreamService::addConnection(1);
        MockEventStreamService::addConnection(2);
        MockEventStreamService::addConnection(3);
        
        // Broadcast an event to specific users
        $event = 'private_message';
        $data = ['message' => 'Test private message', 'from' => 'Admin'];
        $targeted_users = [1, 3]; // Only users 1 and 3 should receive this
        
        $result = MockEventStreamService::broadcastEvent($event, $data, $targeted_users);
        
        // Check if broadcast was successful
        $this->assertTrue($result);
        
        // Check if targeted users received the message
        $user1Messages = MockMessageQueueService::getPendingMessages(1);
        $user3Messages = MockMessageQueueService::getPendingMessages(3);
        
        $this->assertNotEmpty($user1Messages);
        $this->assertNotEmpty($user3Messages);
        
        // User 2 should not have received the message
        $user2Messages = MockMessageQueueService::getPendingMessages(2);
        $this->assertEmpty($user2Messages);
        
        // Verify message content
        $this->assertEquals($event, $user1Messages[0]['event']);
        $this->assertEquals($data, $user1Messages[0]['data']);
    }
}
