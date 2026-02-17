<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');

echo "=== Enabling Deep Linking Capability ===\n\n";

// Get the tool
$tool = $DB->get_record('lti_types', ['name' => 'Pressbooks LTI Platform']);
if (!$tool) {
    echo "✗ Tool not found\n";
    exit(1);
}

echo "✓ Found tool (ID: {$tool->id})\n";

// Parse existing capabilities
$capabilities = json_decode($tool->enabledcapability, true);
if (!$capabilities) {
    $capabilities = [];
}

echo "Current capabilities: " . json_encode($capabilities) . "\n\n";

// Add Deep Linking capabilities
$pressbooks_url = getenv('PRESSBOOKS_URL') ?: 'https://pb.lti.qbnox.com';
$capabilities['LtiDeepLinkingRequest'] = $pressbooks_url . '/wp-json/pb-lti/v1/deep-link';
$capabilities['ContentItemSelectionRequest'] = $pressbooks_url . '/wp-json/pb-lti/v1/deep-link';

// Enable content item message type
$tool->lti_contentitem = 1;

// Update the tool
$tool->enabledcapability = json_encode($capabilities);
$DB->update_record('lti_types', $tool);

echo "✓ Deep Linking capabilities added:\n";
echo "  - LtiDeepLinkingRequest: {$capabilities['LtiDeepLinkingRequest']}\n";
echo "  - ContentItemSelectionRequest: {$capabilities['ContentItemSelectionRequest']}\n";
echo "  - lti_contentitem: {$tool->lti_contentitem}\n\n";

echo "✓ Tool configuration updated!\n";
echo "\n=== Verification ===\n";
$updated = $DB->get_record('lti_types', ['id' => $tool->id]);
$caps = json_decode($updated->enabledcapability, true);
echo "Capabilities stored:\n";
print_r($caps);
