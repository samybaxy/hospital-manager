<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Appointment extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'ID';
    protected $tableName = 'hm_appointments';
    
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'notes',
        'created_at',
        'updated_at'
    ];

    protected $conditions = [];
    protected $rawConditions = [];
    protected $orderBy = [];
    
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
     * Override the find method from FindTrait to handle our constructor's array requirement
     * 
     * @param mixed $ID Record ID.
     * @return object|null
     */
    public static function find($ID = 0)
    {
        global $wpdb;
        
        if (empty($ID)) {
            return null;
        }
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Fetch the appointment record directly from the database.
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $ID);
        $appointment_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$appointment_data) {
            return null;
        }
        
        // Create a new appointment instance with the fetched data.
        return new self($appointment_data);
    }
    
    public function where($column, $operator = null, $value = null)
    {
        // Handle 2 argument scenario (implying = operator)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->conditions[] = [$column, $operator, $value];
        return $this;
    }

    /**
     * Add a raw where clause to the query
     */
    public function whereRaw($sql, $params = [])
    {
        $this->rawConditions[] = [
            'sql' => $sql,
            'params' => $params
        ];
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy[] = [$column, $direction];
        return $this;
    }

    public function get()
    {
        global $wpdb;
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        // Initialize rawConditions array if not already initialized
        if (!isset($this->rawConditions)) {
            $this->rawConditions = [];
        }

        // Add where conditions
        foreach ($this->conditions as $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $query .= $wpdb->prepare(" AND {$column} {$operator} %s", $value);
        }
        
        // Add raw where conditions if any
        foreach ($this->rawConditions as $rawCondition) {
            $sql = $rawCondition['sql'];
            $rawParams = $rawCondition['params'];
            
            // If there are params, use prepare, otherwise just append the raw SQL
            if (!empty($rawParams)) {
                $query .= ' AND ' . $wpdb->prepare($sql, $rawParams);
            } else {
                $query .= ' AND ' . $sql;
            }
        }

        // Add order by
        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy));
        }
        
        error_log("Final SQL query: $query");
        $results = $wpdb->get_results($query);
        
        // Convert results to array of appointment objects
        $formatted_results = [];
        foreach ($results as $data) {
            $data = (array)$data;
            $formatted_results[] = new static($data);
        }
        
        return $formatted_results;
    }

    public function exists()
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";

        // Initialize rawConditions array if not already initialized
        if (!isset($this->rawConditions)) {
            $this->rawConditions = [];
        }

        // Add where conditions
        foreach ($this->conditions as $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $query .= $wpdb->prepare(" AND {$column} {$operator} %s", $value);
        }
        
        // Add raw where conditions if any
        foreach ($this->rawConditions as $rawCondition) {
            $sql = $rawCondition['sql'];
            $rawParams = $rawCondition['params'];
            
            // If there are params, use prepare, otherwise just append the raw SQL
            if (!empty($rawParams)) {
                $query .= ' AND ' . $wpdb->prepare($sql, $rawParams);
            } else {
                $query .= ' AND ' . $sql;
            }
        }

        return (bool)$wpdb->get_var($query);
    }

    public static function query()
    {
        return new static();
    }

    public function pluck($column)
    {
        global $wpdb;
        $query = "SELECT {$column} FROM {$this->table} WHERE 1=1";
        
        // Initialize rawConditions array if not already initialized
        if (!isset($this->rawConditions)) {
            $this->rawConditions = [];
        }
        
        // Add where conditions
        foreach ($this->conditions as $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $query .= $wpdb->prepare(" AND {$column} {$operator} %s", $value);
        }
        
        // Add raw where conditions if any
        foreach ($this->rawConditions as $rawCondition) {
            $sql = $rawCondition['sql'];
            $rawParams = $rawCondition['params'];
            
            // If there are params, use prepare, otherwise just append the raw SQL
            if (!empty($rawParams)) {
                $query .= ' AND ' . $wpdb->prepare($sql, $rawParams);
            } else {
                $query .= ' AND ' . $sql;
            }
        }

        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy));
        }

        $results = $wpdb->get_col($query);
        return $results;
    }
    
    /**
     * Create a new appointment record in the database
     *
     * @param array $data Appointment data to create
     * @return Appointment The newly created appointment instance
     */
    public static function create(array $data)
    {
        global $wpdb;
    
        // Get table name
        $instance = new static();
        $table = $instance->getTable();
        
        // Set created_at timestamp if not provided
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        // Set updated_at if not provided
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }
        
        // Filter data to only include fillable fields
        $fillable_data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Insert the record
        $result = $wpdb->insert(
            $table,
            $fillable_data,
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $fillable_data)
        );
        
        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }
        
        // Get the newly created ID and add it to the data
        $data['ID'] = $wpdb->insert_id;
        
        // Return a new instance with the created data
        return new static($data);
    }

    /**
     * Save the current model instance to the database
     * 
     * @return bool True if saved successfully, false otherwise
     */
    public function save()
    {
        global $wpdb;
        
        // Ensure we have an ID for updating
        $id = $this->getAttribute('ID');
        if (empty($id)) {
            throw new \Exception('Cannot save appointment without ID. Use create() for new appointments.');
        }
        
        // Get current attributes
        $data = $this->attributes;
        
        // Set updated_at timestamp
        $data['updated_at'] = current_time('mysql');
        
        // Filter data to only include fillable fields (excluding ID)
        $fillable_data = array_intersect_key($data, array_flip($this->fillable));
        
        // Remove ID from the data to update (we don't want to update the primary key)
        unset($fillable_data['ID']);
        
        if (empty($fillable_data)) {
            return true; // Nothing to update
        }
        
        // Prepare format array for wpdb
        $format = array_map(function($field) {
            // Determine format based on field name or value
            if (in_array($field, ['patient_id', 'doctor_id'])) {
                return '%d';
            }
            return '%s';
        }, array_keys($fillable_data));
        
        // Update the record
        $result = $wpdb->update(
            $this->getTable(),
            $fillable_data,
            ['ID' => $id],
            $format,
            ['%d'] // ID format
        );
        
        if ($result === false) {
            error_log('Appointment save error: ' . $wpdb->last_error);
            throw new \Exception('Failed to save appointment: ' . $wpdb->last_error);
        }
        
        // Update the model's attributes with the new data
        $this->attributes = array_merge($this->attributes, $fillable_data);
        
        error_log("Appointment {$id} saved successfully with data: " . print_r($fillable_data, true));
        
        return true;
    }

    public function doctor()
    {
        return get_user_by('ID', $this->doctor_id);
    }

    public function patient()
    {
        return get_user_by('ID', $this->patient_id);
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
        
        // Process notes if it's a JSON string
        if (!empty($data['notes']) && is_string($data['notes'])) {
            $decoded = json_decode($data['notes'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['notes'] = $decoded;
            }
        }
        
        // Ensure status defaults to 'pending' if not set
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }
        
        return $data;
    }

    /**
     * Get upcoming appointments for a patient
     */
    public static function getUpcomingForPatient($patientId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE patient_id = %d 
            AND status = %s 
            AND appointment_date >= %s",
            $patientId,
            'scheduled',
            current_time('Y-m-d')
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        $appointments = array_map(function($item) {
            return new static($item);
        }, $results ?: []);
        
        return $appointments;
    }

    /**
     * Get today's appointments for a doctor with patient details
     */
    public static function getTodaysForDoctor($doctorId)
    {
        global $wpdb;
        $table = (new static)->table;
        
        $query = $wpdb->prepare(
            "SELECT a.*, u.display_name as patient_name 
            FROM {$table} a 
            LEFT JOIN {$wpdb->users} u ON a.patient_id = u.ID 
            WHERE a.doctor_id = %d 
            AND DATE(a.appointment_date) = %s 
            ORDER BY a.appointment_date ASC",
            $doctorId,
            current_time('Y-m-d')
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($item) {            
            $model = new static($item);
            if (isset($item['patient_name'])) {
                $model->patient_name = $item['patient_name'];
            }
            return $model;
        }, $results ?: []);
    }
    
    /**
     * Get a single appointment with related doctor and patient information
     * 
     * @param int $ID Appointment ID
     * @return array|null Appointment data with related information or null if not found
     */
    public static function getWithDetails($ID)
    {
        global $wpdb;
        
        if (!$ID) {
            return null;
        }
        
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
            return null;
        }
        
        // Format response data
        return [
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
