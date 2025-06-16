<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Visitation extends BaseModel
{
    use FindTrait;
    
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_visitations';
    protected $cache_expiration = 1200; // 20 minutes

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'date',
        'time',
        'medical_history',
        'complaint',
        'diagnosis',
        'treatment'
    ];
    
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        parent::__construct($attributes);
    }

    /**
     * Relationship with patient
     */
    public function patient()
    {
        if (!isset($this->attributes['patient_id'])) {
            return null;
        }
        return \HospitalManager\Models\Patient::find($this->attributes['patient_id']);
    }

    /**
     * Relationship with doctor
     */
    public function doctor()
    {
        if (!isset($this->attributes['doctor_id'])) {
            return null;
        }
        return \HospitalManager\Models\Doctor::find($this->attributes['doctor_id']);
    }

    /**
     * Relationship with lab investigations
     */
    public function labInvestigations()
    {
        return $this->getLabInvestigations();
    }

    /**
     * Get visitations for a specific patient with caching
     */
    public static function getForPatient($patientId)
    {
        $cache_key = "visitations_patient_{$patientId}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE patient_id = %d ORDER BY date DESC",
                $patientId
            ),
            ARRAY_A
        );
        
        $visitations = array_map(function($item) {
            return new static($item);
        }, $results);
        
        set_transient($cache_key, $visitations, 1200); // 20 minutes
        
        return $visitations;
    }

    /**
     * Get visitations for a specific doctor with caching
     */
    public static function getForDoctor($doctorId)
    {
        $cache_key = "visitations_doctor_{$doctorId}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE doctor_id = %d ORDER BY date DESC",
                $doctorId
            ),
            ARRAY_A
        );
        
        $visitations = array_map(function($item) {
            return new static($item);
        }, $results);
        
        set_transient($cache_key, $visitations, 1200); // 20 minutes
        
        return $visitations;
    }

    /**
     * Get visitations for a specific date range with caching
     */
    public static function getForDateRange($startDate, $endDate)
    {
        $cache_key = "visitations_daterange_" . md5($startDate . $endDate);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = (new static)->table;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE date BETWEEN %s AND %s ORDER BY date DESC",
                $startDate,
                $endDate
            ),
            ARRAY_A
        );
        
        $visitations = array_map(function($item) {
            return new static($item);
        }, $results);
        
        set_transient($cache_key, $visitations, 1800); // 30 minutes
        
        return $visitations;
    }

    /**
     * Get today's visitations with caching
     */
    public static function getTodaysVisitations()
    {
        $today = date('Y-m-d');
        $cache_key = "visitations_today_{$today}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $visitations = static::getForDateRange($today, $today);
        set_transient($cache_key, $visitations, 600); // 10 minutes for today's data
        
        return $visitations;
    }

    /**
     * Get visitation statistics with caching
     */
    public static function getStatistics()
    {
        $cache_key = 'visitation_statistics';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = (new static)->table;
        
        $stats = [
            'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'today' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE DATE(date) = %s",
                date('Y-m-d')
            )),
            'this_week' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE date >= %s",
                date('Y-m-d', strtotime('-7 days'))
            )),
            'this_month' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE date >= %s",
                date('Y-m-01')
            ))
        ];
        
        set_transient($cache_key, $stats, 1800); // 30 minutes
        
        return $stats;
    }

    /**
     * Get all lab investigations for this visitation
     */
    public function getLabInvestigations()
    {
        $cache_key = "visitation_labs_{$this->ID}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $labInvestigation = new \HospitalManager\Models\LabInvestigation();
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$labInvestigation->table} WHERE visitation_id = %d",
                $this->ID
            ),
            ARRAY_A
        );
        
        $labs = array_map(function($item) use ($labInvestigation) {
            return new $labInvestigation($item);
        }, $results);
        
        set_transient($cache_key, $labs, 1200); // 20 minutes
        
        return $labs;
    }

    /**
     * Save the model and invalidate related caches
     */
    public function save()
    {
        $result = parent::save();
        
        if ($result) {
            $this->invalidateVisitationCaches();
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
            $this->invalidateVisitationCaches();
        }
        
        return $result;
    }

    /**
     * Invalidate all visitation-related caches
     */
    private function invalidateVisitationCaches()
    {
        // Clear general statistics cache
        delete_transient('visitation_statistics');
        
        // Clear today's visitations cache
        $today = date('Y-m-d');
        delete_transient("visitations_today_{$today}");
        
        // Clear patient-specific cache if patient_id exists
        if (isset($this->attributes['patient_id'])) {
            delete_transient("visitations_patient_{$this->attributes['patient_id']}");
        }
        
        // Clear doctor-specific cache if doctor_id exists
        if (isset($this->attributes['doctor_id'])) {
            delete_transient("visitations_doctor_{$this->attributes['doctor_id']}");
        }
        
        // Clear lab investigations cache if ID exists
        if (isset($this->attributes['ID'])) {
            delete_transient("visitation_labs_{$this->attributes['ID']}");
        }
    }
}
