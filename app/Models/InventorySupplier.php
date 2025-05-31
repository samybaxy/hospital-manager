<?php

namespace HospitalManager\Models;

class InventorySupplier extends BaseModel
{
    protected $tableName = 'hm_inventory_suppliers';
    protected $primaryKey = 'ID';

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'tax_id',
        'payment_terms',
        'delivery_time_days',
        'minimum_order_amount',
        'is_active',
        'notes',
        'created_at',
        'updated_at'
    ];

    public function __construct($attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        parent::__construct($attributes);
    }

    /**
     * Create a new supplier
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
     * Get all active suppliers
     */
    public static function getActive($limit = 100)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC LIMIT %d",
            $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Find supplier by ID
     */
    public static function findOne($id)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $id);
        $result = $wpdb->get_row($query, ARRAY_A);

        return $result ? new static($result) : null;
    }

    /**
     * Find supplier by ID (alias for API consistency)
     */
    public static function findById($id)
    {
        return self::findOne($id);
    }

    /**
     * Get all suppliers
     */
    public static function getAll($activeOnly = true)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_suppliers';

        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $query = "SELECT * FROM {$table} {$where} ORDER BY name ASC";

        return $wpdb->get_results($query);
    }

    /**
     * Update supplier by ID (static method)
     */
    public static function updateById($id, array $attributes)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_suppliers';

        $attributes['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $table,
            $attributes,
            ['ID' => $id],
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes),
            ['%d']
        );

        if ($result !== false) {
            return self::findById($id);
        }

        return false;
    }

    /**
     * Delete supplier by ID (static method)
     */
    public static function deleteById($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_suppliers';

        $result = $wpdb->delete(
            $table,
            ['ID' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Search suppliers by name or contact info
     */
    public static function search($term, $limit = 50)
    {
        global $wpdb;
        $table = (new static)->table;

        $searchTerm = '%' . $wpdb->esc_like($term) . '%';

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE is_active = 1 
             AND (name LIKE %s OR contact_person LIKE %s OR email LIKE %s)
             ORDER BY name ASC 
             LIMIT %d",
            $searchTerm, $searchTerm, $searchTerm, $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Update supplier
     */
    public function update(array $attributes)
    {
        global $wpdb;

        $attributes['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $attributes,
            ['ID' => $this->ID],
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes),
            ['%d']
        );

        if ($result !== false) {
            foreach ($attributes as $key => $value) {
                $this->$key = $value;
            }
        }

        return $result !== false;
    }

    /**
     * Deactivate supplier
     */
    public function deactivate()
    {
        return $this->update(['is_active' => 0]);
    }

    /**
     * Activate supplier
     */
    public function activate()
    {
        return $this->update(['is_active' => 1]);
    }

    /**
     * Get supplier statistics
     */
    public function getStats()
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(t.ID) as total_transactions,
                SUM(CASE WHEN t.transaction_type = 'stock_in' THEN 1 ELSE 0 END) as stock_in_count,
                SUM(CASE WHEN t.transaction_type = 'stock_in' THEN t.total_cost ELSE 0 END) as total_purchase_value,
                AVG(CASE WHEN t.transaction_type = 'stock_in' THEN t.total_cost ELSE NULL END) as avg_order_value,
                MAX(t.created_at) as last_order_date
             FROM {$wpdb->prefix}hm_inventory_transactions t
             WHERE t.supplier_id = %d",
            $this->ID
        ));
    }
}


class InventoryReorder extends BaseModel
{
    protected $tableName = 'hm_inventory_reorders';
    protected $primaryKey = 'ID';

    protected $fillable = [
        'inventory_id',
        'supplier_id',
        'suggested_quantity',
        'current_quantity',
        'reorder_level',
        'max_stock_level',
        'priority',
        'status',
        'estimated_cost',
        'order_reference',
        'expected_delivery_date',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'ordered_at',
        'created_at',
        'updated_at'
    ];

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_ORDERED = 'ordered';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';

    public function __construct($attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        parent::__construct($attributes);
    }

    /**
     * Create a new reorder suggestion
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = (new static)->table;

        // Set default values
        $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
        $attributes['updated_at'] = $attributes['updated_at'] ?? current_time('mysql');
        $attributes['status'] = $attributes['status'] ?? self::STATUS_PENDING;
        $attributes['priority'] = $attributes['priority'] ?? self::PRIORITY_MEDIUM;
        
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
     * Get pending reorders
     */
    public static function getPending($limit = 100)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT r.*, i.item_name, i.category, s.name as supplier_name
             FROM {$table} r
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON r.inventory_id = i.ID
             LEFT JOIN {$wpdb->prefix}hm_inventory_suppliers s ON r.supplier_id = s.ID
             WHERE r.status = %s
             ORDER BY 
                CASE r.priority 
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                r.created_at ASC
             LIMIT %d",
            self::STATUS_PENDING, $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Generate automatic reorder suggestions
     */
    public static function generateSuggestions()
    {
        global $wpdb;
        $suggestionsCreated = 0;

        // Get items that need reordering (at or below reorder level)
        $items = $wpdb->get_results(
            "SELECT i.*, s.ID as supplier_id, s.name as supplier_name,
                    AVG(t.cost_per_unit) as avg_cost
             FROM {$wpdb->prefix}hm_inventory i
             LEFT JOIN {$wpdb->prefix}hm_inventory_transactions t ON i.ID = t.inventory_id 
                AND t.transaction_type = 'stock_in' 
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             LEFT JOIN {$wpdb->prefix}hm_inventory_suppliers s ON t.supplier_id = s.ID AND s.is_active = 1
             WHERE i.quantity <= i.reorder_level
             AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->prefix}hm_inventory_reorders r
                WHERE r.inventory_id = i.ID AND r.status IN ('pending', 'approved', 'ordered')
             )
             GROUP BY i.ID
             ORDER BY i.quantity ASC"
        );

        foreach ($items as $item) {
            $suggestedQuantity = self::calculateSuggestedQuantity($item);
            $priority = self::calculatePriority($item);
            $estimatedCost = $item->avg_cost ? ($suggestedQuantity * $item->avg_cost) : null;

            $reorderData = [
                'inventory_id' => $item->ID,
                'supplier_id' => $item->supplier_id,
                'suggested_quantity' => $suggestedQuantity,
                'current_quantity' => $item->quantity,
                'reorder_level' => $item->reorder_level,
                'priority' => $priority,
                'estimated_cost' => $estimatedCost,
                'created_by' => 1, // System generated
                'notes' => 'Auto-generated reorder suggestion'
            ];

            try {
                self::create($reorderData);
                $suggestionsCreated++;
            } catch (\Exception $e) {
                error_log('Failed to create reorder suggestion for item ' . $item->ID . ': ' . $e->getMessage());
            }
        }

        return $suggestionsCreated;
    }

    /**
     * Calculate suggested reorder quantity
     */
    private static function calculateSuggestedQuantity($item)
    {
        // Basic algorithm: order enough to reach 2x reorder level, minimum reorder level
        $targetStock = max($item->reorder_level * 2, $item->reorder_level);
        return max($item->reorder_level, $targetStock - $item->quantity);
    }

    /**
     * Calculate reorder priority
     */
    private static function calculatePriority($item)
    {
        if ($item->quantity <= 0) {
            return self::PRIORITY_URGENT;
        } elseif ($item->quantity <= ($item->reorder_level * 0.5)) {
            return self::PRIORITY_HIGH;
        } elseif ($item->quantity <= $item->reorder_level) {
            return self::PRIORITY_MEDIUM;
        } else {
            return self::PRIORITY_LOW;
        }
    }

    /**
     * Approve reorder
     */
    public function approve($userId)
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $this->ID],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->status = self::STATUS_APPROVED;
            $this->approved_by = $userId;
            $this->approved_at = current_time('mysql');
        }

        return $result !== false;
    }

    /**
     * Mark as ordered
     */
    public function markOrdered($orderReference = null)
    {
        global $wpdb;

        $updateData = [
            'status' => self::STATUS_ORDERED,
            'ordered_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        if ($orderReference) {
            $updateData['order_reference'] = $orderReference;
        }

        $result = $wpdb->update(
            $this->table,
            $updateData,
            ['ID' => $this->ID],
            array_fill(0, count($updateData), '%s'),
            ['%d']
        );

        if ($result !== false) {
            $this->status = self::STATUS_ORDERED;
            $this->ordered_at = current_time('mysql');
            if ($orderReference) {
                $this->order_reference = $orderReference;
            }
        }

        return $result !== false;
    }

    /**
     * Mark as received
     */
    public function markReceived()
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_RECEIVED,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $this->ID],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->status = self::STATUS_RECEIVED;
        }

        return $result !== false;
    }

    /**
     * Cancel reorder
     */
    public function cancel()
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_CANCELLED,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $this->ID],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->status = self::STATUS_CANCELLED;
        }

        return $result !== false;
    }

    /**
     * Get reorder statistics
     */
    public static function getStats()
    {
        global $wpdb;
        $table = (new static)->table;

        return $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_reorders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN status = 'ordered' THEN 1 END) as ordered_count,
                COUNT(CASE WHEN status = 'received' THEN 1 END) as received_count,
                COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_count,
                SUM(estimated_cost) as total_estimated_cost
             FROM {$table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    /**
     * Relationship with inventory item
     */
    public function inventory()
    {
        return Inventory::findOne($this->inventory_id);
    }

    /**
     * Relationship with supplier
     */
    public function supplier()
    {
        return InventorySupplier::findOne($this->supplier_id);
    }
}
