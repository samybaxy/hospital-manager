<?php

namespace HospitalManager\Database\Seeders;

use HospitalManager\Models\InventorySupplier;

class InventorySuppliersSeeder extends Seeder
{
    private $supplierNames = [
        'MediPharma Suppliers Ltd',
        'Hospital Equipment Corp',
        'SafeCare Medical Supplies',
        'Apex Healthcare Products',
        'Unity Medical Distributors',
        'Premier Surgical Supplies',
        'Global PPE Solutions',
        'NigMed Pharmaceuticals',
        'TechMed Equipment Providers',
        'Alpine Medical Consumables',
        'Sunset Pharmaceutical Distributors',
        'MedEquip Enterprises',
        'Crown Healthcare Supplies'
    ];

    private $cities = [
        'Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 
        'Enugu', 'Calabar', 'Kaduna', 'Owerri', 'Asaba'
    ];

    private $states = [
        'Lagos State', 'FCT', 'Rivers State', 'Oyo State', 'Kano State', 
        'Enugu State', 'Cross River State', 'Kaduna State', 'Imo State', 'Delta State'
    ];

    public function run()
    {
        $this->log("Creating inventory suppliers");
        
        $created = 0;
        
        foreach ($this->supplierNames as $supplierName) {
            $data = $this->generateSupplierData($supplierName);
            
            $supplier_id = InventorySupplier::create($data);
            
            if ($supplier_id) {
                $created++;
                if ($created % 5 == 0) {
                    $this->log("Created {$created} inventory suppliers...");
                }
            }
        }
        
        $this->log("Created {$created} inventory suppliers successfully");
    }

    private function generateSupplierData($name)
    {
        $cityIndex = array_rand($this->cities);
        $paymentTerms = ['Net 30', 'Net 45', 'COD', 'Prepaid', 'Net 60'];
        $domains = ['medicalsupply.com', 'healthcare.com', 'medsolution.ng', 'pharma.net', 'healthsupplies.org'];

        // Generate a contact person name
        $firstNames = ['John', 'Mary', 'Samuel', 'Amina', 'David', 'Sarah', 'Michael', 'Ngozi', 'Emeka', 'Fatima'];
        $lastNames = ['Smith', 'Johnson', 'Okafor', 'Mohammed', 'Adeyemi', 'Ibrahim', 'Williams', 'Okonkwo', 'Eze', 'Abubakar'];
        $contactPerson = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
        
        // Generate email from company name
        $emailPrefix = strtolower(str_replace(' ', '.', $name));
        $emailPrefix = preg_replace('/[^a-z0-9\.]/', '', $emailPrefix);
        $email = 'info@' . substr($emailPrefix, 0, 10) . '.' . substr($domains[array_rand($domains)], 0, strpos($domains[array_rand($domains)], '.') + 4);
        
        // Generate realistic phone numbers for Nigeria
        $phone = '+234' . rand(700000000, 999999999);
        
        return [
            'name' => $name,
            'contact_person' => $contactPerson,
            'email' => $email,
            'phone' => $phone,
            'address' => $this->faker->streetAddress(),
            'city' => $this->cities[$cityIndex],
            'state' => $this->states[$cityIndex], // Match city and state
            'country' => 'Nigeria',
            'postal_code' => $this->faker->numberBetween(100001, 999999),
            'tax_id' => 'TX-' . $this->faker->numberBetween(1000000, 9999999),
            'payment_terms' => $paymentTerms[array_rand($paymentTerms)],
            'delivery_time_days' => $this->faker->numberBetween(1, 14),
            'minimum_order_amount' => $this->faker->randomFloat(2, 5000, 50000),
            'is_active' => $this->faker->boolean(90) ? 1 : 0, // 90% active
            'notes' => $this->faker->boolean(70) ? $this->faker->paragraph(2) : null // 70% have notes
        ];
    }
}