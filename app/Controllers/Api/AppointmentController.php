<?php

namespace HospitalManager\Controllers\Api;

use Error;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Patient;
use HospitalManager\Services\NotificationService;

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
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_appointment'],
                'permission_callback' => function() {
                    return current_user_can('patient');
                }
            ]
        ]);

        register_rest_route($this->namespace, '/appointments/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_appointment'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_appointment'],
                'permission_callback' => function() {
                    return current_user_can('doctor') || current_user_can('desk_officer');
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
    }

    /**
     * Get appointments with enhanced filtering and error handling - Updated implementation
     */
    public function get_appointments($request)
    {
        try {
            // Get parameters with better handling of nested params
            $params = $request->get_param('params') ?? [];
            $page = (int) ($request->get_param('page') ?? $params['page'] ?? 1);
            $per_page = (int) ($request->get_param('per_page') ?? $params['per_page'] ?? 10);
            $doctor_id = $request->get_param('doctor_id');
            $patient_id = $request->get_param('patient_id');
            $status = $request->get_param('status');
            $upcoming = $request->get_param('upcoming');
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');

            // Validate and sanitize parameters
            $page = max(1, $page);
            $per_page = min(100, max(1, $per_page));
            $offset = ($page - 1) * $per_page;
            
            // Use direct database queries for reliability
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_appointments';
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            $patients_table = $wpdb->prefix . 'hm_patients';
            
            // Verify tables exist
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$table_name}'")) {
                error_log('Appointments table does not exist: ' . $table_name);
                return new WP_Error('table_not_found', 'Appointments table not found', ['status' => 500]);
            }
            
            // Build WHERE conditions
            $where_conditions = [];
            $prepare_values = [];
            
            if ($doctor_id) {
                $where_conditions[] = "a.doctor_id = %d";
                $prepare_values[] = (int) $doctor_id;
            }
            
            if ($patient_id) {
                $where_conditions[] = "a.patient_id = %d";
                $prepare_values[] = (int) $patient_id;
            }
            
            if ($status) {
                $where_conditions[] = "a.status = %s";
                $prepare_values[] = sanitize_text_field($status);
            }
            
            // Handle upcoming filter
            if ($upcoming === 'true' || $upcoming === true) {
                $today = date('Y-m-d');
                $where_conditions[] = "(a.status = 'pending' OR a.status = 'confirmed') AND a.appointment_date >= %s";
                $prepare_values[] = $today;
            }
            
            if ($date_from) {
                $where_conditions[] = "a.appointment_date >= %s";
                $prepare_values[] = sanitize_text_field($date_from);
            }
            
            if ($date_to) {
                $where_conditions[] = "a.appointment_date <= %s";
                $prepare_values[] = sanitize_text_field($date_to);
            }
            
            // Build WHERE clause
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Main query with LEFT JOINs
            $query = "
                SELECT 
                    a.id,
                    a.patient_id,
                    a.doctor_id,
                    a.appointment_date,
                    a.appointment_time,
                    a.reason,
                    a.status,
                    a.notes,
                    a.created_at,
                    a.updated_at,
                    COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'Unknown Doctor') as doctor_name,
                    COALESCE(d.specialty, 'General Practice') as doctor_specialty,
                    COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Patient') as patient_name,
                    p.phone as patient_phone
                FROM {$table_name} a
                LEFT JOIN {$doctors_table} d ON a.doctor_id = d.id
                LEFT JOIN {$patients_table} p ON a.patient_id = p.id
                {$where_clause}
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
                LIMIT %d OFFSET %d
            ";
            
            // Add pagination to prepare values
            $prepare_values[] = $per_page;
            $prepare_values[] = $offset;
            
            // Execute main query
            if (!empty($prepare_values)) {
                $appointments = $wpdb->get_results($wpdb->prepare($query, $prepare_values), ARRAY_A);
            } else {
                $appointments = $wpdb->get_results($query, ARRAY_A);
            }
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM {$table_name} a {$where_clause}";
            if (!empty($where_conditions)) {
                $count_prepare_values = array_slice($prepare_values, 0, -2); // Remove pagination values
                $total_count = $wpdb->get_var($wpdb->prepare($count_query, $count_prepare_values));
            } else {
                $total_count = $wpdb->get_var($count_query);
            }
            
            // Handle database errors
            if ($wpdb->last_error) {
                error_log('Database error in get_appointments: ' . $wpdb->last_error);
                error_log('Query: ' . $wpdb->last_query);
                return new WP_Error('database_error', 'Database query failed', ['status' => 500]);
            }
            
            // Format appointments data
            $formatted_appointments = array_map(function($appointment) {
                return [
                    'id' => (int) $appointment['id'],
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
            }, $appointments ?: []);
            
            // Calculate pagination metadata
            $total_pages = ceil($total_count / $per_page);
            
            // Debug logging
            error_log('get_appointments executed: Found ' . count($formatted_appointments) . ' appointments, Total: ' . $total_count);
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $formatted_appointments,
                'meta' => [
                    'total' => (int) $total_count,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => (int) $total_pages,
                    'current_page' => $page,
                    'last_page' => (int) $total_pages
                ]
            ], 200);
            
        } catch (\Exception $e) {
            error_log('Exception in get_appointments: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return new WP_Error('appointments_error', 'Failed to retrieve appointments: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    public function create_appointment($request)
    {
        $patient_id = get_current_user_id();
        $doctor_id = $request->get_param('doctor_id');
        $date = $request->get_param('date');
        $time = $request->get_param('time');
        $reason = $request->get_param('reason');

        // Validate required fields
        if (!$doctor_id || !$date || !$time) {
            return new WP_Error(
                'missing_required_fields',
                'Missing required fields',
                ['status' => 400]
            );
        }
        
        // Validate that the appointment date is in the future
        $appointment_datetime = strtotime("$date $time");
        $current_datetime = current_time('timestamp');
        
        if ($appointment_datetime <= $current_datetime) {
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
                'status' => 'pending'
            ];
            
            $appointment = Appointment::create($appointment_data);
            
            // Get patient name for notification
            $patient = get_userdata($patient_id);
            $patient_name = $patient ? trim($patient->first_name . ' ' . $patient->last_name) : 'A patient';
            if (empty(trim($patient_name))) {
                $patient_name = $patient->display_name;
            }
            
            // Get doctor's user ID for notification
            global $wpdb;
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            $doctor_user_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM {$doctors_table} WHERE id = %d",
                    $doctor_id
                )
            );
            
            if ($doctor_user_id) {
                // Create notification for doctor
                $notification_title = 'New Appointment';
                $notification_message = sprintf(
                    '%s has booked an appointment with you on %s at %s.',
                    $patient_name,
                    date('F j, Y', strtotime($date)),
                    date('g:i A', strtotime($time))
                );
                
                NotificationService::create(
                    $doctor_user_id,
                    'appointment',
                    $notification_title,
                    $notification_message,
                    [
                        'appointment_id' => $appointment->id,
                        'patient_id' => $patient_id,
                        'appointment_date' => $date,
                        'appointment_time' => $time
                    ]
                );
            }
            
            return new WP_REST_Response([
                'message' => 'Appointment booked successfully',
                'appointment' => [
                    'id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'patient_id' => $appointment->patient_id,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'status' => $appointment->status
                ]
            ], 201);
        } catch (\Exception $e) {
            return new WP_Error(
                'appointment_creation_failed',
                'Failed to create appointment: ' . $e->getMessage(),
                ['status' => 500]
            );
        }

        // Notify doctor about new appointment
        NotificationService::create(
            $doctor_id,
            'new_appointment',
            'New Appointment Request',
            "A new appointment has been requested for {$date} at {$time}",
            ['appointment_id' => $appointment->id]
        );

        return new WP_REST_Response($appointment, 201);
    }

    public function update_appointment($request)
    {
        $appointment_id = $request->get_param('id');
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

        if (!$doctor_id || !$date) {
            return new WP_Error(
                'missing_required_fields',
                'Doctor ID and date are required',
                ['status' => 400]
            );
        }

        // Get the day of the week from the date
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        // Get doctor's availability from the database
        global $wpdb;
        $doctor_table = $wpdb->prefix . 'hm_doctors';
        $doctor_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT appointment_availability FROM {$doctor_table} WHERE id = %d",
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
                "SELECT appointment_availability FROM {$doctor_table} WHERE id = %d",
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
     * Get a single appointment by ID
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_appointment($request)
    {
        $id = $request->get_param('id');
        
        if (!$id) {
            return new WP_Error('missing_id', 'Appointment ID is required', ['status' => 400]);
        }
        
        try {
            $appointment = new Appointment();
            $appointment_data = $appointment->find($id);
            
            if (!$appointment_data) {
                return new WP_Error('appointment_not_found', 'Appointment not found', ['status' => 404]);
            }
            
            // Also try direct database query as fallback
            global $wpdb;
            $table_name = $wpdb->prefix . 'hm_appointments';
            $db_result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d", 
                $id
            ), ARRAY_A);
            
            error_log('Direct DB query result: ' . print_r($db_result, true));
            
            // Convert to array and ensure proper field mapping
            $appointment_array = $appointment_data->toArray();
            
            // Debug log the raw data to see what fields are actually available
            error_log('Raw appointment data: ' . print_r($appointment_array, true));
            error_log('Appointment object properties: ' . print_r(get_object_vars($appointment_data), true));
            
            // Try direct property access as fallback
            $date_value = $appointment_array['appointment_date'] ?? $appointment_data->appointment_date ?? $db_result['appointment_date'] ?? null;
            $time_value = $appointment_array['appointment_time'] ?? $appointment_data->appointment_time ?? $db_result['appointment_time'] ?? null;
            
            // If still null, use the database result directly
            if (!$date_value && $db_result) {
                $date_value = $db_result['appointment_date'];
            }
            if (!$time_value && $db_result) {
                $time_value = $db_result['appointment_time'];
            }
            
            error_log('Final date value: ' . ($date_value ?? 'null'));
            error_log('Final time value: ' . ($time_value ?? 'null'));
            
            // Get related data
            $doctor = new Doctor();
            $doctor_data = $doctor->find($appointment_array['doctor_id']);
            
            $patient = new Patient();
            $patient_data = $patient->find($appointment_array['patient_id']);
            
            // Ensure both field naming conventions are supported
            $response_data = [
                'id' => $appointment_array['id'],
                'patient_id' => $appointment_array['patient_id'],
                'doctor_id' => $appointment_array['doctor_id'],
                // Support both naming conventions with fallbacks
                'date' => $date_value,
                'time' => $time_value,
                'appointment_date' => $date_value,
                'appointment_time' => $time_value,
                'reason' => $appointment_array['reason'] ?? '',
                'status' => $appointment_array['status'] ?? 'pending',
                'notes' => $appointment_array['notes'] ?? '',
                'created_at' => $appointment_array['created_at'] ?? '',
                'updated_at' => $appointment_array['updated_at'] ?? '',
                'doctor' => $doctor_data ? [
                    'id' => $doctor_data->id,
                    'first_name' => $doctor_data->first_name,
                    'last_name' => $doctor_data->last_name,
                    'specialty' => $doctor_data->specialty,
                ] : null,
                'patient' => $patient_data ? [
                    'id' => $patient_data->id,
                    'first_name' => $patient_data->first_name,
                    'last_name' => $patient_data->last_name,
                ] : null
            ];
            
            // Debug log the response data
            error_log('Response appointment data: ' . print_r($response_data, true));
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $response_data
            ], 200);
            
        } catch (\Exception $e) {
            error_log('Error getting appointment: ' . $e->getMessage());
            return new WP_Error('appointment_error', 'Failed to retrieve appointment', ['status' => 500]);
        }
    }

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
                "SELECT id, status, appointment_date FROM {$table_name} WHERE doctor_id = %d ORDER BY id",
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

    // Additional route registration for stats endpoint with proper permissions
    public function register_stats_route()
    {
        register_rest_route('hospital-manager/v1', '/appointments/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_appointment_stats'],
            'permission_callback' => '__return_true', // Allow public access
            'args' => [
                'doctor_id' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    // Ensure routes are properly registered with error handling
    public function ensure_routes_registered()
    {
        // Re-register all appointment routes with proper error handling
        register_rest_route('hospital-manager/v1', '/appointments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_appointments'],
                'permission_callback' => '__return_true',
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint'
                    ],
                    'per_page' => [
                        'default' => 10,
                        'sanitize_callback' => 'absint'
                    ],
                    'doctor_id' => [
                        'sanitize_callback' => 'absint'
                    ],
                    'upcoming' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_appointment'],
                'permission_callback' => '__return_true'
            ]
        ]);
    }
}
