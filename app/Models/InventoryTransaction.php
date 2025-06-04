<?php

namespace HospitalManager\Models;

class InventoryTransaction extends BaseModel
{
    protected $table = 'hm_inventory_transactions';
    
    /**
     * Get filtered transactions
     *
     * @param array $filters
     * @return array
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['item_id'])) {
            $where_conditions[] = 'inventory_id = %d';
            $params[] = intval($filters['item_id']);
        }
        
        if (!empty($filters['type'])) {
            $where_conditions[] = 'transaction_type = %s';
            $params[] = sanitize_text_field($filters['type']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(created_at) >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(created_at) <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $query = "SELECT * FROM {$wpdb->prefix}hm_inventory_transactions WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query) ?: [];
    }

    /**
     * Get count of filtered transactions for pagination
     *
     * @param array $filters
     * @return int
     */
    public static function getFilteredCount($filters = [])
    {
        global $wpdb;
        
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['item_id'])) {
            $where_conditions[] = 'inventory_id = %d';
            $params[] = intval($filters['item_id']);
        }
        
        if (!empty($filters['type'])) {
            $where_conditions[] = 'transaction_type = %s';
            $params[] = sanitize_text_field($filters['type']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(created_at) >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(created_at) <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}hm_inventory_transactions WHERE {$where_clause}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return intval($wpdb->get_var($query));
    }
}
