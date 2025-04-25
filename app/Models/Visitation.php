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
     * Query builder: where clause
     */
    public static function where($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        static::$conditions[] = [$column, $operator, $value];
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

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        $results = $wpdb->get_results($query, ARRAY_A);
        $items = array_map(function($item) {
            return new static($item);
        }, $results ?: []);

        // Handle eager loading
        if (!empty(static::$with)) {
            foreach (static::$with as $relation) {
                if (method_exists($this, $relation)) {
                    foreach ($items as $item) {
                        $item->$relation = $item->$relation()->get();
                    }
                }
            }
        }

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];
        static::$with = [];
        static::$queryType = 'static';

        return $items;
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
}
