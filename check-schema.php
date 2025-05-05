<?php
// Load WordPress
define("WP_DEBUG", true);
require_once dirname(__FILE__, 4) . "/wp-load.php";
global $wpdb;
$table = $wpdb->prefix . "hm_patients";
$results = $wpdb->get_results("DESC $table");
print_r($results);

