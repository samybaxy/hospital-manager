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
     * $wpdb placeholder for a value, based on its PHP type — never on whether a
     * string *looks* numeric. Choosing '%d' for is_numeric() strings turned
     * "08012345678" into 8012345678 (every Nigerian phone number), "000123" into 123
     * and "55.64" into 55. Numeric strings are sent as '%s'; MySQL converts them for
     * INT/DECIMAL columns itself. (NULL values are written as NULL by $wpdb.)
     *
     * @param mixed $value
     * @return string '%d' | '%f' | '%s'
     */
    public static function sqlFormat($value)
    {
        if (is_int($value) || is_bool($value)) {
            return '%d';
        }
        if (is_float($value)) {
            return '%f';
        }
        return '%s';
    }

    /**
     * Find a record by primary key.
     *
     * Overrides WPMVC's FindTrait::find(), which does `new self($id)`. Inside a trait
     * `self` is the class that *uses* the trait — here the abstract BaseModel — so any
     * subclass that doesn't re-declare the trait (all Inventory* models) fatalled with
     * "Cannot instantiate abstract class". `new static()` + load() works for every
     * subclass, including ones whose constructors only accept an attributes array.
     *
     * @param int $id Record ID
     * @return static|null
     */
    public static function find($id = 0)
    {
        if (empty($id)) {
            return null;
        }

        $model = new static();
        $model->load($id);

        return $model->has_trace() ? $model : null;
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
    /**
     * Raw column => value map for this record (custom-table models keep data in
     * protected $attributes, so json_encode() on the model itself yields "{}").
     *
     * @return array
     */
    public function getAttributes()
    {
        return is_array($this->attributes) ? $this->attributes : [];
    }

    /**
     * Array form for API responses. Controllers call ->toArray() on models; models
     * that didn't define it (Appointment, Visitation, ...) fatalled with "undefined
     * method". Subclasses (Patient, Doctor) override this to add formatting.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }

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
        $model_name = static::cacheModelName();
        $params_hash = md5(serialize($params));
        // The generation makes "clear every cache for this model" a single counter
        // bump that works with any cache backend (see clearModelCache()).
        $generation = static::cacheGeneration($model_name);
        // Plus the global data generation, bumped by any write to a hospital table
        // (BaseService::watchWrites()). Many models write with raw $wpdb calls that
        // never reached invalidateCache(), so cached reads went stale.
        $data_generation = \HospitalManager\Services\BaseService::currentDataGeneration();
        return static::$cache_group . '_' . $model_name . '_g' . $generation . '.' . $data_generation . '_' . $method . '_' . $params_hash;
    }

    protected static function cacheModelName()
    {
        return strtolower(basename(str_replace('\\', '/', get_called_class())));
    }

    /**
     * Current cache generation for a model (stored as a non-autoloaded option, so it
     * is read/written through WordPress' cache API and stays coherent).
     */
    protected static function cacheGeneration($model_name)
    {
        return (int) get_option(static::$cache_group . '_cache_gen_' . $model_name, 1);
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

        // Invalidate: bump the generation so every existing key for this model is
        // orphaned. Deleting rows with raw SQL (below) is not enough on its own: it
        // bypasses the object cache, so within a request — and on sites with a
        // persistent object cache, where transients never touch wp_options — stale
        // values kept being served (e.g. Patient::count() after creating patients).
        $option = static::$cache_group . '_cache_gen_' . $model_name;
        update_option($option, static::cacheGeneration($model_name) + 1, false);

        // Housekeeping: drop orphaned rows from wp_options (no-op with an object cache)
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
            // Scalars come back from wp_options as strings ("101")
            return (int) $cached;
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
                return \HospitalManager\Models\BaseModel::sqlFormat($field);
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
    /**
     * Insert or update this record in the model's own table.
     *
     * Without this, models that don't define save() (Visitation, LabInvestigation, ...)
     * fell through to WPMVC's PostModel::save(), which targets wp_posts: their edits
     * were silently discarded.
     *
     * Only real table columns are written, so attributes added by joins (doctor_name,
     * patient_name, ...) are ignored. Arrays/objects are stored as JSON.
     *
     * @return bool
     */
    public function save()
    {
        global $wpdb;
        $table = $this->getTable();
        if (empty($table)) {
            return false;
        }

        $columns = static::tableColumns($table);
        $data = [];
        foreach ($this->getAttributes() as $key => $value) {
            if ($key === $this->primaryKey || !in_array($key, $columns, true)) {
                continue;
            }
            $data[$key] = (is_array($value) || is_object($value)) ? wp_json_encode($value) : $value;
        }

        $now = current_time('mysql');
        if (in_array('updated_at', $columns, true)) {
            $data['updated_at'] = $now;
        }

        $formats = array_map([self::class, 'sqlFormat'], $data);
        $id = $this->attributes[$this->primaryKey] ?? null;

        if (!empty($id)) {
            $result = $wpdb->update($table, $data, [$this->primaryKey => $id], $formats, ['%d']);
            $ok = $result !== false;
        } else {
            if (in_array('created_at', $columns, true) && empty($data['created_at'])) {
                $data['created_at'] = $now;
                $formats['created_at'] = '%s';
            }
            $ok = (bool) $wpdb->insert($table, $data, $formats);
            if ($ok) {
                $this->attributes[$this->primaryKey] = $wpdb->insert_id;
            }
        }

        if ($ok) {
            foreach ($data as $key => $value) {
                $this->attributes[$key] = $value;
            }
            static::invalidateCache();
            static::invalidateCache('findCached', [$this->attributes[$this->primaryKey]]);
        }

        return $ok;
    }

    /**
     * Update a record by ID. Only real table columns are written (see save()).
     * Controllers call this (e.g. PUT /visitations/{id}); models without it fatalled.
     *
     * @param int   $id
     * @param array $data
     * @return static|false The updated model, or false if not found / not saved
     */
    public static function updateById($id, $data)
    {
        $model = static::find($id);
        if (!$model) {
            return false;
        }

        $primary = $model->primaryKey;
        foreach ((array) $data as $key => $value) {
            if ($key !== $primary) {
                $model->attributes[$key] = $value;
            }
        }

        return $model->save() ? $model : false;
    }

    /**
     * Delete a record by ID.
     *
     * @param int $id
     * @return bool False if the record doesn't exist or couldn't be deleted
     */
    public static function deleteById($id)
    {
        $model = static::find($id);
        return $model ? (bool) $model->delete() : false;
    }

    /**
     * Column names of a table (cached per request).
     *
     * @param string $table
     * @return string[]
     */
    protected static function tableColumns($table)
    {
        static $columns = [];
        if (!isset($columns[$table])) {
            global $wpdb;
            $columns[$table] = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`") ?: [];
        }
        return $columns[$table];
    }

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
                return \HospitalManager\Models\BaseModel::sqlFormat($field);
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
