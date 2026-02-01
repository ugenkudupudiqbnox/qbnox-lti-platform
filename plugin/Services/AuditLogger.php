<?php
namespace PB_LTI\Services;

class AuditLogger {
  public static function log(string $event, array $context = []): void {
    global $wpdb;
    $wpdb->insert(
      $wpdb->prefix.'pb_lti_audit',
      [
        'event' => $event,
        'context' => wp_json_encode($context),
        'created_at' => current_time('mysql')
      ]
    );
  }
}
