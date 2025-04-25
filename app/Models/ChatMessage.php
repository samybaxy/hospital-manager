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
     * Get chat messages with pagination
     */
    public static function getChatMessages($chatId, $page = 1, $perPage = 50)
    {
        global $wpdb;
        $table = static::getTable();
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
        return $wpdb->query($wpdb->prepare("
            UPDATE " . static::getTable() . "
            SET `read` = 1
            WHERE chat_id = %d AND receiver_id = %d AND `read` = 0",
            $chatId,
            $userId
        ));
    }

    /**
     * Get table name
     */
    protected static function getTable()
    {
        return (new static)->table;
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
        $table = static::getTable();
        $query = "SELECT * FROM {$table} WHERE 1=1";

        foreach (static::$conditions as $condition) {
            $query .= $wpdb->prepare(" AND {$condition[0]} = %s", $condition[2]);
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
}
