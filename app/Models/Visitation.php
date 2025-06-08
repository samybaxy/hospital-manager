<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Visitation extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'ID';
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
        'complaint',
        'diagnosis',
        'treatment'
    ];
    
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        parent::__construct($attributes);
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
            "SELECT * FROM {$this->table} WHERE {$field} = %s ORDER BY ID ASC",
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
        
        // Fetch the patient record directly from the database
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $ID);
        $patient_visitation_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$patient_visitation_data) {
            return null;
        }

        // Format the date if it exists
        if (isset($patient_visitation_data['date'])) {
            $patient_visitation_data['date'] = date('Y-m-d', strtotime($patient_visitation_data['date']));
        }
        
        // Create a new Patient instance with the fetched data
        return new self($patient_visitation_data);
    }

    /**
     * Get all visitations
     * 
     * @return array
     */
    public static function all()
    {
        global $wpdb;
        $instance = new self();
        $table = $instance->getTable();
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY ID DESC",
            ARRAY_A
        );
        
        return array_map(function($item) {
            return new self($item);
        }, $results ?: []);
    }

    /**
     * Relationship with patient
     */
    public function patient()
    {
        if (!isset($this->attributes['patient_id'])) {
            return null;
        }
        return \HospitalManager\Models\Patient::find($this->attributes['patient_id']);
    }

    /**
     * Relationship with doctor
     */
    public function doctor()
    {
        if (!isset($this->attributes['doctor_id'])) {
            return null;
        }
        return \HospitalManager\Models\Doctor::find($this->attributes['doctor_id']);
    }

    /**
     * Relationship with lab investigations
     */
    public function labInvestigations()
    {
        return $this->getLabInvestigations();
    }

    /**
     * Relationship with radiological exams
     */
    public function radiologicalExams()
    {
        return $this->has_many('HospitalManager\Models\RadiologicalExam', 'visitation_id', 'ID');
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

        $attributes['ID'] = $wpdb->insert_id;
        
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
                "SELECT * FROM {$patient->table} WHERE ID = %d",
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
                "SELECT * FROM {$doctor->table} WHERE ID = %d",
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
                $this->ID
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
     * Save the model to the database.
     * 
     * @return bool
     */
    public function save()
    {
        global $wpdb;
        
        $table = $this->getTable();
        $data = [];
        
        // Prepare only fillable attributes for saving
        foreach ($this->fillable as $field) {
            if (isset($this->attributes[$field])) {
                $data[$field] = $this->attributes[$field];
            }
        }
        
        // Add updated_at timestamp
        $data['updated_at'] = current_time('mysql');
        
        // Determine if this is an update or insert
        if (isset($this->attributes['ID']) && !empty($this->attributes['ID'])) {
            // This is an update
            $result = $wpdb->update(
                $table,
                $data,
                ['ID' => $this->attributes['ID']],
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data),
                ['%d']
            );
            
            return $result !== false;
        } else {
            // This is an insert
            $result = $wpdb->insert(
                $table,
                $data,
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data)
            );
            
            if ($result !== false) {
                $this->attributes['ID'] = $wpdb->insert_id; 
                return true;
            }
            
            return false;
        }
    }
    
    /**
     * Delete the model from the database.
     * 
     * @return bool
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID'])) {
            return false;
        }
        
        $table = $this->getTable();
        $result = $wpdb->delete(
            $table,
            ['ID' => $this->attributes['ID']],
            ['%d']
        );
        
        return $result !== false;
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
    
    /**
     * Update a visitation record by ID
     * 
     * @param int $id The visitation ID
     * @param array $data The data to update
     * @return bool|Visitation Returns updated visitation instance on success, false on failure
     */
    public static function updateById($id, array $data)
    {
        global $wpdb;
        $instance = new self();
        $table = $instance->getTable();
        
        if (empty($id)) {
            return false;
        }
        
        // Validate that the record exists
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        
        // Filter data to only include fillable fields
        $fillable_data = [];
        foreach ($instance->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $fillable_data[$field] = $data[$field];
            }
        }
        
        if (empty($fillable_data)) {
            return false;
        }
        
        // Add updated_at timestamp
        $fillable_data['updated_at'] = current_time('mysql');
        
        // Perform the update
        $result = $wpdb->update(
            $table,
            $fillable_data,
            ['ID' => $id],
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $fillable_data),
            ['%d']
        );
        
        if ($result === false) {
            return false;
        }
        
        // Return the updated record
        return self::find($id);
    }
    
    /**
     * Delete a visitation record by ID
     * 
     * @param int $id The visitation ID
     * @return bool Returns true on success, false on failure
     */
    public static function deleteById($id)
    {
        global $wpdb;
        $instance = new self();
        $table = $instance->getTable();
        
        if (empty($id)) {
            return false;
        }
        
        // Validate that the record exists
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        
        // Perform the delete
        $result = $wpdb->delete(
            $table,
            ['ID' => $id],
            ['%d']
        );
        
        return $result !== false;
    }
}
