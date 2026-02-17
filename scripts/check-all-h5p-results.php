<?php
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '%h5p_results'");
foreach ($tables as $table) {
    if (strpos($table, 'lti_') !== false) continue; // Skip our own audit or something
    $res = $wpdb->get_results("SELECT * FROM $table");
    if (!empty($res)) {
        echo "Found in $table:\n";
        print_r($res);
    }
}
