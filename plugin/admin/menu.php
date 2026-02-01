<?php
defined('ABSPATH') || exit;

add_action('network_admin_menu', function(){
  add_menu_page('LTI Audit','LTI Audit','manage_network','pb-lti-audit','pb_lti_audit_page');
  add_menu_page('LTI Scopes','LTI Scopes','manage_network','pb-lti-scopes','pb_lti_scopes_page');
});

function pb_lti_audit_page() {
  global $wpdb;
  $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pb_lti_audit ORDER BY id DESC LIMIT 100");
  echo '<h1>LTI Audit Log</h1><table><tr><th>Event</th><th>Context</th><th>Time</th></tr>';
  foreach ($rows as $r) {
    echo "<tr><td>{$r->event}</td><td>{$r->context}</td><td>{$r->created_at}</td></tr>";
  }
  echo '</table>';
}

function pb_lti_scopes_page() {
  echo '<h1>AGS Scopes</h1><p>Scopes enforced per LineItem (future UI hooks).</p>';
}
