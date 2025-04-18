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
}
