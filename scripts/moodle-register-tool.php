#!/usr/bin/env php
<?php
/**
 * CLI script to register an LTI 1.3 tool in Moodle
 * This script is intended to be run inside the Moodle container
 */

define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/mod/lti/lib.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

$options = getopt("", ["name:", "baseurl:", "initiate_login_url:", "redirect_uri:", "jwks_url:", "content_selection_url:"]);

$name = $options['name'] ?? 'Pressbooks LTI Platform';
$baseurl = $options['baseurl'] ?? '';
$login_url = $options['initiate_login_url'] ?? '';
$redirect_uri = $options['redirect_uri'] ?? '';
$jwks_url = $options['jwks_url'] ?? '';
$content_selection_url = $options['content_selection_url'] ?? $baseurl;

if (empty($baseurl)) {
    echo "❌ Error: --baseurl is required\n";
    exit(1);
}

echo "🛠 Registering LTI 1.3 tool: $name\n";

// Set up admin user for the session
$USER = $DB->get_record('user', ['username' => 'admin']);

// Check if tool already exists
if ($oldtool = $DB->get_record('lti_types', ['name' => $name])) {
    echo "ℹ️ Tool '$name' already registered. Deleting old one to ensure fresh config...\n";
    lti_delete_type($oldtool->id);
}

$type = new stdClass();
$type->state = LTI_TOOL_STATE_CONFIGURED;
$type->course = 1; // Site tool
$type->coursevisible = 2; // LTI_COURSE_VISIBLE_ACTIVITYCHOOSER (2)

$config = new stdClass();
$config->lti_typename = $name;
$config->lti_coursevisible = 2; // Also set in config for good measure
$config->lti_toolurl = $baseurl;
$config->lti_ltiversion = '1.3.0';
$config->lti_contentitem = 1; // Enable Deep Linking
$config->lti_toolurl_ContentItemSelectionRequest = $content_selection_url;
$config->lti_description = 'Pressbooks Content Picker';
$client_id = 'pressbooks_client_dev'; // Use stable client ID for development
$config->lti_clientid = $client_id;

// Enable Advantage services
// NOTE: ltiservice_* keys are stored as-is by lti_add_type (not stripped like lti_* keys)
// Moodle's gradebookservices checks for 'ltiservice_gradesynchronization' in typeconfig
$config->ltiservice_gradesynchronization = 2; // 2=FULL: read + write scores (required for AGS JWT claim)
$config->lti_acceptgrades = 2; // Delegate: no default grade column; lineItem in Deep Linking response creates one selectively
$config->lti_sendname = 1; // Delegate to teacher
$config->lti_sendemailaddr = 1; // Delegate to teacher
$config->lti_launch_presentation_document_target = 'iframe';

$config->key_set_url = $jwks_url;
$config->initiate_login_url = $login_url;
$config->redirection_uris = $redirect_uri;

// Determine version specific constants
$moodle_version = (float) $CFG->release;
echo "Moodle Version Detected: $moodle_version (Release: $CFG->release)\n";

// In Moodle 4.3+, these strings are preferred. In older versions, integers are safer.
if ($moodle_version >= 4.3) {
    $config->lti_keytype = 'JWK_KEYSET';
    $config->lti_publickeyset = $jwks_url;
} else {
    $config->lti_keytype = 2; // Keyset URL in older Moodle
    $config->lti_pubkey2 = $jwks_url;
}

$config->lti_pubkey = ''; // Clear RSA key if any
$config->lti_initiatelogin = $login_url;
$config->lti_redirectionuris = $redirect_uri;
$config->lti_coursevisible = 2; 

$config->key_set_url = $jwks_url;
$config->initiate_login_url = $login_url;
$config->redirection_uris = $redirect_uri;
$config->keytype = ($moodle_version >= 4.3) ? 'JWK_KEYSET' : 2;
$config->publickeyset = $jwks_url;

$config->token_url = str_replace('/keyset', '/token', $jwks_url);
$config->auth_request_url = str_replace('/keyset', '/auth', $jwks_url);

$typeid = lti_add_type($type, $config);

echo "✅ Tool registered successfully with ID: $typeid\n";
echo "CLIENT_ID: $client_id\n";
