<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Appointment extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'ID';
    protected $tableName = 'hm_appointments';
    protected static $cache_expiration = 1200; // 20 minutes for scheduling data
    
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'notes'
    ];
    
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

    /**
     * Get upcoming appointments for a patient with caching
     * 
     * @param int $patientId Patient ID
     * @return array Array of appointment objects
     */
    public static function getUpcomingForPatient($patientId)
    {
        if (empty($patientId)) {
            return [];
        }

        $cache_key = static::getCacheKey('getUpcomingForPatient', [$patientId]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = (new static)->table;
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        
        $query = $wpdb->prepare(
            "SELECT a.*, 
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                    d.specialty as doctor_specialty
            FROM {$table} a 
            LEFT JOIN {$doctors_table} d ON a.doctor_id = d.ID
            WHERE a.patient_id = %d 
            AND (a.status = 'pending' OR a.status = 'confirmed') 
            AND a.appointment_date >= %s
            ORDER BY a.appointment_date ASC, a.appointment_time ASC",
            $patientId,
            current_time('Y-m-d')
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        $appointments = array_map(function($item) {
            $appointment = new static($item);
            // Add doctor name and formatted date to the appointment object
            if (isset($item['doctor_name'])) {
                $appointment->doctor_name = $item['doctor_name'];
            }
            if (isset($item['doctor_specialty'])) {
                $appointment->doctor_specialty = $item['doctor_specialty'];
            }
            if (isset($item['appointment_date'])) {
                $appointment->formatted_date = mysql2date('F j, Y', $item['appointment_date']);
            }
            return $appointment;
        }, $results ?: []);
        
        static::setToCache($cache_key, $appointments, 1200); // 20 minutes cache
        return $appointments;
    }

    /**
     * Get today's appointments for a doctor with caching
     * 
     * @param int $doctorId Doctor ID
     * @return array Array of appointment objects
     */
    public static function getTodaysForDoctor($doctorId)
    {
        if (empty($doctorId)) {
            return [];
        }

        $cache_key = static::getCacheKey('getTodaysForDoctor', [$doctorId]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = (new static)->table;
        $patients_table = $wpdb->prefix . 'hm_patients';
        
        $query = $wpdb->prepare(
            "SELECT a.*, 
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.phone as patient_phone
            FROM {$table} a 
            LEFT JOIN {$patients_table} p ON a.patient_id = p.ID 
            WHERE a.doctor_id = %d 
            AND DATE(a.appointment_date) = %s 
            ORDER BY a.appointment_time ASC",
            $doctorId,
            current_time('Y-m-d')
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        $appointments = array_map(function($item) {            
            $model = new static($item);
            if (isset($item['patient_name'])) {
                $model->patient_name = $item['patient_name'];
            }
            if (isset($item['patient_phone'])) {
                $model->patient_phone = $item['patient_phone'];
            }
            return $model;
        }, $results ?: []);
        
        static::setToCache($cache_key, $appointments, 600); // 10 minutes cache (frequent changes)
        return $appointments;
    }

    /**
     * Get appointment with detailed information with caching
     * 
     * @param int $ID Appointment ID
     * @return array|null Appointment data with related information or null if not found
     */
    public static function getWithDetails($ID)
    {
        if (empty($ID)) {
            return null;
        }

        $cache_key = static::getCacheKey('getWithDetails', [$ID]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hm_appointments';
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        $patients_table = $wpdb->prefix . 'hm_patients';
        
        // Check if table exists
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table_name}'")) {
            throw new \Exception('Appointments table not found');
        }
        
        // Query with JOINs for related data
        $query = $wpdb->prepare(
            "SELECT 
                a.*,
                CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                d.specialty as doctor_specialty,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.phone as patient_phone
            FROM {$table_name} a
            LEFT JOIN {$doctors_table} d ON a.doctor_id = d.ID
            LEFT JOIN {$patients_table} p ON a.patient_id = p.ID
            WHERE a.ID = %d",
            $ID
        );
        
        $appointment = $wpdb->get_row($query, ARRAY_A);
        
        if (!$appointment) {
            static::setToCache($cache_key, null, 1200); // Cache null results
            return null;
        }
        
        // Format response data
        $result = [
            'ID' => (int) $appointment['ID'],
            'patient_id' => (int) $appointment['patient_id'],
            'doctor_id' => (int) $appointment['doctor_id'],
            'date' => $appointment['appointment_date'],
            'time' => $appointment['appointment_time'],
            'appointment_date' => $appointment['appointment_date'],
            'appointment_time' => $appointment['appointment_time'],
            'reason' => $appointment['reason'] ?? '',
            'status' => $appointment['status'] ?? 'pending',
            'notes' => $appointment['notes'] ?? '',
            'created_at' => $appointment['created_at'],
            'updated_at' => $appointment['updated_at'],
            'doctor_name' => $appointment['doctor_name'],
            'doctor_specialty' => $appointment['doctor_specialty'],
            'patient_name' => $appointment['patient_name'],
            'patient_phone' => $appointment['patient_phone']
        ];
        
        static::setToCache($cache_key, $result, 1200); // 20 minutes cache
        return $result;
    }

    /**
     * Get appointment statistics with caching
     * 
     * @return array|null Statistics array
     */
    public static function getStatistics()
    {
        $cache_key = static::getCacheKey('getStatistics', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        
        $stats = $wpdb->get_row("
            SELECT 
                (SELECT COUNT(*) FROM {$appointments_table}) as total_appointments,
                (SELECT COUNT(*) FROM {$appointments_table} WHERE status = 'pending') as pending_appointments,
                (SELECT COUNT(*) FROM {$appointments_table} WHERE status = 'confirmed') as confirmed_appointments,
                (SELECT COUNT(*) FROM {$appointments_table} WHERE status = 'completed') as completed_appointments,
                (SELECT COUNT(*) FROM {$appointments_table} WHERE DATE(appointment_date) = CURDATE()) as today_appointments,
                (SELECT COUNT(*) FROM {$appointments_table} WHERE appointment_date >= CURDATE() AND appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)) as week_appointments
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
     * Enhanced save with cache invalidation
     * 
     * @return bool True if saved successfully, false otherwise
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
        
        // Add updated_at timestamp
        $data['updated_at'] = current_time('mysql');
        
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
                $this->invalidateAppointmentCaches();
            }
            
            return $result !== false;
        } else {
            // This is an insert
            $data['created_at'] = current_time('mysql');
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
     * Enhanced delete with cache invalidation
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
            $this->invalidateAppointmentCaches();
        }
        
        return $result !== false;
    }

    /**
     * Invalidate appointment-specific caches
     * 
     * @return void
     */
    private function invalidateAppointmentCaches()
    {
        if (isset($this->attributes['patient_id'])) {
            static::invalidateCache('getUpcomingForPatient', [$this->attributes['patient_id']]);
        }
        
        if (isset($this->attributes['doctor_id'])) {
            static::invalidateCache('getTodaysForDoctor', [$this->attributes['doctor_id']]);
        }
        
        if (isset($this->attributes['ID'])) {
            static::invalidateCache('find', [$this->attributes['ID']]);
            static::invalidateCache('getWithDetails', [$this->attributes['ID']]);
        }
        
        // Invalidate general caches
        static::invalidateCache('all');
        static::invalidateCache('count');
        static::invalidateCache('getStatistics');
    }
    
    /**
     * Create notification for appointment creation
     * 
     * @param int $appointment_id Appointment ID
     * @param int $patient_id Patient ID
     * @param int $doctor_id Doctor ID
     * @param string $date Appointment date
     * @param string $time Appointment time
     * @return bool True if notification created successfully, false otherwise
     */
    public static function createAppointmentNotification($appointment_id, $patient_id, $doctor_id, $date, $time)
    {
        try {
            global $wpdb;
            
            // Get patient name
            $patients_table = $wpdb->prefix . 'hm_patients';
            $patient_data = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT first_name, last_name FROM {$patients_table} WHERE ID = %d",
                    $patient_id
                )
            );
            
            $patient_name = $patient_data ? 
                trim($patient_data->first_name . ' ' . $patient_data->last_name) : 
                'A patient';
                
            if (empty(trim($patient_name))) {
                $patient_name = 'A patient';
            }
            
            // Get doctor user_id
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            $doctor_user_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM {$doctors_table} WHERE ID = %d",
                    $doctor_id
                )
            );
            
            // Insert notification record directly into the database if user_id is valid
            if ($doctor_user_id && is_numeric($doctor_user_id) && get_user_by('ID', $doctor_user_id)) {
                // Create notification title and message
                $notification_title = 'New Appointment';
                $notification_message = sprintf(
                    '%s has booked an appointment with you on %s at %s.',
                    $patient_name,
                    date('F j, Y', strtotime($date)),
                    date('g:i A', strtotime($time))
                );
                
                // Get the notifications table name directly
                $notifications_table = $wpdb->prefix . 'hm_notifications';
                
                // Check if the table exists, if not, we'll skip inserting notifications
                if ($wpdb->get_var("SHOW TABLES LIKE '{$notifications_table}'") === $notifications_table) {
                    // Insert notification directly using wpdb
                    $result = $wpdb->insert(
                        $notifications_table,
                        [
                            'user_id' => $doctor_user_id,
                            'type' => 'appointment',
                            'title' => $notification_title,
                            'message' => $notification_message,
                            'is_read' => 0,
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ],
                        [
                            '%d', '%s', '%s', '%s', '%d', '%s', '%s'
                        ]
                    );
                    
                    if ($result) {
                        $notification_id = $wpdb->insert_id;
                        
                        // Insert notification meta data
                        $notifications_meta_table = $wpdb->prefix . 'hm_notification_meta';
                        
                        if ($wpdb->get_var("SHOW TABLES LIKE '{$notifications_meta_table}'") === $notifications_meta_table) {
                            // Insert meta for appointment_id
                            $wpdb->insert(
                                $notifications_meta_table,
                                [
                                    'notification_id' => $notification_id,
                                    'meta_key' => 'appointment_id',
                                    'meta_value' => $appointment_id
                                ],
                                ['%d', '%s', '%s']
                            );
                            
                            // Insert other meta as needed
                            $wpdb->insert(
                                $notifications_meta_table,
                                [
                                    'notification_id' => $notification_id,
                                    'meta_key' => 'appointment_date',
                                    'meta_value' => $date
                                ],
                                ['%d', '%s', '%s']
                            );
                        }
                        
                        error_log("Direct notification insertion successful: ID $notification_id");
                        return true;
                    }
                } else {
                    error_log("Notifications table not found: $notifications_table");
                }
            } else {
                error_log("Skipping notification - invalid doctor user ID: $doctor_user_id");
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Error creating appointment notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set an attribute value
     * 
     * @param string $name Attribute name
     * @param mixed $value Attribute value
     */
    public function setAttribute($name, $value)
    {
        // Only allow setting fillable fields
        if (in_array($name, $this->fillable) || $name === 'ID') {
            $this->attributes[$name] = $value;
        } else {
            error_log("Attempted to set non-fillable field: $name");
        }
    }
    
    /**
     * Get an attribute value
     * 
     * @param string $name Attribute name
     * @return mixed Attribute value
     */
    public function getAttribute($name)
    {
        return $this->attributes[$name] ?? null;
    }
}
