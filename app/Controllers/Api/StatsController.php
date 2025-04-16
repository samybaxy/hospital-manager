<?php

namespace HospitalManager\Controllers\Api;

use WP_REST_Response;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;

class StatsController extends BaseController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/stats', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_stats'],
                'permission_callback' => function($request) {
                    return $this->check_permission($request, 'manage_options');
                },
            ]
        ]);
    }

    public function get_stats()
    {
        global $wpdb;

        $stats = [
            'total_patients' => Patient::count(),
            'total_visitations' => Visitation::count(),
            'visitations_by_month' => $this->get_visitations_by_month(),
            'patients_by_hmo' => $this->get_patients_by_hmo(),
        ];

        return new WP_REST_Response($stats, 200);
    }

    private function get_visitations_by_month()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        return $wpdb->get_results("
            SELECT DATE_FORMAT(date, '%Y-%m') as month, 
                   COUNT(*) as count
            FROM {$table}
            GROUP BY month
            ORDER BY month DESC
            LIMIT 12
        ");
    }
}