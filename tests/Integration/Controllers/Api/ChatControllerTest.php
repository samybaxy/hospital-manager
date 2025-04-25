<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Chat;
use HospitalManager\Models\ChatMessage;
use HospitalManager\Models\Patient;
use WP_REST_Request;
use WP_REST_Server;

class ChatControllerTest extends TestCase
{
    /**
     * @var \WP_REST_Server
     */
    protected $server;

    /**
     * @var string
     */
    protected $namespace = 'hospital-manager/v1';

    /**
     * @var array
     */
    protected $test_users = [];

    /**
     * @var Chat
     */
    protected $test_chat;
    
    /**
     * @var Patient
     */
    protected $test_patient;

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
        
        // Create test users with different roles
        $this->test_users['doctor'] = $this->createUserWithRole('doctor');
        $this->test_users['patient'] = $this->createUserWithRole('patient');
        
        // Create a test patient linked to the patient user
        $this->test_patient = $this->createTestPatient([
            'user_id' => $this->test_users['patient'],
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '1234567890',
            'gender' => 'Male'
        ]);
        
        // Create a test chat between patient and doctor
        $this->test_chat = $this->createTestChat([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_users['doctor'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Add some test messages to the chat
        $this->createTestChatMessage([
            'chat_id' => $this->test_chat->id,
            'sender_id' => $this->test_users['patient'],
            'message' => 'Hello doctor, I have a question.',
            'read' => 1,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]);
        
        $this->createTestChatMessage([
            'chat_id' => $this->test_chat->id,
            'sender_id' => $this->test_users['doctor'],
            'message' => 'Hello, how can I help you?',
            'read' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
        ]);
    }

    /**
     * Create a test chat
     *
     * @param array $data Chat data
     * @return Chat
     */
    protected function createTestChat($data)
    {
        return Chat::create($data);
    }

    /**
     * Create a test chat message
     *
     * @param array $data Message data
     * @return ChatMessage
     */
    protected function createTestChatMessage($data)
    {
        return ChatMessage::create($data);
    }

    /**
     * Test getting user chats as a patient
     */
    public function testGetChatsAsPatient()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to get chats
        $request = new WP_REST_Request('GET', "/{$this->namespace}/chats");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        // Verify the chat data is correct
        $found = false;
        foreach ($data as $chat) {
            if ( (int)$chat->id === $this->test_chat->id ) {
                $found = true;
                $this->assertEquals($this->test_patient->id, $chat->patient_id);
                $this->assertEquals($this->test_users['doctor'], $chat->doctor_id);
                $this->assertEquals('active', $chat->status);
                break;
            }
        }
        $this->assertTrue($found, 'Test chat not found in response');
    }
    
    /**
     * Test getting user chats as a doctor
     */
    public function testGetChatsAsDoctor()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get chats
        $request = new WP_REST_Request('GET', "/{$this->namespace}/chats");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        // Verify the chat data is correct
        $found = false;
        foreach ($data as $chat) {
            if ( (int) $chat->id === $this->test_chat->id ) {
                $found = true;
                $this->assertEquals($this->test_patient->id, $chat->patient_id);
                $this->assertEquals($this->test_users['doctor'], $chat->doctor_id);
                break;
            }
        }
        $this->assertTrue($found, 'Test chat not found in response');
    }
    
    /**
     * Test getting chat messages
     */
    public function testGetChatMessages()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to get chat messages
        $request = new WP_REST_Request('GET', "/{$this->namespace}/chats/{$this->test_chat->id}/messages");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        $this->assertEquals(2, count($data));
        
        // Check message content and order (newest first)
        $this->assertEquals('Hello, how can I help you?', $data[0]->message);
        $this->assertEquals('Hello doctor, I have a question.', $data[1]->message);
    }
    
    /**
     * Test pagination of chat messages
     */
    public function testGetChatMessagesPagination()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Add 20 more messages to the chat
        for ($i = 0; $i < 20; $i++) {
            $this->createTestChatMessage([
                'chat_id' => $this->test_chat->id,
                'sender_id' => ($i % 2 == 0) ? $this->test_users['patient'] : $this->test_users['doctor'],
                'message' => "Test message {$i}",
                'read' => 0,
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} minutes"))
            ]);
        }
        
        // Create request with pagination
        $request = new WP_REST_Request('GET', "/{$this->namespace}/chats/{$this->test_chat->id}/messages");
        $request->set_param('page', 1);
        $request->set_param('per_page', 10);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check correct number of messages returned
        $data = $response->get_data();
        $this->assertCount(10, $data);
    }
    
    /**
     * Test sending a new message
     */
    public function testSendMessage()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to send a new message
        $request = new WP_REST_Request('POST', "/{$this->namespace}/chats/{$this->test_chat->id}/messages");
        $request->set_param('message', 'This is a new test message');
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Verify the new message was saved
        $messagesObj = ChatMessage::getChatMessages($this->test_chat->id, 1, 1);
        $message = isset($messagesObj->data) && !empty($messagesObj->data) ? $messagesObj->data[0] : null;
        $this->assertNotNull($message, 'Message not found');
        $this->assertEquals('This is a new test message', $message->message);
        $this->assertEquals($this->test_users['patient'], $message->sender_id);
    }
    
    /**
     * Test starting a new chat as a patient
     */
    public function testStartChat()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to start a new chat
        $request = new WP_REST_Request('POST', "/{$this->namespace}/chats/start");
        $request->set_param('doctor_id', $this->test_users['doctor']);
        $request->set_param('initial_message', 'I would like to ask about my medication');
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check that a new chat was created
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        $this->assertEquals($this->test_patient->id, $data->patient_id);
        $this->assertEquals($this->test_users['doctor'], $data->doctor_id);
        
        // Check that the initial message was added
        $msgObject = ChatMessage::getChatMessages($data->id, 1, 10);
        $messages = isset($msgObject->data) && !empty($msgObject->data) ? $msgObject->data : [];
        $this->assertNotEmpty($messages);
        $this->assertEquals('I would like to ask about my medication', $messages[0]->message);
    }
    
    /**
     * Test marking messages as read
     */
    public function testMarkMessagesAsRead()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to mark chat as read
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/chats/{$this->test_chat->id}/read");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Verify all messages are marked as read
        $messages = ChatMessage::getChatMessages($this->test_chat->id, 1, 10);
        foreach ($messages->data as $message) {
            if ($message->sender_id != $this->test_users['patient']) {
                $this->assertEquals(1, $message->read);
            }
        }
    }
    
    /**
     * Test accessing chat without permission
     */
    public function testAccessChatWithoutPermission()
    {
        // Create a new user who isn't part of the chat
        $unauthorized_user = $this->createUserWithRole('patient');
        wp_set_current_user($unauthorized_user);
        
        // Try to access chat messages
        $request = new WP_REST_Request('GET', "/{$this->namespace}/chats/{$this->test_chat->id}/messages");
        $response = $this->server->dispatch($request);
        
        // Check response status (should be 403 Forbidden)
        $this->assertEquals(403, $response->get_status());
    }
}
