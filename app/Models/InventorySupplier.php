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
     *
     * @return array
     */
    public static function getAll()
    {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}hm_inventory_suppliers WHERE is_active = 1 ORDER BY name ASC";
        return $wpdb->get_results($query) ?: [];
    }
    
    /**
     * Get filtered suppliers with pagination
     *
     * @param array $filters
     * @return array
     */
    public static function getFiltered($filters = [])
    {
        global $wpdb;
        
        $where_conditions = ['is_active = 1'];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where_conditions[] = '(name LIKE %s OR contact_person LIKE %s OR email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // There is no status column; ?status=active|inactive maps to is_active
        // (filtering on "status" made the SQL fail and the list come back empty).
        if (!empty($filters['status']) && in_array($filters['status'], ['inactive', 'all'], true)) {
            $where_conditions = array_values(array_diff($where_conditions, ['is_active = 1']));
            if ($filters['status'] === 'inactive') {
                $where_conditions[] = 'is_active = 0';
            }
            if (!$where_conditions) {
                $where_conditions[] = '1=1';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 10;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $query = "SELECT * FROM {$wpdb->prefix}hm_inventory_suppliers WHERE {$where_clause} ORDER BY name ASC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query) ?: [];
    }

    /**
     * Get count of filtered suppliers for pagination
     *
     * @param array $filters
     * @return int
     */
    public static function getFilteredCount($filters = [])
    {
        global $wpdb;
        
        $where_conditions = ['is_active = 1'];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where_conditions[] = '(name LIKE %s OR contact_person LIKE %s OR email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // There is no status column; ?status=active|inactive maps to is_active
        // (filtering on "status" made the SQL fail and the list come back empty).
        if (!empty($filters['status']) && in_array($filters['status'], ['inactive', 'all'], true)) {
            $where_conditions = array_values(array_diff($where_conditions, ['is_active = 1']));
            if ($filters['status'] === 'inactive') {
                $where_conditions[] = 'is_active = 0';
            }
            if (!$where_conditions) {
                $where_conditions[] = '1=1';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}hm_inventory_suppliers WHERE {$where_clause}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return intval($wpdb->get_var($query));
    }
    
    /**
     * Update supplier by ID
     *
     * @param int $id
     * @param array $data
     * @return object|false
     */
    public static function updateById($id, $data)
    {
        global $wpdb;
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hm_inventory_suppliers',
            $data,
            ['ID' => $id],
            null,
            ['%d']
        );
        
        return $result !== false ? self::findById($id) : false;
    }
    
    /**
     * Delete supplier by ID
     *
     * @param int $id
     * @return bool
     */
    public static function deleteById($id)
    {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'hm_inventory_suppliers',
            ['ID' => $id],
            ['%d']
        ) !== false;
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
                return \HospitalManager\Models\BaseModel::sqlFormat($field);
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
