<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Appointment extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $tableName = 'hm_appointments';
    
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
    protected $rawConditions = [];
    protected $orderBy = [];
    
    public function __construct($attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        // Ensure $attributes is an array
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true) ?: [];
        } elseif (!is_array($attributes)) {
            $attributes = [];
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility.
        if (isset($attributes['id']) && !isset($attributes['ID'])) {
            $attributes['ID'] = $attributes['id'];
        }
        
        parent::__construct($attributes);
    }

    /**
     * Override the find method from FindTrait to handle our constructor's array requirement
     * 
     * @param mixed $id Record ID.
     * @return object|null
     */
    public static function find($id = 0)
    {
        global $wpdb;
        
        if (empty($id)) {
            return null;
        }
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Fetch the appointment record directly from the database.
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $appointment_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$appointment_data) {
            return null;
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility.
        if (isset($appointment_data['id']) && !isset($appointment_data['ID'])) {
            $appointment_data['ID'] = $appointment_data['id'];
        }
        
        // Create a new appointment instance with the fetched data.
        return new self($appointment_data);
    }
    
    public function where($column, $operator = null, $value = null)
    {
        // Handle 2 argument scenario (implying = operator)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->conditions[] = [$column, $operator, $value];
        return $this;
    }

    /**
     * Add a raw where clause to the query
     */
    public function whereRaw($sql, $params = [])
    {
        $this->rawConditions[] = [
            'sql' => $sql,
            'params' => $params
        ];
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

        // Initialize rawConditions array if not already initialized
        if (!isset($this->rawConditions)) {
            $this->rawConditions = [];
        }

        // Add where conditions
        foreach ($this->conditions as $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $query .= $wpdb->prepare(" AND {$column} {$operator} %s", $value);
        }
        
        // Add raw where conditions if any
        foreach ($this->rawConditions as $rawCondition) {
            $sql = $rawCondition['sql'];
            $rawParams = $rawCondition['params'];
            
            // If there are params, use prepare, otherwise just append the raw SQL
            if (!empty($rawParams)) {
                $query .= ' AND ' . $wpdb->prepare($sql, $rawParams);
            } else {
                $query .= ' AND ' . $sql;
            }
        }

        // Add order by
        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy));
        }
        
        error_log("Final SQL query: $query");
        $results = $wpdb->get_results($query);
        
        // Convert results to array of appointment objects
        // Make sure each result has both 'id' and 'ID' for compatibility
        $formatted_results = [];
        foreach ($results as $data) {
            $data = (array)$data;
            // Ensure both lowercase and uppercase ID exist
            if (isset($data['id'])) {
                $data['ID'] = $data['id']; // Add uppercase ID for PostModel compatibility
            }
            $formatted_results[] = new static($data);
        }
        
        return $formatted_results;
    }

    public function exists()
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";

        // Initialize rawConditions array if not already initialized
        if (!isset($this->rawConditions)) {
            $this->rawConditions = [];
        }

        // Add where conditions
        foreach ($this->conditions as $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $query .= $wpdb->prepare(" AND {$column} {$operator} %s", $value);
        }
        
        // Add raw where conditions if any
        foreach ($this->rawConditions as $rawCondition) {
            $sql = $rawCondition['sql'];
            $rawParams = $rawCondition['params'];
            
            // If there are params, use prepare, otherwise just append the raw SQL
            if (!empty($rawParams)) {
                $query .= ' AND ' . $wpdb->prepare($sql, $rawParams);
            } else {
                $query .= ' AND ' . $sql;
            }
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
        
        // Initialize rawConditions array if not already initialized
        if (!isset($this->rawConditions)) {
            $this->rawConditions = [];
        }
        
        // Add where conditions
        foreach ($this->conditions as $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $query .= $wpdb->prepare(" AND {$column} {$operator} %s", $value);
        }
        
        // Add raw where conditions if any
        foreach ($this->rawConditions as $rawCondition) {
            $sql = $rawCondition['sql'];
            $rawParams = $rawCondition['params'];
            
            // If there are params, use prepare, otherwise just append the raw SQL
            if (!empty($rawParams)) {
                $query .= ' AND ' . $wpdb->prepare($sql, $rawParams);
            } else {
                $query .= ' AND ' . $sql;
            }
        }

        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy));
        }

        $results = $wpdb->get_col($query);
        return $results;
    }
    
    /**
     * Create a new appointment record in the database
     *
     * @param array $data Appointment data to create
     * @return Appointment The newly created appointment instance
     */
    public static function create(array $data)
    {
        global $wpdb;
    
        // Get table name
        $instance = new static();
        $table = $instance->getTable();
        
        // Set created_at timestamp if not provided
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        // Set updated_at if not provided
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }
        
        // Filter data to only include fillable fields
        $fillable_data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Insert the record
        $result = $wpdb->insert(
            $table,
            $fillable_data,
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $fillable_data)
        );
        
        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }
        
        // Get the newly created ID and add it to the data
        $data['id'] = $wpdb->insert_id;
        $data['ID'] = $data['id']; // Add uppercase ID for compatibility
        
        // Return a new instance with the created data
        return new static($data);
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
        // Get the actual status from attributes if it exists, 
        // otherwise try to get it from direct property access
        $status = null;
        if (isset($this->attributes['status'])) {
            $status = $this->attributes['status'];
        } elseif (isset($this->attributes['post_status'])) {
            $status = $this->attributes['post_status']; 
        } elseif (property_exists($this, 'status') && $this->status !== 'draft') {
            // Only use the property if it's not the default 'draft'
            $status = $this->status;
        }
        
        // If no status was found or it's 'draft' from PostModel default, use 'pending' as fallback
        if (empty($status) || $status === 'draft') {
            $status = 'pending';
        }
        
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'appointment_date' => $this->appointment_date,
            'appointment_time' => $this->appointment_time,
            'status' => $status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
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
            AND appointment_date >= %s",
            $patientId,
            'scheduled',
            current_time('Y-m-d')
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        $appointments = array_map(function($item) {
            // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
            if (isset($item['id']) && !isset($item['ID'])) {
                $item['ID'] = $item['id'];
            } elseif (isset($item['ID']) && !isset($item['id'])) {
                $item['id'] = $item['ID'];
            }
            return new static($item);
        }, $results ?: []);
        
        return $appointments;
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
            // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
            if (isset($item['id']) && !isset($item['ID'])) {
                $item['ID'] = $item['id'];
            } elseif (isset($item['ID']) && !isset($item['id'])) {
                $item['id'] = $item['ID'];
            }
            
            $model = new static($item);
            if (isset($item['patient_name'])) {
                $model->patient_name = $item['patient_name'];
            }
            return $model;
        }, $results ?: []);
    }
}
