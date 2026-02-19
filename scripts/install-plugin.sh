#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

if docker compose version &>/dev/null 2>&1; then
  DC="docker compose -f lti-local-lab/docker-compose.yml"
else
  DC="docker-compose -f lti-local-lab/docker-compose.yml"
fi

echo "📦 Activating Pressbooks LTI platform plugin"

# Activation is handled network-wide for multisite
sudo -E $DC exec -T pressbooks wp plugin activate qbnox-lti-platform --network --url="$PRESSBOOKS_URL" --allow-root

echo "🗄️ Running LTI database migrations"
sudo -E $DC exec -T pressbooks wp eval "require_once '/var/www/pressbooks/web/app/plugins/qbnox-lti-platform/db/schema.php'; require_once '/var/www/pressbooks/web/app/plugins/qbnox-lti-platform/db/migrate.php'; pb_lti_run_migrations();" --allow-root --url="$PRESSBOOKS_URL"

echo "🔑 Generating RSA keys"
sudo docker cp "$(dirname "$0")/generate-rsa-keys.php" pressbooks:/var/www/pressbooks/generate-rsa-keys.php
# Use full URL to avoid site not found error
sudo -E $DC exec -T pressbooks php /var/www/pressbooks/generate-rsa-keys.php
sudo -E $DC exec -T pressbooks rm /var/www/pressbooks/generate-rsa-keys.php

echo "🔒 Writing SSL reverse-proxy mu-plugin"
# This mu-plugin must be present for WordPress to function correctly behind
# an Nginx SSL reverse proxy (e.g. pb.lti.qbnox.com → Docker on HTTP).
# It does two things:
#   1. Sets $_SERVER['HTTPS']='on' from X-Forwarded-Proto so is_ssl() works.
#   2. Pre-defines FORCE_SSL_ADMIN=false (mu-plugins load before
#      wp_ssl_constants() in wp-settings.php) so WordPress never fires its own
#      HTTP→HTTPS admin redirect - which causes an infinite reauth=1 loop
#      because Apache always sees plain HTTP internally.
# NOTE: entrypoint.sh also writes this file on every container restart, but
# install-plugin.sh writes it here so the fix is applied immediately without
# requiring a full image rebuild.
sudo -E $DC exec -T pressbooks bash -c '
mkdir -p /var/www/pressbooks/web/app/mu-plugins
cat > /var/www/pressbooks/web/app/mu-plugins/00-ssl-proxy-fix.php <<'"'"'PHPEOF'"'"'
<?php
/**
 * SSL Reverse-Proxy Fix
 *
 * Loaded by WordPress BEFORE wp_ssl_constants() (wp-settings.php ~line 506).
 * Required when Apache runs on plain HTTP behind an Nginx SSL reverse proxy.
 */

// 1. Detect HTTPS from the reverse proxy header so is_ssl() works correctly.
if ( ( ! isset( $_SERVER["HTTPS"] ) || $_SERVER["HTTPS"] !== "on" ) &&
     isset( $_SERVER["HTTP_X_FORWARDED_PROTO"] ) &&
     $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https" ) {
    $_SERVER["HTTPS"]       = "on";
    $_SERVER["SERVER_PORT"] = "443";
}

// 2. Prevent WordPress from enforcing SSL via its own redirect.
//    Nginx already terminates SSL and redirects HTTP->HTTPS before traffic
//    reaches this container. If FORCE_SSL_ADMIN is true AND is_ssl() returns
//    false (e.g. when Nginx does not forward X-Forwarded-Proto), WordPress
//    redirects /wp-admin -> https://.../wp-admin but Apache still sees HTTP
//    so is_ssl() stays false -> infinite redirect loop with reauth=1.
if ( ! defined( "FORCE_SSL_ADMIN" ) ) {
    define( "FORCE_SSL_ADMIN", false );
}
PHPEOF
chown www-data:www-data /var/www/pressbooks/web/app/mu-plugins/00-ssl-proxy-fix.php
'

echo "✅ Plugin and database ready"
