<?php

class AddAppointmentAvailabilityToDoctors
{
    public static function up()
    {
        global $wpdb;
        
        // Add appointment_availability column to doctors table
        $table_name = $wpdb->prefix . 'hm_doctors';
        
        // Check if the column already exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'appointment_availability'");
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `appointment_availability` JSON AFTER `license_number`");
            
            // Log success message
            error_log('Added appointment_availability column to hm_doctors table');
        } else {
            error_log('Column appointment_availability already exists in hm_doctors table');
        }
    }

    public static function down()
    {
        global $wpdb;
        
        // Remove appointment_availability column from doctors table
        $table_name = $wpdb->prefix . 'hm_doctors';
        
        // Check if the column exists before trying to drop it
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'appointment_availability'");
        
        if (!empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN `appointment_availability`");
            
            // Log success message
            error_log('Removed appointment_availability column from hm_doctors table');
        } else {
            error_log('Column appointment_availability does not exist in hm_doctors table');
        }
    }
}
