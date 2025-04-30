<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class ChatMessage extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $tableName = 'hm_chat_messages';
    
    protected $fillable = [
        'chat_id',
        'sender_id',
        'receiver_id',
        'message',
        'read',
        'created_at'
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
        $instance = new static();
        $table = $instance->table;
        
        // Fetch the chat message record directly from the database.
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $message_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$message_data) {
            return null;
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility.
        if (isset($message_data['id']) && !isset($message_data['ID'])) {
            $message_data['ID'] = $message_data['id'];
        }
        
        // Create a new chat message instance with the fetched data.
        return new static($message_data);
    }

    /**
     * Get the sender user
     */
    public function getSender()
    {
        return get_user_by('ID', $this->sender_id);
    }

    /**
     * Get the receiver user
     */
    public function getReceiver()
    {
        return get_user_by('ID', $this->receiver_id);
    }

    /**
     * Create a new message
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
        $attributes['ID'] = $wpdb->insert_id; // Ensure both lowercase and uppercase ID are set
        return new static($attributes);
    }

    /**
     * Get chat messages with pagination
     */
    public static function getChatMessages($chatId, $page = 1, $perPage = 50)
    {
        global $wpdb;
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
        $offset = ($page - 1) * $perPage;

        $query = $wpdb->prepare("
            SELECT SQL_CALC_FOUND_ROWS *
            FROM {$table}
            WHERE chat_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $chatId,
            $perPage,
            $offset
        );

        $results = $wpdb->get_results($query);
        $total = $wpdb->get_var('SELECT FOUND_ROWS()');

        $items = array_map(function($item) {
            $model = new static((array)$item);
            $model->sender = $model->getSender();
            $model->receiver = $model->getReceiver();
            return $model;
        }, $results);

        return (object)[
            'data' => $items,
            'meta' => [
                'current_page' => (int)$page,
                'last_page' => ceil($total / $perPage),
                'per_page' => (int)$perPage,
                'total' => (int)$total
            ]
        ];
    }

    /**
     * Mark messages as read
     */
    public static function markAsRead($chatId, $userId)
    {
        global $wpdb;
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
        return $wpdb->query($wpdb->prepare("
            UPDATE {$table}
            SET `read` = 1
            WHERE chat_id = %d AND receiver_id = %d AND `read` = 0",
            $chatId,
            $userId
        ));
    }

    /**
     * Query builder: where clause
     */
    public static function where($column, $value)
    {
        static::$conditions[] = [$column, '=', $value];
        return new static();
    }

    /**
     * Query builder: order by
     */
    public static function orderBy($column, $direction = 'ASC')
    {
        static::$orderBy[] = [$column, strtoupper($direction)];
        return new static();
    }

    /**
     * Execute query and get results
     */
    public static function get()
    {
        global $wpdb;
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
        $query = "SELECT * FROM {$table} WHERE 1=1";

        foreach (static::$conditions as $condition) {
            $query .= $wpdb->prepare(" AND `{$condition[0]}` = %s", $condition[2]);
        }

        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, static::$orderBy));
        }

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];

        $results = $wpdb->get_results($query);
        return array_map(function($item) {
            $model = new static((array)$item);
            $model->sender = $model->getSender();
            $model->receiver = $model->getReceiver();
            return $model;
        }, $results);
    }

    /**
     * Get the associated chat
     */
    public function chat()
    {
        return Chat::find($this->chat_id);
    }

    /**
     * Save the current message to the database
     * 
     * @return bool Success status
     */
    public function save()
    {
        global $wpdb;
        
        // Make sure we have the correct table name
        $table = $wpdb->prefix . $this->tableName;
        
        if (isset($this->attributes['id']) && intval($this->attributes['id']) > 0) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                [
                    'chat_id' => $this->attributes['chat_id'],
                    'sender_id' => $this->attributes['sender_id'],
                    'receiver_id' => $this->attributes['receiver_id'],
                    'message' => $this->attributes['message'],
                    'read' => $this->attributes['read'],
                    'created_at' => $this->attributes['created_at'],
                ],
                ['id' => $this->attributes['id']],
                [
                    '%d', // chat_id
                    '%d', // sender_id
                    '%d', // receiver_id
                    '%s', // message
                    '%d', // read
                    '%s', // created_at
                ],
                ['%d'] // id
            );
            
            return $result !== false;
        } else {
            // This should not happen as we use the create method for new records
            return false;
        }
    }
    
    /**
     * Delete the current message from the database
     * 
     * @return bool Success status
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['id']) || intval($this->attributes['id']) <= 0) {
            return false;
        }
        
        // Make sure we have the correct table name
        $table = $wpdb->prefix . $this->tableName;
        
        // Delete the record
        $result = $wpdb->delete(
            $table,
            ['id' => $this->attributes['id']],
            ['%d']
        );
        
        return $result !== false;
    }
}
