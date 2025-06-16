<?php

namespace HospitalManager\Models;

class Inventory extends BaseModel
{
    protected $tableName = 'hm_inventory';
    protected $primaryKey = 'ID';
    protected static $cache_expiration = 600; // 10 minutes for frequently changing inventory data

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

    protected $fillable = [
        'item_name',
        'category',
        'quantity',
        'unit',
        'cost',
        'selling_price',
        'supplier_id',
        'location',
        'reorder_level',
        'max_stock_level',
        'expiry_date',
        'batch_number',
        'manufacturer',
        'status',
        'notes'
    ];

    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        parent::__construct($attributes);
    }

    /**
     * Build WHERE clause for filtering (shared between getFiltered and getFilteredCount)
     * 
     * @param array $filters
     * @return array ['where' => array, 'values' => array]
     */
    private static function buildFilterWhereClause($filters = [])
    {
        global $wpdb;
        
        // When a status filter is applied, remove conflicting boolean filters to prevent contradictions
        if (!empty($filters['status'])) {
            $filters['expiring'] = '';
            $filters['low_stock'] = '';
        }
        
        $where = ['1=1'];
        $values = [];

        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $values[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            // Handle computed status filtering based on business logic
            switch ($filters['status']) {
                case 'In Stock':
                    $where[] = 'quantity > reorder_level AND (expiry_date IS NULL OR expiry_date > CURDATE())';
                    break;
                case 'Low Stock':
                    $where[] = 'quantity <= reorder_level AND quantity > 0 AND (expiry_date IS NULL OR expiry_date > CURDATE())';
                    break;
                case 'Out of Stock':
                    $where[] = 'quantity = 0';
                    break;
                case 'Expired':
                    $where[] = 'expiry_date IS NOT NULL AND expiry_date <= CURDATE()';
                    break;
                default:
                    $where[] = 'status = %s';
                    $values[] = $filters['status'];
                    break;
            }
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

        // Handle expiring filter
        if (isset($filters['expiring']) && $filters['expiring'] !== '') {
            $is_expiring = filter_var($filters['expiring'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($is_expiring === true) {
                $where[] = 'expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
            } elseif ($is_expiring === false) {
                $where[] = '(expiry_date IS NULL OR expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY))';
            }
        }

        // Backward compatibility for expiring_soon with custom days
        if (!empty($filters['expiring_soon'])) {
            $days = intval($filters['expiring_soon']);
            $where[] = 'expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)';
            $values[] = $days;
        }

        // Handle low_stock filter
        if (isset($filters['low_stock']) && $filters['low_stock'] !== '') {
            $is_low_stock = filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($is_low_stock === true) {
                $where[] = 'quantity <= reorder_level';
            } elseif ($is_low_stock === false) {
                $where[] = 'quantity > reorder_level';
            }
        }

        return [
            'where' => $where,
            'values' => $values
        ];
    }

    /**
     * Enhanced filtering with business logic and caching
     * 
     * @param array $filters
     * @return array
     */
    public static function getFiltered($filters = [])
    {
        $cache_key = static::getCacheKey('getFiltered', $filters);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $filter_data = static::buildFilterWhereClause($filters);
        $where_clause = implode(' AND ', $filter_data['where']);
        $values = $filter_data['values'];
        
        $order_by = 'ORDER BY created_at DESC, ID DESC';
        if (!empty($filters['sort_by'])) {
            $allowed_sorts = ['item_name', 'category', 'quantity', 'status', 'expiry_date', 'created_at'];
            if (in_array($filters['sort_by'], $allowed_sorts)) {
                $direction = (!empty($filters['sort_direction']) && $filters['sort_direction'] === 'asc') ? 'ASC' : 'DESC';
                $order_by = "ORDER BY {$filters['sort_by']} {$direction}, ID {$direction}";
            }
        }

        $limit = '';
        if (!empty($filters['limit'])) {
            $limit = 'LIMIT ' . intval($filters['limit']);
            if (isset($filters['offset']) && $filters['offset'] >= 0) {
                $limit = 'LIMIT ' . intval($filters['offset']) . ', ' . intval($filters['limit']);
            }
        }

        $query = "SELECT * FROM {$table} WHERE {$where_clause} {$order_by} {$limit}";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $result = $wpdb->get_results($query);
        static::setToCache($cache_key, $result, 600); // 10 minutes cache
        
        return $result;
    }

    /**
     * Get count of filtered inventory items with caching
     * 
     * @param array $filters
     * @return int
     */
    public static function getFilteredCount($filters = [])
    {
        $cache_key = static::getCacheKey('getFilteredCount', $filters);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $filter_data = static::buildFilterWhereClause($filters);
        $where_clause = implode(' AND ', $filter_data['where']);
        $values = $filter_data['values'];
        
        $query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $result = intval($wpdb->get_var($query));
        static::setToCache($cache_key, $result, 600); // 10 minutes cache
        
        return $result;
    }

    /**
     * Get low stock items with caching
     * 
     * @return array
     */
    public static function getLowStockItems()
    {
        $cache_key = static::getCacheKey('getLowStockItems', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = "SELECT * FROM {$table} WHERE quantity <= reorder_level AND quantity > 0 ORDER BY quantity ASC";
        
        $result = $wpdb->get_results($query);
        static::setToCache($cache_key, $result, 300); // 5 minutes cache (critical alerts)
        
        return $result;
    }

    /**
     * Get items expiring soon with caching
     * 
     * @param int $days Number of days to look ahead
     * @return array
     */
    public static function getExpiringItems($days = 30)
    {
        $cache_key = static::getCacheKey('getExpiringItems', [$days]);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

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
        
        $result = $wpdb->get_results($query);
        static::setToCache($cache_key, $result, 900); // 15 minutes cache
        
        return $result;
    }

    /**
     * Get expired items with caching
     * 
     * @return array
     */
    public static function getExpiredItems()
    {
        $cache_key = static::getCacheKey('getExpiredItems', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = "SELECT * FROM {$table} 
                  WHERE expiry_date IS NOT NULL 
                  AND expiry_date < CURDATE()
                  ORDER BY expiry_date DESC";
        
        $result = $wpdb->get_results($query);
        static::setToCache($cache_key, $result, 900); // 15 minutes cache
        
        return $result;
    }

    /**
     * Get inventory summary statistics with caching
     * 
     * @return array|null Statistics array or null on error
     */
    public static function getSummaryStats()
    {
        $cache_key = static::getCacheKey('getSummaryStats', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN quantity <= reorder_level AND quantity > 0 THEN 1 END) as low_stock,
                COUNT(CASE WHEN quantity > reorder_level THEN 1 END) as in_stock,
                COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 1 END) as expiring_soon,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 END) as expired_items,
                SUM(quantity * COALESCE(cost, 0)) as total_value,
                COUNT(DISTINCT category) as categories_count,
                COUNT(DISTINCT location) as locations_count,
                AVG(CASE WHEN cost > 0 THEN cost END) as avg_cost
            FROM {$table}
        ", ARRAY_A);

        static::setToCache($cache_key, $stats, 600); // 10 minutes cache
        
        return $stats;
    }

    /**
     * Get category summary with caching
     * 
     * @return array
     */
    public static function getCategorySummary()
    {
        $cache_key = static::getCacheKey('getCategorySummary', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $query = "SELECT 
                    category,
                    COUNT(*) as item_count,
                    SUM(quantity) as total_quantity,
                    SUM(quantity * COALESCE(cost, 0)) as category_value,
                    COUNT(CASE WHEN quantity <= reorder_level THEN 1 END) as low_stock_items
                  FROM {$table}
                  GROUP BY category
                  ORDER BY item_count DESC";
        
        $result = $wpdb->get_results($query, ARRAY_A);
        static::setToCache($cache_key, $result, 3600); // 1 hour cache (stable data)
        
        return $result;
    }

    /**
     * Get all categories with caching
     * 
     * @return array
     */
    public static function getCategories()
    {
        $cache_key = static::getCacheKey('getCategories', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        $categories = [
            self::CATEGORY_MEDICATION,
            self::CATEGORY_EQUIPMENT,
            self::CATEGORY_SUPPLIES,
            self::CATEGORY_PPE,
            self::CATEGORY_CONSUMABLES,
            self::CATEGORY_SURGICAL
        ];

        static::setToCache($cache_key, $categories, 3600); // 1 hour cache
        
        return $categories;
    }

    /**
     * Get all suppliers with caching
     * 
     * @return array
     */
    public static function getSuppliers()
    {
        $cache_key = static::getCacheKey('getSuppliers', []);
        $cached = static::getFromCache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $suppliers_table = $wpdb->prefix . 'hm_inventory_suppliers';
        
        $query = "SELECT * FROM {$suppliers_table} WHERE is_active = 1 ORDER BY name ASC";
        $result = $wpdb->get_results($query);
        
        static::setToCache($cache_key, $result, 3600); // 1 hour cache
        
        return $result;
    }

    /**
     * Enhanced create with cache invalidation
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
            static::updateStatus($item_id);
            static::invalidateInventoryCaches();
            return $item_id;
        }
        
        return false;
    }

    /**
     * Enhanced update with cache invalidation
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
            static::updateStatus($id);
            static::invalidateInventoryCaches();
            return true;
        }
        
        return false;
    }

    /**
     * Enhanced delete with cache invalidation
     * 
     * @param int $id
     * @return bool
     */
    public static function deleteItem($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $result = $wpdb->delete($table, ['ID' => $id], ['%d']);
        
        if ($result !== false) {
            static::invalidateInventoryCaches();
            return true;
        }
        
        return false;
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

        $result = $wpdb->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['ID' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
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

    /**
     * Invalidate inventory-specific caches
     * 
     * @return void
     */
    private static function invalidateInventoryCaches()
    {
        // Invalidate frequently changing caches
        static::invalidateCache('getFiltered');
        static::invalidateCache('getFilteredCount');
        static::invalidateCache('getLowStockItems');
        static::invalidateCache('getExpiringItems');
        static::invalidateCache('getExpiredItems');
        static::invalidateCache('getSummaryStats');
        static::invalidateCache('getCategorySummary');
        
        // Invalidate base model caches
        static::invalidateCache('all');
        static::invalidateCache('count');
    }

    /**
     * Bulk update all inventory statuses (for maintenance)
     * 
     * @return int Number of items updated
     */
    public static function updateAllStatuses()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $items = $wpdb->get_results("SELECT ID FROM {$table}");
        $updated = 0;
        
        foreach ($items as $item) {
            if (static::updateStatus($item->ID)) {
                $updated++;
            }
        }
        
        // Clear caches after bulk update
        if ($updated > 0) {
            static::invalidateInventoryCaches();
        }
        
        return $updated;
    }

    /**
     * ============================================================================
     * BACKWARD COMPATIBILITY ALIASES
     * ============================================================================
     */

    /**
     * @deprecated Use getLowStockItems() instead
     */
    public static function getCritical()
    {
        return static::getLowStockItems();
    }

    /**
     * @deprecated Use getExpiringItems() instead
     */
    public static function getExpiringSoon($days = 30)
    {
        return static::getExpiringItems($days);
    }

    /**
     * @deprecated Use getExpiredItems() instead
     */
    public static function getExpired()
    {
        return static::getExpiredItems();
    }

    /**
     * @deprecated Use getSummaryStats() instead
     */
    public static function getSummary()
    {
        return static::getSummaryStats();
    }

    /**
     * @deprecated Use find() instead
     */
    public static function findOne($id)
    {
        return static::find($id);
    }
}