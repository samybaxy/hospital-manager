<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Appointment extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'ID';
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
        
        parent::__construct($attributes);
    }

    /**
     * Override the find method from FindTrait to handle our constructor's array requirement
     * 
     * @param mixed $ID Record ID.
     * @return object|null
     */
    public static function find($ID = 0)
    {
        global $wpdb;
        
        if (empty($ID)) {
            return null;
        }
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Fetch the appointment record directly from the database.
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $ID);
        $appointment_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$appointment_data) {
            return null;
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
        $formatted_results = [];
        foreach ($results as $data) {
            $data = (array)$data;
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
        $data['ID'] = $wpdb->insert_id;
        
        // Return a new instance with the created data
        return new static($data);
    }

    public function doctor()
    {
        return get_user_by('ID', $this->doctor_id);
    }

    public function patient()
    {
        return get_user_by('ID', $this->patient_id);
    }

    /**
     * Convert the model instance to an array
     * 
     * @return array
     */
    public function toArray()
    {
        // Start with the attributes
        $data = $this->attributes;
        
        // Process notes if it's a JSON string
        if (!empty($data['notes']) && is_string($data['notes'])) {
            $decoded = json_decode($data['notes'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['notes'] = $decoded;
            }
        }
        
        // Ensure status defaults to 'pending' if not set
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }
        
        return $data;
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
            $model = new static($item);
            if (isset($item['patient_name'])) {
                $model->patient_name = $item['patient_name'];
            }
            return $model;
        }, $results ?: []);
    }
}
