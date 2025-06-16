<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class LabInvestigation extends BaseModel
{
    use FindTrait;

    protected $type = 'lab_investigation';
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_lab_investigations';
    protected static $cache_expiration = 1200; // 20 minutes
    
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
    
    /**
     * LabInvestigation constructor
     * 
     * @param int|array $attributes Model ID or attributes array
     */
    public function __construct($attributes = 0)
    {
        // Set the table name for this model
        $this->tableName = 'hm_lab_investigations';
        
        // Call parent constructor which handles the WPMVC logic
        parent::__construct($attributes);
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
        if (!isset($this->attributes['patient_id'])) {
            return null;
        }
        
        return Patient::find($this->attributes['patient_id']);
    }

    /**
     * Get pending lab tests for a patient with caching
     */
    public static function getPendingForPatient($patientId)
    {
        $cache_key = static::getCacheKey('getPendingForPatient', [$patientId]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE patient_id = %d 
            AND status IN ('requested', 'sample_collected', 'in_progress')
            ORDER BY created_at ASC",
            $patientId
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        $labs = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        static::setToCache($cache_key, $labs, 600); // 10 minutes for pending data
        
        return $labs;
    }

    /**
     * Get completed lab tests count for a technician today with caching
     */
    public static function getCompletedCountForTechToday($techId)
    {
        $cache_key = static::getCacheKey('getCompletedCountForTechToday', [$techId]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        $count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE lab_tech_id = %d 
            AND status = %s 
            AND DATE(created_at) = CURDATE()",
            $techId,
            'completed'
        ));
        
        static::setToCache($cache_key, $count, 300); // 5 minutes for today's count
        
        return $count;
    }

    /**
     * Get pending lab tests for a technician with caching
     */
    public static function getPendingForTech($techId = null, $limit = 10)
    {
        $cache_key = static::getCacheKey('getPendingForTech', [$techId ?: 'all', $limit]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
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
        $labs = array_map(function($item) {
            $model = new static($item);
            if (isset($item['first_name'])) {
                $model->patient_name = $item['first_name'] . ' ' . $item['last_name'];
            }
            if (isset($item['doctor_first_name'])) {
                $model->doctor_name = $item['doctor_first_name'] . ' ' . $item['doctor_last_name'];
            }
            return $model;
        }, $results ?: []);
        
        static::setToCache($cache_key, $labs, 600); // 10 minutes for pending data
        
        return $labs;
    }

    /**
     * Get all investigations for a patient with caching
     */
    public static function getForPatient($patientId, $limit = null)
    {
        $cache_key = static::getCacheKey('getForPatient', [$patientId, $limit ?: 'all']);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        $query = "SELECT * FROM {$table} WHERE patient_id = %d ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT %d";
            $results = $wpdb->get_results($wpdb->prepare($query, $patientId, $limit), ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare($query, $patientId), ARRAY_A);
        }
        
        $labs = array_map(function($data) {
            return new static($data);
        }, $results);
        
        static::setToCache($cache_key, $labs, 1200); // 20 minutes
        
        return $labs;
    }

    /**
     * Get lab investigation statistics with caching
     */
    public static function getStatistics()
    {
        $cache_key = static::getCacheKey('getStatistics', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $instance = new static();
        $table = $instance->getTable();
        
        $stats = [
            'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'pending' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status IN (%s, %s, %s)",
                'requested', 'sample_collected', 'in_progress'
            )),
            'completed_today' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s AND DATE(created_at) = CURDATE()",
                'completed'
            )),
            'abnormal_today' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE is_abnormal = 1 AND DATE(created_at) = CURDATE()"
            )),
            'critical_today' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE is_critical = 1 AND DATE(created_at) = CURDATE()"
            ))
        ];
        
        static::setToCache($cache_key, $stats, 1800); // 30 minutes
        
        return $stats;
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
     * Update the status of the lab investigation
     */
    public function updateStatus($status)
    {
        $this->attributes['status'] = $status;
        $this->status = $status;
        
        $result = $this->save();
        
        if ($result) {
            $this->invalidateLabInvestigationCaches();
        }
        
        return $result;
    }

    /**
     * Update test results and flags
     */
    public function updateResults($results, $flags = null, $lab_notes = null)
    {
        $this->attributes['test_results'] = is_array($results) ? json_encode($results) : $results;
        $this->attributes['status'] = 'completed';
        $this->attributes['updated_at'] = current_time('mysql');
        
        if ($flags !== null) {
            $this->attributes['flags'] = is_array($flags) ? json_encode($flags) : $flags;
        }
        
        if ($lab_notes !== null) {
            $this->attributes['lab_notes'] = $lab_notes;
        }
        
        // Check for abnormal or critical values
        if (is_array($results)) {
            $this->attributes['is_abnormal'] = $this->checkAbnormalResults($results);
            $this->attributes['is_critical'] = $this->checkCriticalResults($results);
        }
        
        $result = $this->save();
        
        if ($result) {
            $this->invalidateLabInvestigationCaches();
        }
        
        return $result;
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
     * Save the model and invalidate related caches
     */
    public function save()
    {
        $result = parent::save();
        
        if ($result) {
            $this->invalidateLabInvestigationCaches();
        }
        
        return $result;
    }
    
    /**
     * Delete the model and invalidate related caches
     */
    public function delete()
    {
        $result = parent::delete();
        
        if ($result) {
            $this->invalidateLabInvestigationCaches();
        }
        
        return $result;
    }

    /**
     * Invalidate all lab investigation-related caches
     */
    private function invalidateLabInvestigationCaches()
    {
        // Clear general statistics cache
        static::invalidateCache('getStatistics');
        
        // Clear patient-specific caches if patient_id exists
        if (isset($this->attributes['patient_id'])) {
            $patientId = $this->attributes['patient_id'];
            static::invalidateCache('getPendingForPatient', [$patientId]);
            static::invalidateCache('getForPatient', [$patientId, 'all']);
            
            // Clear limited result caches (common limits)
            foreach ([5, 10, 20, 50] as $limit) {
                static::invalidateCache('getForPatient', [$patientId, $limit]);
            }
        }
        
        // Clear technician-specific caches if lab_tech_id exists
        if (isset($this->attributes['lab_tech_id'])) {
            $techId = $this->attributes['lab_tech_id'];
            static::invalidateCache('getCompletedCountForTechToday', [$techId]);
            
            // Clear pending caches
            foreach ([5, 10, 20, 50] as $limit) {
                static::invalidateCache('getPendingForTech', [$techId, $limit]);
                static::invalidateCache('getPendingForTech', ['all', $limit]);
            }
        }
    }
}
