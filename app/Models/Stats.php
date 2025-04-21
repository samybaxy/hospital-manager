<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Models\PostModel;
use WPMVC\MVC\Traits\FindTrait;

class Stats extends PostModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $table = 'wp_hm_stats';
    protected static $conditions = [];
    protected static $orderBy = [];

    /**
     * Get visitation trend for the last 30 days
     */
    public static function getVisitationTrend()
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

    /**
     * Get patient distribution by HMO
     */
    public static function getPatientsByHMO()
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

    /**
     * Get monthly lab tests statistics
     */
    public static function getMonthlyLabTests()
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
