<?php

namespace HospitalManager\Models;

class InventoryTransaction extends BaseModel
{
    protected $tableName = 'hm_inventory_transactions';
    protected $primaryKey = 'ID';

    protected $fillable = [
        'inventory_id',
        'user_id',
        'transaction_type',
        'quantity_changed',
        'previous_quantity',
        'new_quantity',
        'reference_number',
        'notes',
        'location_from',
        'location_to',
        'supplier_id',
        'batch_number',
        'expiry_date',
        'cost_per_unit',
        'total_cost',
        'status',
        'created_at',
        'updated_at'
    ];

    // Transaction type constants
    const TYPE_STOCK_IN = 'stock_in';
    const TYPE_STOCK_OUT = 'stock_out';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_EXPIRED = 'expired';
    const TYPE_DAMAGED = 'damaged';
    const TYPE_RETURNED = 'returned';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function __construct($attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        parent::__construct($attributes);
    }

    /**
     * Create a new inventory transaction
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = (new static)->table;

        // Set default values
        $attributes['created_at'] = $attributes['created_at'] ?? current_time('mysql');
        $attributes['updated_at'] = $attributes['updated_at'] ?? current_time('mysql');
        
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
     * Get transactions for a specific inventory item
     */
    public static function getByInventoryId($inventoryId, $limit = 50)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT t.*, u.display_name as user_name, i.item_name
             FROM {$table} t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON t.inventory_id = i.ID
             WHERE t.inventory_id = %d
             ORDER BY t.created_at DESC
             LIMIT %d",
            $inventoryId, $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get recent transactions across all inventory
     */
    public static function getRecent($limit = 100)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT t.*, u.display_name as user_name, i.item_name
             FROM {$table} t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON t.inventory_id = i.ID
             ORDER BY t.created_at DESC
             LIMIT %d",
            $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get transactions by type
     */
    public static function getByType($type, $limit = 50)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT t.*, u.display_name as user_name, i.item_name
             FROM {$table} t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON t.inventory_id = i.ID
             WHERE t.transaction_type = %s
             ORDER BY t.created_at DESC
             LIMIT %d",
            $type, $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get transactions for date range
     */
    public static function getForDateRange($startDate, $endDate, $limit = 100)
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT t.*, u.display_name as user_name, i.item_name
             FROM {$table} t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}hm_inventory i ON t.inventory_id = i.ID
             WHERE DATE(t.created_at) BETWEEN %s AND %s
             ORDER BY t.created_at DESC
             LIMIT %d",
            $startDate, $endDate, $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Record a stock movement transaction
     */
    public static function recordStockMovement($inventoryId, $transactionType, $quantityChanged, $userId, $options = [])
    {
        global $wpdb;

        // Get current inventory item
        $inventory = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_inventory WHERE ID = %d",
            $inventoryId
        ));

        if (!$inventory) {
            throw new \Exception('Inventory item not found');
        }

        $previousQuantity = $inventory->quantity;
        $newQuantity = $previousQuantity + $quantityChanged;

        // Prevent negative stock unless it's an adjustment
        if ($newQuantity < 0 && $transactionType !== self::TYPE_ADJUSTMENT) {
            throw new \Exception('Insufficient stock for this operation');
        }

        $transactionData = [
            'inventory_id' => $inventoryId,
            'user_id' => $userId,
            'transaction_type' => $transactionType,
            'quantity_changed' => $quantityChanged,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reference_number' => $options['reference_number'] ?? null,
            'notes' => $options['notes'] ?? null,
            'location_from' => $options['location_from'] ?? null,
            'location_to' => $options['location_to'] ?? null,
            'supplier_id' => $options['supplier_id'] ?? null,
            'batch_number' => $options['batch_number'] ?? null,
            'expiry_date' => $options['expiry_date'] ?? null,
            'cost_per_unit' => $options['cost_per_unit'] ?? null,
            'total_cost' => $options['total_cost'] ?? null,
            'status' => $options['status'] ?? self::STATUS_COMPLETED
        ];

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Create the transaction record
            $transaction = self::create($transactionData);

            // Update the inventory quantity
            $result = $wpdb->update(
                $wpdb->prefix . 'hm_inventory',
                ['quantity' => $newQuantity, 'updated_at' => current_time('mysql')],
                ['ID' => $inventoryId],
                ['%d', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new \Exception('Failed to update inventory quantity');
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return $transaction;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Get transaction statistics
     */
    public static function getStats($period = '30 days')
    {
        global $wpdb;
        $table = (new static)->table;

        $query = $wpdb->prepare(
            "SELECT 
                transaction_type,
                COUNT(*) as count,
                SUM(ABS(quantity_changed)) as total_quantity,
                SUM(total_cost) as total_value
             FROM {$table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)
             GROUP BY transaction_type
             ORDER BY count DESC",
            $period
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get filtered transactions
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_transactions';

        $where_conditions = [];
        $params = [];

        if (!empty($filters['item_id'])) {
            $where_conditions[] = 't.inventory_id = %d';
            $params[] = $filters['item_id'];
        }

        if (!empty($filters['type'])) {
            $where_conditions[] = 't.transaction_type = %s';
            $params[] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(t.created_at) >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(t.created_at) <= %s';
            $params[] = $filters['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $query = "SELECT t.*, i.item_name, u.display_name as user_name
                  FROM {$table} t
                  LEFT JOIN {$wpdb->prefix}hm_inventory i ON t.inventory_id = i.ID
                  LEFT JOIN {$wpdb->prefix}users u ON t.user_id = u.ID
                  {$where_clause}
                  ORDER BY t.created_at DESC
                  LIMIT {$limit} OFFSET {$offset}";

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

    /**
     * Relationship with user
     */
    public function user()
    {
        return get_user_by('ID', $this->user_id);
    }
}
