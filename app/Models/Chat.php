<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Chat extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'ID';
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
        
        parent::__construct($attributes);
    }

    /**
     * Override the find method from FindTrait to handle our constructor's array requirement
     * 
     * @param mixed $ID Record ID.
     * @return object|null
     */
    public static function find($ID = 0)
    {
        global $wpdb;
        
        if (empty($ID)) {
            return null;
        }
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Fetch the chat record directly from the database.
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $ID);
        $chat_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$chat_data) {
            return null;
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
                LEFT JOIN {$messages_table} m ON m.chat_id = c.ID
                WHERE c.doctor_id = %d
                GROUP BY c.ID
                ORDER BY c.last_message_at DESC
            ", $userId, $userId), ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT c.*,
                    d.display_name as doctor_name,
                    COUNT(CASE WHEN m.read = 0 AND m.receiver_id = %d THEN 1 END) as unread_count
                FROM {$table} c
                JOIN {$wpdb->users} d ON d.ID = c.doctor_id
                LEFT JOIN {$messages_table} m ON m.chat_id = c.ID
                WHERE c.patient_id = %d
                GROUP BY c.ID
                ORDER BY c.last_message_at DESC
            ", $userId, $userId), ARRAY_A);
        }
        
        // Process results to ensure ID case consistency
        $chats = [];
        foreach ($results as $result) {            
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

        $attributes['ID'] = $wpdb->insert_id;
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
            ['ID' => $chatId]
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
            WHERE ID = %d AND (doctor_id = %d OR patient_id = %d)
        ", $chatId, $userId, $userId)) > 0;
    }

    /**
     * Get messages for this chat
     */
    public function messages()
    {
        return ChatMessage::where('chat_id', $this->ID)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Start a new query with conditions
     * 
     * @param string|array $column Column name or array of conditions
     * @param string|null $operator Operator (=, >, <, etc.) or value if third param is omitted
     * @param mixed|null $value Value to compare against
     * @return static
     */
    public static function where($column, $operator = null, $value = null)
    {
        $instance = new static();
        static::$conditions = [];
        
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                static::$conditions[] = [
                    'column' => $key,
                    'operator' => '=',
                    'value' => $val
                ];
            }
        } else {
            // If only two parameters are provided, assume the operator is '='
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            
            static::$conditions[] = [
                'column' => $column,
                'operator' => $operator,
                'value' => $value
            ];
        }
        
        return $instance;
    }
    
    /**
     * Add an AND condition to the query
     * 
     * @param string $column Column name
     * @param string $operator Operator (=, >, <, etc.) or value if third param is omitted
     * @param mixed|null $value Value to compare against
     * @return $this
     */
    public function andWhere($column, $operator = null, $value = null)
    {
        // If only two parameters are provided, assume the operator is '='
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        static::$conditions[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        
        return $this;
    }
    
    /**
     * Order the results
     * 
     * @param string $column Column to order by
     * @param string $direction Direction (ASC or DESC)
     * @return $this
     */
    public function orderBy($column, $direction = 'ASC')
    {
        static::$orderBy = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        
        return $this;
    }
    
    /**
     * Get the first result
     * 
     * @return static|null
     */
    public function first()
    {
        global $wpdb;
        
        // Get the table name
        $table = $this->getTable();
        
        // Build the query
        $query = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];
        
        foreach (static::$conditions as $condition) {
            $query .= " AND {$condition['column']} {$condition['operator']} %s";
            $params[] = $condition['value'];
        }
        
        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . static::$orderBy['column'] . " " . static::$orderBy['direction'];
        }
        
        $query .= " LIMIT 1";
        
        // Prepare and execute the query
        $prepared_query = !empty($params) ? $wpdb->prepare($query, $params) : $query;
        $result = $wpdb->get_row($prepared_query, ARRAY_A);
        
        // Reset static properties for future queries
        static::$conditions = [];
        static::$orderBy = [];
        
        return $result ? new static($result) : null;
    }
    
    /**
     * Get all results
     * 
     * @return array
     */
    public function get()
    {
        global $wpdb;
        
        // Get the table name
        $table = $this->getTable();
        
        // Build the query
        $query = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];
        
        foreach (static::$conditions as $condition) {
            $query .= " AND {$condition['column']} {$condition['operator']} %s";
            $params[] = $condition['value'];
        }
        
        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . static::$orderBy['column'] . " " . static::$orderBy['direction'];
        }
        
        // Prepare and execute the query
        $prepared_query = !empty($params) ? $wpdb->prepare($query, $params) : $query;
        $results = $wpdb->get_results($prepared_query, ARRAY_A);
        
        // Reset static properties for future queries
        static::$conditions = [];
        static::$orderBy = [];
        
        $chats = [];
        foreach ($results as $result) {
            $chats[] = new static($result);
        }
        
        return $chats;
    }

    /**
     * Save the current chat to the database
     * 
     * @return bool Success status
     */
    public function save()
    {
        global $wpdb;
        
        // Make sure we have the correct table name
        $table = $wpdb->prefix . $this->tableName;
        
        if (isset($this->attributes['ID']) && intval($this->attributes['ID']) > 0) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                [
                    'doctor_id' => $this->attributes['doctor_id'],
                    'patient_id' => $this->attributes['patient_id'],
                    'last_message_at' => $this->attributes['last_message_at'],
                    'updated_at' => current_time('mysql')
                ],
                ['ID' => $this->attributes['ID']],
                [
                    '%d', // doctor_id
                    '%d', // patient_id
                    '%s', // last_message_at
                    '%s', // updated_at
                ],
                ['%d'] // ID
            );
            
            return $result !== false;
        } else {
            // This should not happen as we use the create method for new records
            return false;
        }
    }
    
    /**
     * Delete the current chat from the database
     * 
     * @return bool Success status
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID']) || intval($this->attributes['ID']) <= 0) {
            return false;
        }
        
        // Make sure we have the correct table name
        $table = $wpdb->prefix . $this->tableName;
        
        // Delete the record
        $result = $wpdb->delete(
            $table,
            ['ID' => $this->attributes['ID']],
            ['%d']
        );
        
        return $result !== false;
    }
}
