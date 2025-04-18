<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\ChatMessage;
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
        global $wpdb;
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $chats_table = $wpdb->prefix . 'hm_chats';
        $messages_table = $wpdb->prefix . 'hm_chat_messages';

        // Different queries for doctors and patients
        if (in_array('doctor', $user->roles)) {
            $chats = $wpdb->get_results($wpdb->prepare("
                SELECT c.*,
                    p.display_name as patient_name,
                    COUNT(CASE WHEN m.read = 0 AND m.receiver_id = %d THEN 1 END) as unread_count
                FROM {$chats_table} c
                JOIN {$wpdb->users} p ON p.ID = c.patient_id
                LEFT JOIN {$messages_table} m ON m.chat_id = c.id
                WHERE c.doctor_id = %d
                GROUP BY c.id
                ORDER BY c.last_message_at DESC
            ", $user_id, $user_id));
        } else {
            $chats = $wpdb->get_results($wpdb->prepare("
                SELECT c.*,
                    d.display_name as doctor_name,
                    COUNT(CASE WHEN m.read = 0 AND m.receiver_id = %d THEN 1 END) as unread_count
                FROM {$chats_table} c
                JOIN {$wpdb->users} d ON d.ID = c.doctor_id
                LEFT JOIN {$messages_table} m ON m.chat_id = c.id
                WHERE c.patient_id = %d
                GROUP BY c.id
                ORDER BY c.last_message_at DESC
            ", $user_id, $user_id));
        }

        return new WP_REST_Response($chats);
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

        $messages = ChatMessage::where('chat_id', $chat_id)
            ->orderBy('created_at', 'DESC')
            ->paginate($per_page, ['*'], 'page', $page);

        return new WP_REST_Response([
            'data' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total()
            ]
        ]);
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

        global $wpdb;
        $chat = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}hm_chats WHERE id = %d
        ", $chat_id));

        $receiver_id = $chat->doctor_id == $user_id ? $chat->patient_id : $chat->doctor_id;

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'hm_chat_messages',
            [
                'chat_id' => $chat_id,
                'sender_id' => $user_id,
                'receiver_id' => $receiver_id,
                'message' => $message_text,
                'read' => 0,
                'created_at' => current_time('mysql')
            ]
        );
        
        $message_id = $wpdb->insert_id;
        $message = (object)[
            'id' => $message_id,
            'chat_id' => $chat_id,
            'sender_id' => $user_id,
            'receiver_id' => $receiver_id,
            'message' => $message_text,
            'created_at' => current_time('mysql')
        ];

        // Update last_message_at in chat
        $wpdb->update(
            $wpdb->prefix . 'hm_chats',
            ['last_message_at' => current_time('mysql')],
            ['id' => $chat_id]
        );

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
        global $wpdb;
        $patient_id = get_current_user_id();
        $doctor_id = $request->get_param('doctor_id');

        // Check if chat already exists
        $existing_chat = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}hm_chats
            WHERE doctor_id = %d AND patient_id = %d
        ", $doctor_id, $patient_id));

        if ($existing_chat) {
            return new WP_REST_Response($existing_chat);
        }

        // Create new chat
        $wpdb->insert(
            $wpdb->prefix . 'hm_chats',
            [
                'doctor_id' => $doctor_id,
                'patient_id' => $patient_id,
                'created_at' => current_time('mysql'),
                'last_message_at' => current_time('mysql')
            ]
        );

        $chat_id = $wpdb->insert_id;

        return new WP_REST_Response([
            'id' => $chat_id,
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

        // Mark all messages as read
        ChatMessage::where('chat_id', $chat_id)
            ->where('receiver_id', $user_id)
            ->where('read', 0)
            ->update(['read' => 1]);

        return new WP_REST_Response(['success' => true]);
    }

    private function can_access_chat($chat_id, $user_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}hm_chats
            WHERE id = %d AND (doctor_id = %d OR patient_id = %d)
        ", $chat_id, $user_id, $user_id)) > 0;
    }
}
