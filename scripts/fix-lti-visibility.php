<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$pluginname = 'lti';
$pluginman = \core_plugin_manager::instance();
$enabled = $pluginman->get_plugins_of_type('mod');

if (isset($enabled[$pluginname])) {
    $status = $enabled[$pluginname]->get_status();
    echo "Module 'lti' status: $status\n";
    
    // Check if it's disabled in settings
    $disabled = get_config('mod_lti', 'disabled');
    echo "mod_lti 'disabled' setting: " . ($disabled ? 'Yes' : 'No') . "\n";
    
    // Force enable if it's somehow marked disabled
    set_config('disabled', 0, 'mod_lti');
    
    // Check global mod visibility
    $mod = $DB->get_record('modules', array('name' => 'lti'));
    if ($mod) {
        if ($mod->visible == 0) {
            echo "Module was hidden. Enabling...\n";
            $DB->set_field('modules', 'visible', 1, array('id' => $mod->id));
        } else {
            echo "Module is already visible in the database.\n";
        }
    } else {
        echo "❌ Module 'lti' not found in modules table!\n";
    }
} else {
    echo "❌ Module 'lti' not found in plugin manager!\n";
}
