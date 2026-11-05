<?php

namespace HospitalManager\Models;

/**
 * Inventory reorder suggestions / purchase orders (hm_inventory_reorders).
 *
 * This class used to be declared twice — here (only getFiltered/getFilteredCount,
 * with an unprefixed table name) and inside InventorySupplier.php (everything else).
 * Whichever file loaded first won, so reorder endpoints worked or crashed depending on
 * load order, and loading both in one request was a fatal "Cannot declare class".
 */
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

        // Only real columns: unknown keys made the whole insert fail
        $attributes = array_intersect_key($attributes, array_flip((new static)->fillable));
        
        $result = $wpdb->insert(
            $table,
            $attributes,
            array_map(function($field) {
                return \HospitalManager\Models\BaseModel::sqlFormat($field);
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
