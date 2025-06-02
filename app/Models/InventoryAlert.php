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
            $where_conditions[] = 'a.alert_type = %s';
            $params[] = sanitize_text_field($filters['type']);
        }
        
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'a.severity = %s';
            $params[] = sanitize_text_field($filters['severity']);
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where_conditions[] = 'a.is_active = 1';
            } elseif ($filters['status'] === 'resolved') {
                $where_conditions[] = 'a.resolved_at IS NOT NULL';
            } elseif ($filters['status'] === 'acknowledged') {
                $where_conditions[] = 'a.acknowledged_at IS NOT NULL AND a.resolved_at IS NULL';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        
        $query = "SELECT 
                    a.*,
                    i.item_name,
                    i.category,
                    i.quantity,
                    i.unit,
                    i.reorder_level,
                    i.expiry_date,
                    i.location,
                    i.cost
                  FROM {$wpdb->prefix}hm_inventory_alerts a
                  LEFT JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
                  WHERE {$where_clause} 
                  ORDER BY a.created_at DESC 
                  LIMIT %d";
        $params[] = $limit;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query) ?: [];
    }
}
