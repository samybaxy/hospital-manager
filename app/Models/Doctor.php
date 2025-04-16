<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class Doctor extends PostModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $table = 'wp_hm_doctors';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone_number',
        'photo'
    ];

    /**
     * Relationship with WordPress user
     */
    public function user()
    {
        return $this->belongsTo('WPMVC\MVC\Models\UserModel', 'user_id', 'ID');
    }

    /**
     * Relationship with visitations
     */
    public function visitations()
    {
        return $this->hasMany('HospitalManager\Models\Visitation', 'doctor_id', 'id');
    }
}
