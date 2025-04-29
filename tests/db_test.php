<?php
// A simple script to test database connection and table creation

// Bootstrap WordPress
$wp_load_file = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';
require_once $wp_load_file;
require_once $wp_load_file;

// Check WordPress connection
echo "WordPress connection: " . (is_object($GLOBALS['wpdb']) ? "OK\n" : "FAILED\n");

global $wpdb;
$wpdb->show_errors();

// Check if audit logs table exists
$table_name = $wpdb->prefix . 'hm_audit_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
echo "Audit logs table exists: " . ($table_exists ? "YES\n" : "NO\n");

if (!$table_exists) {
    echo "Creating audit logs table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hm_audit_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        action varchar(50) NOT NULL,
        entity_type varchar(50) NOT NULL,
        entity_id bigint(20) NOT NULL,
        changes JSON,
        details JSON,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY entity_type_id (entity_type, entity_id)
    ) " . $wpdb->get_charset_collate() . ";";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    echo "Table creation result: " . ($table_exists ? "SUCCESS\n" : "FAILED\n");
}

// Try to insert a test record
$test_data = [
    'user_id' => 1,
    'action' => 'test_action',
    'entity_type' => 'test_entity',
    'entity_id' => 1,
    'details' => json_encode(['test' => 'data']),
    'changes' => json_encode(['field' => 'value']),
    'created_at' => current_time('mysql')
];

echo "Attempting to insert test record...\n";
$result = $wpdb->insert(
    $table_name,
    $test_data,
    ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
);

if ($result === false) {
    echo "Insert failed: " . $wpdb->last_error . "\n";
} else {
    $id = $wpdb->insert_id;
    echo "Insert successful, ID: " . $id . "\n";
    
    // Verify we can retrieve it
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
    echo "Record retrieval: " . ($record ? "SUCCESS\n" : "FAILED\n");
    if ($record) {
        echo "Record details:\n";
        print_r($record);
    }
}

// Describe the table structure
$structure = $wpdb->get_results("DESCRIBE {$table_name}");
echo "Table structure:\n";
print_r($structure);
