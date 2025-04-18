<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\LabInvestigation;

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
        global $wpdb;

        $stats = [
            'totalPatients' => Patient::count(),
            'activeDoctors' => count(get_users(['role' => 'doctor'])),
            'todayVisitations' => Visitation::where('date', date('Y-m-d'))->count(),
            'pendingLabTests' => LabInvestigation::where('status', 'pending')->count(),
            
            // Get visitation trends for the last 30 days
            'visitationsTrend' => $this->get_visitation_trend(),
            
            // Get patient distribution by HMO
            'patientsByHMO' => $this->get_patients_by_hmo(),
            
            // Get monthly lab tests statistics
            'monthlyLabTests' => $this->get_monthly_lab_tests()
        ];

        return new WP_REST_Response($stats);
    }

    private function get_visitation_trend()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(date) as date,
                COUNT(*) as count
            FROM {$table}
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(date)
            ORDER BY date ASC
        "));
    }

    private function get_patients_by_hmo()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                hmo_id as name,
                COUNT(*) as value
            FROM {$table}
            GROUP BY hmo_id
        "));
    }

    private function get_monthly_lab_tests()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_lab_investigations';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM {$table}
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        "));
    }
}
