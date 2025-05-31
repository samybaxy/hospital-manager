<?php

namespace HospitalManager\Services;

use HospitalManager\Models\Inventory;
use HospitalManager\Models\InventoryTransaction;
use HospitalManager\Models\InventoryAlert;
use HospitalManager\Models\InventorySupplier;
use HospitalManager\Models\InventoryReorder;
use HospitalManager\Models\AuditLog;

class InventoryService
{
    /**
     * Add stock to inventory with transaction tracking
     */
    public static function addStock($inventoryId, $quantity, $userId, $options = [])
    {
        try {
            // Record the transaction
            $transaction = InventoryTransaction::recordStockMovement(
                $inventoryId,
                InventoryTransaction::TYPE_STOCK_IN,
                $quantity,
                $userId,
                $options
            );

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'inventory_stock_in',
                'entity_type' => 'inventory',
                'entity_id' => $inventoryId,
                'changes' => json_encode([
                    'quantity_added' => $quantity,
                    'transaction_id' => $transaction->ID
                ]),
                'details' => json_encode([
                    'reference_number' => $options['reference_number'] ?? null,
                    'supplier_id' => $options['supplier_id'] ?? null,
                    'batch_number' => $options['batch_number'] ?? null,
                    'notes' => $options['notes'] ?? null
                ])
            ]);

            // Check and update alerts
            self::checkAlertsForItem($inventoryId);

            return $transaction;

        } catch (\Exception $e) {
            error_log('Inventory Service - Add Stock Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove stock from inventory with transaction tracking
     */
    public static function removeStock($inventoryId, $quantity, $userId, $options = [])
    {
        try {
            // Record the transaction (negative quantity for stock out)
            $transaction = InventoryTransaction::recordStockMovement(
                $inventoryId,
                InventoryTransaction::TYPE_STOCK_OUT,
                -$quantity,
                $userId,
                $options
            );

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'inventory_stock_out',
                'entity_type' => 'inventory',
                'entity_id' => $inventoryId,
                'changes' => json_encode([
                    'quantity_removed' => $quantity,
                    'transaction_id' => $transaction->ID
                ]),
                'details' => json_encode([
                    'reference_number' => $options['reference_number'] ?? null,
                    'location_to' => $options['location_to'] ?? null,
                    'notes' => $options['notes'] ?? null
                ])
            ]);

            // Check and update alerts
            self::checkAlertsForItem($inventoryId);

            return $transaction;

        } catch (\Exception $e) {
            error_log('Inventory Service - Remove Stock Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Adjust inventory quantity (can be positive or negative)
     */
    public static function adjustStock($inventoryId, $newQuantity, $userId, $notes = null)
    {
        try {
            global $wpdb;

            // Get current quantity
            $currentItem = $wpdb->get_row($wpdb->prepare(
                "SELECT quantity FROM {$wpdb->prefix}hm_inventory WHERE ID = %d",
                $inventoryId
            ));

            if (!$currentItem) {
                throw new \Exception('Inventory item not found');
            }

            $quantityChange = $newQuantity - $currentItem->quantity;

            // Record the transaction
            $transaction = InventoryTransaction::recordStockMovement(
                $inventoryId,
                InventoryTransaction::TYPE_ADJUSTMENT,
                $quantityChange,
                $userId,
                ['notes' => $notes]
            );

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'inventory_adjustment',
                'entity_type' => 'inventory',
                'entity_id' => $inventoryId,
                'changes' => json_encode([
                    'old_quantity' => $currentItem->quantity,
                    'new_quantity' => $newQuantity,
                    'adjustment' => $quantityChange,
                    'transaction_id' => $transaction->ID
                ]),
                'details' => json_encode([
                    'notes' => $notes,
                    'adjustment_type' => $quantityChange > 0 ? 'increase' : 'decrease'
                ])
            ]);

            // Check and update alerts
            self::checkAlertsForItem($inventoryId);

            return $transaction;

        } catch (\Exception $e) {
            error_log('Inventory Service - Adjust Stock Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Transfer stock between locations
     */
    public static function transferStock($inventoryId, $quantity, $fromLocation, $toLocation, $userId, $notes = null)
    {
        try {
            // Record the transaction
            $transaction = InventoryTransaction::recordStockMovement(
                $inventoryId,
                InventoryTransaction::TYPE_TRANSFER,
                0, // No quantity change, just location change
                $userId,
                [
                    'location_from' => $fromLocation,
                    'location_to' => $toLocation,
                    'notes' => $notes
                ]
            );

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'inventory_transfer',
                'entity_type' => 'inventory',
                'entity_id' => $inventoryId,
                'changes' => json_encode([
                    'quantity_transferred' => $quantity,
                    'from_location' => $fromLocation,
                    'to_location' => $toLocation,
                    'transaction_id' => $transaction->ID
                ]),
                'details' => json_encode([
                    'notes' => $notes
                ])
            ]);

            return $transaction;

        } catch (\Exception $e) {
            error_log('Inventory Service - Transfer Stock Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark items as expired and remove from active stock
     */
    public static function markExpired($inventoryId, $userId, $expiredQuantity = null, $notes = null)
    {
        try {
            global $wpdb;

            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hm_inventory WHERE ID = %d",
                $inventoryId
            ));

            if (!$item) {
                throw new \Exception('Inventory item not found');
            }

            // If no expired quantity specified, use total quantity
            $expiredQuantity = $expiredQuantity ?? $item->quantity;

            // Record the transaction
            $transaction = InventoryTransaction::recordStockMovement(
                $inventoryId,
                InventoryTransaction::TYPE_EXPIRED,
                -$expiredQuantity,
                $userId,
                [
                    'notes' => $notes ?? 'Items marked as expired'
                ]
            );

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'inventory_expired',
                'entity_type' => 'inventory',
                'entity_id' => $inventoryId,
                'changes' => json_encode([
                    'expired_quantity' => $expiredQuantity,
                    'expiry_date' => $item->expiry_date,
                    'transaction_id' => $transaction->ID
                ]),
                'details' => json_encode([
                    'notes' => $notes,
                    'item_name' => $item->item_name
                ])
            ]);

            // Create or update expired alert
            self::createExpiredAlert($inventoryId);

            return $transaction;

        } catch (\Exception $e) {
            error_log('Inventory Service - Mark Expired Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check and update alerts for a specific item
     */
    public static function checkAlertsForItem($inventoryId)
    {
        global $wpdb;

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_inventory WHERE ID = %d",
            $inventoryId
        ));

        if ($item) {
            // Deactivate resolved alerts first
            InventoryAlert::deactivateResolvedAlerts();
            
            // Check for new alerts
            InventoryAlert::checkItemAlerts($item);
        }
    }

    /**
     * Create an expired item alert
     */
    private static function createExpiredAlert($inventoryId)
    {
        global $wpdb;

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hm_inventory WHERE ID = %d",
            $inventoryId
        ));

        if ($item && $item->expiry_date) {
            // Check if alert already exists
            $existingAlert = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hm_inventory_alerts 
                 WHERE inventory_id = %d AND alert_type = 'expired' AND is_active = 1",
                $inventoryId
            ));

            if (!$existingAlert) {
                InventoryAlert::create([
                    'inventory_id' => $inventoryId,
                    'alert_type' => InventoryAlert::TYPE_EXPIRED,
                    'severity' => InventoryAlert::SEVERITY_CRITICAL,
                    'title' => 'Expired Item Alert',
                    'message' => "Item '{$item->item_name}' has expired and has been removed from active stock.",
                    'threshold_value' => null,
                    'current_value' => null
                ]);
            }
        }
    }

    /**
     * Run automated inventory checks and alerts
     */
    public static function runAutomatedChecks()
    {
        try {
            $results = [
                'alerts_created' => 0,
                'alerts_resolved' => 0,
                'reorders_created' => 0,
                'errors' => []
            ];

            // Deactivate resolved alerts
            InventoryAlert::deactivateResolvedAlerts();

            // Check and create new alerts
            $results['alerts_created'] = InventoryAlert::checkAndCreateAlerts();

            // Generate reorder suggestions
            $results['reorders_created'] = InventoryReorder::generateSuggestions();

            return $results;

        } catch (\Exception $e) {
            error_log('Inventory Service - Automated Checks Error: ' . $e->getMessage());
            return [
                'alerts_created' => 0,
                'alerts_resolved' => 0,
                'reorders_created' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Get comprehensive inventory dashboard data
     */
    public static function getDashboardData()
    {
        global $wpdb;

        try {
            $data = [];

            // Basic inventory stats
            $data['inventory_summary'] = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_items,
                    COUNT(CASE WHEN quantity <= reorder_level THEN 1 END) as low_stock_items,
                    COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_items,
                    COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() THEN 1 END) as expiring_soon,
                    COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 END) as expired_items,
                    SUM(quantity * COALESCE(cost, 0)) as total_value
                FROM {$wpdb->prefix}hm_inventory
            ", ARRAY_A);

            // Alert summary
            $data['alert_summary'] = InventoryAlert::getDashboardSummary();

            // Recent transactions
            $data['recent_transactions'] = InventoryTransaction::getRecent(10);

            // Transaction stats
            $data['transaction_stats'] = InventoryTransaction::getStats('7 days');

            // Reorder summary
            $data['reorder_summary'] = InventoryReorder::getStats();

            // Pending reorders
            $data['pending_reorders'] = InventoryReorder::getPending(10);

            // Active alerts by severity
            $data['critical_alerts'] = InventoryAlert::getBySeverity(InventoryAlert::SEVERITY_CRITICAL, true, 5);
            $data['high_alerts'] = InventoryAlert::getBySeverity(InventoryAlert::SEVERITY_HIGH, true, 5);

            return $data;

        } catch (\Exception $e) {
            error_log('Inventory Service - Dashboard Data Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get transaction history for an item
     */
    public static function getItemHistory($inventoryId, $limit = 50)
    {
        return InventoryTransaction::getByInventoryId($inventoryId, $limit);
    }

    /**
     * Get alerts for an item
     */
    public static function getItemAlerts($inventoryId, $activeOnly = true)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_alerts';

        $where = $activeOnly ? 'WHERE inventory_id = %d AND is_active = 1' : 'WHERE inventory_id = %d';

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC",
            $inventoryId
        );

        return $wpdb->get_results($query);
    }

    /**
     * Acknowledge an alert
     */
    public static function acknowledgeAlert($alertId, $userId)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_alerts';

        $alert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ID = %d",
            $alertId
        ), ARRAY_A);

        if ($alert) {
            $alertObj = new InventoryAlert($alert);
            $result = $alertObj->acknowledge($userId);

            if ($result) {
                // Create audit log
                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'inventory_alert_acknowledged',
                    'entity_type' => 'inventory_alert',
                    'entity_id' => $alertId,
                    'details' => json_encode([
                        'alert_type' => $alert['alert_type'],
                        'inventory_id' => $alert['inventory_id']
                    ])
                ]);
            }

            return $result;
        }

        return false;
    }

    /**
     * Resolve an alert
     */
    public static function resolveAlert($alertId, $userId, $deactivate = true)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_alerts';

        $alert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ID = %d",
            $alertId
        ), ARRAY_A);

        if ($alert) {
            $alertObj = new InventoryAlert($alert);
            $result = $alertObj->resolve($userId, $deactivate);

            if ($result) {
                // Create audit log
                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'inventory_alert_resolved',
                    'entity_type' => 'inventory_alert',
                    'entity_id' => $alertId,
                    'details' => json_encode([
                        'alert_type' => $alert['alert_type'],
                        'inventory_id' => $alert['inventory_id'],
                        'deactivated' => $deactivate
                    ])
                ]);
            }

            return $result;
        }

        return false;
    }

    /**
     * Get low stock items for reordering
     */
    public static function getLowStockItems($limit = 100)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, 
                    CASE 
                        WHEN i.quantity = 0 THEN 'urgent'
                        WHEN i.quantity <= (i.reorder_level * 0.5) THEN 'high'
                        WHEN i.quantity <= i.reorder_level THEN 'medium'
                        ELSE 'low'
                    END as priority
             FROM {$wpdb->prefix}hm_inventory i
             WHERE i.quantity <= i.reorder_level
             ORDER BY i.quantity ASC, i.reorder_level DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get expiring items
     */
    public static function getExpiringItems($days = 30, $limit = 100)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*,
                    DATEDIFF(i.expiry_date, CURDATE()) as days_to_expiry,
                    CASE 
                        WHEN i.expiry_date < CURDATE() THEN 'expired'
                        WHEN DATEDIFF(i.expiry_date, CURDATE()) <= 7 THEN 'critical'
                        WHEN DATEDIFF(i.expiry_date, CURDATE()) <= 14 THEN 'high'
                        ELSE 'medium'
                    END as expiry_priority
             FROM {$wpdb->prefix}hm_inventory i
             WHERE i.expiry_date IS NOT NULL
             AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
             ORDER BY i.expiry_date ASC
             LIMIT %d",
            $days, $limit
        ));
    }

    /**
     * Record an inventory transaction
     */
    public static function recordTransaction($data)
    {
        return InventoryTransaction::recordStockMovement(
            $data['item_id'],
            $data['type'],
            $data['quantity'],
            $data['user_id'],
            [
                'reference_number' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null
            ]
        );
    }

    /**
     * Generate automatic alerts for all inventory items
     */
    public static function generateAlerts()
    {
        $alerts = [];
        
        // Get all inventory items
        global $wpdb;
        $items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}hm_inventory WHERE status = 'active'"
        );

        foreach ($items as $item) {
            // Check for low stock
            if ($item->quantity <= $item->reorder_level) {
                $type = $item->quantity == 0 ? 'out_of_stock' : 'low_stock';
                $severity = $item->quantity == 0 ? 'critical' : 'high';
                
                $alert = InventoryAlert::create([
                    'inventory_id' => $item->ID,
                    'alert_type' => $type,
                    'severity' => $severity,
                    'message' => $type == 'out_of_stock' ? 
                        "Item '{$item->item_name}' is out of stock" :
                        "Item '{$item->item_name}' is below reorder level",
                    'data' => json_encode([
                        'current_quantity' => $item->quantity,
                        'reorder_level' => $item->reorder_level
                    ])
                ]);
                
                if ($alert) {
                    $alerts[] = $alert;
                }
            }

            // Check for expiring items
            if ($item->expiry_date) {
                $days_to_expiry = (strtotime($item->expiry_date) - time()) / (24 * 60 * 60);
                
                if ($days_to_expiry < 0) {
                    // Expired
                    $alert = InventoryAlert::create([
                        'inventory_id' => $item->ID,
                        'alert_type' => 'expired',
                        'severity' => 'critical',
                        'message' => "Item '{$item->item_name}' has expired",
                        'data' => json_encode([
                            'expiry_date' => $item->expiry_date,
                            'days_expired' => abs($days_to_expiry)
                        ])
                    ]);
                    
                    if ($alert) {
                        $alerts[] = $alert;
                    }
                } elseif ($days_to_expiry <= 30) {
                    // Expiring soon
                    $severity = $days_to_expiry <= 7 ? 'high' : 'medium';
                    $alert = InventoryAlert::create([
                        'inventory_id' => $item->ID,
                        'alert_type' => 'expiring',
                        'severity' => $severity,
                        'message' => "Item '{$item->item_name}' expires in " . round($days_to_expiry) . " days",
                        'data' => json_encode([
                            'expiry_date' => $item->expiry_date,
                            'days_to_expiry' => $days_to_expiry
                        ])
                    ]);
                    
                    if ($alert) {
                        $alerts[] = $alert;
                    }
                }
            }
        }

        return $alerts;
    }

    /**
     * Approve a reorder
     */
    public static function approveReorder($reorderId, $userId)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_reorders';

        $reorder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ID = %d",
            $reorderId
        ), ARRAY_A);

        if ($reorder) {
            $reorderObj = new InventoryReorder($reorder);
            return $reorderObj->approve($userId);
        }

        return false;
    }

    /**
     * Complete a reorder
     */
    public static function completeReorder($reorderId, $receivedQuantity, $notes, $userId)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_reorders';

        $reorder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ID = %d",
            $reorderId
        ), ARRAY_A);

        if ($reorder) {
            $reorderObj = new InventoryReorder($reorder);
            return $reorderObj->complete($receivedQuantity, $notes, $userId);
        }

        return false;
    }

    /**
     * Generate reorder suggestions
     */
    public static function generateReorderSuggestions()
    {
        global $wpdb;
        
        $items = $wpdb->get_results(
            "SELECT i.*, s.name as supplier_name, s.ID as supplier_id 
             FROM {$wpdb->prefix}hm_inventory i
             LEFT JOIN {$wpdb->prefix}hm_inventory_suppliers s ON s.ID = i.supplier_id
             WHERE i.quantity <= i.reorder_level 
             AND i.status = 'active'
             ORDER BY i.quantity ASC"
        );

        $suggestions = [];
        foreach ($items as $item) {
            $suggestions[] = [
                'item_id' => $item->ID,
                'item_name' => $item->item_name,
                'current_quantity' => $item->quantity,
                'reorder_level' => $item->reorder_level,
                'suggested_quantity' => max($item->reorder_level * 2, 50), // Suggest double the reorder level or minimum 50
                'supplier_id' => $item->supplier_id,
                'supplier_name' => $item->supplier_name,
                'priority' => $item->quantity == 0 ? 'urgent' : ($item->quantity <= $item->reorder_level / 2 ? 'high' : 'medium')
            ];
        }

        return $suggestions;
    }
}
