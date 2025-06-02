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
        global $wpdb;
        
        $this->log("Creating doctors");
        
        // Get users with doctor role
        $doctor_users = get_users([
            'role' => 'doctor',
            'fields' => ['ID', 'display_name'],
        ]);
        
        // If no doctor users found, create some fake ones
        if (empty($doctor_users)) {
            $this->log("No doctor users found. Please create some users with the 'doctor' role first.");
            return;
        }
        
        $doctors_table = $wpdb->prefix . 'hm_doctors';
        $created = 0;
        
        foreach ($doctor_users as $user) {
            // Check if doctor already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$doctors_table} WHERE user_id = %d",
                $user->ID
            ));
            
            if (!$exists) {
                $this->log("Creating doctor for user ID: {$user->ID}", 'success');
                
                $first_name = get_user_meta($user->ID, 'first_name', true);
                $last_name = get_user_meta($user->ID, 'last_name', true);
                
                $first_names = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Mary', 'Robert', 'Lisa'];
                $last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
                
                if (empty($first_name)) $first_name = $first_names[array_rand($first_names)];
                if (empty($last_name)) $last_name = $last_names[array_rand($last_names)];
                
                // Add specialty as user meta
                $specialty = $this->specialties[array_rand($this->specialties)];
                update_user_meta($user->ID, 'specialty', $specialty);
                
                // Generate additional doctor information
                $officeNumber = 'Room ' . mt_rand(100, 500);
                $certifications = ['Board Certified', 'Board Eligible', 'Fellowship Trained'];
                $boardCertification = $certifications[array_rand($certifications)];
                $educations = [
                    'MD, Harvard Medical School',
                    'MD, Johns Hopkins University',
                    'MD, Stanford University',
                    'MBBS, University of Lagos',
                    'MD, University of California',
                    'MBBS, University of Ibadan',
                    'MD, Yale University School of Medicine'
                ];
                $education = $educations[array_rand($educations)];
                $yearsExperience = mt_rand(1, 30);
                $licenseNumber = 'MD' . mt_rand(100000, 999999);
                
                // Generate working hours availability
                $availability = $this->generateAvailability();
                
                // Generate phone number
                $phone_numbers = ['+234-800-123-4567', '+234-801-234-5678', '+234-802-345-6789'];
                $phone = $phone_numbers[array_rand($phone_numbers)];
                
                // Generate creation date
                $days_ago = mt_rand(30, 365);
                $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
                
                // Insert doctor record
                $result = $wpdb->insert(
                    $doctors_table,
                    [
                        'user_id' => $user->ID,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'phone' => $phone,
                        'specialty' => $specialty,
                        'office' => $officeNumber,
                        'board_certification' => $boardCertification,
                        'education' => $education,
                        'years_experience' => $yearsExperience,
                        'license_number' => $licenseNumber,
                        'appointment_availability' => json_encode($availability),
                        'created_at' => $created_at,
                        'updated_at' => current_time('mysql'),
                    ]
                );
                
                if ($result) {
                    $created++;
                }
            }
        }
        
        $this->log("Created {$created} doctor records", 'success');
    }

    /**
     * Generate a random working hours availability schedule for a doctor
     * 
     * @return array Working hours for each day of the week
     */
    protected function generateAvailability()
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $availability = [];
        
        // Randomly select 4-6 working days
        $workingDaysCount = rand(4, 6);
        $workingDays = array_slice($days, 0, $workingDaysCount);
        
        foreach ($days as $day) {
            if (in_array($day, $workingDays)) {
                // This is a working day - generate morning shift and maybe afternoon shift
                $shifts = [];
                
                // Morning shift (between 8-10 AM to 12-2 PM)
                $morningStart = sprintf('%02d:00', rand(8, 10));
                $morningEnd = sprintf('%02d:00', rand(12, 14));
                $shifts[] = ['start' => $morningStart, 'end' => $morningEnd];
                
                // 40% chance of afternoon shift
                if (rand(1, 10) <= 4) {
                    $afternoonStart = sprintf('%02d:00', rand(14, 16));
                    $afternoonEnd = sprintf('%02d:00', rand(17, 19));
                    $shifts[] = ['start' => $afternoonStart, 'end' => $afternoonEnd];
                }
                
                $availability[$day] = $shifts;
            } else {
                $availability[$day] = []; // Not working this day
            }
        }
        
        return $availability;
    }
}
