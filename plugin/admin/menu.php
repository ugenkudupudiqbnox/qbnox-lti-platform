<?php
defined('ABSPATH') || exit;

add_action('network_admin_menu', function () {
    add_menu_page('LTI Platforms','LTI Platforms','manage_network','pb-lti-platforms','pb_lti_platforms');
    add_submenu_page('pb-lti-platforms','Deployments','Deployments','manage_network','pb-lti-deployments','pb_lti_deployments');
    add_submenu_page('pb-lti-platforms','Line Items','Line Items','manage_network','pb-lti-lineitems','pb_lti_lineitems');
});

function pb_lti_platforms() {
    echo '<h1>LTI Platforms</h1><p>Platform registry managed here.</p>';
}

function pb_lti_deployments() {
    echo '<h1>LTI Deployments</h1><p>Deployment IDs registry.</p>';
}

function pb_lti_lineitems() {
    echo '<h1>LTI Line Items</h1><p>Stored AGS line items.</p>';
}
