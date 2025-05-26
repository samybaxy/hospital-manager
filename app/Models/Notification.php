<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Notification extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'ID';
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
        $ID = $wpdb->insert_id;
        
        // Return a new instance with the created data
        $created_data = array_merge(['ID' => $ID], $fillable_data);
        return new static($created_data);
    }

    /**
     * Find a notification by ID
     * 
     * @param int $ID The notification ID
     * @return static|null
     */
    public static function find($ID = 0)
    {
        global $wpdb;
        
        if (empty($ID)) {
            return null;
        }
        
        // Get the table name
        $instance = new static();
        $table = $instance->table;
        
        // Query the database
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d LIMIT 1", $ID);
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
        $ID = isset($this->attributes[$primary_key]) ? $this->attributes[$primary_key] : null;
        
        // If we have an ID, update the record, otherwise insert a new one
        if (!empty($ID)) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                $this->attributes,
                array($primary_key => $ID)
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
        
        if (!isset($this->attributes['ID']) || intval($this->attributes['ID']) <= 0) {
            return false;
        }
        
        // Make sure we have the table name
        if (empty($this->table)) {
            $this->table = $wpdb->prefix . $this->tableName;
        }
        
        // Delete the record
        $result = $wpdb->delete(
            $this->table,
            ['ID' => $this->attributes['ID']],
            ['%d']
        );
        
        return $result !== false;
    }
}
