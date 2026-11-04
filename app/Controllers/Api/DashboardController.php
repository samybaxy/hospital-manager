<?php

namespace HospitalManager\Controllers\Api;

use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\MedicalReport;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use HospitalManager\Services\DashboardService;

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
        
        // Add the missing stats endpoint
        register_rest_route('hospital-manager/v1', '/dashboard/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_stats'],
                'permission_callback' => [$this, 'check_stats_permissions'],
            ]
        ]);
    }

    public function check_permissions() 
    {
        return is_user_logged_in();
    }

    /**
     * Hospital-wide figures (counts, recent audit activity, everyone's upcoming
     * appointments with patient names): clinical and front-desk staff only. Any
     * signed-in user, patients included, used to get them.
     */
    public function check_stats_permissions()
    {
        if (!is_user_logged_in()) {
            return new \WP_Error('rest_forbidden', 'You must be logged in to access this endpoint.', ['status' => 401]);
        }
        if (!\HospitalManager\Services\AccessPolicy::allowsAll(wp_get_current_user(), 'view_hospital_stats')) {
            return new \WP_Error('rest_forbidden', 'You do not have permission to view hospital statistics.', ['status' => 403]);
        }
        return true;
    }

    public function get_dashboard_data($request) 
    {
        $user = wp_get_current_user();
        $roles = (array) $user->roles;

        if (in_array('patient', $roles, true)) {
            return $this->get_patient_dashboard($user->ID);
        }
        if (in_array('doctor', $roles, true)) {
            return $this->get_doctor_dashboard($user->ID);
        }
        if (in_array('lab_tech', $roles, true)) {
            return $this->get_lab_dashboard($user->ID);
        }

        // Every other role used to get 403 "Invalid role" (administrator, desk
        // officer, nurse, staff, inventory and pharmacy).
        if (\HospitalManager\Services\AccessPolicy::allowsAll($user, 'view_hospital_stats')) {
            return new WP_REST_Response(array_merge(
                ['role' => $roles[0] ?? ''],
                DashboardService::getDashboardStats()
            ), 200);
        }
        if (array_intersect($roles, ['inventory_manager', 'pharmacy_staff'])) {
            return new WP_REST_Response([
                'role' => $roles[0],
                'inventory' => DashboardService::getInventorySummary(),
            ], 200);
        }

        return new WP_REST_Response(['role' => $roles[0] ?? ''], 200);
    }

    /**
     * Get dashboard data for a patient
     *
     * @param int $user_id WordPress user ID
     * @return WP_REST_Response
     */
    private function get_patient_dashboard($user_id) 
    {
        // hm_patients.ID (not the WP user ID) is what visitations/labs/appointments reference
        $patient = Patient::findByUserId($user_id);

        if (!$patient) {
            return new WP_REST_Response(['error' => 'Patient not found'], 404);
        }

        // Get recent visitations (already ordered newest first)
        $visitations = Visitation::getForPatient($patient->ID);

        // Get pending lab tests
        $lab_tests = LabInvestigation::getPendingForPatient($patient->ID);

        // Get upcoming appointments
        $appointments = Appointment::getUpcomingForPatient($patient->ID);

        return new WP_REST_Response([
            'patient' => $this->model_to_array($patient),
            'recent_visitations' => $this->models_to_array(array_slice($visitations, 0, 5)),
            'pending_lab_tests' => $this->models_to_array($lab_tests),
            'upcoming_appointments' => $this->models_to_array($appointments),
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
        // Appointments reference hm_doctors.ID, so resolve the doctor record first.
        $doctor = Doctor::findByUserId($user_id);

        if (!$doctor) {
            return new WP_REST_Response(['error' => 'Doctor record not found for this user'], 404);
        }

        $todays_appointments = Appointment::getTodaysForDoctor($doctor->ID);
        // hm_medical_reports.doctor_id stores the doctor's WP user ID (see MedicalReport::doctor()).
        $pending_reports = MedicalReport::getPendingForDoctor($user_id);

        return new WP_REST_Response([
            'doctor_id' => (int) $doctor->ID,
            'todays_appointments' => $this->models_to_array($todays_appointments),
            'pending_reports' => $this->models_to_array($pending_reports),
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
        $pending_tests = LabInvestigation::getPendingForTech($user_id);
        $completed_tests = LabInvestigation::getCompletedCountForTechToday($user_id);

        return new WP_REST_Response([
            'pending_tests' => $this->models_to_array($pending_tests),
            'tests_completed_today' => $completed_tests
        ], 200);
    }

    /**
     * Models keep their data in protected attributes, so they must be converted
     * explicitly or json_encode() emits "{}".
     */
    private function model_to_array($model)
    {
        if (is_object($model) && method_exists($model, 'toArray')) {
            return $model->toArray();
        }
        if (is_object($model) && method_exists($model, 'getAttributes')) {
            return $model->getAttributes();
        }
        return $model;
    }

    private function models_to_array($models)
    {
        return array_values(array_map([$this, 'model_to_array'], (array) $models));
    }

    /**
     * Get dashboard statistics for the admin dashboard
     *
     * @param WP_REST_Request $request API request object
     * @return WP_REST_Response Response containing dashboard stats
     */
    public function get_dashboard_stats($request) {
        try {
            $stats = DashboardService::getDashboardStats();
            return new WP_REST_Response($stats, 200);
            
        } catch (\Exception $e) {
            // Log the error
            error_log('Dashboard stats error: ' . $e->getMessage());
            
            // Return default stats with error message
            $default_stats = DashboardService::getDefaultStats();
            $default_stats['error'] = 'Failed to fetch dashboard statistics';
            
            return new WP_REST_Response($default_stats, 500);
        }
    }
}