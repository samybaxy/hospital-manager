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

    /**
     * Demo roles constant
     */
    const DEMO_ROLES = [
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

    public function run()
    {
        global $wpdb;
        
        // Prevent any WordPress authentication or cookie setting during CLI
        if (php_sapi_name() === 'cli') {
            // Remove all authentication hooks that might try to set cookies
            remove_all_actions('wp_login');
            remove_all_actions('wp_logout');
            remove_all_actions('set_auth_cookie');
            remove_all_actions('clear_auth_cookie');
            
            // Prevent cookie setting functions
            add_filter('send_headers', '__return_false');
            add_filter('wp_redirect', '__return_false');
        }
        
        // Check database connection first
        $this->log("Checking database connection...");
        $db_test = $wpdb->get_var("SELECT 1");
        if ($db_test !== '1') {
            $this->log("Database connection test failed", 'error');
            if ($wpdb->last_error) {
                $this->log("Database error: " . $wpdb->last_error, 'error');
            }
            return false;
        }
        $this->log("Database connection OK", 'success');
        
        // Check database permissions
        $this->log("Checking database permissions...");
        $can_insert = $wpdb->query("SHOW GRANTS");
        if ($wpdb->last_error) {
            $this->log("Warning: Could not check database grants: " . $wpdb->last_error, 'warning');
        }
        
        $this->ensureRolesExist();
        
        $overall_created = 0;
        $overall_failed = 0;
        
        foreach ($this->counts as $role => $count) {
            $created = 0;
            $failed = 0;
            
            $this->log("Creating {$count} users with role '{$role}'");
            
            for ($i = 0; $i < $count; $i++) {
                $user_id = $this->createUser($role);
                if ($user_id && is_numeric($user_id) && $user_id > 0) {
                    $created++;
                    $overall_created++;
                } else {
                    $failed++;
                    $overall_failed++;
                    
                    // Log database errors if any
                    if ($wpdb->last_error) {
                        $this->log("Database error during user creation: " . $wpdb->last_error, 'error');
                        // Clear the error to prevent it from affecting next iteration
                        $wpdb->last_error = '';
                    }
                }
                
                // Add a small delay to prevent overwhelming the database
                if ($i > 0 && $i % 10 === 0) {
                    usleep(100000); // 100ms delay every 10 users
                }
            }
            
            if ($created > 0) {
                $this->log("Created {$created} users with role '{$role}'", 'success');
            } else {
                $this->log("Created 0 users with role '{$role}' - all attempts failed", 'error');
            }
            
            if ($failed > 0) {
                $this->log("Failed to create {$failed} users with role '{$role}'", 'warning');
            }
        }
        
        // Create one demo user for each role with known credentials
        $this->log("Creating demo users with known credentials...");
        
        $demo_created = 0;
        $demo_failed = 0;
        
        foreach (self::DEMO_ROLES as $role) {
            $result = $this->createDemoUser($role);
            if ($result) {
                $demo_created++;
            } else {
                $demo_failed++;
            }
        }
        
        $this->log("Demo users created: {$demo_created}, failed: {$demo_failed}");
        
        // Verify demo user credentials after creation
        $this->log("Verifying demo user credentials...");
        $this->verifyDemoCredentials();
        
        // Final summary
        $this->log("=== User Creation Summary ===");
        $this->log("Total regular users created: {$overall_created}");
        $this->log("Total regular users failed: {$overall_failed}");
        $this->log("Demo users created: {$demo_created}");
        $this->log("Demo users failed: {$demo_failed}");
        
        $total_success = $overall_created + $demo_created;
        $total_failed = $overall_failed + $demo_failed;
        
        if ($total_failed > 0) {
            $this->log("WARNING: {$total_failed} users failed to create. Check database permissions and configuration.", 'warning');
        }
        
        return $total_success > 0;
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
        global $wpdb;
        
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
        
        // Create unique username and email with more retries
        $max_attempts = 50;
        $attempts = 0;
        
        do {
            $random_suffix = mt_rand(100, 99999);
            $username = strtolower($first_name . $last_name . $random_suffix);
            $email = $username . '@example.com';
            $attempts++;
            
            if ($attempts > $max_attempts) {
                $this->log("Could not generate unique username after {$max_attempts} attempts", 'error');
                return false;
            }
        } while (username_exists($username) || email_exists($email));
        
        // Check database connection before proceeding
        if ($wpdb->last_error) {
            $this->log("Database error detected: " . $wpdb->last_error, 'error');
            return false;
        }
        
        // Create the user with a strong 8+ character password
        $password = 'hospital123'; // 11 characters, meets our requirement
        
        // Use wp_create_user with better error handling
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $this->log("Error creating user '{$username}': " . $user_id->get_error_message(), 'error');
            
            // Check if it's a database error
            if ($wpdb->last_error) {
                $this->log("Database error: " . $wpdb->last_error, 'error');
            }
            
            return false;
        }
        
        // Verify the user was actually created with a valid ID
        if (!$user_id || $user_id === 0) {
            $this->log("User creation returned invalid ID: {$user_id}", 'error');
            return false;
        }
        
        // Double-check the user exists in database
        $user_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE ID = %d",
            $user_id
        ));
        
        if (!$user_exists) {
            $this->log("User {$user_id} was not found in database after creation", 'error');
            return false;
        }
        
        // Check for database errors after user verification
        if ($wpdb->last_error) {
            $this->log("Database error during user verification: " . $wpdb->last_error, 'error');
            return false;
        }
        
        // Set role
        $user = new \WP_User($user_id);
        $user->set_role($role);
        
        // Add user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        
        // Final verification that everything worked
        $final_user = get_user_by('id', $user_id);
        if (!$final_user || !$final_user->exists()) {
            $this->log("User {$user_id} verification failed after creation", 'error');
            return false;
        }
        
        return $user_id;
    }
    
    /**
     * Create a demo user with known credentials
     */
    protected function createDemoUser($role)
    {
        $username = 'demo_' . $role;
        $email = $role . '@example.com';
        $password = 'demo1234';
        
        $meta = [
            'first_name' => ucfirst(str_replace('_', ' ', $role)),
            'last_name' => 'Demo',
            'description' => "Demo user for {$role} role"
        ];
        
        $user_id = $this->createUserSafely($username, $password, $email, $role, $meta);
        
        if ($user_id && is_numeric($user_id) && $user_id > 0) {
            $this->log("Created demo {$role}: username='{$username}', password='demo1234', email='{$email}'", 'success');
            return $user_id;
        } else {
            $this->log("Failed to create demo {$role} user", 'error');
            
            // Additional debugging for demo user failures
            global $wpdb;
            if ($wpdb->last_error) {
                $this->log("Database error for demo {$role}: " . $wpdb->last_error, 'error');
            }
            
            return false;
        }
    }
    
    /**
     * Verify that demo users can authenticate with their passwords
     */
    protected function verifyDemoCredentials()
    {
        $demo_roles = self::DEMO_ROLES;
        
        foreach ($demo_roles as $role) {
            $username = 'demo_' . $role;
            $password = 'demo1234';
            
            if (username_exists($username)) {
                // Use a safer method to verify credentials without triggering authentication
                $user = get_user_by('login', $username);
                if ($user && wp_check_password($password, $user->user_pass, $user->ID)) {
                    $this->log("✓ {$username} password verification successful", 'success');
                } else {
                    $this->log("✗ {$username} password verification failed", 'error');
                }
            } else {
                $this->log("✗ {$username} does not exist", 'warning');
            }
        }
    }
}
