<?php

namespace HospitalManager\Models;

class InventoryReorder extends BaseModel
{
    protected $table = 'hm_inventory_reorders';
    
    /**
     * Get filtered reorders with pagination
     *
     * @param array $filters
     * @param int $page
     * @param int $per_page
     * @return array
     */
    /**
     * Get filtered reorders with pagination
     *
     * @param array $filters
     * @param int $page
     * @param int $per_page
     * @return array
     */
    public static function getFiltered($filters = [], $page = 1, $per_page = 10)
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
        
        if (!empty($filters['item_id'])) {
            $where_conditions[] = 'inventory_id = %d';
            $params[] = intval($filters['item_id']);
        }
        
        if (!empty($filters['supplier_id'])) {
            $where_conditions[] = 'supplier_id = %d';
            $params[] = intval($filters['supplier_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Check if table exists first
        $table_name = $wpdb->prefix . 'hm_inventory_reorders';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if (!$table_exists) {
            // Return empty result if table doesn't exist
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => intval($page),
                    'per_page' => intval($per_page),
                    'total_items' => 0,
                    'total_pages' => 0,
                    'has_next' => false,
                    'has_prev' => false
                ]
            ];
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        
        $total_items = intval($wpdb->get_var($count_query));
        
        // Get paginated data
        $data_query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        $final_params = $params;
        $final_params[] = intval($per_page);
        $final_params[] = intval($offset);
        
        $data_query = $wpdb->prepare($data_query, $final_params);
        $data = $wpdb->get_results($data_query) ?: [];
        
        // Calculate pagination info
        $total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 0;
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => intval($page),
                'per_page' => intval($per_page),
                'total_items' => $total_items,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Get filtered count (legacy method for backward compatibility)
     *
     * @param array $filters
     * @return int
     */
    public static function getFilteredCount($filters = [])
    {
        $result = self::getFiltered($filters, 1, 1);
        return $result['pagination']['total_items'];
    }
}
