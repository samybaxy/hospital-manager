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
            $booking_data = AppointmentService::getBookingData($doctor_id);
            
            return new WP_REST_Response($booking_data, 200);
            
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
        $user_id = $request->get_param('patient_id') ?: get_current_user_id();
        $doctor_id = $request->get_param('doctor_id');
        $date = $request->get_param('appointment_date') ?: $request->get_param('date');
        $time = $request->get_param('appointment_time') ?: $request->get_param('time');
        $reason = $request->get_param('reason');
        $notes = $request->get_param('notes');

        // Get the actual patient ID from the patients table
        global $wpdb;
        $patient_table = $wpdb->prefix . 'hm_patients';
        $patient_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$patient_table} WHERE user_id = %d",
            $user_id
        ));

        // Check if patient record exists
        if (!$patient_id) {
            return new WP_Error(
                'patient_not_found',
                'Patient record not found for user',
                ['status' => 400]
            );
        }

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
        if (!AppointmentService::isSlotAvailable($doctor_id, $date, $time)) {
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
            
            // Create notification for the appointment
            Appointment::createAppointmentNotification(
                $appointment->ID,
                $user_id, // Use the WordPress user ID for notifications
                $doctor_id,
                $date,
                $time
            );
            
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

        $appointment->setAttribute('status', $status);
        if ($notes) {
            $appointment->setAttribute('notes', $notes);
        }
        $appointment->save();

        // Notify patient about appointment status change
        NotificationService::create(
            $appointment->getAttribute('patient_id'),
            'appointment_update',
            'Appointment Update',
            "Your appointment for {$appointment->getAttribute('appointment_date')} has been {$status}",
            ['appointment_id' => $appointment_id]
        );

        return new WP_REST_Response($appointment->toArray());
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

        try {
            $availability = AppointmentService::getAvailability($doctor_id, $date);
            return new WP_REST_Response($availability);
        } catch (\Exception $e) {
            error_log('Error getting availability: ' . $e->getMessage());
            return new WP_Error(
                'availability_error',
                'Failed to retrieve availability: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
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
            
            $appointment = Appointment::getWithDetails($ID);
            
            if (!$appointment) {
                return new WP_Error('not_found', 'Appointment not found', ['status' => 404]);
            }
            
            return new WP_REST_Response($appointment, 200);
            
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
            $stats = AppointmentService::getAppointmentStats($doctor_id);
            
            // Debug logging
            error_log('Appointment stats for doctor ' . $doctor_id . ': ' . print_r($stats, true));
            
            return new WP_REST_Response($stats, 200);
            
        } catch (\Exception $e) {
            error_log('Error getting appointment stats: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return new WP_Error('stats_error', 'Failed to retrieve appointment statistics: ' . $e->getMessage(), ['status' => 500]);
        }
    }
}
