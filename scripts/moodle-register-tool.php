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

$options = getopt("", ["name:", "baseurl:", "initiate_login_url:", "redirect_uri:", "jwks_url:"]);

$name = $options['name'] ?? 'Pressbooks LTI Platform';
$baseurl = $options['baseurl'] ?? '';
$login_url = $options['initiate_login_url'] ?? '';
$redirect_uri = $options['redirect_uri'] ?? '';
$jwks_url = $options['jwks_url'] ?? '';

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
$type->coursevisible = 1;

$config = new stdClass();
$config->lti_typename = $name;
$config->lti_toolurl = $baseurl;
$config->lti_ltiversion = '1.3.0';
$config->lti_clientid = 'pressbooks_client_' . bin2hex(random_bytes(4));

$config->key_set_url = $jwks_url;
$config->initiate_login_url = $login_url;
$config->redirection_uris = $redirect_uri;
$config->token_url = str_replace('/keyset', '/token', $jwks_url);
$config->auth_request_url = str_replace('/keyset', '/auth', $jwks_url);

$typeid = lti_add_type($type, $config);

echo "✅ Tool registered successfully with ID: $typeid\n";
echo "🔑 Client ID: {$config->lti_clientid}\n";
