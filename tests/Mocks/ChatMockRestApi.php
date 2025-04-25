<?php
/**
 * Mock implementation of Chat REST API for testing
 */

namespace HospitalManager\Tests\Mocks;

use HospitalManager\Models\Chat;
use HospitalManager\Models\ChatMessage;
use HospitalManager\Models\Patient;

/**
 * Mock Chat REST API class for tests
 */
class ChatMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register chat REST API routes
     */
    public static function registerRoutes()
    {
        // GET /chats - Get all chats for the current user
        register_rest_route(self::$namespace, '/chats', [
            'methods' => 'GET',
            'callback' => [self::class, 'getUserChats'],
            'permission_callback' => [self::class, 'checkLoggedInPermission'],
        ]);
        
        // POST /chats/start - Start a new chat
        register_rest_route(self::$namespace, '/chats/start', [
            'methods' => 'POST',
            'callback' => [self::class, 'startChat'],
            'permission_callback' => [self::class, 'checkPatientPermission'],
            'args' => [
                'doctor_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'initial_message' => [
                    'required' => true,
                ]
            ],
        ]);
        
        // GET /chats/{id}/messages - Get messages for a specific chat
        register_rest_route(self::$namespace, '/chats/(?P<id>\d+)/messages', [
            'methods' => 'GET',
            'callback' => [self::class, 'getChatMessages'],
            'permission_callback' => [self::class, 'checkChatAccessPermission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'page' => [
                    'default' => 1,
                ],
                'per_page' => [
                    'default' => 20,
                ],
            ],
        ]);
        
        // POST /chats/{id}/messages - Send a new message
        register_rest_route(self::$namespace, '/chats/(?P<id>\d+)/messages', [
            'methods' => 'POST',
            'callback' => [self::class, 'sendMessage'],
            'permission_callback' => [self::class, 'checkChatAccessPermission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'message' => [
                    'required' => true,
                ],
            ],
        ]);
        
        // PUT /chats/{id}/read - Mark chat messages as read
        register_rest_route(self::$namespace, '/chats/(?P<id>\d+)/read', [
            'methods' => 'PUT',
            'callback' => [self::class, 'markAsRead'],
            'permission_callback' => [self::class, 'checkChatAccessPermission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);
    }

    /**
     * Check if user is logged in
     */
    public static function checkLoggedInPermission()
    {
        return is_user_logged_in();
    }

    /**
     * Check if user is a patient
     */
    public static function checkPatientPermission()
    {
        return current_user_can('patient');
    }

    /**
     * Check if user has access to a specific chat
     */
    public static function checkChatAccessPermission($request)
    {
        $chat_id = $request['id'];
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        $chat = Chat::find($chat_id);
        if (!$chat) {
            return false;
        }
        
        // Get patient ID for the current user if they're a patient
        $patient_id = null;
        if (current_user_can('patient')) {
            // Query to find patient by user_id
            global $wpdb;
            $table = $wpdb->prefix . 'hm_patients';
            $result = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id),
                ARRAY_A
            );
            
            if ($result) {
                $patient = new Patient($result);
                $patient_id = $patient->id;
            }
        }
        
        // Check if the user is the doctor or patient for this chat
        return ($chat->doctor_id == $user_id) || ($chat->patient_id == $patient_id);
    }

    /**
     * Get all chats for the current user
     */
    public static function getUserChats()
    {
        $user_id = get_current_user_id();
        $chats = [];
        
        global $wpdb;
        $chat_table = $wpdb->prefix . 'hm_chats';
        
        if (current_user_can('doctor')) {
            // Doctor's chats
            $results = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $chat_table WHERE doctor_id = %d ORDER BY updated_at DESC", $user_id),
                ARRAY_A
            );
            
            foreach ($results as $result) {
                $chats[] = new Chat($result);
            }
        } elseif (current_user_can('patient')) {
            // Get patient record for the current user
            $patient_table = $wpdb->prefix . 'hm_patients';
            $patient_result = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $patient_table WHERE user_id = %d", $user_id),
                ARRAY_A
            );
            
            if ($patient_result) {
                $patient = new Patient($patient_result);
                
                // Get patient's chats
                $results = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM $chat_table WHERE patient_id = %d ORDER BY updated_at DESC", $patient->id),
                    ARRAY_A
                );
                
                foreach ($results as $result) {
                    $chats[] = new Chat($result);
                }
            }
        }
        
        // Process chats for response
        $processedChats = [];
        foreach ($chats as $chat) {
            $processedChats[] = self::processChatForResponse($chat);
        }
        
        return rest_ensure_response($processedChats);
    }

    /**
     * Start a new chat
     */
    public static function startChat($request)
    {
        $user_id = get_current_user_id();
        $doctor_id = $request['doctor_id'];
        $initial_message = $request['initial_message'];
        
        global $wpdb;
        
        // Get patient record for current user
        $patient_table = $wpdb->prefix . 'hm_patients';
        $patient_result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $patient_table WHERE user_id = %d", $user_id),
            ARRAY_A
        );
        
        if (!$patient_result) {
            return new \WP_Error('patient_not_found', 'Patient record not found for current user', ['status' => 404]);
        }
        
        $patient = new Patient($patient_result);
        
        // Check if a chat already exists between these users
        $chat_table = $wpdb->prefix . 'hm_chats';
        $existing_chat_result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $chat_table WHERE patient_id = %d AND doctor_id = %d AND status = 'active'",
                $patient->id, $doctor_id
            ),
            ARRAY_A
        );
        
        if ($existing_chat_result) {
            $chat = new Chat($existing_chat_result);
        } else {
            // Create a new chat
            $chat = Chat::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor_id,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
        }
        
        // Add the initial message
        ChatMessage::create([
            'chat_id' => $chat->id,
            'sender_id' => $user_id,
            'message' => $initial_message,
            'read' => 0,
            'created_at' => current_time('mysql')
        ]);
        
        // Update the chat's updated_at timestamp
        global $wpdb;
        $chat_table = $wpdb->prefix . 'hm_chats';
        $wpdb->update(
            $chat_table,
            ['updated_at' => current_time('mysql')],
            ['id' => $chat->id]
        );
        
        return rest_ensure_response(self::processChatForResponse($chat));
    }

    /**
     * Get messages for a specific chat
     */
    public static function getChatMessages($request)
    {
        $chat_id = $request['id'];
        $page = isset($request['page']) ? max(1, intval($request['page'])) : 1;
        $per_page = isset($request['per_page']) ? max(1, intval($request['per_page'])) : 20;
        
        // Get messages from the ChatMessage model
        $messagesObj = ChatMessage::getChatMessages($chat_id, $page, $per_page);
        
        // Process messages for response
        $processedMessages = [];
        if (isset($messagesObj->data) && is_array($messagesObj->data)) {
            foreach ($messagesObj->data as $message) {
                $processedMessages[] = self::processMessageForResponse($message);
            }
        }
        
        return rest_ensure_response($processedMessages);
    }

    /**
     * Send a new message
     */
    public static function sendMessage($request)
    {
        $chat_id = $request['id'];
        $message_text = $request['message'];
        $user_id = get_current_user_id();
        
        // Create the new message
        $message = ChatMessage::create([
            'chat_id' => $chat_id,
            'sender_id' => $user_id,
            'message' => $message_text,
            'read' => 0,
            'created_at' => current_time('mysql')
        ]);
        
        // Update the chat's updated_at timestamp
        $chat = Chat::find($chat_id);
        if ($chat) {
            global $wpdb;
            $chat_table = $wpdb->prefix . 'hm_chats';
            $wpdb->update(
                $chat_table,
                ['updated_at' => current_time('mysql')],
                ['id' => $chat_id]
            );
        }
        
        return rest_ensure_response(self::processMessageForResponse($message));
    }

    /**
     * Mark chat messages as read
     */
    public static function markAsRead($request)
    {
        $chat_id = $request['id'];
        $user_id = get_current_user_id();
        
        // Mark all messages from the other user as read
        global $wpdb;
        $message_table = $wpdb->prefix . 'hm_chat_messages';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $message_table SET `read` = 1 WHERE chat_id = %d AND sender_id != %d AND `read` = 0",
                $chat_id, $user_id
            )
        );
        
        return rest_ensure_response(['success' => true]);
    }

    /**
     * Process a chat object for API response
     */
    private static function processChatForResponse($chat)
    {
        // Use reflection to access protected attributes if needed
        $reflection = new \ReflectionObject($chat);
        if ($reflection->hasProperty('attributes')) {
            $attributes = $reflection->getProperty('attributes');
            $attributes->setAccessible(true);
            $attr_values = $attributes->getValue($chat);
            
            // Ensure both id and ID exist
            if (isset($attr_values['id']) && !isset($attr_values['ID'])) {
                $attr_values['ID'] = $attr_values['id'];
            } elseif (isset($attr_values['ID']) && !isset($attr_values['id'])) {
                $attr_values['id'] = $attr_values['ID'];
            }
            
            return (object)$attr_values;
        }
        
        // If not using protected attributes, ensure both id and ID exist
        if (is_object($chat)) {
            if (isset($chat->id) && !isset($chat->ID)) {
                $chat->ID = $chat->id;
            } elseif (isset($chat->ID) && !isset($chat->id)) {
                $chat->id = $chat->ID;
            }
        }
        
        // Fallback if reflection doesn't work
        return $chat;
    }

    /**
     * Process a message object for API response
     */
    private static function processMessageForResponse($message)
    {
        // If message is already an array, handle it directly
        if (is_array($message)) {
            // Ensure both id and ID exist
            if (isset($message['id']) && !isset($message['ID'])) {
                $message['ID'] = $message['id'];
            } elseif (isset($message['ID']) && !isset($message['id'])) {
                $message['id'] = $message['ID'];
            }
            
            return (object)$message;
        }
        
        // If it's an object, use reflection to access protected attributes
        if (is_object($message)) {
            $reflection = new \ReflectionObject($message);
            if ($reflection->hasProperty('attributes')) {
                $attributes = $reflection->getProperty('attributes');
                $attributes->setAccessible(true);
                $attr_values = $attributes->getValue($message);
                
                // Ensure both id and ID exist
                if (isset($attr_values['id']) && !isset($attr_values['ID'])) {
                    $attr_values['ID'] = $attr_values['id'];
                } elseif (isset($attr_values['ID']) && !isset($attr_values['id'])) {
                    $attr_values['id'] = $attr_values['ID'];
                }
                
                // Make sure 'message' property is included
                if (isset($message->message)) {
                    $attr_values['message'] = $message->message;
                }
                
                if (isset($message->sender_id)) {
                    $attr_values['sender_id'] = $message->sender_id;
                }
                
                if (isset($message->read)) {
                    $attr_values['read'] = $message->read;
                }
                
                return (object)$attr_values;
            }
            
            // If not using protected attributes, ensure both id and ID exist
            if (isset($message->id) && !isset($message->ID)) {
                $message->ID = $message->id;
            } elseif (isset($message->ID) && !isset($message->id)) {
                $message->id = $message->ID;
            }
            
            // Create a simple stdClass with all the needed properties
            $result = new \stdClass();
            $result->id = $message->id ?? null;
            $result->ID = $message->ID ?? null;
            $result->message = $message->message ?? null;
            $result->sender_id = $message->sender_id ?? null;
            $result->read = $message->read ?? null;
            
            return $result;
        }
        
        // Return the message
        return $message;
    }
}