<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class LabInvestigation extends BaseModel
{
    use FindTrait;

    protected $type = 'lab_investigation';
    protected $primaryKey = 'id';
    protected $tableName = 'hm_lab_investigations';
    protected static $conditions = [];
    protected static $orderBy = [];
    protected static $queryType = 'static';
    
    protected $fillable = [
        'visitation_id',
        'doctor_id',
        'lab_tech_id',
        'patient_id',
        'test_type',
        'status',
        'notes',
        'results',
        'created_at',
        'updated_at'
    ];
    
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        // Ensure both lowercase 'id' and uppercase 'ID' exist for consistency
        if (isset($attributes['id']) && !isset($attributes['ID'])) {
            $attributes['ID'] = $attributes['id'];
        } elseif (isset($attributes['ID']) && !isset($attributes['id'])) {
            $attributes['id'] = $attributes['ID'];
        }
        
        parent::__construct($attributes);
    }

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

    /**
     * Create a new lab investigation
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = (new static)->table;

        // Set default values
        $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
        
        $result = $wpdb->insert(
            $table,
            $attributes,
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes)
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }

        $attributes['id'] = $wpdb->insert_id;
        return new static($attributes);
    }

    /**
     * Initialize a new query builder instance
     */
    public static function query()
    {
        $instance = new static();
        static::$queryType = 'instance';
        return $instance;
    }

    /**
     * Query builder: where clause
     */
    public static function where($conditions)
    {
        // Handle array of conditions
        if (is_array($conditions)) {
            foreach ($conditions as $column => $value) {
                static::$conditions[] = [$column, '=', $value];
            }
            return new static();
        }

        // Handle method chaining
        $args = func_get_args();
        if (count($args) === 2) {
            static::$conditions[] = [$args[0], '=', $args[1]];
        } elseif (count($args) === 3) {
            static::$conditions[] = [$args[0], $args[1], $args[2]];
        }

        return new static();
    }

    /**
     * Query builder: order by
     */
    public function orderBy($column, $direction = 'ASC')
    {
        static::$orderBy[] = [$column, strtoupper($direction)];
        return static::$queryType === 'instance' ? $this : new static();
    }

    /**
     * Execute query and get results
     */
    public function get()
    {
        global $wpdb;
        $table = $this->table;
        $query = "SELECT * FROM {$table} WHERE 1=1";
        $values = [];
        
        foreach (static::$conditions as $condition) {
            $query .= $wpdb->prepare(" AND {$condition[0]} {$condition[1]} %s", $condition[2]);
        }

        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, static::$orderBy));
        }

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];
        static::$queryType = 'static';

        $results = $wpdb->get_results($query, ARRAY_A);
        
        return array_map(function($item) {
            return new static($item);
        }, $results ?: []);
    }

    /**
     * Get the count of records with current conditions
     */
    public static function count()
    {
        global $wpdb;
        $table = (new static)->table;
        $query = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
        $values = [];
        
        foreach (static::$conditions as $condition) {
            if (is_array($condition[2])) {
                $placeholders = array_fill(0, count($condition[2]), '%s');
                $query .= $wpdb->prepare(
                    " AND {$condition[0]} {$condition[1]} (" . implode(',', $placeholders) . ")",
                    $condition[2]
                );
            } else {
                $query .= $wpdb->prepare(" AND {$condition[0]} {$condition[1]} %s", $condition[2]);
            }
        }

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];
        static::$queryType = 'static';

        return (int)$wpdb->get_var($query);
    }

    /**
     * Update the lab investigation
     */
    public function update(array $attributes)
    {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table,
            $attributes,
            ['id' => $this->id],
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes),
            ['%d']
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }

        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }

        return $this;
    }

    /**
     * Get pending lab tests for a patient
     */
    public static function getPendingForPatient($patientId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE patient_id = %d 
            AND status = %s",
            $patientId,
            'pending'
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {
            // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
            if (isset($item['id']) && !isset($item['ID'])) {
                $item['ID'] = $item['id'];
            } elseif (isset($item['ID']) && !isset($item['id'])) {
                $item['id'] = $item['ID'];
            }
            return new static($item);
        }, $results ?: []);
    }

    /**
     * Get completed lab tests count for a technician today
     */
    public static function getCompletedCountForTechToday($techId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE lab_tech_id = %d 
            AND status = %s 
            AND DATE(created_at) = CURDATE()",
            $techId,
            'completed'
        ));
    }

    /**
     * Get pending lab tests for a technician
     */
    public static function getPendingForTech($techId, $limit = 10)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT t.*, p.display_name as patient_name 
            FROM {$table} t
            LEFT JOIN {$wpdb->users} p ON t.patient_id = p.ID
            WHERE t.lab_tech_id = %d 
            AND t.status = %s
            ORDER BY t.created_at DESC
            LIMIT %d",
            $techId,
            'pending',
            $limit
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
