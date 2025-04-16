<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class Patient extends PostModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $table = 'wp_hm_patients';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'hmo_id',
        'hmo_designated_id',
        'phone_number',
        'age',
        'sex',
        'bio_data'
    ];

    /**
     * Relationship with WordPress user
     */
    public function user()
    {
        return $this->belongsTo('WPMVC\MVC\Models\UserModel', 'user_id', 'ID');
    }

    /**
     * Relationship with HMO
     */
    public function hmo()
    {
        return $this->belongsTo('HospitalManager\Models\HMO', 'hmo_id', 'id');
    }

    /**
     * Relationship with visitations
     */
    public function visitations()
    {
        return $this->hasMany('HospitalManager\Models\Visitation', 'patient_id', 'id');
    }
}
