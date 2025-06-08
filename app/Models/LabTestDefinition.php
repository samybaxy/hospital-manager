<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class LabTestDefinition extends BaseModel
{
    use FindTrait;

    protected $type = 'lab_test_definition';
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_lab_test_definitions';
    protected static $conditions = [];
    protected static $orderBy = [];
    protected static $queryType = 'static';
    
    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'sample_type',
        'container',
        'sample_volume',
        'turnaround_time',
        'test_parameters',
        'specimen_requirements',
        'preparation_instructions',
        'methodology',
        'cost',
        'is_panel',
        'status',
        'created_at',
        'updated_at'
    ];
    
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        // Set attributes directly for custom database tables
        if (!empty($attributes)) {
            $this->attributes = $attributes;
            
            // Also set primary key property for easy access
            if (isset($attributes[$this->primaryKey])) {
                $this->ID = $attributes[$this->primaryKey];
            }
        }
        
        // Don't call parent constructor for custom database models
        // parent::__construct($attributes);
    }

    /**
     * Find a lab test definition by ID
     * 
     * @param int $ID The lab test definition ID
     * @return static|null
     */
    public static function find($ID = 0)
    {
        global $wpdb;
        
        if (empty($ID)) {
            return null;
        }
        
        // Get the table name
        $instance = new static();
        $table = $instance->getTable();
        
        // Clear any potential WordPress cache for this query
        wp_cache_delete($ID, 'hm_lab_test_definitions');
        
        // Add SQL_NO_CACHE to prevent MySQL query caching issues
        $query = $wpdb->prepare("SELECT SQL_NO_CACHE * FROM {$table} WHERE ID = %d LIMIT 1", $ID);
        
        // Use no_found_rows to improve performance and suppress filters
        $lab_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$lab_data) {
            return null;
        }
        
        // Create a new instance with the fetched data
        return new static($lab_data);
    }

    /**
     * Get active test definitions with optional category filter
     * 
     * @param int|null $category_id Optional category ID to filter by
     * @return array
     */
    public static function getActive($category_id = null)
    {
        global $wpdb;
        
        $instance = new static();
        $table = $instance->getTable();
        
        $where_clause = "WHERE td.status = 'active'";
        $where_params = [];
        
        if ($category_id) {
            $where_clause .= " AND td.category_id = %d";
            $where_params[] = $category_id;
        }
        
        $query = "SELECT td.ID, td.category_id, td.code, td.name, td.description, 
                         td.sample_type, td.container, td.sample_volume, 
                         td.turnaround_time, td.test_parameters, td.specimen_requirements,
                         td.preparation_instructions, td.methodology, td.cost, td.is_panel,
                         c.name as category_name
                  FROM {$table} td
                  LEFT JOIN {$wpdb->prefix}hm_lab_categories c ON td.category_id = c.ID
                  {$where_clause}
                  ORDER BY td.name ASC";
        
        if (!empty($where_params)) {
            $prepared_query = $wpdb->prepare($query, ...$where_params);
        } else {
            $prepared_query = $query;
        }
        
        $test_definitions = $wpdb->get_results($prepared_query, ARRAY_A);
        
        if ($wpdb->last_error) {
            throw new \Exception('Database error: ' . $wpdb->last_error);
        }
        
        // Decode JSON test_parameters for each test
        if ($test_definitions) {
            foreach ($test_definitions as &$test) {
                if ($test['test_parameters']) {
                    $test['test_parameters'] = json_decode($test['test_parameters'], true);
                }
            }
        }
        
        return $test_definitions ?: [];
    }

    /**
     * Get a single active test definition with category info
     * 
     * @param int $ID The test definition ID
     * @return array|null
     */
    public static function getActiveWithCategory($ID)
    {
        global $wpdb;
        
        $instance = new static();
        $table = $instance->getTable();
        
        $test_definition = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT td.*, c.name as category_name
                 FROM {$table} td
                 LEFT JOIN {$wpdb->prefix}hm_lab_categories c ON td.category_id = c.ID
                 WHERE td.ID = %d AND td.status = 'active'",
                $ID
            ),
            ARRAY_A
        );
        
        if ($wpdb->last_error) {
            throw new \Exception('Database error: ' . $wpdb->last_error);
        }
        
        if (!$test_definition) {
            return null;
        }
        
        // Decode JSON test_parameters
        if ($test_definition['test_parameters']) {
            $test_definition['test_parameters'] = json_decode($test_definition['test_parameters'], true);
        }
        
        return $test_definition;
    }

    /**
     * Relationship with category
     */
    public function category()
    {
        if (!isset($this->attributes['category_id'])) {
            return null;
        }
        
        return LabCategory::find($this->attributes['category_id']);
    }

    /**
     * Create a new lab test definition
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        
        try {
            $instance = new static();
            $table = $instance->getTable();
            
            // Set default values
            $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
            $attributes['status'] = $attributes['status'] ?? 'active';
            $attributes['is_panel'] = $attributes['is_panel'] ?? 0;
            
            // Only keep fillable attributes
            $filtered_attributes = array_intersect_key($attributes, array_flip($instance->fillable));
            
            $result = $wpdb->insert(
                $table,
                $filtered_attributes,
                array_map(function($value) {
                    return is_numeric($value) ? '%d' : '%s';
                }, $filtered_attributes)
            );
            
            if ($result === false) {
                throw new \Exception('Failed to create lab test definition: ' . $wpdb->last_error);
            }
            
            $new_id = $wpdb->insert_id;
            return static::find($new_id);
            
        } catch (\Exception $e) {
            error_log("LabTestDefinition::create() error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the table name including prefix
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Convert model to array
     */
    public function to_array()
    {
        return $this->attributes;
    }
}
