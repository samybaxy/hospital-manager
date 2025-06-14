<?php

namespace HospitalManager\Services;

use Error;

class DashboardService
{
    /**
     * Get comprehensive dashboard statistics for admin dashboard
     * 
     * @return array Dashboard statistics
     * @throws \Exception If database error occurs
     */
    public static function getDashboardStats()
    {
        global $wpdb;
        
        try {
            return [
                'patients_count' => self::getPatientsCount(),
                'doctors_count' => self::getDoctorsCount(),
                'appointments_count' => self::getAppointmentsCount(),
                'departments_count' => self::getDepartmentsCount(),
                'inventory_summary' => self::getInventorySummary(),
                'recent_activities' => self::getRecentActivities(),
                'upcoming_appointments' => self::getUpcomingAppointments(),
            ];
            
        } catch (\Exception $e) {
            error_log('DashboardService::getDashboardStats error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get total number of patients
     * 
     * @return int Number of patients
     */
    public static function getPatientsCount()
    {
        global $wpdb;
        
        // Check if patients table exists
        if (!self::tableExists('hm_patients')) {
            return 0;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_patients");
        
        if ($wpdb->last_error) {
            error_log('DashboardService::getPatientsCount error: ' . $wpdb->last_error);
            return 0;
        }
        
        return (int) ($count ?: 0);
    }

    /**
     * Get total number of doctors
     * 
     * @return int Number of doctors
     */
    public static function getDoctorsCount()
    {
        $doctors = get_users(['role' => 'doctor']);
        return count($doctors);
    }

    /**
     * Get total number of appointments
     * 
     * @return int Number of appointments
     */
    public static function getAppointmentsCount()
    {
        global $wpdb;
        
        // Check if appointments table exists
        if (!self::tableExists('hm_appointments')) {
            return 0;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_appointments");
        
        if ($wpdb->last_error) {
            error_log('DashboardService::getAppointmentsCount error: ' . $wpdb->last_error);
            return 0;
        }
        
        return (int) ($count ?: 0);
    }

    /**
     * Get total number of departments
     * 
     * @return int Number of departments
     */
    public static function getDepartmentsCount()
    {
        global $wpdb;
        
        // Check if departments table exists
        if (!self::tableExists('hm_departments')) {
            return 0;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hm_departments");
        
        if ($wpdb->last_error) {
            error_log('DashboardService::getDepartmentsCount error: ' . $wpdb->last_error);
            return 0;
        }
        
        return (int) ($count ?: 0);
    }

    /**
     * Get inventory summary statistics
     * 
     * @return array Inventory summary data
     */
    public static function getInventorySummary()
    {
        global $wpdb;
        
        $default_summary = [
            'total_items' => 0,
            'critical_items' => 0,
            'expiring_soon' => 0,
            'expired_items' => 0,
            'out_of_stock' => 0,
            'total_value' => 0
        ];
        
        // Check if inventory table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}hm_inventory'");
        
        if (!$table_exists) {
            return $default_summary;
        }
        
        $inventory_summary = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN quantity <= reorder_level THEN 1 END) as critical_items,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 1 END) as expiring_soon,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 END) as expired_items,
                COUNT(CASE WHEN status = 'Out of Stock' THEN 1 END) as out_of_stock,
                SUM(quantity * COALESCE(cost, 0)) as total_value
            FROM {$wpdb->prefix}hm_inventory
        ", ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log('DashboardService::getInventorySummary error: ' . $wpdb->last_error);
            return $default_summary;
        }
        
        return $inventory_summary ?: $default_summary;
    }

    /**
     * Get recent activities from audit logs
     * 
     * @param int $limit Number of activities to return
     * @return array Recent activities
     */
    public static function getRecentActivities($limit = 5)
    {
        global $wpdb;
        
        // Check if audit logs table exists
        if (!self::tableExists('hm_audit_logs')) {
            return [];
        }
        
        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hm_audit_logs 
                ORDER BY created_at DESC 
                LIMIT %d",
                $limit
            )
        );
        
        if ($wpdb->last_error) {
            error_log('DashboardService::getRecentActivities error: ' . $wpdb->last_error);
            return [];
        }
        
        $formatted_activities = [];
        if ($activities) {
            foreach ($activities as $activity) {
                $activity->created_at = mysql2date('F j, Y g:i a', $activity->created_at);
                $formatted_activities[] = $activity;
            }
        }
        
        return $formatted_activities;
    }

    /**
     * Get upcoming appointments with patient and doctor details
     * 
     * @param int $limit Number of appointments to return
     * @return array Upcoming appointments
     */
    public static function getUpcomingAppointments($limit = 5)
    {
        global $wpdb;
        
        // Check if required tables exist
        if (!self::tableExists('hm_appointments')) {
            return [];
        }
        
        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, 
                        p.first_name, p.last_name,
                        d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                        CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                FROM {$wpdb->prefix}hm_appointments a
                LEFT JOIN {$wpdb->prefix}hm_patients p ON a.patient_id = p.ID
                LEFT JOIN {$wpdb->prefix}hm_doctors d ON a.doctor_id = d.ID
                WHERE a.appointment_date >= CURDATE()
                ORDER BY a.appointment_date ASC, a.appointment_time ASC
                LIMIT %d",
                $limit
            )
        );

        if ($wpdb->last_error) {
            error_log('DashboardService::getUpcomingAppointments error: ' . $wpdb->last_error);
            return [];
        }
        
        $formatted_appointments = [];
        if ($appointments) {
            foreach ($appointments as $appointment) {
                if (isset($appointment->appointment_date)) {
                    $appointment->formatted_date = mysql2date('F j, Y', $appointment->appointment_date);
                }
                $formatted_appointments[] = $appointment;
            }
        }
        
        return $formatted_appointments;
    }

    /**
     * Get default stats structure with zero values for error cases
     * 
     * @return array Default dashboard statistics
     */
    public static function getDefaultStats()
    {
        return [
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
        ];
    }

    /**
     * Check if a database table exists
     * 
     * @param string $table_name Table name without prefix
     * @return bool True if table exists
     */
    private static function tableExists($table_name)
    {
        global $wpdb;
        
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->prefix . $table_name
            )
        );
        
        return !empty($table_exists);
    }
}
