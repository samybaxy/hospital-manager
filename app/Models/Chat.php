<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Chat extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $tableName = 'hm_chats';
    
    protected $fillable = [
        'doctor_id',
        'patient_id',
        'created_at',
        'last_message_at'
    ];

    protected static $conditions = [];
    protected static $orderBy = [];

    /**
     * Get all chats for a user
     */
    public static function getUserChats($userId, $isDoctor = false)
    {
        global $wpdb;
        $table = (new static)->table;
        $messages_table = $wpdb->prefix . 'hm_chat_messages';

        if ($isDoctor) {
            return $wpdb->get_results($wpdb->prepare("
                SELECT c.*,
                    p.display_name as patient_name,
                    COUNT(CASE WHEN m.read = 0 AND m.receiver_id = %d THEN 1 END) as unread_count
                FROM {$table} c
                JOIN {$wpdb->users} p ON p.ID = c.patient_id
                LEFT JOIN {$messages_table} m ON m.chat_id = c.id
                WHERE c.doctor_id = %d
                GROUP BY c.id
                ORDER BY c.last_message_at DESC
            ", $userId, $userId));
        }

        return $wpdb->get_results($wpdb->prepare("
            SELECT c.*,
                d.display_name as doctor_name,
                COUNT(CASE WHEN m.read = 0 AND m.receiver_id = %d THEN 1 END) as unread_count
            FROM {$table} c
            JOIN {$wpdb->users} d ON d.ID = c.doctor_id
            LEFT JOIN {$messages_table} m ON m.chat_id = c.id
            WHERE c.patient_id = %d
            GROUP BY c.id
            ORDER BY c.last_message_at DESC
        ", $userId, $userId));
    }

    /**
     * Create a new chat
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        
        $result = $wpdb->insert(
            static::getTable(),
            $attributes,
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes)
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }

        $attributes['id'] = $wpdb->insert_id;
        return new static($attributes);
    }

    /**
     * Find chat by doctor and patient IDs
     */
    public static function findByUsers($doctorId, $patientId)
    {
        global $wpdb;
        $table = static::getTable();
        
        $chat = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$table}
            WHERE doctor_id = %d AND patient_id = %d
        ", $doctorId, $patientId));

        return $chat ? new static((array)$chat) : null;
    }

    /**
     * Update last message time
     */
    public static function updateLastMessageTime($chatId)
    {
        global $wpdb;
        return $wpdb->update(
            static::getTable(),
            ['last_message_at' => current_time('mysql')],
            ['id' => $chatId]
        );
    }

    /**
     * Check if user can access chat
     */
    public static function canAccess($chatId, $userId)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM " . static::getTable() . "
            WHERE id = %d AND (doctor_id = %d OR patient_id = %d)
        ", $chatId, $userId, $userId)) > 0;
    }

    /**
     * Get table name
     */
    protected static function getTable()
    {
        return (new static)->table;
    }

    /**
     * Get messages for this chat
     */
    public function messages()
    {
        return ChatMessage::where('chat_id', $this->id)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
