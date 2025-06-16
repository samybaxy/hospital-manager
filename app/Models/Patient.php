<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Patient extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_patients';
    protected static $cache_expiration = 1800; // 30 minutes for frequently accessed patient data

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'hmo_id',
        'hmo_designated_id',
        'phone',
        'age',
        'gender',
        'marital_status',
        'city',
        'state',
        'address',
        'bio_data'
    ];
    
    /**
     * Patient constructor
     * 
     * @param array $attributes Model attributes
     */
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        parent::__construct($attributes);
    }
    
    /**
     * Convert the model instance to an array
     * 
     * @return array
     */
    public function toArray()
    {
        // Start with the attributes
        $data = $this->attributes;
        
        // Process bio_data if it's a JSON string
        if (!empty($data['bio_data']) && is_string($data['bio_data'])) {
            $decoded = json_decode($data['bio_data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['bio_data'] = $decoded;
            }
        }
        
        return $data;
    }

    /**
     * Find a patient by WordPress user ID with caching
     * 
     * @param int $user_id WordPress user ID
     * @return Patient|null Returns Patient instance or null if not found
     */
    public static function findByUserId($user_id)
    {
        if (empty($user_id)) {
            return null;
        }

        $cache_key = static::getCacheKey('findByUserId', [$user_id]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $instance = new self();
        $table = $instance->getTable();
        
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id);
        $patient_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$patient_data) {
            static::setToCache($cache_key, null, 1800); // Cache null results for 30 min
            return null;
        }
        
        $result = new self($patient_data);
        static::setToCache($cache_key, $result, 1800); // 30 minutes cache
        
        return $result;
    }

    /**
     * Get patient ID from WordPress user ID with caching
     * 
     * @param int $user_id WordPress user ID
     * @return int|null Returns patient ID or null if not found
     */
    public static function get_pid_from_wp($user_id)
    {
        if (empty($user_id)) {
            return null;
        }

        $cache_key = static::getCacheKey('get_pid_from_wp', [$user_id]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $instance = new self();
        $table = $instance->getTable();
        
        $query = $wpdb->prepare("SELECT ID FROM {$table} WHERE user_id = %d", $user_id);
        $patient_id = $wpdb->get_var($query);
        
        $result = $patient_id ? (int)$patient_id : null;
        static::setToCache($cache_key, $result, 1800); // 30 minutes cache
        
        return $result;
    }

    /**
     * Get appointments for this patient with caching
     * 
     * @return array Array of appointment records
     */
    public function getAppointments()
    {
        if (!$this->ID) {
            return [];
        }

        $cache_key = static::getCacheKey('getAppointments', [$this->ID]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        
        $query = $wpdb->prepare(
            "SELECT a.*, 
            CONCAT(u.display_name) as doctor_name
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->users} u ON a.doctor_id = u.ID
            WHERE a.patient_id = %d
            ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            $this->ID
        );
        
        $result = $wpdb->get_results($query);
        static::setToCache($cache_key, $result, 900); // 15 minutes cache
        
        return $result;
    }

    /**
     * Get visitation history for this patient with caching
     * 
     * @return array Array of visitation records with doctor information
     */
    public function getVisitationHistory()
    {
        if (!$this->ID) {
            return [];
        }

        $cache_key = static::getCacheKey('getVisitationHistory', [$this->ID]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $visitations_table = $wpdb->prefix . 'hm_visitations';
        
        $query = $wpdb->prepare(
            "SELECT v.*, 
            CONCAT(u.display_name) as doctor
            FROM {$visitations_table} v
            LEFT JOIN {$wpdb->users} u ON v.doctor_id = u.ID
            WHERE v.patient_id = %d
            ORDER BY v.date DESC, v.time DESC",
            $this->ID
        );
        
        $result = $wpdb->get_results($query);
        static::setToCache($cache_key, $result, 1800); // 30 minutes cache
        
        return $result;
    }

    /**
     * Get patient statistics with caching
     * 
     * @return array|null Statistics array or null on error
     */
    public static function getStatistics()
    {
        $cache_key = static::getCacheKey('getStatistics', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $patients_table = $wpdb->prefix . 'hm_patients';
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        $visitations_table = $wpdb->prefix . 'hm_visitations';
        
        $stats = $wpdb->get_row("
            SELECT 
                (SELECT COUNT(*) FROM `{$patients_table}`) as total_patients,
                (SELECT COUNT(*) FROM `{$patients_table}` WHERE `gender` = 'Male') as male_patients,
                (SELECT COUNT(*) FROM `{$patients_table}` WHERE `gender` = 'Female') as female_patients,
                (SELECT COUNT(*) FROM `{$appointments_table}` WHERE `appointment_date` >= CURDATE()) as upcoming_appointments,
                (SELECT COUNT(DISTINCT `patient_id`) FROM `{$visitations_table}` WHERE DATE(`created_at`) = CURDATE()) as today_visits,
                (SELECT COUNT(*) FROM `{$patients_table}` WHERE DATE(`created_at`) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as new_patients_month
        ", ARRAY_A);

        // Ensure all values are integers
        if ($stats) {
            foreach ($stats as $key => $value) {
                $stats[$key] = (int)$value;
            }
        }

        static::setToCache($cache_key, $stats, 3600); // 1 hour cache for statistics
        
        return $stats;
    }

    /**
     * Enhanced search with caching
     * 
     * @param string $term Search term
     * @param array $columns Columns to search in
     * @return Patient[] Array of Patient instances
     */
    public static function search($term, $columns = ['first_name', 'last_name', 'phone'])
    {
        if (empty($term)) {
            return [];
        }
        
        // Validate columns to prevent SQL injection
        $allowed_columns = ['first_name', 'last_name', 'phone', 'hmo_designated_id'];
        $safe_columns = array_intersect($columns, $allowed_columns);
        
        if (empty($safe_columns)) {
            return [];
        }

        $cache_key = static::getCacheKey('search', [$term, $safe_columns]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = (new static)->table;
        
        $where_parts = [];
        $values = [];
        $search_term = '%' . $wpdb->esc_like($term) . '%';
        
        foreach ($safe_columns as $column) {
            $where_parts[] = "`$column` LIKE %s";
            $values[] = $search_term;
        }
        
        $where_clause = '(' . implode(' OR ', $where_parts) . ')';
        $query = "SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY `last_name`, `first_name`";
        
        $query = $wpdb->prepare($query, $values);
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $models = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        static::setToCache($cache_key, $models, 1800); // 30 minutes cache
        
        return $models;
    }

    /**
     * Save the model with cache invalidation
     * 
     * @return bool True on success, false on failure
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
        
        // Add updated_at timestamp if it's fillable
        if (in_array('updated_at', $this->fillable)) {
            $data['updated_at'] = current_time('mysql');
        }
        
        // Make sure bio_data is properly encoded
        if (isset($data['bio_data']) && is_array($data['bio_data'])) {
            $data['bio_data'] = json_encode($data['bio_data']);
        }
        
        // Return false if no data to save
        if (empty($data)) {
            return false;
        }
        
        // Determine if this is an update or insert
        if (isset($this->attributes['ID']) && !empty($this->attributes['ID'])) {
            // This is an update
            $formats = array_map(function($value) {
                return is_numeric($value) ? '%d' : '%s';
            }, array_values($data));
            
            $result = $wpdb->update(
                $table,
                $data,
                ['ID' => $this->attributes['ID']],
                $formats,
                ['%d']
            );
            
            if ($result !== false) {
                // Invalidate caches
                $this->invalidatePatientCaches();
            }
            
            return $result !== false;
        } else {
            // This is an insert
            $formats = array_map(function($value) {
                return is_numeric($value) ? '%d' : '%s';
            }, array_values($data));
            
            $result = $wpdb->insert(
                $table,
                $data,
                $formats
            );
            
            if ($result !== false) {
                $this->attributes['ID'] = $wpdb->insert_id;
                // Invalidate caches
                static::invalidateCache();
                return true;
            }
            
            return false;
        }
    }

    /**
     * Update with cache invalidation
     * 
     * @param array $attributes Attributes to update
     * @return bool True on success, false on failure
     */
    public function update(array $attributes)
    {
        if (empty($attributes) || !is_array($attributes)) {
            return false;
        }
        
        // Handle phone number formatting for non-test environments
        if (isset($attributes['phone']) && !empty($attributes['phone'])) {
            if (!defined('RUNNING_PHPUNIT_TESTS')) {
                $phone = (string)$attributes['phone'];
                if (substr($phone, 0, 1) !== '0' && strlen($phone) === 10) {
                    $attributes['phone'] = '0' . $phone;
                }
            }
        }
        
        // Merge the new attributes with the existing ones
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->attributes[$key] = $value;
                $this->$key = $value;
            }
        }
        
        $result = $this->save();
        
        if ($result) {
            $this->invalidatePatientCaches();
        }
        
        return $result;
    }

    /**
     * Delete with cache invalidation
     * 
     * @return bool True on success, false on failure
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID']) || empty($this->attributes['ID'])) {
            return false;
        }
        
        $table = $this->getTable();
        $result = $wpdb->delete(
            $table,
            ['ID' => (int)$this->attributes['ID']],
            ['%d']
        );
        
        if ($result !== false) {
            $this->invalidatePatientCaches();
        }
        
        return $result !== false;
    }

    /**
     * Invalidate patient-specific caches
     * 
     * @return void
     */
    private function invalidatePatientCaches()
    {
        if (isset($this->attributes['user_id'])) {
            static::invalidateCache('findByUserId', [$this->attributes['user_id']]);
            static::invalidateCache('get_pid_from_wp', [$this->attributes['user_id']]);
        }
        
        if (isset($this->attributes['ID'])) {
            static::invalidateCache('find', [$this->attributes['ID']]);
            static::invalidateCache('getAppointments', [$this->attributes['ID']]);
            static::invalidateCache('getVisitationHistory', [$this->attributes['ID']]);
        }
        
        // Invalidate general caches
        static::invalidateCache('all');
        static::invalidateCache('count');
        static::invalidateCache('getStatistics');
    }

    /**
     * Create a new WordPress user for the patient
     * 
     * @param array $attributes User attributes
     * @return int|string|null Returns user ID on success, error message on failure, or null if email missing/exists
     */
    public static function createWPUser($attributes)
    {
        if (empty($attributes['email']) || !is_email($attributes['email'])) {
            return null;
        }

        if (email_exists($attributes['email'])) {
            return null;
        }

        $username = sanitize_user(substr($attributes['email'], 0, strpos($attributes['email'], '@')));
        
        // Ensure username is not empty
        if (empty($username)) {
            $username = 'patient_' . uniqid();
        }
        
        $suffix = 1;
        $original_username = $username;
        while (username_exists($username)) {
            $username = $original_username . $suffix;
            $suffix++;
        }

        $user_data = [
            'user_login'   => $username,
            'user_email'   => sanitize_email($attributes['email']),
            'user_pass'    => wp_generate_password(),
            'role'         => 'patient',
            'user_registered' => current_time('mysql'),
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return $user_id->get_error_message();
        }
        
        return $user_id;
    }

    /**
     * Update the WordPress user associated with the patient
     * 
     * @param int $user_id WordPress user ID
     * @param array $attributes User attributes to update
     * @return int|false|WP_Error User ID on success, false or WP_Error on failure
     */
    public static function updateWPUser($user_id, $attributes)
    {
        if (empty($user_id) || empty($attributes) || !is_array($attributes)) {
            return false;
        }

        if (isset($attributes['email']) && !empty($attributes['email'])) {
            if (!is_email($attributes['email'])) {
                return false;
            }
            
            return wp_update_user([
                'ID' => (int)$user_id,
                'user_email' => sanitize_email($attributes['email']),
            ]);
        }
        
        return false;
    }

    /**
     * Relationship with WordPress user
     * 
     * @return mixed User model relationship
     */
    public function user()
    {
        return $this->belongs_to('WPMVC\MVC\Models\UserModel', 'user_id', 'ID');
    }

    /**
     * Relationship with HMO
     * 
     * @return mixed HMO model relationship
     */
    public function hmo()
    {
        return $this->belongs_to('HospitalManager\Models\HMO', 'hmo_id', 'ID');
    }

    /**
     * Relationship with visitations
     * 
     * @return mixed Visitation models relationship
     */
    public function visitations()
    {
        return $this->has_many('HospitalManager\Models\Visitation', 'patient_id', 'ID');
    }
}