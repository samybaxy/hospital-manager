<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class LabInvestigation extends PostModel
{
    use FindTrait;

    protected $type = 'lab_investigation';
    protected $primaryKey = 'id';
    protected $table = 'wp_hm_lab_investigations';
    protected $fillable = [
        'visitation_id',
        'lab_tech_id',
        'patient_id',
        'test_type',
        'results',
        'report_url',
        'requested_by',
        'notes',
        'status',
        'completed_at',
        'created_at'
    ];

    /**
     * Relationship with visitation
     */
    public function visitation()
    {
        return $this->belongs_to('HospitalManager\Models\Visitation', 'visitation_id');
    }

    /**
     * Relationship with lab technician (WordPress user)
     */
    public function labTech()
    {
        return $this->belongs_to('WPMVC\MVC\Models\UserModel', 'lab_tech_id');
    }

    /**
     * Relationship with requesting doctor (WordPress user)
     */
    public function requestedBy()
    {
        return $this->belongs_to('WPMVC\MVC\Models\UserModel', 'requested_by');
    }

    /**
     * Relationship with patient
     */
    public function patient()
    {
        return $this->belongs_to('HospitalManager\Models\Patient', 'patient_id');
    }
}
