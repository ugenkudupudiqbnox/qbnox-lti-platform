<?php
defined('ABSPATH') || exit;

function pb_lti_schema_sql() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    return [
        "platforms" => "
        CREATE TABLE {$wpdb->base_prefix}lti_platforms (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            issuer VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            auth_login_url TEXT NOT NULL,
            key_set_url TEXT NOT NULL,
            token_url TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY issuer (issuer)
        ) $charset;",

        "deployments" => "
        CREATE TABLE {$wpdb->base_prefix}lti_deployments (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            platform_issuer VARCHAR(255) NOT NULL,
            deployment_id VARCHAR(255) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY issuer_deployment (platform_issuer, deployment_id)
        ) $charset;",

        "lineitems" => "
        CREATE TABLE {$wpdb->prefix}lti_lineitems (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            context_id VARCHAR(255) NOT NULL,
            lineitem_url TEXT NOT NULL,
            scopes TEXT NOT NULL,
            PRIMARY KEY  (id)
        ) $charset;",

        "keys" => "
        CREATE TABLE {$wpdb->base_prefix}lti_keys (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            kid VARCHAR(255) NOT NULL,
            private_key TEXT NOT NULL,
            public_key TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY kid (kid)
        ) $charset;",

        "nonces" => "
        CREATE TABLE {$wpdb->base_prefix}lti_nonces (
            nonce VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY  (nonce)
        ) $charset;",

        "audit" => "
        CREATE TABLE {$wpdb->prefix}lti_audit (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            event VARCHAR(255) NOT NULL,
            context TEXT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) $charset;"
    ];
}
