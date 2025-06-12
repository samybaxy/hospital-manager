<?php

namespace HospitalManager\Database\Seeders;

class UserSeeder extends Seeder
{
    protected $counts = [
        'doctor' => 25,
        'patient' => 100,
        'lab_tech' => 5,
        'developer' => 3,
        'hospital_nurse' => 10,
        'hospital_staff' => 8,
        'pharmacy_staff' => 4,
        'inventory_manager' => 2,
        'administrator' => 1,
    ];

    public function run()
    {
        $this->ensureRolesExist();
        
        foreach ($this->counts as $role => $count) {
            $created = 0;
            
            $this->log("Creating {$count} users with role '{$role}'");
            
            for ($i = 0; $i < $count; $i++) {
                $user_id = $this->createUser($role);
                if ($user_id) {
                    $created++;
                }
            }
            
            $this->log("Created {$created} users with role '{$role}'", 'success');
        }
        
        // Create one demo user for each role with known credentials
        $demo_roles = [
            'administrator', 
            'doctor', 
            'patient', 
            'lab_tech', 
            'developer',
            'hospital_nurse',
            'hospital_staff',
            'pharmacy_staff',
            'inventory_manager'
        ];
        
        foreach ($demo_roles as $role) {
            $username = 'demo_' . $role;
            $email = $role . '@example.com';
            $password = 'demo1234'; // 8 characters, meets our requirement
            
            // Check if user exists
            if (!username_exists($username) && !email_exists($email)) {
                $user_id = wp_create_user($username, $password, $email);
                
                if (!is_wp_error($user_id)) {
                    // Explicitly set password again to ensure it's properly hashed
                    wp_set_password($password, $user_id);
                    
                    $user = new \WP_User($user_id);
                    $user->set_role($role);
                    
                    // Set first and last name
                    $first_name = ucfirst($role);
                    $last_name = 'User';
                    
                    update_user_meta($user_id, 'first_name', $first_name);
                    update_user_meta($user_id, 'last_name', $last_name);
                    
                    $this->log("Created demo {$role} user with username '{$username}' and password '{$password}'", 'success');
                }
            } else {
                $this->log("Demo {$role} user already exists", 'warning');
            }
        }
    }
    
    /**
     * Ensure all required roles exist
     */
    protected function ensureRolesExist()
    {
        $roles = [
            'doctor' => 'Doctor',
            'patient' => 'Patient', 
            'lab_tech' => 'Lab Technician',
            'developer' => 'Developer',
            'hospital_nurse' => 'Nurse',
            'hospital_staff' => 'Hospital Staff',
            'pharmacy_staff' => 'Pharmacy Staff',
            'inventory_manager' => 'Inventory Manager',
            'administrator' => 'Administrator'
        ];
        
        foreach ($roles as $role_name => $display_name) {
            if (!get_role($role_name)) {
                add_role($role_name, $display_name, [
                    'read' => true,
                    'edit_posts' => $role_name !== 'patient', // Only non-patients can edit posts
                    'upload_files' => $role_name !== 'patient', // Only non-patients can upload files
                ]);
                $this->log("Created '{$role_name}' role", 'success');
            }
        }
    }

    /**
     * Create a single user with the given role
     */
    protected function createUser($role)
    {
        // Nigerian names for realistic data
        $nigerian_first_names = [
            // Male names
            'Adebayo', 'Emeka', 'Gbenga', 'Ibrahim', 'Kemi', 'Lateef', 'Mahmud', 'Oluwaseun', 
            'Quadri', 'Rasheed', 'Tunde', 'Uche', 'Victor', 'Wale', 'Yemi', 'Adunni',
            'Bukola', 'Damilola', 'Ebuka', 'Goodness', 'Hassan', 'Jideofor', 'Kingsley',
            'Lekan', 'Muyiwa', 'Nnamdi', 'Obinna', 'Peter', 'Rotimi', 'Samuel', 'Tosin',
            // Female names
            'Chioma', 'Folake', 'Halima', 'Joke', 'Ngozi', 'Priscilla', 'Sade', 'Zainab',
            'Chiamaka', 'Funmi', 'Adaeze', 'Blessing', 'Chinelo', 'Doris', 'Esther', 'Faith',
            'Grace', 'Hope', 'Ifeoma', 'Jennifer', 'Khadijah', 'Loveth', 'Mary', 'Nkechi',
            'Omolara', 'Peace', 'Queen', 'Ruth', 'Stella', 'Tolu', 'Uju', 'Victoria'
        ];
        
        $nigerian_last_names = [
            'Adebayo', 'Okafor', 'Adeleke', 'Okonkwo', 'Adeola', 'Okoro', 'Adeyemi', 'Oluwaseun',
            'Ajayi', 'Olatunji', 'Akinyemi', 'Omolara', 'Alabi', 'Onuoha', 'Babatunde', 'Oyedepo',
            'Balogun', 'Tijani', 'Chukwu', 'Udoh', 'Dada', 'Umar', 'Egwu', 'Yakubu',
            'Fashola', 'Yusuf', 'Gbenga', 'Zahra', 'Hassan', 'Ibrahim', 'Jideofor', 'Mohammed',
            'Nwankwo', 'Ogbonna', 'Okwu', 'Orimolade', 'Oseni', 'Otunba', 'Owolabi', 'Salami',
            'Sanusi', 'Taiwo', 'Umaru', 'Waziri', 'Yaro', 'Zubair', 'Abdullahi', 'Bello'
        ];
        
        $first_name = $nigerian_first_names[array_rand($nigerian_first_names)];
        $last_name = $nigerian_last_names[array_rand($nigerian_last_names)];
        
        // Create unique username and email
        do {
            $username = strtolower($first_name . $last_name . mt_rand(10, 999));
            $email = $username . '@example.com';
        } while (username_exists($username) || email_exists($email));
        
        // Create the user with a strong 8+ character password
        $password = 'hospital123'; // 11 characters, meets our requirement
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $this->log("Error creating user: " . $user_id->get_error_message(), 'error');
            return false;
        }
        
        // Ensure wp_create_user properly sets the password hash
        // This is necessary because sometimes wp_create_user doesn't correctly set the password
        wp_set_password($password, $user_id);
        
        // Set role
        $user = new \WP_User($user_id);
        $user->set_role($role);
        
        // Add user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        
        return $user_id;
    }
}
