<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class Notification extends PostModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $table = 'wp_hm_notifications';
    protected $fillable = [
        'user_id',
        'type',
        'title', 
        'message',
        'data',
        'read',
        'created_at'
    ];

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
}
