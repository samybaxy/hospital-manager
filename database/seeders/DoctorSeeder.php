<?php

namespace HospitalManager\Database\Seeders;

class DoctorSeeder extends Seeder
{
    protected $specialties = [
        'Cardiology',
        'Dermatology',
        'Endocrinology',
        'Gastroenterology',
        'Neurology',
        'Obstetrics',
        'Oncology',
        'Ophthalmology',
        'Orthopedics',
        'Pediatrics',
        'Psychiatry',
        'Radiology',
        'Urology'
    ];
    
    public function run()
    {
        // Get users with doctor role
        $doctor_users = get_users([
            'role' => 'doctor',
            'fields' => ['ID', 'display_name'],
        ]);
        
        $doctors_table = $this->wpdb->prefix . 'hm_doctors';
        $created = 0;
        
        $this->log("Creating doctors");
        
        foreach ($doctor_users as $user) {
            // Check if doctor already exists
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$doctors_table} WHERE user_id = %d",
                $user->ID
            ));
            
            if (!$exists) {
                $first_name = get_user_meta($user->ID, 'first_name', true);
                $last_name = get_user_meta($user->ID, 'last_name', true);
                
                if (empty($first_name)) $first_name = $this->faker->firstName();
                if (empty($last_name)) $last_name = $this->faker->lastName();
                
                // Add specialty as user meta
                $specialty = $this->faker->randomElement($this->specialties);
                update_user_meta($user->ID, 'specialty', $specialty);
                
                // Insert doctor record
                $result = $this->wpdb->insert(
                    $doctors_table,
                    [
                        'user_id' => $user->ID,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'phone' => $this->faker->phoneNumber(),
                        'created_at' => $this->faker->dateTimeBetween('-1 year', '-6 months')->format('Y-m-d H:i:s'),
                        'updated_at' => current_time('mysql'),
                    ],
                    [
                        '%d', '%s', '%s', '%s', '%d', '%s', '%s'
                    ]
                );
                
                if ($result) {
                    $created++;
                }
            }
        }
        
        $this->log("Created {$created} doctor records");
    }
}
