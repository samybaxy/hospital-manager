<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;


class HMO extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $tableName = 'hm_hmos';
    protected $fillable = [
        'name'
    ];

    /**
     * Relationship with patients
     */
    public function patients()
    {
        return $this->has_many('HospitalManager\Models\Patient', 'hmo_id', 'id');
    }
}
