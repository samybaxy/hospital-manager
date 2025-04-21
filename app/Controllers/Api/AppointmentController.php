<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Appointment;
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
    }

    public function get_appointments($request)
    {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $date = $request->get_param('date');
        $status = $request->get_param('status');

        $query = Appointment::query();

        // Filter by user role
        if (in_array('doctor', $user->roles)) {
            $query->where('doctor_id', $user_id);
        } elseif (in_array('patient', $user->roles)) {
            $query->where('patient_id', $user_id);
        }

        if ($date) {
            $query->where('appointment_date', $date);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $appointments = $query
            ->orderBy('appointment_date', 'ASC')
            ->orderBy('appointment_time', 'ASC')
            ->get();

        return new WP_REST_Response($appointments);
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

        // Check if slot is available
        if (!$this->is_slot_available($doctor_id, $date, $time)) {
            return new WP_Error(
                'slot_unavailable',
                'This time slot is not available',
                ['status' => 400]
            );
        }

        $appointment = new Appointment([
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'reason' => $reason,
            'status' => 'pending'
        ]);
        $appointment->save();

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

        // Get doctor's working hours (assume 9 AM to 5 PM)
        $working_hours = [
            'start' => '09:00:00',
            'end' => '17:00:00',
            'slot_duration' => 30 // minutes
        ];

        // Get existing appointments
        $existing_appointments = Appointment::query()
            ->where('doctor_id', $doctor_id)
            ->where('appointment_date', $date)
            ->pluck('appointment_time');

        // Generate available slots
        $available_slots = [];
        $current_time = strtotime($working_hours['start']);
        $end_time = strtotime($working_hours['end']);

        while ($current_time < $end_time) {
            $time_slot = date('H:i:s', $current_time);
            if (!in_array($time_slot, $existing_appointments)) {
                $available_slots[] = $time_slot;
            }
            $current_time += ($working_hours['slot_duration'] * 60);
        }

        return new WP_REST_Response([
            'date' => $date,
            'available_slots' => $available_slots
        ]);
    }

    private function is_slot_available($doctor_id, $date, $time)
    {
        return !Appointment::query()
            ->where('doctor_id', $doctor_id)
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->exists();
    }
}
