<?php
defined('ABSPATH') || exit;

function pb_lti_schema_sql() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    return [
        "platforms" => "
        CREATE TABLE {$wpdb->prefix}pb_lti_platforms (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            issuer VARCHAR(255) NOT NULL UNIQUE,
            client_id VARCHAR(255) NOT NULL,
            auth_login_url TEXT NOT NULL,
            jwks_url TEXT NOT NULL,
            token_url TEXT NOT NULL,
            created_at DATETIME NOT NULL
        ) $charset;",

        "deployments" => "
        CREATE TABLE {$wpdb->prefix}pb_lti_deployments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            platform_issuer VARCHAR(255) NOT NULL,
            deployment_id VARCHAR(255) NOT NULL,
            UNIQUE(platform_issuer, deployment_id)
        ) $charset;",

        "nonces" => "
        CREATE TABLE {$wpdb->prefix}pb_lti_nonces (
            nonce VARCHAR(255) PRIMARY KEY,
            expires_at DATETIME NOT NULL
        ) $charset;",

        "lineitems" => "
        CREATE TABLE {$wpdb->prefix}pb_lti_lineitems (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            context_id VARCHAR(255) NOT NULL,
            lineitem_url TEXT NOT NULL
        ) $charset;"
    ];
}
