<?php

namespace HospitalManager\Database\Seeders;

use HospitalManager\Services\RoleService;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return bool
     */
    public function run()
    {
        $this->log("Initializing roles and permissions...");
        
        try {
            // Initialize all roles using the RoleService
            $this->log("Initializing roles with RoleService::initializeRoles()...");
            RoleService::initializeRoles();
            
            $this->log("Roles initialized successfully", 'success');
            $this->log("Created roles: ", 'success');
            $roles = RoleService::getAvailableRoles();
            
            foreach ($roles as $role => $display_name) {
                $this->log(" - $display_name ($role)", 'success');
            }
            
            // Create example users for each role
            $this->log("Creating example users for each role...");
            $this->createExampleUsers();
            
            return true;
        } catch (\Throwable $e) {
            $this->log("Error: " . $e->getMessage());
            $this->log("File: " . $e->getFile() . ":" . $e->getLine());
            $this->log("Trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Create example users for each role
     */
    protected function createExampleUsers()
    {
        // Get all available roles
        $all_roles = RoleService::getAvailableRoles();
        
        // Add WordPress default roles
        $roles = [
            'administrator' => 'Admin',
        ];
        
        // Add hospital roles
        foreach ($all_roles as $role_key => $role_name) {
            $prefix = explode(' ', $role_name)[0]; // Use first word of role name as prefix
            $roles[$role_key] = $prefix;
        }
        
        $this->log("Creating example users for each role...");
        
        foreach ($roles as $role => $prefix) {
            // Skip administrator since it likely already exists
            if ($role == 'administrator' && username_exists('admin')) {
                continue;
            }
            
            // Use a different naming pattern to avoid conflicts with UserSeeder
            $username = 'example_' . strtolower($prefix);
            $email = 'example_' . strtolower($prefix) . '@hospital.local';
            $password = 'password123';
            
            $meta = [
                'first_name' => $prefix,
                'last_name' => 'Example',
                'description' => "Example user for {$role} role"
            ];
            
            // Only create if user doesn't exist
            if (!username_exists($username)) {
                $user_id = $this->createUserSafely($username, $password, $email, $role, $meta);
                
                if ($user_id) {
                    $this->log(" - Created example user: $username with role: $role", 'success');
                }
            } else {
                $this->log(" - User $username already exists, skipping", 'warning');
            }
        }
    }
}
