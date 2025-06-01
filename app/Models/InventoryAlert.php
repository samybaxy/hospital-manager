<?php

namespace HospitalManager\Models;

class InventoryAlert extends BaseModel
{
    protected $table = 'hm_inventory_alerts';
    
    /**
     * Get filtered alerts
     *
     * @param array $filters
     * @return array
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where_conditions[] = 'alert_type = %s';
            $params[] = sanitize_text_field($filters['type']);
        }
        
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'severity = %s';
            $params[] = sanitize_text_field($filters['severity']);
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where_conditions[] = 'is_active = 1';
            } elseif ($filters['status'] === 'resolved') {
                $where_conditions[] = 'resolved_at IS NOT NULL';
            } elseif ($filters['status'] === 'acknowledged') {
                $where_conditions[] = 'acknowledged_at IS NOT NULL AND resolved_at IS NULL';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        
        $query = "SELECT * FROM {$wpdb->prefix}hm_inventory_alerts WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query) ?: [];
    }
}
