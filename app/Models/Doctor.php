<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Doctor extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_doctors';
    protected static $cache_expiration = 3600; // 1 hour for doctor data
    
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'specialty',
        'status',
        'office',
        'board_certification',
        'education',
        'years_experience',
        'license_number',
        'appointment_availability',
    ];
    
    /**
     * Doctor constructor
     * 
     * @param int|array $attributes Model ID or attributes array
     */
    public function __construct($attributes = 0)
    {
        // Set the table name for this model
        $this->tableName = 'hm_doctors';
        
        // Call parent constructor which handles the WPMVC logic
        parent::__construct($attributes);
    }

    /**
     * Find doctors by specialty with caching
     */
    public static function findBySpecialty($specialty)
    {
        $cache_key = static::getCacheKey('findBySpecialty', [$specialty]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE specialty = %s AND status = 'active'",
                $specialty
            ),
            ARRAY_A
        );
        
        $models = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        static::setToCache($cache_key, $models, 3600); // 1 hour cache
        
        return $models;
    }

    /**
     * Get active doctors with caching
     */
    public static function getActive()
    {
        $cache_key = static::getCacheKey('getActive', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'active' ORDER BY last_name, first_name",
            ARRAY_A
        );
        
        $models = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        static::setToCache($cache_key, $models, 3600); // 1 hour cache
        
        return $models;
    }

    /**
     * Get doctor statistics with caching
     */
    public static function getStatistics()
    {
        $cache_key = static::getCacheKey('getStatistics', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        
        $stats = $wpdb->get_row("
            SELECT 
                (SELECT COUNT(*) FROM {$doctors_table}) as total_doctors,
                (SELECT COUNT(*) FROM {$doctors_table} WHERE status = 'active') as active_doctors,
                (SELECT COUNT(DISTINCT specialty) FROM {$doctors_table} WHERE status = 'active') as specialties_count,
                (SELECT COUNT(*) FROM {$appointments_table} WHERE appointment_date >= CURDATE()) as upcoming_appointments,
                (SELECT AVG(years_experience) FROM {$doctors_table} WHERE years_experience > 0) as avg_experience
        ", ARRAY_A);

        static::setToCache($cache_key, $stats, 3600); // 1 hour cache
        
        return $stats;
    }

    /**
     * Get all specialties with caching
     */
    public static function getSpecialties()
    {
        $cache_key = static::getCacheKey('getSpecialties', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = (new static)->table;
        
        $specialties = $wpdb->get_col("
            SELECT DISTINCT specialty 
            FROM $table 
            WHERE status = 'active' AND specialty IS NOT NULL AND specialty != ''
            ORDER BY specialty ASC
        ");
        
        static::setToCache($cache_key, $specialties, 7200); // 2 hours cache (rarely changes)
        
        return $specialties;
    }

    /**
     * Get appointments for this doctor with caching
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
        $patients_table = $wpdb->prefix . 'hm_patients';
        
        $query = $wpdb->prepare(
            "SELECT a.*, 
            CONCAT(p.first_name, ' ', p.last_name) as patient_name
            FROM {$appointments_table} a
            LEFT JOIN {$patients_table} p ON a.patient_id = p.ID
            WHERE a.doctor_id = %d
            ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            $this->ID
        );
        
        $result = $wpdb->get_results($query);
        static::setToCache($cache_key, $result, 900); // 15 minutes cache (frequently changing)
        
        return $result;
    }

    /**
     * Enhanced search with caching and pagination
     */
    public static function searchAndPaginate($search = null, $page = 1, $perPage = 20, $status = null, $specialty = null, $orderby = 'last_name', $order = 'asc')
    {
        $cache_key = static::getCacheKey('searchAndPaginate', func_get_args());
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = (new static)->table;
        $offset = ($page - 1) * $perPage;
        
        // Build where clause
        $where_parts = [];
        $values = [];
        
        if (!empty($status)) {
            $where_parts[] = "status = %s";
            $values[] = $status;
        }
        
        if (!empty($specialty)) {
            $where_parts[] = "specialty = %s";
            $values[] = $specialty;
        }
        
        if (!empty($search)) {
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $where_parts[] = "(first_name LIKE %s OR last_name LIKE %s OR specialty LIKE %s)";
            $values[] = $search_param;
            $values[] = $search_param;
            $values[] = $search_param;
        }
        
        $where_clause = !empty($where_parts) ? "WHERE " . implode(' AND ', $where_parts) : '';
        
        // Validate orderby and order
        $allowed_order_fields = ['ID', 'first_name', 'last_name', 'specialty', 'created_at', 'updated_at'];
        if (!in_array($orderby, $allowed_order_fields)) {
            $orderby = 'last_name';
        }
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        
        // Count total records
        $count_query = "SELECT COUNT(*) FROM $table $where_clause";
        $prepared_count = !empty($values) ? $wpdb->prepare($count_query, $values) : $count_query;
        $total = (int)$wpdb->get_var($prepared_count);
        
        // Get records
        $query = "SELECT * FROM $table $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $all_values = array_merge($values, [$perPage, $offset]);
        $prepared_query = $wpdb->prepare($query, $all_values);
        
        $results = $wpdb->get_results($prepared_query, ARRAY_A);
        
        $doctors = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        $result = [
            'data' => $doctors,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];

        static::setToCache($cache_key, $result, 1800); // 30 minutes cache
        
        return $result;
    }

    /**
     * Save with cache invalidation
     */
    public function save()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->tableName;
        
        if (isset($this->attributes['ID']) && intval($this->attributes['ID']) > 0) {
            // Prepare the update data
            $update_data = [];
            
            foreach ($this->fillable as $field) {
                if (isset($this->attributes[$field])) {
                    $update_data[$field] = $this->attributes[$field];
                }
            }
            
            $update_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table,
                $update_data,
                ['ID' => $this->attributes['ID']],
                null,
                ['%d']
            );
            
            if ($result !== false) {
                $this->invalidateDoctorCaches();
            }
            
            return $result !== false;
        }
        
        return false;
    }

    /**
     * Delete with cache invalidation
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID']) || intval($this->attributes['ID']) <= 0) {
            return false;
        }
        
        $table = $wpdb->prefix . $this->tableName;
        
        $result = $wpdb->delete(
            $table,
            ['ID' => $this->attributes['ID']],
            ['%d']
        );
        
        if ($result !== false) {
            $this->invalidateDoctorCaches();
        }
        
        return $result !== false;
    }

    /**
     * Invalidate doctor-specific caches
     */
    private function invalidateDoctorCaches()
    {
        // Invalidate specific caches
        if (isset($this->attributes['ID'])) {
            static::invalidateCache('findCached', [$this->attributes['ID']]);
            static::invalidateCache('getAppointments', [$this->attributes['ID']]);
        }
        
        if (isset($this->attributes['specialty'])) {
            static::invalidateCache('findBySpecialty', [$this->attributes['specialty']]);
        }
        
        // Invalidate general caches
        static::invalidateCache('getActive');
        static::invalidateCache('getSpecialties');
        static::invalidateCache('getStatistics');
        static::invalidateCache('all');
        static::invalidateCache('count');
    }

    /**
     * Convert to array for API responses
     */
    public function toArray()
    {
        return [
            'ID' => $this->attributes['ID'] ?? null,
            'first_name' => $this->attributes['first_name'] ?? '',
            'last_name' => $this->attributes['last_name'] ?? '',
            'fullName' => ($this->attributes['first_name'] ?? '') . ' ' . ($this->attributes['last_name'] ?? ''),
            'phone' => $this->attributes['phone'] ?? '',
            'email' => $this->attributes['email'] ?? '',
            'specialty' => $this->attributes['specialty'] ?? '',
            'license_number' => $this->attributes['license_number'] ?? '',
            'years_experience' => $this->attributes['years_experience'] ?? null,
            'education' => $this->attributes['education'] ?? '',
            'certification' => $this->attributes['board_certification'] ?? '',
            'office' => $this->attributes['office'] ?? '',
            'department' => $this->attributes['department'] ?? '',
            'status' => $this->attributes['status'] ?? 'active',
            'appointment_availability' => $this->attributes['appointment_availability'] ?? '',
            'created_at' => $this->attributes['created_at'] ?? null,
            'updated_at' => $this->attributes['updated_at'] ?? null
        ];
    }
}

