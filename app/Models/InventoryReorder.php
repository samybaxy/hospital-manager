<?php

namespace HospitalManager\Models;

class InventoryReorder extends BaseModel
{
    protected $tableName = 'hm_inventory_reorders';
    protected $primaryKey = 'ID';

    protected $fillable = [
        'inventory_id',
        'supplier_id',
        'quantity_requested',
        'quantity_received',
        'priority',
        'status',
        'estimated_cost',
        'actual_cost',
        'requested_by',
        'approved_by',
        'completed_by',
        'requested_at',
        'approved_at',
        'ordered_at',
        'expected_delivery_date',
        'received_at',
        'notes',
        'po_number',
        'invoice_number',
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
     * Create a new reorder
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_reorders';

        // Set default values
        $attributes['status'] = $attributes['status'] ?? self::STATUS_PENDING;
        $attributes['priority'] = $attributes['priority'] ?? self::PRIORITY_MEDIUM;
        $attributes['requested_at'] = $attributes['requested_at'] ?? current_time('mysql');
        $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
        $attributes['updated_at'] = $attributes['updated_at'] ?? current_time('mysql');
        
        $result = $wpdb->insert(
            $table,
            $attributes
        );

        if ($result !== false) {
            $id = $wpdb->insert_id;
            return self::findById($id);
        }

        return false;
    }

    /**
     * Find reorder by ID
     */
    public static function findById($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_reorders';

        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $id);
        $result = $wpdb->get_row($query, ARRAY_A);

        return $result ? new static($result) : null;
    }

    /**
     * Get filtered reorders
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_reorders';

        $where_conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where_conditions[] = 'r.status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where_conditions[] = 'r.priority = %s';
            $params[] = $filters['priority'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $query = "SELECT r.*, i.item_name, s.name as supplier_name, 
                         u1.display_name as requested_by_name,
                         u2.display_name as approved_by_name
                  FROM {$table} r
                  LEFT JOIN {$wpdb->prefix}hm_inventory i ON r.inventory_id = i.ID
                  LEFT JOIN {$wpdb->prefix}hm_inventory_suppliers s ON r.supplier_id = s.ID
                  LEFT JOIN {$wpdb->prefix}users u1 ON r.requested_by = u1.ID
                  LEFT JOIN {$wpdb->prefix}users u2 ON r.approved_by = u2.ID
                  {$where_clause}
                  ORDER BY 
                    CASE r.priority 
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    r.created_at DESC";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get pending reorders
     */
    public static function getPending($limit = 50)
    {
        return self::getFiltered(['status' => self::STATUS_PENDING]);
    }

    /**
     * Get reorder statistics
     */
    public static function getStats()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_reorders';

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
     * Approve this reorder
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
            $this->updated_at = current_time('mysql');

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'reorder_approved',
                'entity_type' => 'inventory_reorder',
                'entity_id' => $this->ID,
                'details' => json_encode([
                    'inventory_id' => $this->inventory_id,
                    'supplier_id' => $this->supplier_id,
                    'quantity_requested' => $this->quantity_requested
                ])
            ]);
        }

        return $result !== false;
    }

    /**
     * Complete this reorder
     */
    public function complete($receivedQuantity, $notes, $userId)
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_RECEIVED,
                'quantity_received' => $receivedQuantity,
                'completed_by' => $userId,
                'received_at' => current_time('mysql'),
                'notes' => $notes,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $this->ID],
            ['%s', '%d', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->status = self::STATUS_RECEIVED;
            $this->quantity_received = $receivedQuantity;
            $this->completed_by = $userId;
            $this->received_at = current_time('mysql');
            $this->notes = $notes;
            $this->updated_at = current_time('mysql');

            // Add stock to inventory
            $inventory = Inventory::findOne($this->inventory_id);
            if ($inventory) {
                $inventory->addStock($receivedQuantity, [
                    'transaction_type' => 'stock_in',
                    'reference_number' => 'PO-' . $this->ID,
                    'supplier_id' => $this->supplier_id,
                    'notes' => "Reorder completed: {$notes}"
                ]);
            }

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'reorder_completed',
                'entity_type' => 'inventory_reorder',
                'entity_id' => $this->ID,
                'details' => json_encode([
                    'inventory_id' => $this->inventory_id,
                    'supplier_id' => $this->supplier_id,
                    'quantity_requested' => $this->quantity_requested,
                    'quantity_received' => $receivedQuantity
                ])
            ]);
        }

        return $result !== false;
    }

    /**
     * Cancel this reorder
     */
    public function cancel($userId, $reason = '')
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_CANCELLED,
                'notes' => $reason,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $this->ID],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->status = self::STATUS_CANCELLED;
            $this->notes = $reason;
            $this->updated_at = current_time('mysql');

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'reorder_cancelled',
                'entity_type' => 'inventory_reorder',
                'entity_id' => $this->ID,
                'details' => json_encode([
                    'inventory_id' => $this->inventory_id,
                    'supplier_id' => $this->supplier_id,
                    'reason' => $reason
                ])
            ]);
        }

        return $result !== false;
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
     * Relationship with requesting user
     */
    public function requester()
    {
        return get_user_by('ID', $this->requested_by);
    }

    /**
     * Relationship with approving user
     */
    public function approver()
    {
        return get_user_by('ID', $this->approved_by);
    }
}
