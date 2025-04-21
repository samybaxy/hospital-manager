<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class Appointment extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $table = 'wp_hm_appointments';
    
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'notes',
        'created_at',
        'updated_at'
    ];

    protected $conditions = [];
    protected $orderBy = [];
    
    public function where($column, $value)
    {
        $this->conditions[] = [$column, '=', $value];
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy[] = [$column, $direction];
        return $this;
    }

    public function get()
    {
        global $wpdb;
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        // Add where conditions
        foreach ($this->conditions as $condition) {
            $query .= $wpdb->prepare(" AND {$condition[0]} = %s", $condition[2]);
        }

        // Add order by
        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy));
        }

        $results = $wpdb->get_results($query);
        return array_map(function($data) {
            return new static((array)$data);
        }, $results);
    }

    public function exists()
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        $params = [];

        foreach ($this->conditions as $condition) {
            $query .= $wpdb->prepare(" AND {$condition[0]} = %s", $condition[2]);
        }

        return (bool)$wpdb->get_var($query);
    }

    public static function query()
    {
        return new static();
    }

    public function pluck($column)
    {
        global $wpdb;
        $query = "SELECT {$column} FROM {$this->table} WHERE 1=1";
        
        foreach ($this->conditions as $condition) {
            $query .= $wpdb->prepare(" AND {$condition[0]} = %s", $condition[2]);
        }

        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy));
        }

        $results = $wpdb->get_col($query);
        return $results;
    }

    public function doctor()
    {
        return get_user_by('id', $this->doctor_id);
    }

    public function patient()
    {
        return get_user_by('id', $this->patient_id);
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'appointment_date' => $this->appointment_date,
            'appointment_time' => $this->appointment_time,
            'status' => $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'doctor' => $this->doctor(),
            'patient' => $this->patient()
        ];
    }

    /**
     * Get upcoming appointments for a patient
     */
    public static function getUpcomingForPatient($patientId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE patient_id = %d 
            AND status = %s 
            AND date >= %s",
            $patientId,
            'scheduled',
            current_time('Y-m-d')
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {
            return new static($item);
        }, $results ?: []);
    }

    /**
     * Get today's appointments for a doctor with patient details
     */
    public static function getTodaysForDoctor($doctorId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT a.*, u.display_name as patient_name 
            FROM {$table} a 
            LEFT JOIN {$wpdb->users} u ON a.patient_id = u.ID 
            WHERE a.doctor_id = %d 
            AND DATE(a.appointment_date) = %s 
            ORDER BY a.appointment_date ASC",
            $doctorId,
            current_time('Y-m-d')
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {
            $model = new static($item);
            if (isset($item['patient_name'])) {
                $model->patient_name = $item['patient_name'];
            }
            return $model;
        }, $results ?: []);
    }
}
