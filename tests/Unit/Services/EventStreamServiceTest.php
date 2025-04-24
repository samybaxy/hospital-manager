<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\EventStreamService;
use HospitalManager\Services\MessageQueueService;
use Brain\Monkey\Functions;
use Mockery;

class EventStreamServiceTest extends TestCase
{
    /**
     * @var bool Flag to track if rest route was registered
     */
    private $register_rest_route_called = false;
    
    /**
     * @var array Stores registered routes
     */
    private $registered_routes = [];
    
    /**
     * @var array Mock message queue
     */
    private $message_queue = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset test flags and data
        $this->register_rest_route_called = false;
        $this->registered_routes = [];
        $this->message_queue = [];
        
        // Mock WordPress functions
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('is_user_logged_in')->justReturn(true);
        
        // Mock wp_send_json function for event streaming
        Functions\when('wp_send_json')->alias(function($data) {
            return $data;
        });
        
        // Track headers sent via server-sent events
        $this->headers_sent = [];
        Functions\when('header')->alias(function($header) {
            $this->headers_sent[] = $header;
            return true;
        });
        
        // Mock register_rest_route function using WordPress-MVC pattern
        Functions\when('register_rest_route')->alias(function($namespace, $route, $args) {
            $this->register_rest_route_called = true;
            $this->registered_routes[] = [
                'namespace' => $namespace,
                'route' => $route,
                'args' => $args
            ];
            return true;
        });
        
        // Mock WordPress hooks system
        Functions\when('add_action')->alias(function($hook, $callback) {
            if ($hook === 'rest_api_init') {
                // Store the callback to be executed during tests
                $this->rest_api_init_callback = $callback;
            }
            return true;
        });
        
        Functions\when('apply_filters')->alias(function($tag, $value, ...$args) {
            if ($tag === 'hospital_manager_event_stream_headers') {
                // Allow customizing headers through filter
                return $value;
            } elseif ($tag === 'hospital_manager_event_access') {
                // Default to allowing access
                return true;
            }
            return $value;
        });
        
        // Mock the MessageQueueService
        Functions\when('MessageQueueService::getMessagesForUser')->alias(function($user_id) {
            return $this->message_queue;
        });
        
        Functions\when('MessageQueueService::addMessage')->alias(function($user_id, $event, $data) {
            $this->message_queue[] = [
                'user_id' => $user_id,
                'event' => $event,
                'data' => $data,
                'id' => uniqid('msg_'),
                'timestamp' => time()
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
     * Simulate triggering the rest_api_init hook
     */
    private function triggerRestApiInit()
    {
        if (isset($this->rest_api_init_callback) && is_callable($this->rest_api_init_callback)) {
            call_user_func($this->rest_api_init_callback);
        }
    }
    
    /**
     * Test endpoint registration
     */
    public function testEndpointRegistration()
    {
        // Call the initialization method
        EventStreamService::initEndpoints();
        
        // Simulate running the rest_api_init action
        $this->triggerRestApiInit();
        
        // Verify that register_rest_route was called
        $this->assertTrue(
            $this->register_rest_route_called, 
            "Event stream endpoints were not registered. This would prevent real-time updates for users, breaking critical functionality like chat notifications and alerts."
        );
        
        // Verify the route was registered with correct parameters
        $this->assertNotEmpty($this->registered_routes, "No routes were registered for event streaming");
        
        $route = $this->registered_routes[0];
        $this->assertEquals('hospital-manager/v1', $route['namespace'], "Incorrect API namespace used for event streams");
        $this->assertEquals('/events', $route['route'], "Incorrect route path for event streams");
        $this->assertEquals('GET', $route['args']['methods'], "Event streams should use GET method");
        $this->assertEquals(
            [EventStreamService::class, 'handleSSEConnection'], 
            $route['args']['callback'],
            "Event stream route is not using the correct handler method"
        );
        $this->assertTrue(
            is_callable($route['args']['permission_callback']),
            "Permission callback must be callable to properly secure event streams"
        );
    }
    
    /**
     * Test permission callback for authenticated users
     */
    public function testPermissionCallbackForAuthenticatedUsers()
    {
        // Set up an authenticated user
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        
        // Call the initialization method to get the permission callback
        EventStreamService::initEndpoints();
        $this->triggerRestApiInit();
        
        // Get the permission callback
        $permission_callback = $this->registered_routes[0]['args']['permission_callback'];
        
        // Test the permission callback
        $this->assertTrue(
            call_user_func($permission_callback),
            "Authenticated users should have access to event streams"
        );
    }
    
    /**
     * Test permission callback for unauthenticated users
     */
    public function testPermissionCallbackForUnauthenticatedUsers()
    {
        // Set up an unauthenticated user
        Functions\when('is_user_logged_in')->justReturn(false);
        
        // Call the initialization method to get the permission callback
        EventStreamService::initEndpoints();
        $this->triggerRestApiInit();
        
        // Get the permission callback
        $permission_callback = $this->registered_routes[0]['args']['permission_callback'];
        
        // Test the permission callback
        $this->assertFalse(
            call_user_func($permission_callback),
            "Unauthenticated users should not have access to event streams to protect sensitive medical data"
        );
    }
    
    /**
     * Test sending events to a client
     */
    public function testSendEvent()
    {
        // Call the send event method
        $event = 'test_event';
        $data = ['message' => 'Test message'];
        
        ob_start();
        $result = EventStreamService::sendEvent($event, $data);
        $output = ob_get_clean();
        
        // Verify event format
        $this->assertTrue($result, "sendEvent should return true on success");
        $this->assertStringContainsString("event: {$event}", $output, "Event type should be included in the output");
        $this->assertStringContainsString("data: " . json_encode($data), $output, "Event data should be included as JSON");
        $this->assertStringContainsString("\n\n", $output, "Events should be properly terminated with double newlines");
    }
    
    /**
     * Test broadcasting events to users
     */
    public function testBroadcastEvent()
    {
        $user_ids = [1, 2, 3];
        $event = 'notification';
        $data = ['type' => 'alert', 'message' => 'Test broadcast'];
        
        // Reset message queue
        $this->message_queue = [];
        
        // Call the broadcast method
        $result = EventStreamService::broadcastEvent($user_ids, $event, $data);
        
        // Verify results
        $this->assertTrue($result, "broadcastEvent should return true on success");
        $this->assertCount(count($user_ids), $this->message_queue, "A message should be queued for each user");
        
        // Check the first message
        $this->assertEquals($user_ids[0], $this->message_queue[0]['user_id'], "Message should be associated with correct user");
        $this->assertEquals($event, $this->message_queue[0]['event'], "Event type should be preserved");
        $this->assertEquals($data, $this->message_queue[0]['data'], "Event data should be preserved");
    }
    
    /**
     * Test SSE connection headers
     */
    public function testSSEConnectionHeaders()
    {
        // Mock output control functions
        Functions\when('ob_implicit_flush')->justReturn(true);
        Functions\when('ob_end_flush')->justReturn(true);
        
        // Create a mock WP_REST_Request
        $request = Mockery::mock('WP_REST_Request');
        
        // Capture the headers
        EventStreamService::handleSSEConnection($request);
        
        // Check headers
        $required_headers = [
            'Content-Type: text/event-stream',
            'Cache-Control: no-cache',
            'X-Accel-Buffering: no'
        ];
        
        foreach ($required_headers as $header) {
            $this->assertContains(
                $header, 
                $this->headers_sent,
                "Missing required SSE header: $header. This would prevent proper event stream functioning."
            );
        }
    }
}
