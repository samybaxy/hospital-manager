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
        
        // Add the missing stats endpoint
        register_rest_route('hospital-manager/v1', '/dashboard/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_stats'],
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
        $visitations = \HospitalManager\Models\Visitation::forPatient($patient->ID);

        // Get pending lab tests
        $lab_tests = \HospitalManager\Models\LabInvestigation::getPendingForPatient($patient->ID);

        // Get upcoming appointments
        $appointments = \HospitalManager\Models\Appointment::getUpcomingForPatient($patient->ID);

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

    /**
     * Get dashboard statistics for the admin dashboard
     *
     * @param WP_REST_Request $request API request object
     * @return WP_REST_Response Response containing dashboard stats
     */
    public function get_dashboard_stats($request) {
        global $wpdb;
        
        try {
            // Get count of patients from the database
            $patients_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_patients"
            ) ?: 0;
            
            // Get count of doctors (users with doctor role)
            $doctors_count = count(get_users(['role' => 'doctor'])) ?: 0;
            
            // Get count of appointments
            $appointments_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_appointments"
            ) ?: 0;
            
            // Get count of departments
            $departments_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_departments"
            ) ?: 0;
            
            // Get inventory summary
            $inventory_summary = null;
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}hm_inventory'")) {
                $inventory_summary = $wpdb->get_row("
                    SELECT 
                        COUNT(*) as total_items,
                        COUNT(CASE WHEN quantity <= reorder_level THEN 1 END) as critical_items,
                        COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 1 END) as expiring_soon,
                        COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 END) as expired_items,
                        COUNT(CASE WHEN status = 'Out of Stock' THEN 1 END) as out_of_stock,
                        SUM(quantity * COALESCE(cost, 0)) as total_value
                    FROM {$wpdb->prefix}hm_inventory
                ", ARRAY_A) ?: [
                    'total_items' => 0,
                    'critical_items' => 0,
                    'expiring_soon' => 0,
                    'expired_items' => 0,
                    'out_of_stock' => 0,
                    'total_value' => 0
                ];
            } else {
                $inventory_summary = [
                    'total_items' => 0,
                    'critical_items' => 0,
                    'expiring_soon' => 0,
                    'expired_items' => 0,
                    'out_of_stock' => 0,
                    'total_value' => 0
                ];
            }
            
            // Get recent activities (last 5)
            $recent_activities = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}hm_audit_logs 
                ORDER BY created_at DESC 
                LIMIT 5"
            ) ?: [];
            
            // Get upcoming appointments (next 5)
            $upcoming_appointments = $wpdb->get_results(
                "SELECT a.*, 
                        p.first_name, p.last_name,
                        d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                        CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                FROM {$wpdb->prefix}hm_appointments a
                LEFT JOIN {$wpdb->prefix}hm_patients p ON a.patient_id = p.ID
                LEFT JOIN {$wpdb->prefix}hm_doctors d ON a.doctor_id = d.ID
                WHERE a.appointment_date >= CURDATE()
                ORDER BY a.appointment_date ASC, a.appointment_time ASC
                LIMIT 5"
            ) ?: [];
            
            // Format the activities and appointments if needed
            foreach ($recent_activities as &$activity) {
                $activity->created_at = mysql2date('F j, Y g:i a', $activity->created_at);
            }
            
            foreach ($upcoming_appointments as &$appointment) {
                if (isset($appointment->appointment_date)) {
                    $appointment->formatted_date = mysql2date('F j, Y', $appointment->appointment_date);
                }
            }
            
            return new WP_REST_Response([
                'patients_count' => (int)$patients_count,
                'doctors_count' => (int)$doctors_count,
                'appointments_count' => (int)$appointments_count,
                'departments_count' => (int)$departments_count,
                'inventory_summary' => $inventory_summary,
                'recent_activities' => $recent_activities,
                'upcoming_appointments' => $upcoming_appointments,
            ], 200);
            
        } catch (\Exception $e) {
            // Log the error
            error_log('Dashboard stats error: ' . $e->getMessage());
            
            // Return a generic error response
            return new WP_REST_Response([
                'patients_count' => 0,
                'doctors_count' => 0,
                'appointments_count' => 0,
                'departments_count' => 0,
                'inventory_summary' => [
                    'total_items' => 0,
                    'critical_items' => 0,
                    'expiring_soon' => 0,
                    'expired_items' => 0,
                    'out_of_stock' => 0,
                    'total_value' => 0
                ],
                'recent_activities' => [],
                'upcoming_appointments' => [],
                'error' => 'Failed to fetch dashboard statistics'
            ], 500);
        }
    }
}