<?php

namespace HospitalManager\Database\Seeders;

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
        
        global $wpdb;
        $table = $wpdb->prefix . 'hm_inventory_suppliers';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$table_exists) {
            $this->log("Error: Table $table does not exist!");
            return;
        }
        
        $created = 0;
        
        foreach ($this->supplierNames as $supplierName) {
            try {
                $data = $this->generateSupplierData($supplierName);
                
                // Insert directly into database
                $result = $wpdb->insert($table, $data);
                
                if ($result === false) {
                    $this->log("Error inserting supplier '$supplierName': " . $wpdb->last_error);
                    continue;
                }
                
                $supplier_id = $wpdb->insert_id;
                
                if ($supplier_id) {
                    $created++;
                    if ($created % 5 == 0) {
                        $this->log("Created {$created} inventory suppliers...");
                    }
                }
            } catch (\Exception $e) {
                $this->log("Exception creating supplier '$supplierName': " . $e->getMessage());
                continue;
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
        $selectedDomain = $domains[array_rand($domains)];
        $email = 'info@' . substr($emailPrefix, 0, 10) . '.' . $selectedDomain;
        
        // Generate realistic phone numbers for Nigeria
        $phone = '+234' . mt_rand(700000000, 999999999);
        
        // Use basic functions instead of faker if faker is not available
        $streetAddresses = [
            'Plot 123 Victoria Island',
            '45 Allen Avenue',
            'Suite 67 Marina Street',
            '12 Independence Way',
            '89 Constitution Avenue',
            'Block C Industrial Estate',
            '156 Market Road',
            '23 Government House Road'
        ];
        
        return [
            'name' => $name,
            'contact_person' => $contactPerson,
            'email' => $email,
            'phone' => $phone,
            'address' => $streetAddresses[array_rand($streetAddresses)],
            'city' => $this->cities[$cityIndex],
            'state' => $this->states[$cityIndex], // Match city and state
            'country' => 'Nigeria',
            'postal_code' => mt_rand(100001, 999999),
            'tax_id' => 'TX-' . mt_rand(1000000, 9999999),
            'payment_terms' => $paymentTerms[array_rand($paymentTerms)],
            'delivery_time_days' => mt_rand(1, 14),
            'minimum_order_amount' => round(mt_rand(5000, 50000) + (mt_rand(0, 99) / 100), 2),
            'is_active' => mt_rand(1, 100) <= 90 ? 1 : 0, // 90% active
            'notes' => mt_rand(1, 100) <= 70 ? $this->getRandomNote() : null // 70% have notes
        ];
    }
    
    private function getRandomNote()
    {
        $notes = [
            'Reliable supplier with good delivery times.',
            'Competitive pricing for bulk orders.',
            'Established relationship with quality products.',
            'New supplier with promising service.',
            'Specializes in medical equipment and supplies.',
            'Local supplier with quick delivery options.',
            'International supplier with premium products.',
            'Cost-effective solutions for hospital needs.'
        ];
        
        return $notes[array_rand($notes)];
    }
}