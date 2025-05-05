<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Notification extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $tableName = 'hm_notifications';
    protected $fillable = [
        'user_id',
        'type',
        'title', 
        'message',
        'read',
        'created_at'
    ];

    /**
     * Constructor
     * 
     * @param array $attributes Initial attributes
     */
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
     * Get the user associated with this notification
     */
    public function getUser()
    {
        return get_user_by('ID', $this->user_id);
    }
    
    /**
     * Create a new notification record in the database
     *
     * @param array $data Notification data to create
     * @return Notification The newly created notification instance
     */
    public static function create(array $data)
    {
        global $wpdb;
        
        // Set created_at timestamp if not provided
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        // Filter data to only include fillable fields
        $instance = new static();
        $fillable_data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Insert the record
        $wpdb->insert(
            $instance->table,
            $fillable_data
        );
        
        // Get the newly created ID
        $id = $wpdb->insert_id;
        
        // Return a new instance with the created data
        $created_data = array_merge(['id' => $id], $fillable_data);
        return new static($created_data);
    }

    /**
     * Find a notification by ID
     * 
     * @param int $id The notification ID
     * @return static|null
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
        
        // Query the database
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);
        $notification_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$notification_data) {
            return null;
        }
        
        // Create a new instance with the fetched data
        return new static($notification_data);
    }

    /**
     * Save the notification to the database
     * 
     * @return bool Success status
     */
    public function save()
    {
        global $wpdb;
    
        // Make sure we have a table name
        $table = $wpdb->prefix . $this->tableName;
        
        // Get the primary key and value
        $primary_key = $this->primaryKey;
        $id = isset($this->attributes[$primary_key]) ? $this->attributes[$primary_key] : null;
        
        // If we have an ID, update the record, otherwise insert a new one
        if (!empty($id)) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                $this->attributes,
                array($primary_key => $id)
            );
            
            return $result !== false;
        } else {
            // Insert new record
            $result = $wpdb->insert($table, $this->attributes);
            
            if ($result) {
                // Set the ID on the instance
                $this->attributes[$primary_key] = $wpdb->insert_id;
                return true;
            }
            
            return false;
        }
    }

    /**
     * Delete the notification from the database
     * 
     * @return bool Success status
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['id']) || intval($this->attributes['id']) <= 0) {
            return false;
        }
        
        // Make sure we have the table name
        if (empty($this->table)) {
            $this->table = $wpdb->prefix . $this->tableName;
        }
        
        // Delete the record
        $result = $wpdb->delete(
            $this->table,
            ['id' => $this->attributes['id']],
            ['%d']
        );
        
        return $result !== false;
    }
}
