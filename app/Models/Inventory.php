<?php

namespace HospitalManager\Models;

class Inventory extends BaseModel
{
    protected $tableName = 'hm_inventory';
    protected $primaryKey = 'ID';

    // Status constants
    const STATUS_IN_STOCK = 'In Stock';
    const STATUS_LOW_STOCK = 'Low Stock';
    const STATUS_OUT_OF_STOCK = 'Out of Stock';
    const STATUS_EXPIRED = 'Expired';

    // Category constants
    const CATEGORY_MEDICATION = 'Medication';
    const CATEGORY_EQUIPMENT = 'Equipment';
    const CATEGORY_SUPPLIES = 'Supplies';
    const CATEGORY_PPE = 'PPE';
    const CATEGORY_CONSUMABLES = 'Consumables';
    const CATEGORY_SURGICAL = 'Surgical';

    /**
     * Find a single inventory item by ID
     *
     * @param int $id
     * @return object|null
     */
    public static function findOne($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $id);
        return $wpdb->get_row($query);
    }

    /**
     * Get all inventory items with filtering
     *
     * @param array $filters
     * @return array
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $where = ['1=1'];
        $values = [];

        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $values[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        if (!empty($filters['location'])) {
            $where[] = 'location = %s';
            $values[] = $filters['location'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(item_name LIKE %s OR category LIKE %s OR location LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        if (!empty($filters['expiring_soon'])) {
            $days = intval($filters['expiring_soon']);
            $where[] = 'expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)';
            $values[] = $days;
        }

        if (!empty($filters['low_stock'])) {
            $where[] = 'quantity <= reorder_level';
        }

        $where_clause = implode(' AND ', $where);
        
        $order_by = 'ORDER BY created_at DESC';
        if (!empty($filters['sort_by'])) {
            $allowed_sorts = ['item_name', 'category', 'quantity', 'status', 'expiry_date', 'created_at'];
            if (in_array($filters['sort_by'], $allowed_sorts)) {
                $direction = (!empty($filters['sort_direction']) && $filters['sort_direction'] === 'asc') ? 'ASC' : 'DESC';
                $order_by = "ORDER BY {$filters['sort_by']} {$direction}";
            }
        }

        $limit = '';
        if (!empty($filters['limit'])) {
            $limit = 'LIMIT ' . intval($filters['limit']);
            if (!empty($filters['offset'])) {
                $limit = 'LIMIT ' . intval($filters['offset']) . ', ' . intval($filters['limit']);
            }
        }

        $query = "SELECT * FROM {$table} WHERE {$where_clause} {$order_by} {$limit}";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get critical inventory items (below reorder level)
     *
     * @return array
     */
    public static function getCritical()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = "SELECT * FROM {$table} WHERE quantity <= reorder_level ORDER BY quantity ASC";
        
        return $wpdb->get_results($query);
    }

    /**
     * Get items expiring soon
     *
     * @param int $days Number of days to look ahead
     * @return array
     */
    public static function getExpiringSoon($days = 30)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE expiry_date IS NOT NULL 
             AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
             AND expiry_date >= CURDATE()
             ORDER BY expiry_date ASC",
            $days
        );
        
        return $wpdb->get_results($query);
    }

    /**
     * Get expired items
     *
     * @return array
     */
    public static function getExpired()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = "SELECT * FROM {$table} 
                  WHERE expiry_date IS NOT NULL 
                  AND expiry_date < CURDATE()
                  ORDER BY expiry_date DESC";
        
        return $wpdb->get_results($query);
    }

    /**
     * Get inventory summary statistics
     *
     * @return array
     */
    public static function getSummary()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN quantity <= reorder_level AND quantity > 0 THEN 1 END) as low_stock,
                COUNT(CASE WHEN quantity > reorder_level THEN 1 END) as in_stock,
                COUNT(CASE WHEN quantity <= reorder_level THEN 1 END) as critical_items,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 1 END) as expiring_soon,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 END) as expired_items,
                COUNT(CASE WHEN status = 'Out of Stock' THEN 1 END) as out_of_stock,
                SUM(quantity * COALESCE(cost, 0)) as total_value
            FROM {$table}
        ", ARRAY_A);

        return $stats;
    }

    /**
     * Get categories with item counts
     *
     * @return array
     */
    public static function getCategorySummary()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = "SELECT 
                    category,
                    COUNT(*) as item_count,
                    SUM(quantity) as total_quantity,
                    SUM(quantity * COALESCE(cost, 0)) as category_value
                  FROM {$table}
                  GROUP BY category
                  ORDER BY item_count DESC";
        
        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Update inventory status based on current quantity and expiry
     *
     * @param int $id Inventory item ID
     * @return bool
     */
    public static function updateStatus($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT quantity, reorder_level, expiry_date FROM {$table} WHERE ID = %d",
            $id
        ));

        if (!$item) {
            return false;
        }

        $status = self::STATUS_IN_STOCK;

        // Check if expired
        if (!empty($item->expiry_date) && strtotime($item->expiry_date) < time()) {
            $status = self::STATUS_EXPIRED;
        }
        // Check if out of stock
        elseif ($item->quantity == 0) {
            $status = self::STATUS_OUT_OF_STOCK;
        }
        // Check if low stock
        elseif ($item->quantity <= $item->reorder_level) {
            $status = self::STATUS_LOW_STOCK;
        }

        return $wpdb->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['ID' => $id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Update all inventory statuses
     *
     * @return int Number of items updated
     */
    public static function updateAllStatuses()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        // Get all items
        $items = $wpdb->get_results("SELECT ID FROM {$table}");
        $updated = 0;
        
        foreach ($items as $item) {
            if (self::updateStatus($item->ID)) {
                $updated++;
            }
        }
        
        return $updated;
    }

    /**
     * Create a new inventory item
     *
     * @param array $data
     * @return int|false Item ID on success, false on failure
     */
    public static function create($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $defaults = [
            'quantity' => 0,
            'unit' => 'units',
            'reorder_level' => 10,
            'status' => self::STATUS_IN_STOCK,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $data = array_merge($defaults, $data);
        
        // Validate required fields
        if (empty($data['item_name']) || empty($data['category'])) {
            return false;
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result !== false) {
            $item_id = $wpdb->insert_id;
            self::updateStatus($item_id);
            return $item_id;
        }
        
        return false;
    }

    /**
     * Update an inventory item
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function updateItem($id, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table,
            $data,
            ['ID' => $id],
            null,
            ['%d']
        );
        
        if ($result !== false) {
            self::updateStatus($id);
            return true;
        }
        
        return false;
    }

    /**
     * Delete an inventory item
     *
     * @param int $id
     * @return bool
     */
    public static function deleteItem($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        return $wpdb->delete($table, ['ID' => $id], ['%d']) !== false;
    }

    /**
     * Get available categories
     *
     * @return array
     */
    public static function getCategories()
    {
        return [
            self::CATEGORY_MEDICATION,
            self::CATEGORY_EQUIPMENT,
            self::CATEGORY_SUPPLIES,
            self::CATEGORY_PPE,
            self::CATEGORY_CONSUMABLES,
            self::CATEGORY_SURGICAL
        ];
    }

    /**
     * Get available statuses
     *
     * @return array
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_IN_STOCK,
            self::STATUS_LOW_STOCK,
            self::STATUS_OUT_OF_STOCK,
            self::STATUS_EXPIRED
        ];
    }
}
