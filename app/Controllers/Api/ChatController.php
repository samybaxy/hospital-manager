<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\ChatMessage;
use HospitalManager\Models\Chat;
use HospitalManager\Services\WebSocketService;

class ChatController extends BaseController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/chats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_chats'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/chats/(?P<id>\d+)/messages', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_messages'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'send_message'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/chats/start', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'start_chat'],
                'permission_callback' => function() {
                    return current_user_can('patient');
                }
            ]
        ]);

        register_rest_route($this->namespace, '/chats/(?P<id>\d+)/read', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'mark_as_read'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);
    }

    public function get_chats()
    {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        return new WP_REST_Response(Chat::getUserChats($user_id, in_array('doctor', $user->roles)));
    }

    public function get_messages($request)
    {
        $chat_id = $request->get_param('id');
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 50;
        $user_id = get_current_user_id();

        // Verify chat access
        if (!$this->can_access_chat($chat_id, $user_id)) {
            return new WP_Error(
                'chat_access_denied',
                'You do not have access to this chat',
                ['status' => 403]
            );
        }

        $messages = ChatMessage::getChatMessages($chat_id, $page, $per_page);
        return new WP_REST_Response($messages);
    }

    public function send_message($request)
    {
        $chat_id = $request->get_param('id');
        $message_text = $request->get_param('message');
        $user_id = get_current_user_id();

        // Verify chat access
        if (!$this->can_access_chat($chat_id, $user_id)) {
            return new WP_Error(
                'chat_access_denied',
                'You do not have access to this chat',
                ['status' => 403]
            );
        }

        $chat = Chat::find($chat_id);
        $receiver_id = $chat->doctor_id == $user_id ? $chat->patient_id : $chat->doctor_id;

        $message = ChatMessage::create([
            'chat_id' => $chat_id,
            'sender_id' => $user_id,
            'receiver_id' => $receiver_id,
            'message' => $message_text,
            'read' => 0,
            'created_at' => current_time('mysql')
        ]);

        // Update last_message_at in chat
        Chat::updateLastMessageTime($chat_id);

        // Send real-time notification through WebSocket
        WebSocketService::sendMessage('chat', [
            'type' => 'new_message',
            'chat_id' => $chat_id,
            'message' => $message
        ], $receiver_id);

        return new WP_REST_Response($message, 201);
    }

    public function start_chat($request)
    {
        $patient_id = get_current_user_id();
        $doctor_id = $request->get_param('doctor_id');

        $existing_chat = Chat::findByUsers($doctor_id, $patient_id);
        if ($existing_chat) {
            return new WP_REST_Response($existing_chat);
        }

        $chat = Chat::create([
            'doctor_id' => $doctor_id,
            'patient_id' => $patient_id,
            'created_at' => current_time('mysql'),
            'last_message_at' => current_time('mysql')
        ]);

        return new WP_REST_Response([
            'id' => $chat->id,
            'doctor_id' => $doctor_id,
            'patient_id' => $patient_id
        ], 201);
    }

    public function mark_as_read($request)
    {
        $chat_id = $request->get_param('id');
        $user_id = get_current_user_id();

        // Verify chat access
        if (!$this->can_access_chat($chat_id, $user_id)) {
            return new WP_Error(
                'chat_access_denied',
                'You do not have access to this chat',
                ['status' => 403]
            );
        }

        ChatMessage::markAsRead($chat_id, $user_id);
        return new WP_REST_Response(['success' => true]);
    }

    private function can_access_chat($chat_id, $user_id)
    {
        return Chat::canAccess($chat_id, $user_id);
    }
}
