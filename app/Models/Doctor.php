<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Doctor extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'id';
    protected $tableName = 'hm_doctors';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'photo'
    ];
    
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
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
        
        // Fetch the doctor record directly from the database
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $doctor_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$doctor_data) {
            return null;
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
        if (isset($doctor_data['id']) && !isset($doctor_data['ID'])) {
            $doctor_data['ID'] = $doctor_data['id'];
        }
        
        // Create a new doctor instance with the fetched data
        return new self($doctor_data);
    }

    /**
     * Create a new doctor record
     */
    public static function create(array $data)
    {
        global $wpdb;
        // Get the table name
        $instance = new static();
        $table = $instance->getTable();
        
        // Set created_at if applicable
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        // Set updated_at if applicable
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }
        
        try {
            // Insert the record
            $result = $wpdb->insert(
                $table,
                $data,
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data)
            );
            
            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }
            
            $data['id'] = $wpdb->insert_id;
            $data['ID'] = $data['id']; // Add uppercase ID for compatibility
            
            return new static($data);
        } catch (\Exception $e) {
            throw new \Exception('Failed to create doctor record: ' . $e->getMessage());
        }
    }

    /**
     * Get doctors by a specific condition
     */
    public static function where($column, $value)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE $column = %s",
                $value
            ),
            ARRAY_A
        );
        
        return array_map(function($item) {
            return new static($item);
        }, $results);
    }

    /**
     * Order doctors by a column
     */
    public static function orderBy($column, $direction = 'ASC')
    {
        global $wpdb;
        $table = (new static)->table;
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY $column $direction",
            ARRAY_A
        );
        
        return array_map(function($item) {
            return new static($item);
        }, $results);
    }

    /**
     * Check if a doctor exists
     */
    public static function exists($conditions)
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
            "SELECT COUNT(*) FROM $table WHERE $whereClause",
            $values
        );
        
        return (int) $wpdb->get_var($query) > 0;
    }

    /**
     * Get active doctors
     */
    public static function getActiveDoctors()
    {
        return static::where('status', 'active');
    }

    /**
     * Get all doctors with pagination
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
     * Find doctors by specific conditions
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
     * Search doctors by name
     */
    public static function search($term)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE first_name LIKE %s OR last_name LIKE %s",
                "%$term%",
                "%$term%"
            ),
            ARRAY_A
        );
        
        return array_map(function($item) {
            return new static($item);
        }, $results);
    }

    /**
     * Get WordPress user associated with this doctor
     */
    public function getUser()
    {
        return get_user_by('ID', $this->user_id);
    }

    /**
     * Get all visitations for this doctor
     */
    public function getVisitations()
    {
        global $wpdb;
        $visitation = new \HospitalManager\Models\Visitation();
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$visitation->table} WHERE doctor_id = %d",
                $this->id
            ),
            ARRAY_A
        );
        
        return array_map(function($item) use ($visitation) {
            return new $visitation($item);
        }, $results);
    }
}
