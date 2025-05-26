<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Chat;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;

class ChatTest extends TestCase
{
    /**
     * @var int Doctor user ID
     */
    protected $doctor_id;
    
    /**
     * @var int Patient user ID
     */
    protected $patient_id;
    
    /**
     * @var Doctor Test doctor
     */
    protected $doctor;
    
    /**
     * @var Patient Test patient
     */
    protected $patient;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->doctor_id = $this->createUserWithRole('doctor');
        $this->patient_id = $this->createUserWithRole('patient');
        
        // Create doctor and patient records
        $this->doctor = $this->createTestDoctor([
            'user_id' => $this->doctor_id
        ]);
        
        $this->patient = $this->createTestPatient([
            'user_id' => $this->patient_id
        ]);
    }
    
    /**
     * Create a test chat 
     *
     * @param array $overrides Override default chat data
     * @return Chat
     */
    protected function createTestChat(array $overrides = [])
    {
        $default_data = [
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'created_at' => current_time('mysql'),
            'last_message_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            return Chat::create($data);
        } catch (\Exception $e) {
            error_log('Failed to create test chat: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Test chat creation
     */
    public function testCreateChat()
    {
        $data = [
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'created_at' => current_time('mysql'),
            'last_message_at' => current_time('mysql')
        ];

        $chat = Chat::create($data);

        $this->assertInstanceOf(Chat::class, $chat);
        $this->assertEquals($this->doctor_id, $chat->doctor_id);
        $this->assertEquals($this->patient_id, $chat->patient_id);
        $this->assertNotNull($chat->created_at);
        $this->assertNotNull($chat->last_message_at);
    }

    /**
     * Test finding a chat by ID
     */
    public function testFindChat()
    {
        // Create a test chat
        $chat = $this->createTestChat([
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id
        ]);
        
        // Find the chat by ID
        $found_chat = Chat::find($chat->ID);
        
        $this->assertInstanceOf(Chat::class, $found_chat);
        $this->assertEquals($chat->ID, $found_chat->ID);
        $this->assertEquals($chat->doctor_id, $found_chat->doctor_id);
        $this->assertEquals($chat->patient_id, $found_chat->patient_id);
    }

    /**
     * Test getting chats for a doctor
     */
    public function testGetDoctorChats()
    {
        // Create multiple chats for this doctor
        for ($i = 0; $i < 3; $i++) {
            $patient_id = $this->createUserWithRole('patient');
            $this->createTestChat([
                'doctor_id' => $this->doctor_id,
                'patient_id' => $patient_id
            ]);
        }
        
        // Get chats for this doctor
        $chats = Chat::getUserChats($this->doctor_id, true);
        
        $this->assertNotEmpty($chats);
        foreach ($chats as $chat) {
            $this->assertEquals($this->doctor_id, $chat->doctor_id);
        }
    }

    /**
     * Test getting chats for a patient
     */
    public function testGetPatientChats()
    {
        // Create multiple chats for this patient
        for ($i = 0; $i < 2; $i++) {
            $doctor_id = $this->createUserWithRole('doctor');
            $this->createTestChat([
                'doctor_id' => $doctor_id,
                'patient_id' => $this->patient_id
            ]);
        }
        
        // Get chats for this patient
        $chats = Chat::getUserChats($this->patient_id, false);
        
        $this->assertNotEmpty($chats);
        foreach ($chats as $chat) {
            $this->assertEquals($this->patient_id, $chat->patient_id);
        }
    }

    /**
     * Test finding a chat between specific doctor and patient
     */
    public function testFindChatBetweenUsers()
    {
        // Create a test chat
        $this->createTestChat([
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id
        ]);
        
        // Find chat between these users
        $chat = Chat::where([
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id
        ])->first();
        
        $this->assertNotNull($chat);
        $this->assertEquals($this->doctor_id, $chat->doctor_id);
        $this->assertEquals($this->patient_id, $chat->patient_id);
    }

    /**
     * Test updating last message timestamp
     */
    public function testUpdateLastMessageTime()
    {
        // Create a test chat with initial timestamp
        $initial_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $chat = $this->createTestChat([
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'last_message_at' => $initial_time
        ]);
        
        // Update the last message time
        $new_time = current_time('mysql');
        $chat->last_message_at = $new_time;
        $chat->save();
        
        // Retrieve the chat again
        $updated_chat = Chat::find($chat->ID);
        
        $this->assertEquals($new_time, $updated_chat->last_message_at);
        $this->assertNotEquals($initial_time, $updated_chat->last_message_at);
    }

    /**
     * Test deleting a chat
     */
    public function testDeleteChat()
    {
        // Create a test chat
        $chat = $this->createTestChat([
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id
        ]);
        
        $chat_id = $chat->ID;
        
        // Delete the chat
        $chat->delete();
        
        // Try to find the deleted chat
        $deleted = Chat::find($chat_id);
        
        $this->assertNull($deleted);
    }
}
