<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));

if ($roleid) {
    echo "Ensuring editingteacher (ID: $roleid) has LTI capabilities...\n";
    $capabilities = [
        'mod/lti:addinstance',
        'mod/lti:view',
        'mod/lti:addcoursetool',
        'mod/lti:addpreconfiguredinstance'
    ];
    
    foreach ($capabilities as $cap) {
        assign_capability($cap, CAP_ALLOW, $roleid, context_system::instance()->id, true);
        echo "✓ Allowed $cap\n";
    }
} else {
    echo "❌ Role editingteacher not found!\n";
}
