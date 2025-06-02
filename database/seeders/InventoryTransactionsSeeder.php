<?php

namespace HospitalManager\Database\Seeders;

class InventoryTransactionsSeeder extends Seeder
{
    // Transaction type distribution probabilities
    private $transactionTypes = [
        'stock_in' => 40,    // 40% probability
        'stock_out' => 35,   // 35% probability
        'adjustment' => 10,  // 10% probability
        'transfer' => 8,     // 8% probability
        'expired' => 3,      // 3% probability
        'damaged' => 2,      // 2% probability
        'returned' => 2      // 2% probability
    ];

    public function run()
    {
        $this->log("Creating inventory transactions");

        // Get all inventory items and suppliers
        global $wpdb;
        $inventoryTable = $wpdb->prefix . 'hm_inventory';
        $supplierTable = $wpdb->prefix . 'hm_inventory_suppliers';
        
        $inventoryItems = $wpdb->get_results("
            SELECT ID, item_name, quantity, category, cost, location 
            FROM {$inventoryTable}
        ", ARRAY_A);
        
        $suppliers = $wpdb->get_results("
            SELECT ID, name 
            FROM {$supplierTable}
        ", ARRAY_A);
        
        $users = $this->getUsers();
        
        if (empty($inventoryItems)) {
            $this->log("No inventory items found. Please run InventorySeeder first.");
            return;
        }
        
        if (empty($suppliers)) {
            $this->log("No suppliers found. Please run InventorySuppliersSeeder first.");
            return;
        }
        
        if (empty($users)) {
            $this->log("No users found. Creating transactions with admin user ID 1.");
            $users = [['ID' => 1]];
        }
        
        $created = 0;
        $totalTransactions = min(count($inventoryItems) * 5, 500); // Aim for about 5 transactions per item, max 500
        
        // Create transactions spread over the past 6 months
        $startDate = strtotime('-6 months');
        $endDate = time();
        
        for ($i = 0; $i < $totalTransactions; $i++) {
            // Select a random item
            $item = $inventoryItems[array_rand($inventoryItems)];
            
            // Generate transaction timestamp
            $transactionTime = $this->faker->numberBetween($startDate, $endDate);
            $transactionDate = date('Y-m-d H:i:s', $transactionTime);
            
            // Determine transaction type based on weighted probabilities
            $transactionType = $this->getRandomWeightedElement($this->transactionTypes);
            
            // Generate transaction data
            $data = $this->generateTransactionData(
                $item, 
                $transactionType, 
                $suppliers[array_rand($suppliers)],
                $users[array_rand($users)]['ID'],
                $transactionDate
            );
            
            // Insert transaction into database
            global $wpdb;
            $transactionTable = $wpdb->prefix . 'hm_inventory_transactions';
            $result = $wpdb->insert($transactionTable, $data);
            $transaction_id = $result ? $wpdb->insert_id : false;
            
            if ($transaction_id) {
                $created++;
                if ($created % 50 == 0) {
                    $this->log("Created {$created} inventory transactions...");
                }
                
                // Update the item's quantity for subsequent transactions
                foreach ($inventoryItems as &$invItem) {
                    if ($invItem['ID'] == $item['ID']) {
                        $invItem['quantity'] = $data['new_quantity'];
                        break;
                    }
                }
            }
        }
        
        $this->log("Created {$created} inventory transactions successfully");
    }
    
    private function generateTransactionData($item, $transactionType, $supplier, $userId, $transactionDate)
    {
        // Get item properties
        $itemId = $item['ID'];
        $currentQuantity = $item['quantity'];
        $itemCategory = $item['category'];
        $itemCost = $item['cost'];
        $itemLocation = $item['location'];
        
        // Generate quantity changed based on transaction type
        $quantityChanged = $this->getQuantityForTransactionType($transactionType, $currentQuantity);
        
        // Calculate new quantity
        $newQuantity = max(0, $currentQuantity + $quantityChanged);
        
        // Generate reference number
        $refPrefix = [
            'stock_in' => 'PO',
            'stock_out' => 'REQ',
            'adjustment' => 'ADJ',
            'transfer' => 'TRF',
            'expired' => 'EXP',
            'damaged' => 'DMG',
            'returned' => 'RET'
        ];
        $referenceNumber = $refPrefix[$transactionType] . '-' . strtoupper($this->faker->bothify('##???'));
        
        // Generate batch number for stock_in
        $batchNumber = null;
        $expiryDate = null;
        if ($transactionType == 'stock_in') {
            $batchNumber = strtoupper($this->faker->bothify('BAT-????##'));
            
            // Generate expiry date for medications and supplies
            if (in_array($itemCategory, ['Medication', 'Supplies', 'PPE', 'Consumables'])) {
                // Expiry date between 6 months and 3 years from transaction date
                $expiryDate = date('Y-m-d', strtotime("+{$this->faker->numberBetween(6, 36)} months", strtotime($transactionDate)));
            }
        }
        
        // Generate location details
        $locationFrom = null;
        $locationTo = null;
        
        if ($transactionType == 'transfer') {
            $locations = [
                'Pharmacy', 'Central Supply', 'OR Storage', 'ICU Supply Room', 
                'Emergency Supply', 'Pediatric Ward', 'Medical Ward', 'Surgical Ward',
                'Lab Storage', 'Radiology Storage', 'Main Warehouse', 'Clean Utility Room'
            ];
            
            $locationFrom = $itemLocation;
            $locationTo = $locations[array_rand($locations)];
            
            // Make sure to and from are different
            while ($locationTo == $locationFrom) {
                $locationTo = $locations[array_rand($locations)];
            }
        }
        
        // Generate cost information
        $costPerUnit = ($transactionType == 'stock_in') ? $itemCost : null;
        $totalCost = ($costPerUnit && $quantityChanged) ? abs($quantityChanged) * $costPerUnit : null;
        
        // Generate notes
        $notes = null;
        if ($this->faker->boolean(70)) { // 70% chance to have notes
            $notesTemplates = [
                'stock_in' => ['Received from supplier.', 'Regular stock replenishment.', 'Bulk order received.', 'Emergency order.'],
                'stock_out' => ['Used for patient care.', 'Dispensed to ward.', 'Used during procedure.', 'Regular consumption.'],
                'adjustment' => ['Inventory count correction.', 'Found additional stock.', 'Stocktaking adjustment.', 'System reconciliation.'],
                'transfer' => ['Moved to another location for better accessibility.', 'Rebalancing inventory across locations.', 'Consolidating similar items.'],
                'expired' => ['Items reached expiry date.', 'Found expired during audit.', 'Removed during regular check.'],
                'damaged' => ['Items damaged during handling.', 'Water damage.', 'Packaging compromised.', 'Quality issues found.'],
                'returned' => ['Returned unused items.', 'Quality issues.', 'Wrong items delivered.', 'Excess quantity returned.']
            ];
            
            $notes = $notesTemplates[$transactionType][array_rand($notesTemplates[$transactionType])];
        }
        
        // Determine supplier ID (only for stock_in and returned)
        $supplierId = in_array($transactionType, ['stock_in', 'returned']) ? $supplier['ID'] : null;
        
        return [
            'inventory_id' => $itemId,
            'user_id' => $userId,
            'transaction_type' => $transactionType,
            'quantity_changed' => $quantityChanged,
            'previous_quantity' => $currentQuantity,
            'new_quantity' => $newQuantity,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'location_from' => $locationFrom,
            'location_to' => $locationTo,
            'supplier_id' => $supplierId,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
            'cost_per_unit' => $costPerUnit,
            'total_cost' => $totalCost,
            'created_at' => $transactionDate
        ];
    }
    
    private function getQuantityForTransactionType($transactionType, $currentQuantity)
    {
        switch ($transactionType) {
            case 'stock_in':
                return $this->faker->numberBetween(5, 100);
                
            case 'stock_out':
                $maxOut = min($currentQuantity, 50); // Don't take out more than exists or 50 max
                return $maxOut > 0 ? -1 * $this->faker->numberBetween(1, $maxOut) : 0;
                
            case 'adjustment':
                if ($this->faker->boolean(50)) { // 50% positive adjustment
                    return $this->faker->numberBetween(1, 10);
                } else { // 50% negative adjustment
                    $maxOut = min($currentQuantity, 10);
                    return $maxOut > 0 ? -1 * $this->faker->numberBetween(1, $maxOut) : 0;
                }
                
            case 'transfer':
                // Transfers don't change quantity
                return 0;
                
            case 'expired':
            case 'damaged':
                $maxOut = min($currentQuantity, 15);
                return $maxOut > 0 ? -1 * $this->faker->numberBetween(1, $maxOut) : 0;
                
            case 'returned':
                return $this->faker->numberBetween(1, 10);
                
            default:
                return 0;
        }
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