<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Inventory;
use HospitalManager\Models\InventoryTransaction;
use HospitalManager\Models\InventoryAlert;
use HospitalManager\Models\InventoryReorder;

class InventoryService
{
    /**
     * Record an inventory transaction
     *
     * @param array $data
     * @return object|false
     */
    public static function recordTransaction($data)
    {
        global $wpdb;
        
        // Get the current inventory item
        $item = Inventory::findOne($data['item_id']);
        if (!$item) {
            throw new \Exception('Inventory item not found');
        }
        
        $previous_quantity = $item->quantity;
        $quantity_change = intval($data['quantity']);
        
        // Calculate new quantity based on transaction type
        switch ($data['type']) {
            case 'stock_in':
            case 'returned':
                $new_quantity = $previous_quantity + $quantity_change;
                break;
            case 'stock_out':
            case 'expired':
            case 'damaged':
                $new_quantity = $previous_quantity - $quantity_change;
                break;
            case 'adjustment':
                $new_quantity = $quantity_change; // Direct set
                $quantity_change = $new_quantity - $previous_quantity;
                break;
            default:
                $new_quantity = $previous_quantity;
                break;
        }
        
        // Ensure quantity doesn't go negative
        if ($new_quantity < 0) {
            $new_quantity = 0;
        }
        
        // Create transaction record
        $transaction_data = [
            'inventory_id' => $data['item_id'],
            'user_id' => $data['user_id'],
            'transaction_type' => $data['type'],
            'quantity_changed' => $quantity_change,
            'previous_quantity' => $previous_quantity,
            'new_quantity' => $new_quantity,
            'reference_number' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'completed',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'hm_inventory_transactions',
            $transaction_data
        );
        
        if ($result === false) {
            throw new \Exception('Failed to create transaction');
        }
        
        // Update inventory quantity
        Inventory::updateItem($data['item_id'], ['quantity' => $new_quantity]);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_inventory_transactions WHERE ID = %d",
            $wpdb->insert_id
        ));
    }
    
    /**
     * Acknowledge an alert
     *
     * @param int $alert_id
     * @param int $user_id
     * @return bool
     */
    public static function acknowledgeAlert($alert_id, $user_id)
    {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'hm_inventory_alerts',
            [
                'acknowledged_at' => current_time('mysql'),
                'acknowledged_by' => $user_id,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $alert_id],
            ['%s', '%d', '%s'],
            ['%d']
        ) !== false;
    }
    
    /**
     * Resolve an alert
     *
     * @param int $alert_id
     * @param int $user_id
     * @return bool
     */
    public static function resolveAlert($alert_id, $user_id)
    {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'hm_inventory_alerts',
            [
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $user_id,
                'is_active' => 0,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $alert_id],
            ['%s', '%d', '%d', '%s'],
            ['%d']
        ) !== false;
    }
    
    /**
     * Generate automatic alerts
     *
     * @return array
     */
    public static function generateAlerts()
    {
        global $wpdb;
        
        $alerts_created = [];
        
        // Get items that need alerts
        $items = Inventory::getFiltered([]);
        
        foreach ($items as $item) {
            // Low stock alert
            if ($item->quantity <= $item->reorder_level && $item->quantity > 0) {
                $alert_data = [
                    'inventory_id' => $item->ID,
                    'alert_type' => 'low_stock',
                    'severity' => 'medium',
                    'title' => 'Low Stock Alert',
                    'message' => "Item '{$item->item_name}' is running low. Current quantity: {$item->quantity}, Reorder level: {$item->reorder_level}",
                    'threshold_value' => $item->reorder_level,
                    'current_value' => $item->quantity,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];
                
                $wpdb->insert($wpdb->prefix . 'hm_inventory_alerts', $alert_data);
                $alerts_created[] = $alert_data;
            }
            
            // Out of stock alert
            if ($item->quantity <= 0) {
                $alert_data = [
                    'inventory_id' => $item->ID,
                    'alert_type' => 'out_of_stock',
                    'severity' => 'high',
                    'title' => 'Out of Stock Alert',
                    'message' => "Item '{$item->item_name}' is out of stock.",
                    'threshold_value' => 0,
                    'current_value' => $item->quantity,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];
                
                $wpdb->insert($wpdb->prefix . 'hm_inventory_alerts', $alert_data);
                $alerts_created[] = $alert_data;
            }
        }
        
        return $alerts_created;
    }
    
    /**
     * Approve a reorder
     *
     * @param int $reorder_id
     * @param int $user_id
     * @return bool
     */
    public static function approveReorder($reorder_id, $user_id)
    {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'hm_inventory_reorders',
            [
                'status' => 'approved',
                'approved_by' => $user_id,
                'approved_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $reorder_id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        ) !== false;
    }
    
    /**
     * Complete a reorder
     *
     * @param int $reorder_id
     * @param int $received_quantity
     * @param string $notes
     * @param int $user_id
     * @return bool
     */
    public static function completeReorder($reorder_id, $received_quantity, $notes, $user_id)
    {
        global $wpdb;
        
        // Get reorder details
        $reorder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_inventory_reorders WHERE ID = %d",
            $reorder_id
        ));
        
        if (!$reorder) {
            return false;
        }
        
        // Update reorder status
        $wpdb->update(
            $wpdb->prefix . 'hm_inventory_reorders',
            [
                'status' => 'received',
                'notes' => $notes,
                'updated_at' => current_time('mysql')
            ],
            ['ID' => $reorder_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        // Create stock in transaction
        $transaction_data = [
            'item_id' => $reorder->inventory_id,
            'type' => 'stock_in',
            'quantity' => $received_quantity,
            'reference' => "Reorder #{$reorder_id}",
            'notes' => "Received from reorder: {$notes}",
            'user_id' => $user_id
        ];
        
        return self::recordTransaction($transaction_data) !== false;
    }
    
    /**
     * Generate reorder suggestions
     *
     * @return array
     */
    public static function generateReorderSuggestions()
    {
        global $wpdb;
        
        $suggestions = [];
        
        // Get items below reorder level
        $items = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}hm_inventory 
            WHERE quantity <= reorder_level 
            ORDER BY quantity ASC
        ");
        
        foreach ($items as $item) {
            $suggested_quantity = max($item->reorder_level * 2, 10); // Suggest double the reorder level or minimum 10
            
            $suggestions[] = [
                'item_id' => $item->ID,
                'item_name' => $item->item_name,
                'current_quantity' => $item->quantity,
                'reorder_level' => $item->reorder_level,
                'suggested_quantity' => $suggested_quantity,
                'priority' => $item->quantity <= 0 ? 'urgent' : ($item->quantity <= $item->reorder_level * 0.5 ? 'high' : 'medium')
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Generate reorder suggestions with pagination
     *
     * @param int $page Current page number
     * @param int $per_page Items per page
     * @return array
     */
    public static function generateReorderSuggestionsPaginated($page = 1, $per_page = 10)
    {
        global $wpdb;
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get total count of items below reorder level
        $total_items = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}hm_inventory 
            WHERE quantity <= reorder_level
        ");
        
        // Get paginated items below reorder level
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}hm_inventory 
            WHERE quantity <= reorder_level 
            ORDER BY quantity ASC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        $suggestions = [];
        foreach ($items as $item) {
            $suggested_quantity = max($item->reorder_level * 2, 10); // Suggest double the reorder level or minimum 10
            
            $suggestions[] = [
                'item_id' => $item->ID,
                'item_name' => $item->item_name,
                'category' => $item->category,
                'quantity' => $item->quantity,
                'current_quantity' => $item->quantity,
                'reorder_level' => $item->reorder_level,
                'max_stock_level' => $item->max_stock_level,
                'suggested_quantity' => $suggested_quantity,
                'priority' => $item->quantity <= 0 ? 'urgent' : ($item->quantity <= $item->reorder_level * 0.5 ? 'high' : 'medium')
            ];
        }
        
        // Calculate pagination info
        $total_pages = ceil($total_items / $per_page);
        
        return [
            'data' => $suggestions,
            'pagination' => [
                'current_page' => intval($page),
                'per_page' => intval($per_page),
                'total_items' => intval($total_items),
                'total_pages' => intval($total_pages),
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ];
    }

    /**
     * Get dashboard data
     *
     * @return array
     */
    public static function getDashboardData()
    {
        $summary = Inventory::getSummary();
        $critical_items = Inventory::getCritical();
        $expiring_items = Inventory::getExpiringSoon(30);
        
        return [
            'summary' => $summary,
            'critical_items' => array_slice($critical_items, 0, 5), // Top 5 critical items
            'expiring_items' => array_slice($expiring_items, 0, 5), // Top 5 expiring items
            'alerts_count' => self::getActiveAlertsCount(),
            'reorders_pending' => self::getPendingReordersCount()
        ];
    }
    
    /**
     * Get active alerts count
     *
     * @return int
     */
    private static function getActiveAlertsCount()
    {
        global $wpdb;
        
        return (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}hm_inventory_alerts 
            WHERE is_active = 1
        ");
    }
    
    /**
     * Get pending reorders count
     *
     * @return int
     */
    private static function getPendingReordersCount()
    {
        global $wpdb;
        
        return (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}hm_inventory_reorders 
            WHERE status IN ('pending', 'approved')
        ");
    }
}
