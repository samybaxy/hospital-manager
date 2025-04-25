<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class RadiologicalExam extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $tableName = 'hm_radiological_exams';
    protected $fillable = [
        'visitation_id',
        'tech_id',
        'results'
    ];

    /**
     * Relationship with visitation
     */
    public function visitation()
    {
        return $this->belongs_to('HospitalManager\Models\Visitation', 'visitation_id', 'id');
    }

    /**
     * Relationship with technician (WordPress user)
     */
    public function technician()
    {
        return $this->belongs_to('WPMVC\MVC\Models\UserModel', 'tech_id', 'ID');
    }
}
