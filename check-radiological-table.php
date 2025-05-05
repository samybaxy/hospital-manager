<?php
define("WP_DEBUG", true);
require_once dirname(__FILE__, 4) . "/wp-load.php";
global $wpdb;
$table = $wpdb->prefix . "hm_radiological_exams";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE \"$table\"") === $table;
echo "Table exists: " . ($table_exists ? "YES" : "NO") . "\n";
if ($table_exists) {
    $results = $wpdb->get_results("DESC $table");
    print_r($results);
}

