<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class LabCategory extends BaseModel
{
    use FindTrait;

    protected $type = 'lab_category';
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_lab_categories';
    protected static $conditions = [];
    protected static $orderBy = [];
    protected static $queryType = 'static';
    
    protected $fillable = [
        'name',
        'description', 
        'display_order',
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
     * Find a lab category by ID
     * 
     * @param int $ID The lab category ID
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
        wp_cache_delete($ID, 'hm_lab_categories');
        
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
     * Get all active categories ordered by display_order and name
     * 
     * @return array
     */
    public static function getAllActive()
    {
        global $wpdb;
        
        $instance = new static();
        $table = $instance->getTable();
        
        $categories = $wpdb->get_results(
            "SELECT ID, name, description, display_order, status 
             FROM {$table} 
             WHERE status = 'active' 
             ORDER BY display_order ASC, name ASC",
            ARRAY_A
        );
        
        if ($wpdb->last_error) {
            throw new \Exception('Database error: ' . $wpdb->last_error);
        }
        
        return $categories ?: [];
    }

    /**
     * Create a new lab category
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
            $attributes['display_order'] = $attributes['display_order'] ?? 0;
            
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
                throw new \Exception('Failed to create lab category: ' . $wpdb->last_error);
            }
            
            $new_id = $wpdb->insert_id;
            return static::find($new_id);
            
        } catch (\Exception $e) {
            error_log("LabCategory::create() error: " . $e->getMessage());
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
