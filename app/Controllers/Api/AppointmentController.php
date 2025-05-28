<?php

namespace HospitalManager\Controllers\Api;

use Error;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\Doctor;
use HospitalManager\Services\NotificationService;
use HospitalManager\Services\AppointmentService;

class AppointmentController extends BaseController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/appointments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_appointments'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/appointments/book/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_booking_data'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
                'args' => [
                    'ID' => [
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        },
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/appointments', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_appointment'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/appointments/availability', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_availability'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);

        register_rest_route($this->namespace, '/appointments/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_appointment_stats'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]
        ]);

        // Single appointment route - consolidated
        register_rest_route($this->namespace, '/appointments/(?P<ID>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_appointment'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
                'args' => [
                    'ID' => [
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        },
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_appointment'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
                'args' => [
                    'ID' => [
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        },
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get appointments with enhanced filtering and error handling - Updated implementation
     */
    public function get_appointments($request)
    {
        try {
            // Extract parameters from request
            $params = $request->get_params();
            
            // Use the service to handle all business logic
            error_log('Fetching appointments with params: ' . print_r($params, true));
            $result = AppointmentService::getAppointments($params);
            
            return $this->success_response(
                $result, 
                'Appointments retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error_response(
                'Error retrieving appointments: ' . $e->getMessage(), 
                500
            );
        }
    }

    /**
     * Get booking data for appointment booking form
     */
    public function get_booking_data($request)
    {
        $doctor_id = $request->get_param('ID');
        
        if (!$doctor_id) {
            return new WP_Error(
                'missing_doctor_id',
                'Doctor ID is required',
                ['status' => 400]
            );
        }
        
        try {
            // Get doctor information
            global $wpdb;
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            $doctor = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$doctors_table} WHERE ID = %d",
                    $doctor_id
                ),
                ARRAY_A
            );
            
            if (!$doctor) {
                return new WP_Error(
                    'doctor_not_found',
                    'Doctor not found',
                    ['status' => 404]
                );
            }
            
            // Get available dates
            $available_dates = $this->get_available_dates($doctor_id);
            
            return new WP_REST_Response([
                'doctor' => $doctor,
                'available_dates' => $available_dates->data['dates'] ?? [],
                'doctor_id' => $doctor_id
            ], 200);
            
        } catch (\Exception $e) {
            error_log('Error getting booking data: ' . $e->getMessage());
            return new WP_Error(
                'booking_data_error',
                'Failed to retrieve booking data: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function create_appointment($request)
    {
        // Get parameters from request body (form submission)
        $patient_id = $request->get_param('patient_id') ?: get_current_user_id();
        $doctor_id = $request->get_param('doctor_id');
        $date = $request->get_param('appointment_date') ?: $request->get_param('date');
        $time = $request->get_param('appointment_time') ?: $request->get_param('time');
        $reason = $request->get_param('reason');
        $notes = $request->get_param('notes');

        // Debug incoming parameters
        error_log('Appointment creation parameters: ' . json_encode([
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'date' => $date,
            'time' => $time,
            'reason' => $reason
        ]));

        // Validate required fields
        if (!$patient_id || !$doctor_id || !$date || !$time) {
            return new WP_Error(
                'missing_required_fields',
                'Missing required fields: patient_id, doctor_id, date, and time are required',
                ['status' => 400]
            );
        }
        
        // Validate that the appointment date is in the future
        $appointment_date = strtotime("$date");
        $current_datetime = current_time('timestamp'); // Use current_time instead of current_datetime for better compatibility
        
        if ($appointment_date < strtotime('today')) {
            return new WP_Error(
                'invalid_appointment_time',
                'Appointment time must be in the future',
                ['status' => 400]
            );
        }

        // Check if slot is available
        if (!$this->is_slot_available($doctor_id, $date, $time)) {
            return new WP_Error(
                'slot_unavailable',
                'This time slot is not available',
                ['status' => 400]
            );
        }
        
        // Create the appointment
        try {
            $appointment_data = [
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'reason' => sanitize_text_field($reason),
                'notes' => sanitize_textarea_field($notes),
                'status' => 'pending'
            ];
            
            $appointment = Appointment::create($appointment_data);
            
            // MANUAL NOTIFICATION APPROACH: Skip the NotificationService and insert notification directly
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
                        $wpdb->insert(
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
                        
                        $notification_id = $wpdb->insert_id;
                        
                        if ($notification_id) {
                            error_log("Direct notification insertion successful: ID $notification_id");
                            
                            // Insert notification meta data
                            $notifications_meta_table = $wpdb->prefix . 'hm_notification_meta';
                            
                            if ($wpdb->get_var("SHOW TABLES LIKE '{$notifications_meta_table}'") === $notifications_meta_table) {
                                // Insert meta for appointment_id
                                $wpdb->insert(
                                    $notifications_meta_table,
                                    [
                                        'notification_id' => $notification_id,
                                        'meta_key' => 'appointment_id',
                                        'meta_value' => $appointment->ID
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
                        }
                    } else {
                        error_log("Notifications table not found: $notifications_table");
                    }
                } else {
                    error_log("Skipping notification - invalid doctor user ID: $doctor_user_id");
                }
            } catch (\Exception $notifyEx) {
                error_log("Error in direct notification insert: " . $notifyEx->getMessage());
                // Don't stop the appointment flow for notification errors
            }
            
            return new WP_REST_Response([
                'message' => 'Appointment booked successfully',
                'data' => [
                    'ID' => $appointment->ID,
                    'doctor_id' => $appointment->doctor_id,
                    'patient_id' => $appointment->patient_id,
                    'appointment_date' => $appointment->appointment_date,
                    'appointment_time' => $appointment->appointment_time,
                    'reason' => $appointment->reason,
                    'notes' => $appointment->notes,
                    'status' => $appointment->status,
                    'created_at' => $appointment->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            error_log('Appointment creation error: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
            return new WP_Error(
                'appointment_creation_failed',
                'Failed to create appointment: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function update_appointment($request)
    {
        $appointment_id = $request->get_param('ID');
        $status = $request->get_param('status');
        $notes = $request->get_param('notes');

        $appointment = Appointment::find($appointment_id);
        if (!$appointment) {
            return new WP_Error(
                'appointment_not_found',
                'Appointment not found',
                ['status' => 404]
            );
        }

        $appointment->status = $status;
        if ($notes) {
            $appointment->notes = $notes;
        }
        $appointment->save();

        // Notify patient about appointment status change
        NotificationService::create(
            $appointment->patient_id,
            'appointment_update',
            'Appointment Update',
            "Your appointment for {$appointment->appointment_date} has been {$status}",
            ['appointment_id' => $appointment_id]
        );

        return new WP_REST_Response($appointment);
    }

    public function get_availability($request)
    {
        $doctor_id = $request->get_param('doctor_id');
        $date = $request->get_param('date');

        if (!$doctor_id) {
            return new WP_Error(
                'missing_required_fields',
                'Doctor ID is required',
                ['status' => 400]
            );
        }

        // If no date provided, return available dates for the next 30 days
        if (!$date) {
            return $this->get_available_dates($doctor_id);
        }

        // Get the day of the week from the date
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        // Get doctor's availability from the database
        global $wpdb;
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        $doctor_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT appointment_availability FROM {$doctor_table} WHERE ID = %d",
                $doctor_id
            )
        );
        
        // Default working hours if no custom availability set
        $working_hours = [
            'start' => '09:00:00',
            'end' => '17:00:00',
            'slot_duration' => 30 // minutes
        ];
        
        $available_slots = [];
        
        // If doctor has custom availability
        if ($doctor_data && !empty($doctor_data->appointment_availability)) {
            $availability = json_decode($doctor_data->appointment_availability, true);
            
            // Check if doctor works on this day
            if (isset($availability[$day_of_week]) && !empty($availability[$day_of_week])) {
                foreach ($availability[$day_of_week] as $time_slot) {
                    // Generate 30-minute slots between start and end times
                    // Ensure time format is correct (may be stored as HH:MM or HH:MM:SS)
                    $start_time = $this->ensure_time_format($time_slot['start']);
                    $end_time = $this->ensure_time_format($time_slot['end']);
                    
                    for ($time = strtotime($start_time); $time < strtotime($end_time); $time += 30 * 60) {
                        $available_slots[] = date('H:i:s', $time);
                    }
                }
            } else {
                // Doctor doesn't work on this day
                return new WP_REST_Response([
                    'available_slots' => [],
                    'message' => 'The doctor is not available on this day.'
                ]);
            }
        } else {
            // Use default working hours
            $start_time = strtotime($working_hours['start']);
            $end_time = strtotime($working_hours['end']);
            
            for ($time = $start_time; $time < $end_time; $time += $working_hours['slot_duration'] * 60) {
                $available_slots[] = date('H:i:s', $time);
            }
        }

        // Get existing appointments for this doctor on this date
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        $booked_times = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT appointment_time FROM {$appointments_table} 
                WHERE doctor_id = %d AND appointment_date = %s AND status != 'cancelled'",
                $doctor_id, $date
            )
        );

        // Remove already booked slots
        $available_slots = array_filter($available_slots, function($slot) use ($booked_times) {
            return !in_array($slot, $booked_times);
        });

        return new WP_REST_Response([
            'date' => $date,
            'available_slots' => array_values($available_slots) // Reset array indexes
        ]);
    }
    
    /**
     * Ensure time is in HH:MM:SS format
     * 
     * @param string $time Time string
     * @return string Formatted time
     */
    private function ensure_time_format($time) {
        // If time is in HH:MM format, convert to HH:MM:SS
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time . ':00';
        }
        return $time;
    }

    private function is_slot_available($doctor_id, $date, $time)
    {
        // Check if requested time is within doctor's availability hours
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        // Get doctor's availability
        global $wpdb;
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        $doctor_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT appointment_availability FROM {$doctor_table} WHERE ID = %d",
                $doctor_id
            )
        );
        
        // First check if the doctor works on this day/time
        $is_available = false;
        
        if ($doctor_data && !empty($doctor_data->appointment_availability)) {
            $availability = json_decode($doctor_data->appointment_availability, true);
            
            if (isset($availability[$day_of_week]) && !empty($availability[$day_of_week])) {
                $request_time = strtotime($time);
                
                // Check each time slot for this day
                foreach ($availability[$day_of_week] as $time_slot) {
                    $start_time = strtotime($this->ensure_time_format($time_slot['start']));
                    $end_time = strtotime($this->ensure_time_format($time_slot['end']));
                    
                    if ($request_time >= $start_time && $request_time < $end_time) {
                        $is_available = true;
                        break;
                    }
                }
            }
        } else {
            // Default working hours if no custom availability (9 AM to 5 PM)
            $request_hour = (int)date('H', strtotime($time));
            if ($request_hour >= 9 && $request_hour < 17) {
                $is_available = true;
            }
        }
        
        // If not available based on schedule, return false immediately
        if (!$is_available) {
            return false;
        }
        
        // Check if the slot is already booked
        $appointments_table = $wpdb->prefix . 'hm_appointments';
        $existing_appointment = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$appointments_table} 
                WHERE doctor_id = %d 
                AND appointment_date = %s 
                AND appointment_time = %s 
                AND status != 'cancelled'",
                $doctor_id, $date, $time
            )
        );
        
        return $existing_appointment == 0;
    }

    /**
     * Get a single appointment with robust error handling
     */
    public function get_appointment($request)
    {
        try {
            $ID = (int)$request->get_param('ID');
            
            if (!$ID) {
                return new WP_Error('missing_id', 'Appointment ID is required', ['status' => 400]);
            }
            
            // Use direct DB query for reliability
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_appointments';
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            $patients_table = $wpdb->prefix . 'hm_patients';
            
            // Check if table exists
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$table_name}'")) {
                error_log('Appointments table not found: ' . $table_name);
                return new WP_Error('table_not_found', 'Appointments table not found', ['status' => 500]);
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
                return new WP_Error('not_found', 'Appointment not found', ['status' => 404]);
            }
            
            // Format response data
            $formatted_appointment = [
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
            
            return new WP_REST_Response($formatted_appointment, 200);
            
        } catch (\Exception $e) {
            error_log('Error getting appointment: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return new WP_Error('error', 'Failed to retrieve appointment: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    // Removed redundant get_appointment_fixed method - consolidated into get_appointment

    /**
     * Get appointment statistics for a doctor - Updated implementation
     */
    public function get_appointment_stats($request)
    {
        $doctor_id = $request->get_param('doctor_id');
        
        if (!$doctor_id) {
            return new WP_Error('missing_doctor_id', 'Doctor ID is required', ['status' => 400]);
        }
        
        try {
            // Verify doctor exists first
            $doctor = new Doctor();
            $doctor_exists = $doctor->find($doctor_id);
            if (!$doctor_exists) {
                return new WP_Error('doctor_not_found', 'Doctor not found', ['status' => 404]);
            }
            
            // Use direct database queries for reliability
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_appointments';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            if (!$table_exists) {
                error_log('Appointments table does not exist: ' . $table_name);
                return new WP_Error('table_not_found', 'Appointments table not found', ['status' => 500]);
            }
            
            // Get all appointments count for this doctor (excluding cancelled)
            // Include all statuses except 'cancelled' - this should include pending, confirmed, completed, etc.
            $total_appointments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND (status != 'cancelled' OR status IS NULL OR status = '')",
                $doctor_id
            ));
            
            // Debug: Get count of all appointments regardless of status
            $all_appointments_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d",
                $doctor_id
            ));
            
            // Debug: Get all appointments for this doctor to see what we have
            $all_appointments_debug = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, status, appointment_date FROM {$table_name} WHERE doctor_id = %d ORDER BY ID",
                $doctor_id
            ), ARRAY_A);
            error_log('Doctor ' . $doctor_id . ' - Total appointments in DB: ' . $all_appointments_count . ', Non-cancelled: ' . $total_appointments);
            error_log('All appointments for doctor ' . $doctor_id . ': ' . print_r($all_appointments_debug, true));
            
            // Get appointments by status
            $pending_appointments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'pending'",
                $doctor_id
            ));
            
            $confirmed_appointments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'confirmed'",
                $doctor_id
            ));
            
            $completed_appointments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'completed'",
                $doctor_id
            ));
            
            $cancelled_appointments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE doctor_id = %d AND status = 'cancelled'",
                $doctor_id
            ));
            
            // Get upcoming appointments (confirmed + pending for future dates)
            $today = date('Y-m-d');
            $upcoming_appointments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                 WHERE doctor_id = %d 
                 AND (status = 'pending' OR status = 'confirmed') 
                 AND appointment_date >= %s",
                $doctor_id, $today
            ));
            
            // Ensure all values are integers and handle null results
            $stats = [
                'totalAppointments' => (int) ($total_appointments ?? 0),
                'upcomingAppointments' => (int) ($upcoming_appointments ?? 0),
                'pendingAppointments' => (int) ($pending_appointments ?? 0),
                'confirmedAppointments' => (int) ($confirmed_appointments ?? 0),
                'completedAppointments' => (int) ($completed_appointments ?? 0),
                'cancelledAppointments' => (int) ($cancelled_appointments ?? 0)
            ];
            
            // Debug logging
            error_log('Appointment stats for doctor ' . $doctor_id . ': ' . print_r($stats, true));
            
            return new WP_REST_Response($stats, 200);
            
        } catch (\Exception $e) {
            error_log('Error getting appointment stats: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return new WP_Error('stats_error', 'Failed to retrieve appointment statistics: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get available dates for a doctor (next 30 days)
     * 
     * @param int $doctor_id Doctor ID
     * @return WP_REST_Response
     */
    private function get_available_dates($doctor_id)
    {
        global $wpdb;
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        
        // Get doctor's availability settings
        $doctor_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT appointment_availability FROM {$doctor_table} WHERE ID = %d",
                $doctor_id
            )
        );
        
        $availability = [];
        if ($doctor_data && !empty($doctor_data->appointment_availability)) {
            $availability = json_decode($doctor_data->appointment_availability, true);
        }
        
        // Default working days if no custom availability
        $default_working_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        
        $available_dates = [];
        $start_date = strtotime('today');
        $end_date = strtotime('+30 days');
        
        for ($date = $start_date; $date <= $end_date; $date += 86400) { // 86400 seconds = 1 day
            $day_of_week = strtolower(date('l', $date));
            $date_string = date('Y-m-d', $date);
            
            // Skip past dates
            if ($date < strtotime('today')) {
                continue;
            }
            
            $is_available = false;
            
            // Check if doctor works on this day
            if (!empty($availability)) {
                $is_available = isset($availability[$day_of_week]) && !empty($availability[$day_of_week]);
            } else {
                // Use default working days
                $is_available = in_array($day_of_week, $default_working_days);
            }
            
            $available_dates[] = [
                'date' => $date_string,
                'day' => ucfirst($day_of_week),
                'available' => $is_available
            ];
        }
        
        return new WP_REST_Response([
            'dates' => $available_dates,
            'doctor_id' => $doctor_id
        ]);
    }

    // Removed redundant methods - all functionality consolidated into main methods above
}
