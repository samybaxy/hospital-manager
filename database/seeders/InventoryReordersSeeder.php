<?php

namespace HospitalManager\Database\Seeders;

class InventoryReordersSeeder extends Seeder
{
    // Status distribution for reorders
    private $statusDistribution = [
        'pending' => 30,    // 30% pending
        'approved' => 25,   // 25% approved
        'ordered' => 20,    // 20% ordered
        'received' => 20,   // 20% received
        'cancelled' => 5    // 5% cancelled
    ];
    
    private $priorities = ['low', 'medium', 'high', 'urgent'];
    
    public function run()
    {
        $this->log("Creating inventory reorder requests");
        
        // Get low stock inventory items
        $lowStockItems = $this->getLowStockItems();
        
        if (empty($lowStockItems)) {
            $this->log("No low stock inventory items found. Please run InventorySeeder first.");
            return;
        }
        
        // Get active suppliers
        global $wpdb;
        $supplierTable = $wpdb->prefix . 'hm_inventory_suppliers';
        $suppliers = $wpdb->get_results("
            SELECT ID, name
            FROM {$supplierTable}
            WHERE is_active = 1
        ", ARRAY_A);
        
        if (empty($suppliers)) {
            $this->log("No active suppliers found. Using default supplier ID 1.");
            $suppliers = [['ID' => 1]];
        }
        
        // Get user IDs for creation and approval
        $users = $this->getUsers();
        if (empty($users)) {
            $users = [['ID' => 1]];
        }
        
        $created = 0;
        $totalReorders = min(count($lowStockItems) * 2, 200); // About 2 reorders per low stock item, max 200
        
        // Create date range - past 3 months
        $startDate = strtotime('-3 months');
        $endDate = time();
        
        // Use a subset of items to create reorders
        $selectedItems = $this->faker->randomElements($lowStockItems, $totalReorders);
        
        foreach ($selectedItems as $item) {
            // Create random timestamp in the past 3 months
            $createdAt = date('Y-m-d H:i:s', $this->faker->numberBetween($startDate, $endDate));
            
            // Generate reorder status - weighted distribution
            $status = $this->getRandomWeightedElement($this->statusDistribution);
            
            // Generate reorder data
            $data = $this->generateReorderData(
                $item,
                $suppliers[array_rand($suppliers)],
                $users[array_rand($users)]['ID'],
                $status,
                $createdAt,
                $users[array_rand($users)]['ID']
            );
            
            // Insert reorder into database
            global $wpdb;
            $reorderTable = $wpdb->prefix . 'hm_inventory_reorders';
            $result = $wpdb->insert($reorderTable, $data);
            $reorder_id = $result ? $wpdb->insert_id : false;
            
            if ($reorder_id) {
                $created++;
                if ($created % 20 == 0) {
                    $this->log("Created {$created} inventory reorders...");
                }
            }
        }
        
        $this->log("Created {$created} inventory reorders successfully");
    }
    
    private function getLowStockItems()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory';
        
        // Get items where quantity is at or below reorder_level
        $items = $wpdb->get_results("
            SELECT ID, item_name, category, quantity, reorder_level, cost 
            FROM {$table}
            WHERE quantity <= reorder_level
            ORDER BY (quantity / reorder_level) ASC
            LIMIT 100
        ", ARRAY_A);
        
        return $items;
    }
    
    private function generateReorderData($item, $supplier, $userId, $status, $createdAt, $approvedBy)
    {
        // Calculate suggested quantity - between 1.5x and 3x the difference between reorder level and current quantity
        $shortfall = max(0, $item['reorder_level'] - $item['quantity']);
        $suggestedQuantity = $shortfall > 0 ? 
            $shortfall + $this->faker->numberBetween(
                ceil($shortfall * 0.5), 
                ceil($shortfall * 2)
            ) : 
            $this->faker->numberBetween(5, 20);
            
        // Calculate max stock level - approximately 1.5-2x reorder level
        $maxStockLevel = ceil($item['reorder_level'] * $this->faker->randomFloat(2, 1.5, 2));
        
        // Determine priority based on how low the stock is
        $ratio = $item['quantity'] / max(1, $item['reorder_level']);
        $priority = $ratio <= 0.1 ? 'urgent' : 
                   ($ratio <= 0.3 ? 'high' : 
                   ($ratio <= 0.7 ? 'medium' : 'low'));
        
        // Select a random priority, with weighting toward the determined priority
        if ($this->faker->boolean(70)) {
            // 70% chance to use the calculated priority
            $selectedPriority = $priority;
        } else {
            // 30% chance to use a random priority
            $selectedPriority = $this->priorities[array_rand($this->priorities)];
        }
        
        // Generate estimated cost based on item cost
        $estimatedCost = $item['cost'] ? $item['cost'] * $suggestedQuantity : null;
        
        // Generate dates based on status and created date
        $approvedAt = null;
        $orderedAt = null;
        $expectedDeliveryDate = null;
        
        // For approved, ordered and received statuses, generate approved date
        if (in_array($status, ['approved', 'ordered', 'received'])) {
            // Approved 1-3 days after creation
            $approvedAt = date('Y-m-d H:i:s', strtotime('+' . $this->faker->numberBetween(1, 3) . ' days', strtotime($createdAt)));
            
            // For ordered and received, generate ordered date
            if (in_array($status, ['ordered', 'received'])) {
                // Ordered 1-2 days after approval
                $orderedAt = date('Y-m-d H:i:s', strtotime('+' . $this->faker->numberBetween(1, 2) . ' days', strtotime($approvedAt)));
                
                // Expected delivery 3-14 days after order
                $expectedDeliveryDate = date('Y-m-d', strtotime('+' . $this->faker->numberBetween(3, 14) . ' days', strtotime($orderedAt)));
            }
        }
        
        // Generate notes
        $notes = null;
        if ($this->faker->boolean(70)) { // 70% chance to have notes
            $noteTemplates = [
                'pending' => [
                    'Standard reorder based on inventory levels.',
                    'Requesting restock before items run out.',
                    'Regular replenishment needed.',
                    'Stock levels are getting low, please approve.'
                ],
                'approved' => [
                    'Reorder approved by management.',
                    'Approved based on current consumption rate.',
                    'Expedited approval due to critical need.',
                    'Standard reorder approved within budget.'
                ],
                'ordered' => [
                    'Order placed with supplier, awaiting confirmation.',
                    'Order #REF-' . $this->faker->randomNumber(5) . ' sent to supplier.',
                    'Bulk order placed, delivery expected soon.',
                    'Partial order placed due to supplier limitations.'
                ],
                'received' => [
                    'All items received in good condition.',
                    'Items received and added to inventory.',
                    'Order fulfilled as requested.',
                    'Items received with proper documentation.'
                ],
                'cancelled' => [
                    'Order cancelled due to alternative supplier selection.',
                    'No longer needed - inventory levels adjusted.',
                    'Cancelled due to budget constraints.',
                    'Duplicate order identified and cancelled.'
                ]
            ];
            
            $notes = $noteTemplates[$status][array_rand($noteTemplates[$status])];
        }
        
        // Generate order reference for ordered/received statuses
        $orderReference = in_array($status, ['ordered', 'received']) ? 
            'PO-' . strtoupper($this->faker->bothify('##???')) : 
            null;
        
        return [
            'inventory_id' => $item['ID'],
            'supplier_id' => $supplier['ID'],
            'suggested_quantity' => $suggestedQuantity,
            'current_quantity' => $item['quantity'],
            'reorder_level' => $item['reorder_level'],
            'max_stock_level' => $maxStockLevel,
            'priority' => $selectedPriority,
            'status' => $status,
            'estimated_cost' => $estimatedCost,
            'order_reference' => $orderReference,
            'expected_delivery_date' => $expectedDeliveryDate,
            'notes' => $notes,
            'created_by' => $userId,
            'approved_by' => in_array($status, ['approved', 'ordered', 'received']) ? $approvedBy : null,
            'approved_at' => $approvedAt,
            'ordered_at' => $orderedAt,
            'created_at' => $createdAt
        ];
    }
    
    private function getRandomWeightedElement(array $weightedValues)
    {
        $rand = mt_rand(1, array_sum($weightedValues));
        
        foreach ($weightedValues as $key => $value) {
            $rand -= $value;
            if ($rand <= 0) {
                return $key;
            }
        }
        
        return array_key_first($weightedValues);
    }
    
    private function getUsers()
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
            LIMIT 10
        ", ARRAY_A);
        
        return $users;
    }
}