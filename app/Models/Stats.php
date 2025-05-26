<?php

namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class Stats extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'ID';
    protected $tableName = 'hm_stats';
    protected static $conditions = [];
    protected static $orderBy = [];

    /**
     * Get visitation trend for the last 30 days
     */
    public static function getVisitationTrend()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        $query = "
            SELECT 
                DATE(date) as date,
                COUNT(*) as count
            FROM {$table}
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(date)
            ORDER BY date ASC
        ";
        
        return $wpdb->get_results($query);
    }

    /**
     * Get patient distribution by HMO
     * 
     * Returns an array of objects with properties:
     * - name (which is actually the hmo_id)
     * - value (count of patients with that HMO)
     */
    public static function getPatientsByHMO()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $query = "
            SELECT 
                hmo_id as name,
                COUNT(*) as value
            FROM {$table}
            GROUP BY hmo_id
        ";
        
        return $wpdb->get_results($query);
    }

    /**
     * Get monthly lab tests statistics
     */
    public static function getMonthlyLabTests()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_lab_investigations';
        
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM {$table}
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ";
        
        return $wpdb->get_results($query);
    }
}
