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

    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        // Ensure both lowercase 'id' and uppercase 'ID' exist for consistency
        if (isset($attributes['id']) && !isset($attributes['ID'])) {
            $attributes['ID'] = $attributes['id'];
        } elseif (isset($attributes['ID']) && !isset($attributes['id'])) {
            $attributes['id'] = $attributes['ID'];
        }
        
        parent::__construct($attributes);
    }

    /**
     * Override the find method from FindTrait to handle our constructor's array requirement
     * 
     * @param mixed $id Record ID.
     * @return object|null
     */
    public static function find($id = 0)
    {
        global $wpdb;
        
        if (empty($id)) {
            return null;
        }
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Fetch the chat record directly from the database.
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $chat_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$chat_data) {
            return null;
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility.
        if (isset($chat_data['id']) && !isset($chat_data['ID'])) {
            $chat_data['ID'] = $chat_data['id'];
        }
        
        // Create a new chat instance with the fetched data.
        return new self($chat_data);
    }

    /**
     * Get all chats for a user
     */
    public static function getUserChats($userId, $isDoctor = false)
    {
        global $wpdb;
        $table = (new static)->table;
        $messages_table = $wpdb->prefix . 'hm_chat_messages';

        if ($isDoctor) {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT c.*,
                    p.display_name as patient_name,
                    COUNT(CASE WHEN m.read = 0 AND m.receiver_id = %d THEN 1 END) as unread_count
                FROM {$table} c
                JOIN {$wpdb->users} p ON p.ID = c.patient_id
                LEFT JOIN {$messages_table} m ON m.chat_id = c.id
                WHERE c.doctor_id = %d
                GROUP BY c.id
                ORDER BY c.last_message_at DESC
            ", $userId, $userId), ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT c.*,
                    d.display_name as doctor_name,
                    COUNT(CASE WHEN m.read = 0 AND m.receiver_id = %d THEN 1 END) as unread_count
                FROM {$table} c
                JOIN {$wpdb->users} d ON d.ID = c.doctor_id
                LEFT JOIN {$messages_table} m ON m.chat_id = c.id
                WHERE c.patient_id = %d
                GROUP BY c.id
                ORDER BY c.last_message_at DESC
            ", $userId, $userId), ARRAY_A);
        }
        
        // Process results to ensure ID case consistency
        $chats = [];
        foreach ($results as $result) {
            // Make sure both lowercase 'id' and uppercase 'ID' exist
            if (isset($result['id']) && !isset($result['ID'])) {
                $result['ID'] = $result['id'];
            } elseif (isset($result['ID']) && !isset($result['id'])) {
                $result['id'] = $result['ID'];
            }
            
            $chats[] = new static($result);
        }
        
        return $chats;
    }

    /**
     * Create a new chat
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
        $result = $wpdb->insert(
            $table,
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
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
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
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
        return $wpdb->update(
            $table,
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
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE id = %d AND (doctor_id = %d OR patient_id = %d)
        ", $chatId, $userId, $userId)) > 0;
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
