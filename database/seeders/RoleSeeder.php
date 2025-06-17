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
            
            // Don't create users here - let UserSeeder handle all user creation
            $this->log("RoleSeeder completed. UserSeeder will handle user creation.", 'info');
            
            return true;
        } catch (\Throwable $e) {
            $this->log("Error: " . $e->getMessage(), 'error');
            $this->log("File: " . $e->getFile() . ":" . $e->getLine(), 'error');
            return false;
        }
    }
}
