#!/usr/bin/env php
<?php
/**
 * Enable Deep Linking capability in Moodle tool configuration
 * This allows the "Select Content" button to appear in activity creation
 */

define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->libdir.'/adminlib.php');

echo "🔧 Configuring Moodle tool for Deep Linking\n";
echo "==========================================\n\n";

// Get the Pressbooks LTI tool
$tool = $DB->get_record('lti_types', ['name' => 'Pressbooks LTI Platform']);

if (!$tool) {
    echo "❌ Error: Pressbooks LTI Platform tool not found\n";
    echo "   Run: make enable-lti\n";
    exit(1);
}

echo "📋 Current tool configuration:\n";
echo "   Tool ID: {$tool->id}\n";
echo "   Name: {$tool->name}\n";
echo "   Base URL: {$tool->baseurl}\n\n";

// Enable Deep Linking capability
$capabilities = json_decode($tool->enabledcapability, true);
if (!$capabilities) {
    $capabilities = [];
}

// Add Deep Linking endpoints
$deep_link_url = 'https://pb.lti.qbnox.com/wp-json/pb-lti/v1/deep-link';

$capabilities['ContentItemSelectionRequest.url'] = $deep_link_url;
$capabilities['LtiDeepLinkingRequest.url'] = $deep_link_url;

// Update tool configuration
$tool->enabledcapability = json_encode($capabilities);
$tool->lti_contentitem = 1; // Enable content item (required for "Select Content" button)

$DB->update_record('lti_types', $tool);

echo "✅ Deep Linking enabled!\n\n";
echo "📝 Updated capabilities:\n";
echo json_encode($capabilities, JSON_PRETTY_PRINT) . "\n\n";

echo "✅ Content item enabled: {$tool->lti_contentitem}\n\n";

echo "🎉 Configuration complete!\n\n";
echo "📖 Next Steps:\n";
echo "   1. Go to your Moodle course: https://moodle.lti.qbnox.com/course/view.php?id=2\n";
echo "   2. Turn editing on\n";
echo "   3. Add activity → External Tool\n";
echo "   4. Select 'Pressbooks LTI Platform'\n";
echo "   5. Look for 'Select Content' button\n";
echo "   6. Click it to open content picker!\n";
