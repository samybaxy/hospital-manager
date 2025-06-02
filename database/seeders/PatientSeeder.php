<?php

namespace HospitalManager\Database\Seeders;

class PatientSeeder extends Seeder
{
    public function run()
    {
        global $wpdb;
        
        // Get users with patient role
        $patient_users = get_users([
            'role' => 'patient',
            'fields' => ['ID', 'display_name'],
        ]);
        
        // Get HMO IDs
        $hmo_table = $wpdb->prefix . 'hm_hmos';
        $hmo_ids = $wpdb->get_col("SELECT ID FROM {$hmo_table}");
        
        if (empty($hmo_ids)) {
            $this->log("Warning: No HMOs found. Make sure HMOSeeder was run before this seeder.");
            $hmo_ids = [null]; // Ensure we have at least a null value
        }
        
        $patients_table = $wpdb->prefix . 'hm_patients';
        $created = 0;
        
        $this->log("Creating patients");
        
        foreach ($patient_users as $user) {
            // Check if patient already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$patients_table} WHERE user_id = %d",
                $user->ID
            ));
            
            if (!$exists) {
                $this->log("Creating patient for user ID: {$user->ID}", 'success');
                
                $first_name = get_user_meta($user->ID, 'first_name', true);
                $last_name = get_user_meta($user->ID, 'last_name', true);
                
                $first_names = ['John', 'Jane', 'Mary', 'Michael', 'Sarah', 'David', 'Linda', 'Robert'];
                $last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
                
                if (empty($first_name)) $first_name = $first_names[array_rand($first_names)];
                if (empty($last_name)) $last_name = $last_names[array_rand($last_names)];
                
                $genders = ['Male', 'Female'];
                $gender = $genders[array_rand($genders)];
                $age = mt_rand(18, 80);
                $marital_statuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
                $marital_status = $marital_statuses[array_rand($marital_statuses)];
                $cities = ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 'Enugu'];
                $city = $cities[array_rand($cities)];
                // Replace state() with randomElement of states
                $states = ['Lagos', 'Abuja', 'Rivers', 'Kano', 'Oyo', 'Enugu', 'Kaduna', 'Delta', 'Anambra', 'Imo'];
                $state = $states[array_rand($states)];
                
                // Randomly assign an HMO or null
                $hmo_id = (mt_rand(1, 100) <= 70 && !empty($hmo_ids)) ? $hmo_ids[array_rand($hmo_ids)] : null;
                
                // Generate bio data
                $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                $allergies_list = ['Penicillin', 'Nuts', 'Shellfish', 'Latex', 'Dust'];
                $conditions_list = ['Diabetes', 'Hypertension', 'Asthma'];
                $relationships = ['Spouse', 'Parent', 'Child', 'Sibling', 'Friend'];
                $emergency_names = ['John Doe', 'Jane Smith', 'Mary Johnson', 'David Brown'];
                $phone_numbers = ['+234-800-123-4567', '+234-801-234-5678', '+234-802-345-6789'];
                $addresses = ['123 Main St', '456 Oak Ave', '789 Pine Rd', '321 Elm St'];
                
                $bio_data = [
                    'blood_group' => $blood_groups[array_rand($blood_groups)],
                    'allergies' => (mt_rand(1, 100) <= 40) ? $allergies_list[array_rand($allergies_list)] : null,
                    'chronic_conditions' => (mt_rand(1, 100) <= 30) ? $conditions_list[array_rand($conditions_list)] : null,
                    'emergency_contact' => [
                        'name' => $emergency_names[array_rand($emergency_names)],
                        'phone' => $phone_numbers[array_rand($phone_numbers)],
                        'relationship' => $relationships[array_rand($relationships)]
                    ],
                    'height' => mt_rand(150, 200), // in cm
                    'weight' => mt_rand(50, 120), // in kg
                ];
                
                $days_ago = mt_rand(30, 180);
                $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
                
                $result = $wpdb->insert(
                    $patients_table,
                    [
                        'user_id' => $user->ID,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'hmo_id' => $hmo_id,
                        'hmo_designated_id' => $hmo_id ? 'HMO-' . mt_rand(100000, 999999) : null,
                        'phone' => $phone_numbers[array_rand($phone_numbers)],
                        'age' => $age,
                        'gender' => $gender,
                        'marital_status' => $marital_status,
                        'address' => $addresses[array_rand($addresses)],
                        'city' => $city,
                        'state' => $state,
                        'bio_data' => json_encode($bio_data),
                        'created_at' => $created_at,
                        'updated_at' => current_time('mysql'),
                    ],
                    [
                        '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                    ]
                );
                
                if ($result) {
                    $created++;
                }
            }
        }
        
        $this->log("Created {$created} patient records", 'success');
    }
}
