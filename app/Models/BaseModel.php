<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

abstract class BaseModel extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'ID';
    protected $tableName; // Name portion after prefix
    
    // Caching properties
    protected static $cache_enabled = true;
    protected static $cache_expiration = 3600; // 1 hour default
    protected static $cache_group = 'hospital_manager';
    
    /**
     * WPMVC-compatible constructor that handles int, array, or object parameters
     * 
     * @param int|array|\WP_Post $post_or_attributes Post ID, attributes array, or WP_Post object
     */
    public function __construct($post_or_attributes = 0)
    {
        // Set table name before calling parent constructor
        if (!empty($this->tableName)) {
            global $wpdb;
            $this->table = $wpdb->prefix . $this->tableName;
        }
        
        if ($post_or_attributes) {
            if (is_numeric($post_or_attributes)) {
                // Load by ID from our custom table
                $this->load($post_or_attributes);
            } elseif (is_array($post_or_attributes)) {
                // Load from attributes array
                $this->load_attributes($post_or_attributes);
            } elseif (is_object($post_or_attributes) && is_a($post_or_attributes, 'WP_Post')) {
                // Load from WP_Post object (for backward compatibility)
                $this->load_wp_post($post_or_attributes);
            }
        }
    }
    
    /**
     * Override PostModel's load method to work with custom tables
     * 
     * @param int $id Record ID
     */
    public function load($id)
    {
        global $wpdb;
        $table = $this->getTable();
        
        if (empty($table) || empty($id)) {
            return;
        }
        
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $id);
        $data = $wpdb->get_row($query, ARRAY_A);
        
        if ($data) {
            $this->load_attributes($data);
        }
    }
    
    /**
     * Check if the model has a valid trace (exists in database)
     * This is used by the FindTrait to determine if the model exists
     * 
     * @return bool
     */
    public function has_trace()
    {
        return !empty($this->attributes) && isset($this->attributes['ID']) && !empty($this->attributes['ID']);
    }
    
    /**
     * Get the table name with the correct prefix
     */
    public function getTable()
    {
        global $wpdb;
        if (empty($this->table) && !empty($this->tableName)) {
            $this->table = $wpdb->prefix . $this->tableName;
        }
        return $this->table;
    }
    
    /**
     * Set the tableName property to be used with dynamic prefix
     */
    public function setTableName($name)
    {
        $this->tableName = $name;
        $this->table = null; // Reset table so getTable() will recompute with prefix
        return $this;
    }

    /**
     * Create table for this model
     */
    public static function createTable()
    {
        // This method should be implemented by child classes
        // to create their respective database tables
    }
    
    /**
     * Override PostModel's load_attributes to prevent issues with load_meta for custom tables
     */
    public function load_attributes( $attributes )
    {
        if ( !empty( $attributes ) ) {
            $this->attributes = $attributes;
            // Don't call load_meta() for custom database tables
        }
    }
    
    /**
     * Override PostModel's load_meta to prevent issues with custom tables
     */
    public function load_meta()
    {
        // Do nothing for custom database tables
        // Meta loading is handled differently for custom tables
    }

    /**
     * Generate cache key for method calls
     */
    protected static function getCacheKey($method, $params = [])
    {
        $model_name = strtolower(basename(str_replace('\\', '/', get_called_class())));
        $params_hash = md5(serialize($params));
        return static::$cache_group . '_' . $model_name . '_' . $method . '_' . $params_hash;
    }

    /**
     * Get data from cache
     */
    protected static function getFromCache($key)
    {
        if (!static::$cache_enabled) {
            return false;
        }
        return get_transient($key);
    }

    /**
     * Set data to cache
     */
    protected static function setToCache($key, $data, $expiration = null)
    {
        if (!static::$cache_enabled) {
            return false;
        }
        $expiration = $expiration ?: static::$cache_expiration;
        return set_transient($key, $data, $expiration);
    }

    /**
     * Invalidate cache for specific method or all model caches
     */
    public static function invalidateCache($method = null, $params = [])
    {
        if ($method) {
            $key = static::getCacheKey($method, $params);
            delete_transient($key);
        } else {
            // Invalidate all caches for this model (pattern delete would require custom implementation)
            $model_name = strtolower(basename(str_replace('\\', '/', get_called_class())));
            // For now, we'll track known cache keys or use a more sophisticated approach
            static::clearModelCache($model_name);
        }
    }

    /**
     * Clear all caches for a model (basic implementation)
     */
    protected static function clearModelCache($model_name)
    {
        global $wpdb;
        // Delete transients matching our pattern
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . static::$cache_group . '_' . $model_name . '_%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . static::$cache_group . '_' . $model_name . '_%'
        ));
    }

    /**
     * Enhanced findCached with caching - use this instead of find() when you need caching
     */
    public static function findCached($ID = 0)
    {
        if (empty($ID)) {
            return null;
        }

        $cache_key = static::getCacheKey('findCached', [$ID]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        // Use the WPMVC find method which properly handles the constructor
        $result = static::find($ID);
        
        if ($result) {
            static::setToCache($cache_key, $result);
        }
        
        return $result;
    }

    /**
     * Alias for findCached for backwards compatibility
     * You can use this in places where you were using custom find() with caching
     */
    public static function findWithCache($ID = 0)
    {
        return static::findCached($ID);
    }

    /**
     * Enhanced all with caching
     */
    public static function all($conditions = [])
    {
        $cache_key = static::getCacheKey('all', $conditions);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        $where_clause = '';
        $values = [];
        
        if (!empty($conditions)) {
            $where_parts = [];
            foreach ($conditions as $column => $value) {
                $where_parts[] = "$column = %s";
                $values[] = $value;
            }
            $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
        }
        
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY ID ASC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $models = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        static::setToCache($cache_key, $models);
        
        return $models;
    }

    /**
     * Enhanced count with caching
     */
    public static function count($conditions = [])
    {
        $cache_key = static::getCacheKey('count', $conditions);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        $where_clause = '';
        $values = [];
        
        if (!empty($conditions)) {
            $where_parts = [];
            foreach ($conditions as $column => $value) {
                $where_parts[] = "$column = %s";
                $values[] = $value;
            }
            $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
        }
        
        $query = "SELECT COUNT(*) FROM {$table} {$where_clause}";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $result = (int)$wpdb->get_var($query);
        static::setToCache($cache_key, $result);
        
        return $result;
    }

    /**
     * Enhanced search with caching
     */
    public static function search($term, $columns = ['*'])
    {
        $cache_key = static::getCacheKey('search', [$term, $columns]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        // Build search query for specified columns
        $where_parts = [];
        $values = [];
        $search_term = '%' . $wpdb->esc_like($term) . '%';
        
        foreach ($columns as $column) {
            if ($column !== '*') {
                $where_parts[] = "$column LIKE %s";
                $values[] = $search_term;
            }
        }
        
        if (empty($where_parts)) {
            return [];
        }
        
        $where_clause = '(' . implode(' OR ', $where_parts) . ')';
        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY ID ASC";
        
        $query = $wpdb->prepare($query, $values);
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $models = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        static::setToCache($cache_key, $models);
        
        return $models;
    }

    /**
     * Enhanced create with cache invalidation
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        // Set timestamps
        $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
        $attributes['updated_at'] = $attributes['updated_at'] ?? current_time('mysql');
        
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
        
        // Invalidate related caches
        static::invalidateCache();
        
        return new static($attributes);
    }

    /**
     * Enhanced update with cache invalidation
     */
    public function update(array $attributes)
    {
        global $wpdb;
        $table = $this->getTable();
        
        if (!isset($this->attributes['ID'])) {
            return false;
        }
        
        // Set updated timestamp
        $attributes['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table,
            $attributes,
            ['ID' => $this->attributes['ID']],
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes),
            ['%d']
        );
        
        if ($result !== false) {
            // Update local attributes
            foreach ($attributes as $key => $value) {
                $this->attributes[$key] = $value;
            }
            
            // Invalidate related caches
            static::invalidateCache();
            static::invalidateCache('findCached', [$this->attributes['ID']]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Enhanced delete with cache invalidation
     */
    public function delete()
    {
        global $wpdb;
        $table = $this->getTable();
        
        if (!isset($this->attributes['ID'])) {
            return false;
        }
        
        $result = $wpdb->delete(
            $table,
            ['ID' => $this->attributes['ID']],
            ['%d']
        );
        
        if ($result !== false) {
            // Invalidate related caches
            static::invalidateCache();
            static::invalidateCache('findCached', [$this->attributes['ID']]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Set cache enabled/disabled
     */
    public static function setCacheEnabled($enabled)
    {
        static::$cache_enabled = $enabled;
    }

    /**
     * Set cache expiration
     */
    public static function setCacheExpiration($seconds)
    {
        static::$cache_expiration = $seconds;
    }

    /**
     * Get cache settings
     */
    public static function getCacheSettings()
    {
        return [
            'enabled' => static::$cache_enabled,
            'expiration' => static::$cache_expiration,
            'group' => static::$cache_group
        ];
    }
}
