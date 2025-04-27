<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Visitation extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $tableName = 'hm_visitations';
    protected static $conditions = [];
    protected static $orderBy = [];
    protected static $queryType = 'static';
    protected static $with = [];

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'date',
        'time',
        'medical_history',
        'diagnosis',
        'treatment'
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
        
        // Also ensure object properties have both id and ID
        if (isset($this->id) && !isset($this->ID)) {
            $this->ID = $this->id;
        } elseif (isset($this->ID) && !isset($this->id)) {
            $this->id = $this->ID;
        }
    }

    /**
     * Get visitations based on a field value
     * 
     * @param string $field Field to filter by
     * @param mixed $value Value to match
     * @return object Model
     */
    public static function where($field, $value)
    {
        global $wpdb;
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Create a model for chained calls
        $model = new self();
        $model->_where = [$field => $value];
        
        return $model;
    }
    
    /**
     * Get all records based on the where condition
     * 
     * @return array
     */
    public function get()
    {
        global $wpdb;
        
        if (empty($this->_where)) {
            return [];
        }
        
        // Get the first where condition
        $field = key($this->_where);
        $value = $this->_where[$field];
        
        // Prepare the query
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$field} = %s ORDER BY id ASC",
            $value
        );
        
        // Fetch records
        $records = $wpdb->get_results($query, ARRAY_A);
        
        // Convert to Visitation models
        return array_map(function($record) {
            return new self($record);
        }, $records ?: []);
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
        $patient_visitation_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$patient_visitation_data) {
            return null;
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
        if (isset($patient_visitation_data['id']) && !isset($patient_visitation_data['ID'])) {
            $patient_visitation_data['ID'] = $patient_visitation_data['id'];
        } elseif (isset($patient_visitation_data['ID']) && !isset($patient_visitation_data['id'])) {
            $patient_visitation_data['id'] = $patient_visitation_data['ID'];
        }
        
        // Create a new Patient instance with the fetched data
        return new self($patient_visitation_data);
    }

    /**
     * Relationship with patient
     */
    public function patient()
    {
        return $this->belongs_to('HospitalManager\Models\Patient', 'patient_id', 'id');
    }

    /**
     * Relationship with doctor
     */
    public function doctor()
    {
        return $this->belongs_to('HospitalManager\Models\Doctor', 'doctor_id', 'id');
    }

    /**
     * Relationship with lab investigations
     */
    public function labInvestigations()
    {
        return $this->has_many('HospitalManager\Models\LabInvestigation', 'visitation_id', 'id');
    }

    /**
     * Relationship with radiological exams
     */
    public function radiologicalExams()
    {
        return $this->has_many('HospitalManager\Models\RadiologicalExam', 'visitation_id', 'id');
    }

    /**
     * Create a new visitation record
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = (new static)->table;
        
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
        $attributes['ID'] = $attributes['id']; // Ensure both ID versions exist
        
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
     * Add eager loading relationships
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        static::$with = array_merge(static::$with, $relations);
        return $this;
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
     * Get the count of records with current conditions
     */
    public static function count()
    {
        global $wpdb;
        $table = (new static)->table;
        $query = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
        $values = [];
        
        foreach (static::$conditions as $condition) {
            if (isset($condition[0]) && $condition[0] === 'OR') {
                $query .= $wpdb->prepare(" OR {$condition[1]} {$condition[2]} %s", $condition[3]);
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
     * Get all visitations with pagination
     */
    public static function paginate($perPage = 10, $page = 1)
    {
        global $wpdb;
        $offset = ($page - 1) * $perPage;
        $table = (new static)->table;
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ),
            ARRAY_A
        );
        
        return [
            'data' => array_map(function($item) {
                return new static($item);
            }, $items),
            'total' => (int)$total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Find visitations by specific conditions
     */
    public static function findWhere(array $conditions)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $where = [];
        $values = [];
        foreach ($conditions as $column => $value) {
            $where[] = "$column = %s";
            $values[] = $value;
        }
        
        $whereClause = implode(' AND ', $where);
        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE $whereClause",
            $values
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {
            return new static($item);
        }, $results);
    }

    /**
     * Get visitations for a specific patient
     */
    public static function forPatient($patientId)
    {
        return static::findWhere(['patient_id' => $patientId]);
    }

    /**
     * Get visitations for a specific doctor
     */
    public static function forDoctor($doctorId)
    {
        return static::findWhere(['doctor_id' => $doctorId]);
    }

    /**
     * Get visitations for a specific date range
     */
    public static function forDateRange($startDate, $endDate)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE date BETWEEN %s AND %s",
                $startDate,
                $endDate
            ),
            ARRAY_A
        );
        
        return array_map(function($item) {
            return new static($item);
        }, $results);
    }

    /**
     * Get the patient associated with this visitation
     */
    public function getPatient()
    {
        global $wpdb;
        $patient = new \HospitalManager\Models\Patient();
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$patient->table} WHERE id = %d",
                $this->patient_id
            ),
            ARRAY_A
        );
        
        return $result ? new $patient($result) : null;
    }

    /**
     * Get the doctor associated with this visitation
     */
    public function getDoctor()
    {
        global $wpdb;
        $doctor = new \HospitalManager\Models\Doctor();
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$doctor->table} WHERE id = %d",
                $this->doctor_id
            ),
            ARRAY_A
        );
        
        return $result ? new $doctor($result) : null;
    }

    /**
     * Get all lab investigations for this visitation
     */
    public function getLabInvestigations()
    {
        global $wpdb;
        $labInvestigation = new \HospitalManager\Models\LabInvestigation();
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$labInvestigation->table} WHERE visitation_id = %d",
                $this->id
            ),
            ARRAY_A
        );
        
        return array_map(function($item) use ($labInvestigation) {
            return new $labInvestigation($item);
        }, $results);
    }

    /**
     * Convert the model to an array
     * 
     * @return array
     */
    public function toArray()
    {
        // Get all public properties
        $properties = get_object_vars($this);
        
        // Remove any internal properties that start with underscore
        foreach ($properties as $key => $value) {
            if (strpos($key, '_') === 0) {
                unset($properties[$key]);
            }
        }
        
        return $properties;
    }
    
    /**
     * Returns object converted to array.
     * Required by Arrayable interface.
     * 
     * @return array
     */
    public function to_array()
    {
        return $this->toArray();
    }
    
    /**
     * Returns object converted to array.
     * Required by Arrayable interface.
     * 
     * @return array
     */
    public function __toArray()
    {
        return $this->toArray();
    }
}
