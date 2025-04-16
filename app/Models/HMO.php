<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;


class HMO extends PostModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $table = 'wp_hm_hmos';
    protected $fillable = [
        'name'
    ];

    /**
     * Relationship with patients
     */
    public function patients()
    {
        return $this->hasMany('HospitalManager\Models\Patient', 'hmo_id', 'id');
    }
}
