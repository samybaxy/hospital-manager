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
            'todayVisitations' => Visitation::where('date', date('Y-m-d'))->count(),
            'pendingLabTests' => LabInvestigation::where('status', 'pending')->count(),
            
            // Get visitation trends for the last 30 days
            // 'visitationsTrend' => Stats::getVisitationTrend(),
            
            // Get patient distribution by HMO
            // 'patientsByHMO' => Stats::getPatientsByHMO(),
            
            // Get monthly lab tests statistics
            // 'monthlyLabTests' => Stats::getMonthlyLabTests()
        ];

        return new WP_REST_Response($stats);
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
