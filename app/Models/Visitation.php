<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class Visitation extends PostModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $table = 'wp_hm_visitations';
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'date',
        'medical_history',
        'diagnosis',
        'treatment'
    ];

    /**
     * Relationship with patient
     */
    public function patient()
    {
        return $this->belongsTo('HospitalManager\Models\Patient', 'patient_id', 'id');
    }

    /**
     * Relationship with doctor
     */
    public function doctor()
    {
        return $this->belongsTo('HospitalManager\Models\Doctor', 'doctor_id', 'id');
    }

    /**
     * Relationship with lab investigations
     */
    public function labInvestigations()
    {
        return $this->hasMany('HospitalManager\Models\LabInvestigation', 'visitation_id', 'id');
    }

    /**
     * Relationship with radiological exams
     */
    public function radiologicalExams()
    {
        return $this->hasMany('HospitalManager\Models\RadiologicalExam', 'visitation_id', 'id');
    }
}
