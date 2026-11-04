<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Stats;

class StatsController extends BaseController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_stats'],
                'permission_callback' => function() {
                    return current_user_can('administrator');
                }
            ]
        ]);
    }

    public function get_stats()
    {
        $stats = [
            'totalPatients' => Patient::count(),
            'activeDoctors' => count(get_users(['role' => 'doctor'])),
            'todayVisitations' => $this->count_todays_visitations(),
            'pendingLabTests' => $this->count_pending_lab_tests(),
            
            // Get visitation trends for the last 30 days
            // 'visitationsTrend' => Stats::getVisitationTrend(),
            
            // Get patient distribution by HMO
            // 'patientsByHMO' => Stats::getPatientsByHMO(),
            
            // Get monthly lab tests statistics
            // 'monthlyLabTests' => Stats::getMonthlyLabTests()
        ];

        return new WP_REST_Response($stats);
    }

    /**
     * Uncached on purpose: BaseModel::count() caches for an hour, which is wrong for "today".
     */
    private function count_todays_visitations()
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hm_visitations WHERE date = %s",
            current_time('Y-m-d')
        ));
    }

    /**
     * "Pending" = not yet resulted. hm_lab_investigations.status is an ENUM with no 'pending' value.
     */
    private function count_pending_lab_tests()
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hm_lab_investigations
             WHERE status IN ('requested', 'sample_collected', 'in_progress')"
        );
    }

    private function get_visitation_trend()
    {
        return Stats::getVisitationTrend();
    }

    private function get_patients_by_hmo()
    {
        return Stats::getPatientsByHMO();
    }

    private function get_monthly_lab_tests()
    {
        return Stats::getMonthlyLabTests();
    }
}
