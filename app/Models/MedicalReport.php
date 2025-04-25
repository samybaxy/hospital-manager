<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class MedicalReport extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $tableName = 'hm_medical_reports';
    
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
    
    /**
     * Add a where condition to the query
     *
     * @param string $column Column name
     * @param mixed $value Value to compare (or operator if three parameters)
     * @param mixed $value2 Value to compare if using operator
     * @return $this
     */
    public function where($column, $value, $value2 = null)
    {
        if ($value2 !== null) {
            // If three parameters, the second is the operator
            $this->conditions[] = [$column, $value, $value2];
        } else {
            // If two parameters, assume equals operator
            $this->conditions[] = [$column, '=', $value];
        }
        return $this;
    }
    
    /**
     * Add an ORDER BY clause to the query
     *
     * @param string $column Column name
     * @param string $direction Sort direction (ASC or DESC)
     * @return $this
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy[] = [$column, strtoupper($direction)];
        return $this;
    }
    
    /**
     * Execute the query and return the results
     *
     * @return array Array of MedicalReport instances
     */
    public function get()
    {
        global $wpdb;
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        // Add where conditions
        foreach ($this->conditions as $condition) {
            if ($condition[1] === 'LIKE') {
                $query .= $wpdb->prepare(" AND {$condition[0]} LIKE %s", $condition[2]);
            } else {
                $query .= $wpdb->prepare(" AND {$condition[0]} {$condition[1]} %s", $condition[2]);
            }
        }

        // Add order by
        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy));
        }

        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Reset query conditions and order by for next query
        $this->conditions = [];
        $this->orderBy = [];
        
        return array_map(function($data) {
            return new static($data);
        }, $results ?: []);
    }
    
    /**
     * Count the number of records matching the query conditions
     *
     * @return int Number of matching records
     */
    public function count()
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";

        // Add where conditions
        foreach ($this->conditions as $condition) {
            if ($condition[1] === 'LIKE') {
                $query .= $wpdb->prepare(" AND {$condition[0]} LIKE %s", $condition[2]);
            } else {
                $query .= $wpdb->prepare(" AND {$condition[0]} {$condition[1]} %s", $condition[2]);
            }
        }

        // Reset query conditions for next query
        $this->conditions = [];
        
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Create a new medical report record in the database
     *
     * @param array $data Medical report data to create
     * @return MedicalReport The newly created medical report instance
     */
    public static function create(array $data)
    {
        global $wpdb;
        
        // Set created_at timestamp if not provided
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        // Filter data to only include fillable fields
        $instance = new static();
        $fillable_data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Insert the record
        $wpdb->insert(
            $instance->table,
            $fillable_data
        );
        
        // Get the newly created ID
        $id = $wpdb->insert_id;
        
        // Return a new instance with the created data
        $created_data = array_merge(['id' => $id], $fillable_data);
        return new static($created_data);
    }
}
