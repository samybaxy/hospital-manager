<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class LabInvestigation extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $table = 'wp_hm_lab_investigations';
    protected $fillable = [
        'visitation_id',
        'lab_tech_id',
        'results'
    ];

    /**
     * Relationship with visitation
     */
    public function visitation()
    {
        return $this->belongsTo('HospitalManager\Models\Visitation', 'visitation_id', 'id');
    }

    /**
     * Relationship with lab technician (WordPress user)
     */
    public function labTech()
    {
        return $this->belongsTo('WPMVC\MVC\Models\UserModel', 'lab_tech_id', 'ID');
    }
}
