<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;

class DashboardController extends WP_REST_Controller 
{
    public function register_routes() 
    {
        register_rest_route('hospital-manager/v1', '/dashboard', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_data'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        ]);
    }

    public function check_permissions() 
    {
        return is_user_logged_in();
    }

    public function get_dashboard_data($request) 
    {
        $user = wp_get_current_user();
        $role = $user->roles[0];
        
        switch ($role) {
            case 'patient':
                return $this->get_patient_dashboard($user->ID);
            case 'doctor':
                return $this->get_doctor_dashboard($user->ID);
            case 'lab_tech':
                return $this->get_lab_dashboard($user->ID);
            default:
                return new WP_REST_Response(['error' => 'Invalid role'], 403);
        }
    }

    /**
     * Get dashboard data for a patient
     *
     * @param int $user_id WordPress user ID
     * @return WP_REST_Response
     */
    private function get_patient_dashboard($user_id) 
    {
        $patients = \HospitalManager\Models\Patient::findWhere(['user_id' => $user_id]);
        $patient = !empty($patients) ? $patients[0] : null;
        
        if (!$patient) {
            return new WP_REST_Response(['error' => 'Patient not found'], 404);
        }

        // Get recent visitations
        $visitations = \HospitalManager\Models\Visitation::forPatient($patient->id);

        // Get pending lab tests
        $lab_tests = \HospitalManager\Models\LabInvestigation::getPendingForPatient($patient->id);

        // Get upcoming appointments
        $appointments = \HospitalManager\Models\Appointment::getUpcomingForPatient($patient->id);

        return new WP_REST_Response([
            'patient' => $patient,
            'recent_visitations' => array_slice($visitations, 0, 5),
            'pending_lab_tests' => $lab_tests,
            'upcoming_appointments' => $appointments,
        ]);
    }

    /**
     * Get dashboard data for doctor users
     *
     * @param int $user_id User ID of the doctor
     * @return WP_REST_Response Response containing doctor dashboard data
     */
    private function get_doctor_dashboard($user_id) 
    {
        $todays_appointments = \HospitalManager\Models\Appointment::getTodaysForDoctor($user_id);
        $pending_reports = \HospitalManager\Models\MedicalReport::getPendingForDoctor($user_id);

        return new WP_REST_Response([
            'todays_appointments' => $todays_appointments,
            'pending_reports' => $pending_reports,
            'total_patients_today' => count($todays_appointments)
        ], 200);
    }

    /**
     * Get dashboard data for lab technician users
     *
     * @param int $user_id User ID of the lab technician
     * @return WP_REST_Response Response containing lab dashboard data
     */
    private function get_lab_dashboard($user_id) 
    {
        $pending_tests = \HospitalManager\Models\LabInvestigation::getPendingForTech($user_id);
        $completed_tests = \HospitalManager\Models\LabInvestigation::getCompletedCountForTechToday($user_id);

        return new WP_REST_Response([
            'pending_tests' => $pending_tests,
            'tests_completed_today' => $completed_tests
        ], 200);
    }
}