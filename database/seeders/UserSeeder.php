<?php

namespace HospitalManager\Database\Seeders;

class UserSeeder extends Seeder
{
    protected $counts = [
        'doctor' => 10,
        'patient' => 50,
        'lab_tech' => 5,
        'desk_officer' => 3,
        'administrator' => 2,
    ];
    
    public function run()
    {
        foreach ($this->counts as $role => $count) {
            $created = 0;
            
            $this->log("Creating {$count} users with role '{$role}'");
            
            for ($i = 0; $i < $count; $i++) {
                $user_id = $this->createUser($role);
                if ($user_id) {
                    $created++;
                }
            }
            
            $this->log("Created {$created} users with role '{$role}'");
        }
        
        // Create one demo user for each role with known credentials
        $demo_roles = ['administrator', 'doctor', 'patient', 'lab_tech', 'desk_officer'];
        
        foreach ($demo_roles as $role) {
            $username = 'demo_' . $role;
            $email = $role . '@example.com';
            $password = 'demo123';
            
            // Check if user exists
            if (!username_exists($username) && !email_exists($email)) {
                $user_id = wp_create_user($username, $password, $email);
                
                if (!is_wp_error($user_id)) {
                    $user = new \WP_User($user_id);
                    $user->set_role($role);
                    
                    // Set first and last name
                    $first_name = ucfirst($role);
                    $last_name = 'User';
                    
                    update_user_meta($user_id, 'first_name', $first_name);
                    update_user_meta($user_id, 'last_name', $last_name);
                    
                    $this->log("Created demo {$role} user with username '{$username}' and password '{$password}'");
                }
            } else {
                $this->log("Demo {$role} user already exists");
            }
        }
    }
}
