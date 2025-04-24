<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Chat;
use HospitalManager\Models\ChatMessage;

class ChatMessageTest extends TestCase
{
    /**
     * @var int Sender user ID
     */
    protected $sender_id;
    
    /**
     * @var int Recipient user ID
     */
    protected $recipient_id;
    
    /**
     * @var Chat Test chat
     */
    protected $chat;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->sender_id = $this->createUserWithRole('doctor');
        $this->recipient_id = $this->createUserWithRole('patient');
        
        // Create a test chat
        $this->chat = $this->createTestChat([
            'doctor_id' => $this->sender_id,
            'patient_id' => $this->recipient_id,
            'created_at' => current_time('mysql'),
            'last_message_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Test chat message creation
     */
    public function testCreateChatMessage()
    {
        $data = [
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id,
            'message' => 'Hello, this is a test message.',
            'read' => 0,
            'created_at' => current_time('mysql')
        ];

        $message = ChatMessage::create($data);

        $this->assertInstanceOf(ChatMessage::class, $message);
        $this->assertEquals($this->chat->id, $message->chat_id);
        $this->assertEquals($this->sender_id, $message->sender_id);
        $this->assertEquals($this->recipient_id, $message->receiver_id);
        $this->assertEquals('Hello, this is a test message.', $message->message);
        $this->assertEquals(0, $message->read);
    }

    /**
     * Test finding a chat message by ID
     */
    public function testFindChatMessage()
    {
        // Create a test message
        $message = $this->createTestChatMessage([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id
        ]);
        
        // Find the message by ID
        $found_message = ChatMessage::find($message->id);
        
        $this->assertInstanceOf(ChatMessage::class, $found_message);
        $this->assertEquals($message->id, $found_message->id);
        $this->assertEquals($message->chat_id, $found_message->chat_id);
        $this->assertEquals($message->message, $found_message->message);
    }

    /**
     * Test getting chat messages for a specific chat
     */
    public function testGetChatMessages()
    {
        // Create multiple test messages
        for ($i = 0; $i < 3; $i++) {
            $this->createTestChatMessage([
                'chat_id' => $this->chat->id,
                'sender_id' => $this->sender_id,
                'receiver_id' => $this->recipient_id,
                'message' => "Test message $i"
            ]);
        }
        
        // Get messages for this chat
        $messages = ChatMessage::where('chat_id', $this->chat->id)->get();
        
        $this->assertNotEmpty($messages);
        $this->assertCount(3, $messages);
        foreach ($messages as $message) {
            $this->assertEquals($this->chat->id, $message->chat_id);
        }
    }

    /**
     * Test getting the sender user
     */
    public function testGetSender()
    {
        // Create a test message
        $message = $this->createTestChatMessage([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id
        ]);
        
        // Get the sender
        $sender = $message->getSender();
        
        $this->assertNotNull($sender);
        $this->assertEquals($this->sender_id, $sender->ID);
    }

    /**
     * Test getting the receiver user
     */
    public function testGetReceiver()
    {
        // Create a test message
        $message = $this->createTestChatMessage([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id
        ]);
        
        // Get the receiver
        $receiver = $message->getReceiver();
        
        $this->assertNotNull($receiver);
        $this->assertEquals($this->recipient_id, $receiver->ID);
    }

    /**
     * Test marking a message as read
     */
    public function testMarkMessageAsRead()
    {
        // Create an unread message
        $message = $this->createTestChatMessage([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id,
            'read' => 0
        ]);
        
        // Mark as read
        $message->read = 1;
        $message->save();
        
        // Verify it was updated
        $updated_message = ChatMessage::find($message->id);
        $this->assertEquals(1, $updated_message->read);
    }
    
    /**
     * Test getting unread messages for a recipient
     */
    public function testGetUnreadMessages()
    {
        // Create some read and unread messages
        $this->createTestChatMessage([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id,
            'read' => 1 // Read
        ]);
        
        $this->createTestChatMessage([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id,
            'read' => 0 // Unread
        ]);
        
        // Get unread messages
        $unread_messages = ChatMessage::where('receiver_id', $this->recipient_id)
            ->where('read', 0)
            ->get();
        
        $this->assertNotEmpty($unread_messages);
        $this->assertCount(1, $unread_messages);
        foreach ($unread_messages as $message) {
            $this->assertEquals(0, $message->read);
            $this->assertEquals($this->recipient_id, $message->receiver_id);
        }
    }

    /**
     * Test getting messages ordered by creation time
     */
    public function testGetMessagesOrdered()
    {
        // Create messages with different timestamps
        $timestamps = [
            date('Y-m-d H:i:s', strtotime('-3 days')),
            date('Y-m-d H:i:s', strtotime('-1 day')),
            current_time('mysql')
        ];
        
        foreach ($timestamps as $index => $timestamp) {
            $this->createTestChatMessage([
                'chat_id' => $this->chat->id,
                'sender_id' => $this->sender_id,
                'receiver_id' => $this->recipient_id,
                'message' => "Message $index",
                'created_at' => $timestamp
            ]);
        }
        
        // Get messages in ascending order
        $messages = ChatMessage::where('chat_id', $this->chat->id)
            ->orderBy('created_at', 'ASC')
            ->get();
        
        $this->assertCount(3, $messages);
        // Verify order
        $this->assertEquals('Message 0', $messages[0]->message);
        $this->assertEquals('Message 2', $messages[2]->message);
    }

    /**
     * Test deleting a chat message
     */
    public function testDeleteChatMessage()
    {
        // Create a test message
        $message = $this->createTestChatMessage([
            'chat_id' => $this->chat->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->recipient_id
        ]);
        
        $message_id = $message->id;
        
        // Delete the message
        $message->delete();
        
        // Try to find the deleted message
        $deleted = ChatMessage::find($message_id);
        
        $this->assertNull($deleted);
    }
}
