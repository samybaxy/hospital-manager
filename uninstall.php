<?php
/**
 * Runs when the plugin is deleted from Plugins > Installed Plugins.
 *
 * Hospital records are kept unless an administrator opted in beforehand:
 *
 *   wp option update hospital_manager_delete_data_on_uninstall 1
 *
 * (Deactivating the plugin never deletes anything.)
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!get_option('hospital_manager_delete_data_on_uninstall')) {
    return;
}

require_once __DIR__ . '/database/migrations/create_hospital_tables.php';
CreateHospitalTables::down();

foreach (['doctor', 'patient', 'receptionist', 'lab_tech', 'hospital_nurse', 'hospital_staff',
          'pharmacy_staff', 'inventory_manager'] as $role) {
    remove_role($role);
}

global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'hospital\\_manager\\_%'
              OR option_name LIKE '\\_transient\\_hospital\\_manager\\_%'
              OR option_name LIKE '\\_transient\\_timeout\\_hospital\\_manager\\_%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'hospital\\_manager\\_%'");
