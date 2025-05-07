<?php

namespace HospitalManager\Database\Seeders;

class ResetSeeder extends Seeder
{
    public function run()
    {
        try {
            $this->log("Starting data reset...");
            
            // Truncate custom tables
            $this->truncateCustomTables();
            
            // Remove custom user roles and data
            $this->removeRolesAndUsers();
            
            $this->log("Data reset completed successfully.");
            return true;
        } catch (\Exception $e) {
            $this->log("Error during reset: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Truncate all custom plugin tables
     */
    private function truncateCustomTables()
    {
        try {
            global $wpdb;
            
            $tables = [
                $wpdb->prefix . 'hm_patients',
                $wpdb->prefix . 'hm_doctors',
                $wpdb->prefix . 'hm_appointments',
                $wpdb->prefix . 'hm_visitations',
                $wpdb->prefix . 'hm_lab_investigations',
                $wpdb->prefix . 'hm_medical_reports',
                $wpdb->prefix . 'hm_notifications',
                $wpdb->prefix . 'hm_chats',
                $wpdb->prefix . 'hm_audit_logs',
                $wpdb->prefix . 'hm_hmos',
            ];
            
            foreach ($tables as $table) {
                try {
                    // Check if table exists before truncating
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                    
                    if ($table_exists) {
                        $wpdb->query("TRUNCATE TABLE $table");
                        $this->log("Truncated table: $table");
                    } else {
                        $this->log("Table does not exist: $table");
                    }
                } catch (\Exception $e) {
                    $this->log("Error processing table $table: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->log("Error in truncateCustomTables: " . $e->getMessage());
        }
    }
    
    /**
     * Remove all users with plugin-specific roles and remove the roles
     */
    private function removeRolesAndUsers()
    {
        try {
            // Custom roles created by the plugin
            $roles = ['doctor', 'patient', 'lab_tech', 'desk_officer'];
            
            foreach ($roles as $role) {
                // Get users with this role
                $users = get_users(['role' => $role]);
                
                if (!is_array($users)) {
                    $this->log("Warning: get_users() did not return an array for role '$role'");
                    continue;
                }
                
                // Delete each user
                foreach ($users as $user) {
                    if (isset($user->ID)) {
                        $result = wp_delete_user($user->ID);
                        if ($result) {
                            $this->log("Deleted user: {$user->user_login} (ID: {$user->ID})");
                        } else {
                            $this->log("Failed to delete user: {$user->user_login} (ID: {$user->ID})");
                        }
                    }
                }
            }
            
            // Don't remove the roles themselves, as they might be needed for the application
            // Just log that we're keeping them
            $this->log("Custom roles were kept intact for application functionality.");
        } catch (\Exception $e) {
            $this->log("Error in removeRolesAndUsers: " . $e->getMessage());
        }
    }
}
