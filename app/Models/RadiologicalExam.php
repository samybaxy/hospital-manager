<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class RadiologicalExam extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $table = 'wp_hm_radiological_exams';
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
