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

    public function get_appointments($request)
    {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Get parameters from the request
        $params = $request->get_params();
        
        // Check if parameters are in a nested 'params' array or directly in request
        if (isset($params['params']) && is_array($params['params'])) {
            $params = $params['params'];
        }
        
        // Extract parameters with fallbacks
        $date = isset($params['date']) ? $params['date'] : null;
        $status = isset($params['status']) ? $params['status'] : null;
        $doctor_id = isset($params['doctor_id']) ? intval($params['doctor_id']) : null;
        $upcoming = isset($params['upcoming']) && $params['upcoming'] === 'true';
        
        // Debug logging
        error_log("Appointment request params: " . print_r($params, true));
        error_log("Doctor ID: $doctor_id, Upcoming: " . ($upcoming ? 'true' : 'false') . ", Status: $status");
        
        // Pagination parameters
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $per_page = isset($params['per_page']) ? intval($params['per_page']) : 10;
        
        // Ensure per_page has a reasonable value
        $per_page = min(max($per_page, 5), 100); // Min 5, max 100

        $query = Appointment::query();

        // Filter by user role if no specific doctor_id is provided
        if (!$doctor_id) {
            if (in_array('doctor', $user->roles)) {
                $query->where('doctor_id', $user_id);
            } elseif (in_array('patient', $user->roles)) {
                $query->where('patient_id', $user_id);
            }
        } else {
            // Filter by specified doctor_id
            $query->where('doctor_id', $doctor_id);
        }

        if ($date) {
            $query->where('appointment_date', $date);
        }

        if ($status) {
            $query->where('status', $status);
        }
        
        // Handle upcoming appointments filter
        if ($upcoming) {
            $today = date('Y-m-d');
            error_log("Filtering for upcoming appointments from date: $today");
            // Use the enhanced where method with >= operator
            $query->where('appointment_date', '>=', $today)
                  ->orderBy('appointment_date', 'ASC')
                  ->orderBy('appointment_time', 'ASC');
        } else {
            $query->orderBy('appointment_date', 'DESC')
                  ->orderBy('appointment_time', 'DESC');
        }

        $appointments = $query->get();
        
        error_log("Found " . count($appointments) . " appointments before processing");

        // Convert appointments to array format to avoid any ID issues with the PostModel
        $appointments_array = array_map(function($appointment) {
            $appointment_data = $appointment->toArray();
            // error_log('Appointment Data: ' . print_r($appointment_data, true));
            // Add patient and doctor names to the array for display
            if (isset($appointment_data['patient_id'])) {
                $patient = Patient::find( $appointment_data['patient_id'] );
                $appointment_data['patient_name'] = $patient ? $patient->first_name . ' ' . $patient->last_name : 'Unknown Patient';
            }
            
            if (isset($appointment_data['doctor_id'])) {
                $doctor = Doctor::find( $appointment_data['doctor_id'] );
                $appointment_data['doctor_name'] = $doctor ? $doctor->first_name . ' ' . $doctor->last_name : 'Unknown Doctor';
            }
            
            return $appointment_data;
        }, $appointments);

        error_log('Final appointments array count: ' . count($appointments_array));
        
        // Calculate pagination
        $total_items = count($appointments_array);
        $total_pages = max(1, ceil($total_items / $per_page));
        
        // Ensure current page is valid
        $page = min(max(1, $page), $total_pages);
        
        // Apply pagination
        $offset = ($page - 1) * $per_page;
        $appointments_page = array_slice($appointments_array, $offset, $per_page);
        
        $response = new WP_REST_Response([
            'data' => $appointments_page,
            'meta' => [
                'total' => $total_items,
                'count' => count($appointments_page),
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => $total_pages,
                'first_page' => 1,
                'has_more_pages' => ($page < $total_pages)
            ]
        ]);
        
        // Set X-WP-Total and X-WP-TotalPages headers for compatibility
        $response->header('X-WP-Total', $total_items);
        $response->header('X-WP-TotalPages', $total_pages);
        
        return $response;
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
        $appointment_id = $request->get_param('id');
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Find the appointment
        $appointment = Appointment::find($appointment_id);
        
        if (!$appointment) {
            return new WP_Error(
                'appointment_not_found',
                'Appointment not found',
                ['status' => 404]
            );
        }
        
        // Security check: Only allow users to view their own appointments
        // unless they are an admin or desk officer
        if (!in_array('administrator', $user->roles) && 
            !in_array('desk_officer', $user->roles)) {
            
            if (in_array('doctor', $user->roles) && $appointment->doctor_id != $user_id) {
                return new WP_Error(
                    'permission_denied',
                    'You do not have permission to view this appointment',
                    ['status' => 403]
                );
            }
            
            if (in_array('patient', $user->roles) && $appointment->patient_id != $user_id) {
                return new WP_Error(
                    'permission_denied',
                    'You do not have permission to view this appointment',
                    ['status' => 403]
                );
            }
        }
        
        // Convert appointment to array format and add additional data
        $appointment_data = $appointment->toArray();
        
        // Add patient and doctor names to the array for display
        if (isset($appointment_data['patient_id'])) {
            $patient = get_user_by('id', $appointment_data['patient_id']);
            $appointment_data['patient_name'] = $patient ? $patient->display_name : 'Unknown Patient';
            
            // Get additional patient data if needed
            $patient_meta = get_user_meta($appointment_data['patient_id']);
            $appointment_data['patient_details'] = [
                'email' => $patient ? $patient->user_email : '',
                'phone' => isset($patient_meta['phone']) ? $patient_meta['phone'][0] : '',
            ];
        }
        
        if (isset($appointment_data['doctor_id'])) {
            $doctor = get_user_by('id', $appointment_data['doctor_id']);
            $appointment_data['doctor_name'] = $doctor ? $doctor->display_name : 'Unknown Doctor';
            
            // Get doctor's specialty and other details if available
            global $wpdb;
            $doctors_table = $wpdb->prefix . 'hm_doctors';
            $doctor_details = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT specialty, education, years_experience FROM {$doctors_table} WHERE user_id = %d",
                    $appointment_data['doctor_id']
                ),
                ARRAY_A
            );
            
            if ($doctor_details) {
                $appointment_data['doctor_details'] = $doctor_details;
            }
        }
        
        return new WP_REST_Response($appointment_data);
    }

    /**
     * Get appointment statistics for a doctor
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_appointment_stats($request)
    {
        // Get doctor_id from request parameters
        $doctor_id = $request->get_param('doctor_id');
        
        error_log("Getting appointment stats for doctor ID: $doctor_id");
        
        if (!$doctor_id) {
            return new WP_Error(
                'missing_doctor_id',
                'Doctor ID is required',
                ['status' => 400]
            );
        }
        
        // Validate doctor exists
        $doctor = Doctor::find($doctor_id);
        if (!$doctor) {
            return new WP_Error(
                'doctor_not_found',
                'Doctor not found',
                ['status' => 404]
            );
        }
        
        // Initialize stats
        $stats = [
            'totalAppointments' => 0,
            'upcomingAppointments' => 0,
            'completedAppointments' => 0,
            'cancelledAppointments' => 0,
            'pendingAppointments' => 0,
            'confirmedAppointments' => 0
        ];
        
        try {
            // Get doctor's appointments
            $query = Appointment::query()->where('doctor_id', $doctor_id);
            $appointments = $query->get();
            
            error_log("Found " . count($appointments) . " total appointments for doctor $doctor_id");
            
            // Get today's date for comparison
            $today = date('Y-m-d');
            error_log("Today's date for comparison: $today");
            
            // Count by status and date
            foreach ($appointments as $appointment) {
                $appointmentData = $appointment->toArray();
                $status = isset($appointmentData['status']) ? $appointmentData['status'] : 'pending';
                $date = isset($appointmentData['appointment_date']) ? $appointmentData['appointment_date'] : '';
                
                error_log("Appointment ID: {$appointmentData['id']}, Status: $status, Date: $date");
                
                // Count by status
                if ($status === 'completed') {
                    $stats['completedAppointments']++;
                    // Include completed in total count
                    $stats['totalAppointments']++;
                } elseif ($status === 'cancelled') {
                    $stats['cancelledAppointments']++;
                    // We don't include cancelled in the total count
                } elseif ($status === 'pending') {
                    $stats['pendingAppointments']++;
                    // Include pending in total count
                    $stats['totalAppointments']++;
                    
                    // Count pending appointments in upcoming if date is in the future
                    if ($date >= $today) {
                        $stats['upcomingAppointments']++;
                        error_log("Pending appointment on $date counted as upcoming");
                    }
                } elseif ($status === 'confirmed') {
                    $stats['confirmedAppointments']++;
                    // Include confirmed in total count
                    $stats['totalAppointments']++;
                    
                    // Count confirmed appointments in upcoming if date is in the future
                    if ($date >= $today) {
                        $stats['upcomingAppointments']++;
                        error_log("Confirmed appointment on $date counted as upcoming");
                    }
                }
            }
            
            error_log("Final stats: " . print_r($stats, true));
            
            return new WP_REST_Response($stats);
            
        } catch (\Exception $e) {
            error_log("Error in get_appointment_stats: " . $e->getMessage());
            return new WP_Error(
                'appointment_stats_error',
                'Error retrieving appointment statistics: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
