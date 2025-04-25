<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class AuditLog extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $tableName = 'hm_audit_logs';
    
    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'user_agent',
        'created_at'
    ];

    protected static $conditions = [];
    protected static $orderBy = [];
    
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
    
    public static function create(array $attributes)
    {
        global $wpdb;
        
        $table = (new static)->table;
        
        // Ensure both uppercase 'ID' and lowercase 'id' are handled
        if (isset($attributes['id']) && !isset($attributes['ID'])) {
            $attributes['ID'] = $attributes['id'];
        } elseif (isset($attributes['ID']) && !isset($attributes['id'])) {
            $attributes['id'] = $attributes['ID'];
        }
        
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
        
        // Add the generated ID to the attributes
        $attributes['id'] = $wpdb->insert_id;
        $attributes['ID'] = $attributes['id']; // Ensure both ID formats are available

        return new static($attributes);
    }

    public static function where($column, $value)
    {
        static::$conditions[] = [$column, '=', $value];
        return new static();
    }

    public static function orderBy($column, $direction = 'ASC')
    {
        static::$orderBy[] = [$column, strtoupper($direction)];
        return new static();
    }

    public function paginate($perPage = 20, $columns = ['*'], $pageName = 'page', $page = null)
    {
        global $wpdb;
        
        $query = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table} WHERE 1=1";
        
        // Add where conditions
        foreach (static::$conditions as $condition) {
            $query .= $wpdb->prepare(" AND {$condition[0]} = %s", $condition[2]);
        }

        // Add order by
        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, static::$orderBy));
        }

        // Add pagination
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT {$offset}, {$perPage}";

        $results = $wpdb->get_results($query);
        $total = $wpdb->get_var('SELECT FOUND_ROWS()');

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];

        // Process results to ensure ID consistency
        $items = array_map(function($item) {
            $data = (array)$item;
            
            // Ensure both id and ID exist
            if (isset($data['id']) && !isset($data['ID'])) {
                $data['ID'] = $data['id'];
            } elseif (isset($data['ID']) && !isset($data['id'])) {
                $data['id'] = $data['ID'];
            }
            
            return new static($data);
        }, $results);

        return (object)[
            'items' => $items,
            'currentPage' => (int)$page,
            'lastPage' => ceil($total / $perPage),
            'perPage' => (int)$perPage,
            'total' => (int)$total,
            'each' => function($callback) use ($items) {
                array_map($callback, $items);
                return $items;
            }
        ];
    }

    public static function get()
    {
        global $wpdb;
        $table = (new static)->table;
        $query = "SELECT * FROM {$table} WHERE 1=1";

        foreach (static::$conditions as $condition) {
            $query .= $wpdb->prepare(" AND {$condition[0]} = %s", $condition[2]);
        }

        if (!empty(static::$orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, static::$orderBy));
        }

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];

        $results = $wpdb->get_results($query);
        
        // Process results to ensure ID consistency
        $items = array_map(function($item) {
            $data = (array)$item;
            
            // Ensure both id and ID exist
            if (isset($data['id']) && !isset($data['ID'])) {
                $data['ID'] = $data['id'];
            } elseif (isset($data['ID']) && !isset($data['id'])) {
                $data['id'] = $data['ID'];
            }
            
            return new static($data);
        }, $results);

        return (object)[
            'items' => $items,
            'each' => function($callback) use ($items) {
                array_map($callback, $items);
                return $items;
            }
        ];
    }
}
