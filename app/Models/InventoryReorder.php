<?php

namespace HospitalManager\Models;

class InventoryReorder extends BaseModel
{
    protected $table = 'hm_inventory_reorders';
    
    /**
     * Get filtered reorders
     *
     * @param array $filters
     * @return array
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $params[] = sanitize_text_field($filters['status']);
        }
        
        if (!empty($filters['priority'])) {
            $where_conditions[] = 'priority = %s';
            $params[] = sanitize_text_field($filters['priority']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM {$wpdb->prefix}hm_inventory_reorders WHERE {$where_clause} ORDER BY created_at DESC";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query) ?: [];
    }
}
