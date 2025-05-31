<?php

namespace HospitalManager\Models;

class InventoryAlert extends BaseModel
{
    protected $tableName = 'hm_inventory_alerts';
    protected $primaryKey = 'ID';

    protected $fillable = [
        'inventory_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'threshold_value',
        'current_value',
        'is_active',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'next_check_at',
        'created_at',
        'updated_at'
    ];

    // Alert type constants
    const TYPE_LOW_STOCK = 'low_stock';
    const TYPE_OUT_OF_STOCK = 'out_of_stock';
    const TYPE_EXPIRED = 'expired';
    const TYPE_EXPIRING_SOON = 'expiring_soon';
    const TYPE_CRITICAL_LEVEL = 'critical_level';
    const TYPE_REORDER_POINT = 'reorder_point';

    // Severity constants
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    public function __construct($attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        parent::__construct($attributes);
    }

    /**
     * Create a new inventory alert
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = (new static)->table;

        // Set default values
        $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
        $attributes['updated_at'] = $attributes['updated_at'] ?? current_time('mysql');
        $attributes['is_active'] = $attributes['is_active'] ?? 1;
        
        $result = $wpdb->insert(
            $table,
            $attributes,
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes)
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }

        $attributes['ID'] = $wpdb->insert_id;
        return new static($attributes);
    }

    /**
     * Get active alerts
     */
    public static function getActive($limit = 100)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT a.*, i.item_name, i.category, i.location
             FROM {$table} a
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
             WHERE a.is_active = 1
             ORDER BY 
                CASE a.severity 
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                a.created_at DESC
             LIMIT %d",
            $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get alerts by severity
     */
    public static function getBySeverity($severity, $activeOnly = true, $limit = 50)
    {
        global $wpdb;
        $table = (new static)->table;

        $where = $activeOnly ? 'WHERE a.is_active = 1 AND a.severity = %s' : 'WHERE a.severity = %s';

        $query = $wpdb->prepare(
            "SELECT a.*, i.item_name, i.category, i.location
             FROM {$table} a
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
             {$where}
             ORDER BY a.created_at DESC
             LIMIT %d",
            $severity, $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get alerts by type
     */
    public static function getByType($type, $activeOnly = true, $limit = 50)
    {
        global $wpdb;
        $table = (new static)->table;

        $where = $activeOnly ? 'WHERE a.is_active = 1 AND a.alert_type = %s' : 'WHERE a.alert_type = %s';

        $query = $wpdb->prepare(
            "SELECT a.*, i.item_name, i.category, i.location
             FROM {$table} a
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
             {$where}
             ORDER BY a.created_at DESC
             LIMIT %d",
            $type, $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get alert counts by type and severity
     */
    public static function getAlertCounts()
    {
        global $wpdb;
        $table = (new static)->table;

        $query = "SELECT 
                    alert_type,
                    severity,
                    COUNT(*) as count
                  FROM {$table}
                  WHERE is_active = 1
                  GROUP BY alert_type, severity
                  ORDER BY 
                    CASE severity 
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END";

        return $wpdb->get_results($query);
    }

    /**
     * Check and create alerts for inventory items
     */
    public static function checkAndCreateAlerts()
    {
        global $wpdb;
        $alertsCreated = 0;

        // Get all inventory items
        $inventoryItems = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}hm_inventory ORDER BY ID"
        );

        foreach ($inventoryItems as $item) {
            $alertsCreated += self::checkItemAlerts($item);
        }

        return $alertsCreated;
    }

    /**
     * Check alerts for a specific inventory item
     */
    public static function checkItemAlerts($item)
    {
        $alertsCreated = 0;

        // Check for low stock alert
        if ($item->quantity <= $item->reorder_level && $item->quantity > 0) {
            if (!self::alertExists($item->ID, self::TYPE_LOW_STOCK)) {
                self::createLowStockAlert($item);
                $alertsCreated++;
            }
        }

        // Check for out of stock alert
        if ($item->quantity <= 0) {
            if (!self::alertExists($item->ID, self::TYPE_OUT_OF_STOCK)) {
                self::createOutOfStockAlert($item);
                $alertsCreated++;
            }
        }

        // Check for expiry alerts
        if ($item->expiry_date) {
            $expiryDate = new \DateTime($item->expiry_date);
            $today = new \DateTime();
            $daysToExpiry = $today->diff($expiryDate)->days;

            // Expired items
            if ($expiryDate < $today) {
                if (!self::alertExists($item->ID, self::TYPE_EXPIRED)) {
                    self::createExpiredAlert($item);
                    $alertsCreated++;
                }
            }
            // Expiring soon (within 30 days)
            elseif ($daysToExpiry <= 30) {
                if (!self::alertExists($item->ID, self::TYPE_EXPIRING_SOON)) {
                    self::createExpiringSoonAlert($item, $daysToExpiry);
                    $alertsCreated++;
                }
            }
        }

        // Check for critical level (50% below reorder level)
        $criticalLevel = max(1, $item->reorder_level * 0.5);
        if ($item->quantity <= $criticalLevel && $item->quantity > 0) {
            if (!self::alertExists($item->ID, self::TYPE_CRITICAL_LEVEL)) {
                self::createCriticalLevelAlert($item);
                $alertsCreated++;
            }
        }

        return $alertsCreated;
    }

    /**
     * Check if an alert already exists for an item
     */
    private static function alertExists($inventoryId, $alertType)
    {
        global $wpdb;
        $table = (new static)->table;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE inventory_id = %d AND alert_type = %s AND is_active = 1",
            $inventoryId, $alertType
        ));

        return $count > 0;
    }

    /**
     * Create low stock alert
     */
    private static function createLowStockAlert($item)
    {
        return self::create([
            'inventory_id' => $item->ID,
            'alert_type' => self::TYPE_LOW_STOCK,
            'severity' => self::SEVERITY_MEDIUM,
            'title' => 'Low Stock Alert',
            'message' => "Item '{$item->item_name}' is running low. Current stock: {$item->quantity} {$item->unit}. Reorder level: {$item->reorder_level}",
            'threshold_value' => $item->reorder_level,
            'current_value' => $item->quantity
        ]);
    }

    /**
     * Create out of stock alert
     */
    private static function createOutOfStockAlert($item)
    {
        return self::create([
            'inventory_id' => $item->ID,
            'alert_type' => self::TYPE_OUT_OF_STOCK,
            'severity' => self::SEVERITY_HIGH,
            'title' => 'Out of Stock Alert',
            'message' => "Item '{$item->item_name}' is out of stock. Immediate reorder required.",
            'threshold_value' => 0,
            'current_value' => $item->quantity
        ]);
    }

    /**
     * Create expired alert
     */
    private static function createExpiredAlert($item)
    {
        return self::create([
            'inventory_id' => $item->ID,
            'alert_type' => self::TYPE_EXPIRED,
            'severity' => self::SEVERITY_CRITICAL,
            'title' => 'Expired Item Alert',
            'message' => "Item '{$item->item_name}' has expired on {$item->expiry_date}. Remove from inventory immediately.",
            'threshold_value' => null,
            'current_value' => null
        ]);
    }

    /**
     * Create expiring soon alert
     */
    private static function createExpiringSoonAlert($item, $daysToExpiry)
    {
        $severity = $daysToExpiry <= 7 ? self::SEVERITY_HIGH : self::SEVERITY_MEDIUM;
        
        return self::create([
            'inventory_id' => $item->ID,
            'alert_type' => self::TYPE_EXPIRING_SOON,
            'severity' => $severity,
            'title' => 'Expiring Soon Alert',
            'message' => "Item '{$item->item_name}' will expire in {$daysToExpiry} days on {$item->expiry_date}.",
            'threshold_value' => 30,
            'current_value' => $daysToExpiry
        ]);
    }

    /**
     * Create critical level alert
     */
    private static function createCriticalLevelAlert($item)
    {
        return self::create([
            'inventory_id' => $item->ID,
            'alert_type' => self::TYPE_CRITICAL_LEVEL,
            'severity' => self::SEVERITY_HIGH,
            'title' => 'Critical Stock Level',
            'message' => "Item '{$item->item_name}' has reached critical stock level. Current stock: {$item->quantity} {$item->unit}",
            'threshold_value' => max(1, $item->reorder_level * 0.5),
            'current_value' => $item->quantity
        ]);
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledge($userId)
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'acknowledged_at' => current_time('mysql'),
                'acknowledged_by' => $userId,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $this->ID],
            ['%s', '%d', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->acknowledged_at = current_time('mysql');
            $this->acknowledged_by = $userId;
        }

        return $result !== false;
    }

    /**
     * Resolve an alert
     */
    public function resolve($userId, $deactivate = true)
    {
        global $wpdb;

        $updateData = [
            'resolved_at' => current_time('mysql'),
            'resolved_by' => $userId,
            'updated_at' => current_time('mysql')
        ];

        if ($deactivate) {
            $updateData['is_active'] = 0;
        }

        $result = $wpdb->update(
            $this->table,
            $updateData,
            ['ID' => $this->ID],
            array_fill(0, count($updateData), '%s'),
            ['%d']
        );

        if ($result !== false) {
            $this->resolved_at = current_time('mysql');
            $this->resolved_by = $userId;
            if ($deactivate) {
                $this->is_active = 0;
            }
        }

        return $result !== false;
    }

    /**
     * Deactivate alerts for resolved issues
     */
    public static function deactivateResolvedAlerts()
    {
        global $wpdb;
        $table = (new static)->table;

        // Deactivate low stock alerts for items that are now above reorder level
        $wpdb->query("
            UPDATE {$table} a
            JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
            SET a.is_active = 0, a.resolved_at = NOW()
            WHERE a.alert_type = 'low_stock' 
            AND a.is_active = 1 
            AND i.quantity > i.reorder_level
        ");

        // Deactivate out of stock alerts for items that now have stock
        $wpdb->query("
            UPDATE {$table} a
            JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
            SET a.is_active = 0, a.resolved_at = NOW()
            WHERE a.alert_type = 'out_of_stock' 
            AND a.is_active = 1 
            AND i.quantity > 0
        ");

        // Deactivate critical level alerts for items above critical threshold
        $wpdb->query("
            UPDATE {$table} a
            JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
            SET a.is_active = 0, a.resolved_at = NOW()
            WHERE a.alert_type = 'critical_level' 
            AND a.is_active = 1 
            AND i.quantity > (i.reorder_level * 0.5)
        ");
    }

    /**
     * Get dashboard summary
     */
    public static function getDashboardSummary()
    {
        global $wpdb;
        $table = (new static)->table;

        return $wpdb->get_row("
            SELECT 
                COUNT(*) as total_active,
                COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_count,
                COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_count,
                COUNT(CASE WHEN severity = 'medium' THEN 1 END) as medium_count,
                COUNT(CASE WHEN severity = 'low' THEN 1 END) as low_count,
                COUNT(CASE WHEN alert_type = 'out_of_stock' THEN 1 END) as out_of_stock_count,
                COUNT(CASE WHEN alert_type = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN alert_type = 'expiring_soon' THEN 1 END) as expiring_soon_count
            FROM {$table}
            WHERE is_active = 1
        ");
    }

    /**
     * Get filtered alerts
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_alerts';

        $where_conditions = ['a.is_active = 1']; // Always show active alerts by default
        $params = [];

        if (!empty($filters['type'])) {
            $where_conditions[] = 'a.alert_type = %s';
            $params[] = $filters['type'];
        }

        if (!empty($filters['severity'])) {
            $where_conditions[] = 'a.severity = %s';
            $params[] = $filters['severity'];
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where_conditions[] = 'a.is_active = 1';
            } elseif ($filters['status'] === 'resolved') {
                $where_conditions[] = 'a.is_active = 0';
                array_shift($where_conditions); // Remove the default active filter
            } elseif ($filters['status'] === 'acknowledged') {
                $where_conditions[] = 'a.acknowledged_at IS NOT NULL AND a.is_active = 1';
            }
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;

        // Order by severity (critical first) then by creation date
        $query = "SELECT a.*, i.item_name
                  FROM {$table} a
                  LEFT JOIN {$wpdb->prefix}hm_inventory i ON a.inventory_id = i.ID
                  {$where_clause}
                  ORDER BY 
                    CASE a.severity 
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    a.created_at DESC
                  LIMIT {$limit}";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Relationship with inventory item
     */
    public function inventory()
    {
        return Inventory::findOne($this->inventory_id);
    }
}
