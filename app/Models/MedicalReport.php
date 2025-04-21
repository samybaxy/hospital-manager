<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class MedicalReport extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $table = 'wp_hm_medical_reports';
    
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'visitation_id',
        'report_content',
        'status',
        'created_at',
        'updated_at'
    ];

    protected static $conditions = [];
    protected static $orderBy = [];

    /**
     * Get pending reports for a doctor
     */
    public static function getPendingForDoctor($doctorId, $limit = 5)
    {
        global $wpdb;
        $table = static::getTable();
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE doctor_id = %d 
            AND status = 'pending' 
            ORDER BY created_at DESC 
            LIMIT %d",
            $doctorId,
            $limit
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {
            return new static($item);
        }, $results ?: []);
    }

    /**
     * Get table name
     */
    protected static function getTable()
    {
        return (new static)->table;
    }

    /**
     * Get the patient associated with this report
     */
    public function patient()
    {
        return $this->belongs_to('HospitalManager\Models\Patient', 'patient_id');
    }

    /**
     * Get the doctor associated with this report
     */
    public function doctor()
    {
        return $this->belongs_to('WPMVC\MVC\Models\UserModel', 'doctor_id');
    }

    /**
     * Get the visitation associated with this report
     */
    public function visitation()
    {
        return $this->belongs_to('HospitalManager\Models\Visitation', 'visitation_id');
    }
}
