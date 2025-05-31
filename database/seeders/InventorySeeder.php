<?php

namespace HospitalManager\Database\Seeders;

use HospitalManager\Models\Inventory;

class InventorySeeder extends Seeder
{
    private $categories = [
        'Medication' => [
            'Paracetamol 500mg',
            'Ibuprofen 400mg',
            'Amoxicillin 250mg',
            'Metformin 500mg',
            'Lisinopril 10mg',
            'Amlodipine 5mg',
            'Atorvastatin 20mg',
            'Omeprazole 20mg',
            'Ciprofloxacin 500mg',
            'Prednisone 5mg',
            'Aspirin 81mg',
            'Insulin (Rapid Acting)',
            'Morphine 10mg',
            'Diazepam 5mg',
            'Furosemide 40mg'
        ],
        'Equipment' => [
            'Digital Thermometer',
            'Blood Pressure Monitor',
            'Stethoscope',
            'Pulse Oximeter',
            'ECG Machine',
            'Defibrillator',
            'Ventilator',
            'X-Ray Machine',
            'Ultrasound Machine',
            'Infusion Pump',
            'Patient Monitor',
            'Wheelchair',
            'Hospital Bed',
            'Stretcher',
            'Otoscope'
        ],
        'Supplies' => [
            'Disposable Syringes 5ml',
            'Medical Gauze',
            'Adhesive Bandages',
            'Cotton Swabs',
            'Alcohol Swabs',
            'Medical Tape',
            'IV Tubing',
            'Oxygen Mask',
            'Urinary Catheter',
            'Blood Collection Tubes',
            'Specimen Containers',
            'Saline Solution 0.9%',
            'Dextrose 5%',
            'Wound Dressing',
            'Elastic Bandages'
        ],
        'PPE' => [
            'Surgical Masks',
            'N95 Respirators',
            'Face Shields',
            'Latex Gloves (Small)',
            'Latex Gloves (Medium)',
            'Latex Gloves (Large)',
            'Nitrile Gloves (Small)',
            'Nitrile Gloves (Medium)',
            'Nitrile Gloves (Large)',
            'Disposable Gowns',
            'Surgical Caps',
            'Shoe Covers',
            'Safety Goggles',
            'Isolation Gowns',
            'Hair Covers'
        ],
        'Consumables' => [
            'Bed Sheets',
            'Pillow Cases',
            'Patient Gowns',
            'Blankets',
            'Towels',
            'Disposable Cups',
            'Paper Towels',
            'Tissues',
            'Disinfectant Wipes',
            'Hand Sanitizer',
            'Soap Dispensers',
            'Trash Bags',
            'Food Service Items',
            'Disposable Utensils',
            'Cleaning Supplies'
        ],
        'Surgical' => [
            'Scalpel Blades',
            'Surgical Scissors',
            'Forceps',
            'Hemostats',
            'Retractors',
            'Suture Materials',
            'Surgical Needles',
            'Surgical Drapes',
            'Electrocautery Tips',
            'Surgical Staples',
            'Laparoscopic Instruments',
            'Surgical Clips',
            'Bone Drill Bits',
            'Surgical Mesh',
            'Endotracheal Tubes'
        ]
    ];

    private $units = [
        'Medication' => ['tablets', 'capsules', 'vials', 'bottles', 'boxes'],
        'Equipment' => ['units', 'pieces', 'sets'],
        'Supplies' => ['boxes', 'packs', 'units', 'bottles', 'rolls'],
        'PPE' => ['boxes', 'packs', 'pieces'],
        'Consumables' => ['packs', 'boxes', 'units', 'bottles'],
        'Surgical' => ['boxes', 'sets', 'pieces', 'packs']
    ];

    private $locations = [
        'Pharmacy',
        'Central Supply',
        'OR Storage',
        'ICU Supply Room',
        'Emergency Supply',
        'Pediatric Ward',
        'Medical Ward',
        'Surgical Ward',
        'Lab Storage',
        'Radiology Storage',
        'Main Warehouse',
        'Clean Utility Room'
    ];

    private $costs = [
        'Medication' => ['min' => 5, 'max' => 150],
        'Equipment' => ['min' => 50, 'max' => 5000],
        'Supplies' => ['min' => 2, 'max' => 80],
        'PPE' => ['min' => 1, 'max' => 50],
        'Consumables' => ['min' => 3, 'max' => 45],
        'Surgical' => ['min' => 15, 'max' => 300]
    ];

    public function run()
    {
        $this->log("Creating inventory items");
        
        $created = 0;
        
        foreach ($this->categories as $category => $items) {
            foreach ($items as $item_name) {
                // Create variations for some items
                $variations = $this->getItemVariations($item_name);
                
                foreach ($variations as $variation) {
                    $data = $this->generateInventoryData($category, $variation);
                    
                    $item_id = Inventory::create($data);
                    
                    if ($item_id) {
                        $created++;
                        if ($created % 20 == 0) {
                            $this->log("Created {$created} inventory items...");
                        }
                    }
                }
            }
        }
        
        $this->log("Created {$created} inventory items successfully");
        
        // Update all statuses after creation
        $updated = Inventory::updateAllStatuses();
        $this->log("Updated statuses for {$updated} items");
    }

    private function getItemVariations($item_name)
    {
        // For some items, create variations (e.g., different sizes)
        $variations = [$item_name];
        
        // Add size variations for gloves
        if (strpos($item_name, 'Gloves') !== false && !preg_match('/\((Small|Medium|Large)\)/', $item_name)) {
            $variations = [
                $item_name . ' (Small)',
                $item_name . ' (Medium)', 
                $item_name . ' (Large)'
            ];
        }
        
        // Add concentration variations for some medications
        if (in_array($item_name, ['Paracetamol 500mg', 'Ibuprofen 400mg'])) {
            $base_name = preg_replace('/\s+\d+mg$/', '', $item_name);
            if ($base_name === 'Paracetamol') {
                $variations = ['Paracetamol 250mg', 'Paracetamol 500mg'];
            } elseif ($base_name === 'Ibuprofen') {
                $variations = ['Ibuprofen 200mg', 'Ibuprofen 400mg'];
            }
        }
        
        return $variations;
    }

    private function generateInventoryData($category, $item_name)
    {
        $units = $this->units[$category];
        $unit = $units[array_rand($units)];
        
        $location = $this->locations[array_rand($this->locations)];
        
        // Generate realistic quantities based on category
        $quantity = $this->generateQuantity($category);
        $reorder_level = max(5, intval($quantity * 0.2)); // 20% of current stock
        
        // Generate cost
        $cost_range = $this->costs[$category];
        $cost = $this->faker->randomFloat(2, $cost_range['min'], $cost_range['max']);
        
        // Generate expiry date (only for applicable categories)
        $expiry_date = null;
        if (in_array($category, ['Medication', 'Supplies', 'PPE', 'Consumables'])) {
            $expiry_date = $this->generateExpiryDate($category);
        }
        
        // Determine status based on quantity and expiry
        $status = $this->determineStatus($quantity, $reorder_level, $expiry_date);
        
        return [
            'item_name' => $item_name,
            'category' => $category,
            'quantity' => $quantity,
            'unit' => $unit,
            'reorder_level' => $reorder_level,
            'expiry_date' => $expiry_date,
            'location' => $location,
            'cost' => $cost,
            'status' => $status
        ];
    }

    private function generateQuantity($category)
    {
        $ranges = [
            'Medication' => ['min' => 0, 'max' => 500],
            'Equipment' => ['min' => 1, 'max' => 20],
            'Supplies' => ['min' => 0, 'max' => 1000],
            'PPE' => ['min' => 0, 'max' => 2000],
            'Consumables' => ['min' => 5, 'max' => 800],
            'Surgical' => ['min' => 2, 'max' => 300]
        ];
        
        $range = $ranges[$category];
        
        // Create some items with zero or very low stock
        if ($this->faker->boolean(15)) { // 15% chance of low/zero stock
            return $this->faker->numberBetween(0, 3);
        }
        
        return $this->faker->numberBetween($range['min'], $range['max']);
    }

    private function generateExpiryDate($category)
    {
        $date_ranges = [
            'Medication' => ['min' => '-30 days', 'max' => '+2 years'],
            'Supplies' => ['min' => '+6 months', 'max' => '+3 years'],
            'PPE' => ['min' => '+3 months', 'max' => '+2 years'],
            'Consumables' => ['min' => '+1 month', 'max' => '+1 year']
        ];
        
        $range = $date_ranges[$category];
        
        // Some items get expiry dates in different ranges for testing
        if ($this->faker->boolean(20)) { // 20% chance of expiring soon/expired
            if ($this->faker->boolean(30)) { // 30% of those are expired
                $start_date = strtotime('-60 days');
                $end_date = strtotime('-1 day');
            } else { // 70% are expiring soon (within 30 days)
                $start_date = strtotime('today');
                $end_date = strtotime('+30 days');
            }
        } else {
            $start_date = strtotime($range['min']);
            $end_date = strtotime($range['max']);
        }
        
        $random_timestamp = $this->faker->numberBetween($start_date, $end_date);
        return date('Y-m-d', $random_timestamp);
    }

    private function determineStatus($quantity, $reorder_level, $expiry_date)
    {
        // Check if expired first
        if ($expiry_date && strtotime($expiry_date) < time()) {
            return Inventory::STATUS_EXPIRED;
        }
        
        // Check stock levels
        if ($quantity == 0) {
            return Inventory::STATUS_OUT_OF_STOCK;
        } elseif ($quantity <= $reorder_level) {
            return Inventory::STATUS_LOW_STOCK;
        }
        
        return Inventory::STATUS_IN_STOCK;
    }
}
