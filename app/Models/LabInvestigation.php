<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class LabInvestigation extends BaseModel
{
    use FindTrait;

    protected $type = 'lab_investigation';
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_lab_investigations';
    protected static $conditions = [];
    protected static $orderBy = [];
    protected static $queryType = 'static';
    
    protected $fillable = [
        'visitation_id',
        'patient_id',
        'doctor_id',
        'lab_tech_id',
        'test_type',
        'sample_type',
        'request_notes',
        'lab_notes',
        'test_results',
        'flags',
        'is_abnormal',
        'is_critical',
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
        }
        
        // Don't call parent constructor for custom database models
        // parent::__construct($attributes);
    }

    /**
     * Find a lab investigation by ID
     * 
     * @param int $ID The lab investigation ID
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
        wp_cache_delete($ID, 'hm_lab_investigations');
        
        // Add SQL_NO_CACHE to prevent MySQL query caching issues
        $query = $wpdb->prepare("SELECT SQL_NO_CACHE * FROM {$table} WHERE ID = %d LIMIT 1", $ID);
        error_log("Finding lab investigation with query: {$query}");
        
        // Use no_found_rows to improve performance and suppress filters
        $lab_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$lab_data) {
            error_log("No lab investigation found with ID: {$ID}");
            return null;
        }
        
        error_log("Lab investigation found: " . print_r($lab_data, true));
        
        // Create a new instance with the fetched data
        return new static($lab_data);
    }
    
    /**
     * Relationship with visitation
     */
    public function visitation()
    {
        if (!isset($this->attributes['visitation_id'])) {
            return null;
        }
        
        return Visitation::find($this->attributes['visitation_id']);
    }

    /**
     * Relationship with lab technician (WordPress user)
     */
    public function labTech()
    {
        return $this->belongs_to('WPMVC\MVC\Models\UserModel', 'lab_tech_id');
    }

    /**
     * Relationship with requesting doctor (Doctor model)
     */
    public function requestedBy()
    {
        if (!isset($this->attributes['doctor_id'])) {
            return null;
        }
        
        return Doctor::find($this->attributes['doctor_id']);
    }

    /**
     * Relationship with patient
     */
    public function patient()
    {
        return $this->belongs_to('HospitalManager\Models\Patient', 'patient_id');
    }

    /**
     * Create a new lab investigation
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        
        try {
            $instance = new static();
            $table = $instance->getTable();
            
            error_log("LabInvestigation::create() - Table: " . $table);

            // Set default values
            $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
            $attributes['status'] = $attributes['status'] ?? 'requested';
            $attributes['is_abnormal'] = $attributes['is_abnormal'] ?? 0;
            $attributes['is_critical'] = $attributes['is_critical'] ?? 0;
            
            // Ensure JSON fields are properly encoded
            if (isset($attributes['test_results']) && is_array($attributes['test_results'])) {
                $attributes['test_results'] = json_encode($attributes['test_results']);
            }
            
            if (isset($attributes['flags']) && is_array($attributes['flags'])) {
                $attributes['flags'] = json_encode($attributes['flags']);
            }
            
            $result = $wpdb->insert(
                $table,
                $attributes,
                array_map(function($value) {
                    if (is_int($value) || (is_string($value) && is_numeric($value) && (int)$value == $value)) {
                        return '%d';
                    }
                    return '%s';
                }, $attributes)
            );
            
            if ($result === false) {
                error_log("LabInvestigation::create() - Database error: " . $wpdb->last_error);
                throw new \Exception($wpdb->last_error);
            }

            $attributes['ID'] = $wpdb->insert_id;
            error_log("LabInvestigation::create() - Insert ID: " . $wpdb->insert_id);
            
            $new_instance = new static($attributes);
            error_log("LabInvestigation::create() - Instance created successfully with attributes: " . (isset($new_instance->attributes) ? 'YES' : 'NO'));
            error_log("LabInvestigation::create() - Attributes ID: " . (isset($new_instance->attributes['ID']) ? $new_instance->attributes['ID'] : 'NOT_SET'));
            error_log("LabInvestigation::create() - Attributes count: " . (isset($new_instance->attributes) ? count($new_instance->attributes) : '0'));
            
            return $new_instance;
            
        } catch (\Exception $e) {
            error_log("LabInvestigation::create() - Exception: " . $e->getMessage());
            throw $e;
        }
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
     * Query builder: where clause
     */
    public static function where($conditions)
    {
        // Handle array of conditions
        if (is_array($conditions)) {
            foreach ($conditions as $column => $value) {
                static::$conditions[] = [$column, '=', $value];
            }
            return new static();
        }

        // Handle method chaining
        $args = func_get_args();
        if (count($args) === 2) {
            static::$conditions[] = [$args[0], '=', $args[1]];
        } elseif (count($args) === 3) {
            static::$conditions[] = [$args[0], $args[1], $args[2]];
        }

        return new static();
    }

    /**
     * Query builder: order by
     */
    public function orderBy($column, $direction = 'ASC')
    {
        error_log('orderBy called with column: ' . var_export($column, true) . ', direction: ' . var_export($direction, true));
        
        // Ensure direction is not null before calling strtoupper
        if ($direction === null) {
            error_log('WARNING: orderBy direction is null, defaulting to ASC');
            $direction = 'ASC';
        }
        
        $upper_direction = strtoupper($direction);
        error_log('orderBy upper direction: ' . $upper_direction);
        
        static::$orderBy[] = [$column, $upper_direction];
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

        // Reset static properties
        static::$conditions = [];
        static::$orderBy = [];
        static::$queryType = 'static';

        $results = $wpdb->get_results($query, ARRAY_A);
        
        return array_map(function($item) {
            return new static($item);
        }, $results ?: []);
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
            if (is_array($condition[2])) {
                $placeholders = array_fill(0, count($condition[2]), '%s');
                $query .= $wpdb->prepare(
                    " AND {$condition[0]} {$condition[1]} (" . implode(',', $placeholders) . ")",
                    $condition[2]
                );
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
     * Update the lab investigation
     */
    public function update(array $attributes)
    {
        error_log('=== LabInvestigation::update() START ===');
        error_log('Raw attributes received: ' . print_r($attributes, true));
        
        global $wpdb;
        
        // Debug: Check current instance state
        error_log('Current instance ID: ' . (isset($this->ID) ? $this->ID : 'NOT_SET'));
        error_log('Current instance attributes ID: ' . (isset($this->attributes['ID']) ? $this->attributes['ID'] : 'NOT_SET'));
        error_log('Table name: ' . $this->table);
        
        // Filter out null values and prepare format array
        $clean_attributes = array_filter($attributes, function($value) {
            $is_valid = $value !== null && $value !== '';
            error_log("Filtering value: " . var_export($value, true) . " -> " . ($is_valid ? 'KEEP' : 'REMOVE'));
            return $is_valid;
        });
        
        error_log('Clean attributes after filtering: ' . print_r($clean_attributes, true));
        
        // Build format array based on field types
        $formats = [];
        foreach ($clean_attributes as $key => $value) {
            if (in_array($key, ['visitation_id', 'patient_id', 'doctor_id', 'lab_tech_id', 'is_abnormal', 'is_critical'])) {
                $formats[] = '%d';
                error_log("Field '{$key}' -> format: %d, value: " . var_export($value, true));
            } else {
                $formats[] = '%s';
                error_log("Field '{$key}' -> format: %s, value: " . var_export($value, true));
            }
        }
        
        error_log('Final formats array: ' . print_r($formats, true));
        
        // Debug: Show the exact update parameters
        error_log('wpdb->update parameters:');
        error_log('  - table: ' . $this->table);
        error_log('  - data: ' . print_r($clean_attributes, true));
        error_log('  - where: ' . print_r(['ID' => $this->ID], true));
        error_log('  - format: ' . print_r($formats, true));
        error_log('  - where_format: %d');
        
        $result = $wpdb->update(
            $this->table,
            $clean_attributes,
            ['ID' => $this->ID],
            $formats,
            ['%d']
        );
        
        error_log('wpdb->update result: ' . var_export($result, true));
        error_log('wpdb->last_error: ' . $wpdb->last_error);
        error_log('wpdb->last_query: ' . $wpdb->last_query);

        if ($result === false) {
            error_log('Update failed with error: ' . $wpdb->last_error);
            throw new \Exception($wpdb->last_error);
        }

        error_log('Update successful, updating instance attributes...');
        foreach ($clean_attributes as $key => $value) {
            $this->$key = $value;
            if (isset($this->attributes)) {
                $this->attributes[$key] = $value;
            }
            error_log("Updated attribute '{$key}' to: " . var_export($value, true));
        }

        error_log('=== LabInvestigation::update() END ===');
        return $this;
    }

    /**
     * Get pending lab tests for a patient
     */
    public static function getPendingForPatient($patientId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE patient_id = %d 
            AND status IN ('requested', 'sample_collected', 'in_progress')",
            $patientId
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {
            return new static($item);
        }, $results ?: []);
    }

    /**
     * Get completed lab tests count for a technician today
     */
    public static function getCompletedCountForTechToday($techId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE lab_tech_id = %d 
            AND status = %s 
            AND DATE(created_at) = CURDATE()",
            $techId,
            'completed'
        ));
    }

    /**
     * Get pending lab tests for a technician
     */
    public static function getPendingForTech($techId = null, $limit = 10)
    {
        global $wpdb;
        $table = (new static)->table;
        
        if ($techId) {
            $query = $wpdb->prepare(
                "SELECT l.*, p.first_name, p.last_name, d.first_name as doctor_first_name, d.last_name as doctor_last_name
                FROM {$table} l
                LEFT JOIN {$wpdb->prefix}hm_patients p ON l.patient_id = p.ID
                LEFT JOIN {$wpdb->prefix}hm_doctors d ON l.doctor_id = d.ID
                WHERE l.lab_tech_id = %d 
                AND l.status IN ('requested', 'sample_collected', 'in_progress')
                ORDER BY l.created_at ASC
                LIMIT %d",
                $techId,
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT l.*, p.first_name, p.last_name, d.first_name as doctor_first_name, d.last_name as doctor_last_name
                FROM {$table} l
                LEFT JOIN {$wpdb->prefix}hm_patients p ON l.patient_id = p.ID
                LEFT JOIN {$wpdb->prefix}hm_doctors d ON l.doctor_id = d.ID
                WHERE l.status IN ('requested', 'sample_collected', 'in_progress')
                ORDER BY l.created_at ASC
                LIMIT %d",
                $limit
            );
        }

        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {
            $model = new static($item);
            if (isset($item['first_name'])) {
                $model->patient_name = $item['first_name'] . ' ' . $item['last_name'];
            }
            if (isset($item['doctor_first_name'])) {
                $model->doctor_name = $item['doctor_first_name'] . ' ' . $item['doctor_last_name'];
            }
            return $model;
        }, $results ?: []);
    }
    
    /**
     * Save the current lab investigation to the database
     * 
     * @return bool Success status
     */
    public function save()
    {
        global $wpdb;
    
        // Make sure we have a table name
        $table = $wpdb->prefix . $this->tableName;

        // Ensure timestamps are set
        if (!isset($this->attributes['updated_at'])) {
            $this->attributes['updated_at'] = current_time('mysql');
        }
        
        // Get the primary key and value
        $primary_key = $this->primaryKey;
        $ID = isset($this->attributes[$primary_key]) ? $this->attributes[$primary_key] : null;
        
        // If we have an ID, update the record, otherwise insert a new one
        if (!empty($ID)) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                $this->attributes,
                array($primary_key => $ID)
            );
            
            return $result !== false;
        } else {
            // Insert new record
            $result = $wpdb->insert($table, $this->attributes);
            
            if ($result) {
                // Set the ID on the instance
                $this->attributes[$primary_key] = $wpdb->insert_id;
                return true;
            }
            
            return false;
        }
    }
    
    /**
     * Delete the current lab investigation from the database
     * 
     * @return bool Success status
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID']) || intval($this->attributes['ID']) <= 0) {
            return false;
        }
        
        // Make sure we have the table name
        $table = $wpdb->prefix . $this->tableName;
        
        // Debug log
        error_log('Deleting lab investigation with ID: ' . $this->attributes['ID'] . ' from table: ' . $table);
        
        // Delete the record
        $result = $wpdb->delete(
            $table,
            ['ID' => $this->attributes['ID']],
            ['%d']
        );
        
        if ($result === false) {
            error_log('Failed to delete lab investigation: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }

    /**
     * Update the status of the lab investigation
     * 
     * @param string $status The new status
     * @return bool Success status
     */
    public function updateStatus($status)
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID']) || intval($this->attributes['ID']) <= 0) {
            return false;
        }
        
        // Update both the attribute and direct property
        $this->attributes['status'] = $status;
        $this->status = $status;
        
        $result = $wpdb->update(
            $this->table,
            ['status' => $status],
            ['ID' => $this->attributes['ID']],
            ['%s'],
            ['%d']
        );
        
        error_log("Status update query: " . $wpdb->last_query);
        
        if ($result === false) {
            error_log("Failed to update status: " . $wpdb->last_error);
            return false;
        }
        
        error_log("Status updated successfully to: {$status}");
        return true;
    }

    /**
     * Update test results and flags
     */
    public function updateResults($results, $flags = null, $lab_notes = null)
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID']) || intval($this->attributes['ID']) <= 0) {
            return false;
        }
        
        $update_data = [
            'test_results' => is_array($results) ? json_encode($results) : $results,
            'status' => 'completed',
            'updated_at' => current_time('mysql')
        ];
        
        if ($flags !== null) {
            $update_data['flags'] = is_array($flags) ? json_encode($flags) : $flags;
        }
        
        if ($lab_notes !== null) {
            $update_data['lab_notes'] = $lab_notes;
        }
        
        // Check for abnormal or critical values
        if (is_array($results)) {
            $update_data['is_abnormal'] = $this->checkAbnormalResults($results);
            $update_data['is_critical'] = $this->checkCriticalResults($results);
        }
        
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['ID' => $this->attributes['ID']],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
        
        if ($result !== false) {
            // Update instance attributes
            foreach ($update_data as $key => $value) {
                $this->attributes[$key] = $value;
            }
        }
        
        return $result !== false;
    }

    /**
     * Check if results contain abnormal values
     */
    protected function checkAbnormalResults($results)
    {
        if (!is_array($results)) {
            return 0;
        }
        
        foreach ($results as $parameter) {
            if (isset($parameter['is_abnormal']) && $parameter['is_abnormal']) {
                return 1;
            }
        }
        
        return 0;
    }

    /**
     * Check if results contain critical values
     */
    protected function checkCriticalResults($results)
    {
        if (!is_array($results)) {
            return 0;
        }
        
        foreach ($results as $parameter) {
            if (isset($parameter['is_critical']) && $parameter['is_critical']) {
                return 1;
            }
        }
        
        return 0;
    }

    /**
     * Get formatted test results
     */
    public function getFormattedResults()
    {
        if (!isset($this->attributes['test_results'])) {
            return null;
        }
        
        $results = is_string($this->attributes['test_results']) 
            ? json_decode($this->attributes['test_results'], true) 
            : $this->attributes['test_results'];
            
        return $results;
    }

    /**
     * Get formatted flags
     */
    public function getFormattedFlags()
    {
        if (!isset($this->attributes['flags'])) {
            return null;
        }
        
        $flags = is_string($this->attributes['flags']) 
            ? json_decode($this->attributes['flags'], true) 
            : $this->attributes['flags'];
            
        return $flags;
    }

    /**
     * Get all investigations for a patient
     */
    public static function getForPatient($patientId, $limit = null)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = "SELECT * FROM {$table} WHERE patient_id = %d ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT %d";
            $results = $wpdb->get_results($wpdb->prepare($query, $patientId, $limit), ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare($query, $patientId), ARRAY_A);
        }
        
        return array_map(function($data) {
            return new static($data);
        }, $results);
    }
}
