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
        'phone_number',
        'photo'
    ];

    /**
     * Create a new doctor record
     */
    public static function create(array $data)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $wpdb->insert($table, $data);
        return static::find($wpdb->insert_id);
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
