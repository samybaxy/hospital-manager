<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Patient extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $tableName = 'hm_patients';
    protected static $conditions = [];
    protected static $orderBy = [];
    protected static $queryType = 'static'; // Track if we're using static or instance query

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'hmo_id',
        'hmo_designated_id',
        'phone',
        'age',
        'gender',
        'address',
        'bio_data'
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
        
        // Fetch the patient record directly from the database
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $patient_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$patient_data) {
            return null;
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
        if (isset($patient_data['id']) && !isset($patient_data['ID'])) {
            $patient_data['ID'] = $patient_data['id'];
        }
        
        // Create a new Patient instance with the fetched data
        return new self($patient_data);
    }

    /**
     * Get all patients
     * 
     * @return array
     */
    public static function all()
    {
        global $wpdb;
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Get all patients
        $patients = $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC", ARRAY_A);
        
        // Convert to Patient models
        return array_map(function($patient) {
            return new self($patient);
        }, $patients ?: []);
    }

    /**
     * Create a new patient record
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = (new static)->table;
        
        // Set created_at if applicable
        if (in_array('created_at', (new static)->fillable)) {
            $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
        }
        
        // Clone the attributes to avoid modifying the original
        $db_attributes = $attributes;
        
        // Make sure bio_data is properly encoded
        if (isset($db_attributes['bio_data']) && is_array($db_attributes['bio_data'])) {
            $db_attributes['bio_data'] = json_encode($db_attributes['bio_data']);
        }
        
        $result = $wpdb->insert(
            $table,
            $db_attributes,
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $db_attributes)
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }

        $attributes['id'] = $wpdb->insert_id;
        $attributes['ID'] = $attributes['id']; // Add uppercase ID for compatibility
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
    public function where($column, $operator = null, $value = null)
    {
        // Handle closure for complex where conditions
        if ($column instanceof \Closure) {
            $query = new static();
            $column($query);
            static::$conditions = array_merge(static::$conditions, $query::$conditions);
            return static::$queryType === 'instance' ? $this : new static();
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        static::$conditions[] = [$column, $operator, $value];
        return static::$queryType === 'instance' ? $this : new static();
    }

    /**
     * Query builder: orWhere clause
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        static::$conditions[] = ['OR', $column, $operator, $value];
        return static::$queryType === 'instance' ? $this : new static();
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
        
        // Add where conditions
        $where = [];
        $orWhere = [];
        
        foreach (static::$conditions as $condition) {
            if (isset($condition[0]) && $condition[0] === 'OR') {
                $orWhere[] = "{$condition[1]} {$condition[2]} %s";
                $values[] = $condition[3];
            } else {
                $where[] = "{$condition[0]} {$condition[1]} %s";
                $values[] = $condition[2];
            }
        }
        
        if (!empty($where)) {
            $query .= " AND (" . implode(" AND ", $where) . ")";
        }
        if (!empty($orWhere)) {
            $query .= " OR (" . implode(" OR ", $orWhere) . ")";
        }

        // Add order by
        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, static::$orderBy));
        }

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
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
     * Paginate results
     */
    public function paginate($perPage = 20, $columns = ['*'], $pageName = 'page', $page = null)
    {
        global $wpdb;
        $table = $this->table;
        
        // Build base query
        $query = "SELECT SQL_CALC_FOUND_ROWS * FROM {$table} WHERE 1=1";
        $values = [];
        
        // Add where conditions
        $where = [];
        $orWhere = [];
        
        foreach (static::$conditions as $condition) {
            if (isset($condition[0]) && $condition[0] === 'OR') {
                $orWhere[] = "{$condition[1]} {$condition[2]} %s";
                $values[] = $condition[3];
            } else {
                $where[] = "{$condition[0]} {$condition[1]} %s";
                $values[] = $condition[2];
            }
        }
        
        if (!empty($where)) {
            $query .= " AND (" . implode(" AND ", $where) . ")";
        }
        if (!empty($orWhere)) {
            $query .= " OR (" . implode(" OR ", $orWhere) . ")";
        }

        // Add order by
        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, static::$orderBy));
        }

        // Add pagination
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT %d OFFSET %d";
        $values[] = $perPage;
        $values[] = $offset;

        // Execute query
        $query = $wpdb->prepare($query, $values);
        $results = $wpdb->get_results($query, ARRAY_A);
        $total = $wpdb->get_var('SELECT FOUND_ROWS()');

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];
        static::$queryType = 'static';

        // Create pagination object
        return (object)[
            'items' => array_map(function($item) {
                return new static($item);
            }, $results ?: []),
            'currentPage' => (int)$page,
            'lastPage' => ceil($total / $perPage),
            'perPage' => (int)$perPage,
            'total' => (int)$total
        ];
    }

    /**
     * Static method to search patients by name
     */
    public static function search($term)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE first_name LIKE %s 
            OR last_name LIKE %s",
            '%' . $wpdb->esc_like($term) . '%',
            '%' . $wpdb->esc_like($term) . '%'
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        
        return array_map(function($item) {
            return new static($item);
        }, $results ?: []);
    }

    /**
     * Get the count of records
     */
    public static function count()
    {
        global $wpdb;
        $table = (new static)->table;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Find patients by specific conditions
     *
     * @param array $conditions Key-value pairs of column conditions
     * @return Patient[] Array of Patient objects matching conditions
     */
    public static function findWhere(array $conditions)
    {
        $query = new static();
        foreach ($conditions as $column => $value) {
            $query->where($column, '=', $value);
        }
        return $query->get();
    }

    /**
     * Relationship with WordPress user
     */
    public function user()
    {
        return $this->belongs_to('WPMVC\MVC\Models\UserModel', 'user_id', 'ID');
    }

    /**
     * Relationship with HMO
     */
    public function hmo()
    {
        return $this->belongs_to('HospitalManager\Models\HMO', 'hmo_id', 'id');
    }

    /**
     * Relationship with visitations
     */
    public function visitations()
    {
        return $this->has_many('HospitalManager\Models\Visitation', 'patient_id', 'id');
    }
}
