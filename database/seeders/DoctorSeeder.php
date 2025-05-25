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
                
                // Generate additional doctor information
                $officeNumber = 'Room ' . $this->faker->numberBetween(100, 500);
                $boardCertification = $this->faker->randomElement(['Board Certified', 'Board Eligible', 'Fellowship Trained']);
                $education = $this->faker->randomElement([
                    'MD, Harvard Medical School',
                    'MD, Johns Hopkins University',
                    'MD, Stanford University',
                    'MBBS, University of Lagos',
                    'MD, University of California',
                    'MBBS, University of Ibadan',
                    'MD, Yale University School of Medicine'
                ]);
                $yearsExperience = $this->faker->numberBetween(1, 30);
                $licenseNumber = 'MD' . $this->faker->randomNumber(6, true);
                
                // Generate working hours availability
                $availability = $this->generateAvailability();
                
                // Insert doctor record
                $result = $this->wpdb->insert(
                    $doctors_table,
                    [
                        'user_id' => $user->ID,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'phone' => $this->faker->phoneNumber(),
                        'specialty' => $specialty,
                        'office' => $officeNumber,
                        'board_certification' => $boardCertification,
                        'education' => $education,
                        'years_experience' => $yearsExperience,
                        'license_number' => $licenseNumber,
                        'appointment_availability' => json_encode($availability),
                        'created_at' => $this->faker->dateTimeBetween('-1 year', '-6 months')->format('Y-m-d H:i:s'),
                        'updated_at' => current_time('mysql'),
                    ],
                    [
                        '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
                    ]
                );
                
                if ($result) {
                    $created++;
                }
            }
        }
        
        $this->log("Created {$created} doctor records");
    }

    /**
     * Generate a random working hours availability schedule for a doctor
     * 
     * @return array Working hours for each day of the week in the format:
     * {"friday": [{"end": "14:00", "start": "08:00"}], "thursday": [{"end": "14:00", "start": "07:00"}], ...}
     */
    protected function generateAvailability()
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $availability = [];
        
        // Randomly select 4-6 working days
        $workingDaysCount = rand(4, 6);
        $workingDays = (array) array_rand(array_flip($days), $workingDaysCount);
        
        foreach ($days as $day) {
            // If it's a working day, generate time slot
            if (in_array($day, $workingDays)) {
                // Randomly choose a start time between 6 AM and 12 PM
                $startHour = rand(6, 12);
                
                // End time is 2-6 hours after start time, but no later than 6 PM
                $endHour = min(rand($startHour + 2, $startHour + 6), 18);
                
                // Format as 24-hour time for storage in JSON - each day has a single slot in an array
                $availability[$day] = [
                    [
                        'start' => sprintf('%02d:00', $startHour),
                        'end' => sprintf('%02d:00', $endHour)
                    ]
                ];
            } else {
                // Not a working day - empty array
                $availability[$day] = [];
            }
        }
        
        return $availability;
    }
}
