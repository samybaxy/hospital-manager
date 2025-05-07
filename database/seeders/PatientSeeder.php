<?php

namespace HospitalManager\Database\Seeders;

class PatientSeeder extends Seeder
{
    public function run()
    {
        // Get users with patient role
        $patient_users = get_users([
            'role' => 'patient',
            'fields' => ['ID', 'display_name'],
        ]);
        
        // Get HMO IDs
        $hmo_table = $this->wpdb->prefix . 'hm_hmos';
        $hmo_ids = $this->wpdb->get_col("SELECT id FROM {$hmo_table}");
        
        if (empty($hmo_ids)) {
            $this->log("Warning: No HMOs found. Make sure HMOSeeder was run before this seeder.");
            $hmo_ids = [null]; // Ensure we have at least a null value
        }
        
        $patients_table = $this->wpdb->prefix . 'hm_patients';
        $created = 0;
        
        $this->log("Creating patients");
        
        foreach ($patient_users as $user) {
            // Check if patient already exists
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$patients_table} WHERE user_id = %d",
                $user->ID
            ));
            
            if (!$exists) {
                $first_name = get_user_meta($user->ID, 'first_name', true);
                $last_name = get_user_meta($user->ID, 'last_name', true);
                
                if (empty($first_name)) $first_name = $this->faker->firstName();
                if (empty($last_name)) $last_name = $this->faker->lastName();
                
                $gender = $this->faker->randomElement(['Male', 'Female']);
                $age = $this->faker->numberBetween(18, 80);
                
                // Randomly assign an HMO or null
                $hmo_id = $this->faker->optional(0.7)->randomElement($hmo_ids);
                
                // Generate bio data
                $bio_data = [
                    'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
                    'allergies' => $this->faker->optional(0.4)->words(rand(1, 3), true),
                    'chronic_conditions' => $this->faker->optional(0.3)->words(rand(1, 2), true),
                    'emergency_contact' => [
                        'name' => $this->faker->name(),
                        'phone' => $this->faker->phoneNumber(),
                        'relationship' => $this->faker->randomElement(['Spouse', 'Parent', 'Child', 'Sibling', 'Friend'])
                    ],
                    'height' => $this->faker->numberBetween(150, 200), // in cm
                    'weight' => $this->faker->numberBetween(50, 120), // in kg
                ];
                
                $result = $this->wpdb->insert(
                    $patients_table,
                    [
                        'user_id' => $user->ID,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'hmo_id' => $hmo_id,
                        'hmo_designated_id' => $hmo_id ? 'HMO-' . $this->faker->unique()->numerify('######') : null,
                        'phone' => $this->faker->phoneNumber(),
                        'age' => $age,
                        'gender' => $gender,
                        'address' => $this->faker->address(),
                        'bio_data' => json_encode($bio_data),
                        'created_at' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s'),
                        'updated_at' => current_time('mysql'),
                    ],
                    [
                        '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
                    ]
                );
                
                if ($result) {
                    $created++;
                }
            }
        }
        
        $this->log("Created {$created} patient records");
    }
}
