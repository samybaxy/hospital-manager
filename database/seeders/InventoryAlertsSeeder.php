<?php

namespace HospitalManager\Database\Seeders;

use HospitalManager\Models\Inventory;
use HospitalManager\Models\InventoryAlert;

class InventoryAlertsSeeder extends Seeder
{
    // Alert types with their associated titles and messages
    private $alertTypes = [
        'low_stock' => [
            'title' => 'Low Stock Alert',
            'message' => 'This item is below the recommended reorder level. Please consider reordering soon.',
            'severity' => ['medium', 'high']
        ],
        'out_of_stock' => [
            'title' => 'Out of Stock Alert',
            'message' => 'This item is completely out of stock. Immediate reordering is required.',
            'severity' => ['high', 'critical']
        ],
        'expired' => [
            'title' => 'Expired Item Alert',
            'message' => 'This item has expired and should be removed from inventory.',
            'severity' => ['high', 'critical']
        ],
        'expiring_soon' => [
            'title' => 'Item Expiring Soon',
            'message' => 'This item will expire within the next 30 days. Please plan to use or replace it soon.',
            'severity' => ['medium', 'high']
        ],
        'critical_level' => [
            'title' => 'Critical Stock Level',
            'message' => 'Stock level is critically low and may disrupt operations if not replenished immediately.',
            'severity' => ['critical']
        ],
        'reorder_point' => [
            'title' => 'Reorder Point Reached',
            'message' => 'This item has reached its reorder point. Please generate a purchase order.',
            'severity' => ['low', 'medium']
        ]
    ];

    public function run()
    {
        $this->log("Creating inventory alerts");
        
        // Get inventory items
        $items = $this->getItemsWithIssues();
        
        if (empty($items)) {
            $this->log("No inventory items with issues found. Please run InventorySeeder first.");
            return;
        }
        
        $created = 0;
        $users = $this->getAdminUsers();
        $userId = !empty($users) ? $users[array_rand($users)]['ID'] : 1;
        
        foreach ($items as $item) {
            // Determine which alerts to create based on the item's status
            $alertsToCreate = $this->getAlertsForItem($item);
            
            foreach ($alertsToCreate as $alertType) {
                $data = $this->generateAlertData($item, $alertType, $userId);
                
                // Insert alert into database
                global $wpdb;
                $table = $wpdb->prefix . 'hm_inventory_alerts';
                $result = $wpdb->insert($table, $data);
                $alert_id = $result ? $wpdb->insert_id : false;
                
                if ($alert_id) {
                    $created++;
                    if ($created % 10 == 0) {
                        $this->log("Created {$created} inventory alerts...");
                    }
                }
            }
        }
        
        $this->log("Created {$created} inventory alerts successfully");
    }
    
    private function getItemsWithIssues()
    {
        // Get items that have potential issues (low stock, expired, etc.)
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        $items = $wpdb->get_results("
            SELECT ID, item_name, category, quantity, reorder_level, expiry_date, status 
            FROM {$table}
        ", ARRAY_A);
        
        if (empty($items)) {
            return [];
        }
        
        // Filter to only include items with issues
        return array_filter($items, function($item) {
            // Check for low stock or out of stock
            if ($item['quantity'] <= $item['reorder_level']) {
                return true;
            }
            
            // Check for expired items
            if (!empty($item['expiry_date']) && strtotime($item['expiry_date']) < time()) {
                return true;
            }
            
            // Check for items expiring soon (next 30 days)
            if (!empty($item['expiry_date']) && 
                strtotime($item['expiry_date']) >= time() && 
                strtotime($item['expiry_date']) <= strtotime('+30 days')) {
                return true;
            }
            
            // Check if quantity is less than 20% of reorder level (critical)
            if ($item['quantity'] < ($item['reorder_level'] * 0.2)) {
                return true;
            }
            
            // Include a few random items for reorder point alerts (20% of remaining items)
            return $this->faker->boolean(20);
        });
    }
    
    private function getAlertsForItem($item)
    {
        $alerts = [];
        
        // Out of stock
        if ($item['quantity'] == 0) {
            $alerts[] = 'out_of_stock';
        }
        // Low stock but not zero
        elseif ($item['quantity'] <= $item['reorder_level'] && $item['quantity'] > 0) {
            $alerts[] = 'low_stock';
            
            // Very low stock (critical level)
            if ($item['quantity'] < ($item['reorder_level'] * 0.2)) {
                $alerts[] = 'critical_level';
            }
        }
        
        // Expired items
        if (!empty($item['expiry_date']) && strtotime($item['expiry_date']) < time()) {
            $alerts[] = 'expired';
        }
        // Items expiring soon
        elseif (!empty($item['expiry_date']) && 
                strtotime($item['expiry_date']) >= time() && 
                strtotime($item['expiry_date']) <= strtotime('+30 days')) {
            $alerts[] = 'expiring_soon';
        }
        
        // If no serious alerts but we still want to create one, add reorder_point
        if (empty($alerts) && $this->faker->boolean(80)) {
            $alerts[] = 'reorder_point';
        }
        
        return $alerts;
    }
    
    private function generateAlertData($item, $alertType, $userId)
    {
        $alertConfig = $this->alertTypes[$alertType];
        
        // Select a random severity based on the alert type
        $severity = $alertConfig['severity'][array_rand($alertConfig['severity'])];
        
        // Create title and message with specific details
        $title = $alertConfig['title'];
        $message = str_replace(
            ['[ITEM_NAME]', '[QUANTITY]', '[REORDER_LEVEL]', '[EXPIRY_DATE]'],
            [$item['item_name'], $item['quantity'], $item['reorder_level'], $item['expiry_date'] ?? 'N/A'],
            $alertConfig['message']
        );
        
        // Some alerts should be acknowledged or resolved
        $isAcknowledged = $this->faker->boolean(30); // 30% acknowledged
        $acknowledgedAt = $isAcknowledged ? date('Y-m-d H:i:s', strtotime('-' . $this->faker->numberBetween(1, 5) . ' days')) : null;
        
        $isResolved = $isAcknowledged && $this->faker->boolean(40); // 40% of acknowledged are resolved
        $resolvedAt = $isResolved ? date('Y-m-d H:i:s', strtotime('-' . $this->faker->numberBetween(1, 3) . ' days', strtotime($acknowledgedAt))) : null;
        
        // Status based on resolution
        $isActive = !$isResolved;
        
        // Next check date (for active alerts)
        $nextCheckAt = $isActive ? date('Y-m-d H:i:s', strtotime('+' . $this->faker->numberBetween(1, 7) . ' days')) : null;
        
        return [
            'inventory_id' => $item['ID'],
            'alert_type' => $alertType,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'threshold_value' => $alertType == 'reorder_point' ? $item['reorder_level'] : null,
            'current_value' => in_array($alertType, ['low_stock', 'out_of_stock', 'critical_level', 'reorder_point']) ? $item['quantity'] : null,
            'is_active' => $isActive ? 1 : 0,
            'acknowledged_at' => $acknowledgedAt,
            'acknowledged_by' => $isAcknowledged ? $userId : null,
            'resolved_at' => $resolvedAt,
            'resolved_by' => $isResolved ? $userId : null,
            'next_check_at' => $nextCheckAt,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . $this->faker->numberBetween(1, 10) . ' days'))
        ];
    }
    
    private function getAdminUsers()
    {
        global $wpdb;
        
        $users = $wpdb->get_results("
            SELECT ID 
            FROM {$wpdb->users} 
            WHERE ID IN (
                SELECT user_id 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = '{$wpdb->prefix}capabilities' 
                AND meta_value LIKE '%administrator%'
            )
            LIMIT 5
        ", ARRAY_A);
        
        return $users;
    }
}