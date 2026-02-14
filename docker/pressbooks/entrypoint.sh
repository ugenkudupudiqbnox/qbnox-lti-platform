#!/usr/bin/env bash
set -e

APP_ROOT="/var/www/pressbooks"
WP_PATH="${APP_ROOT}/web/wp"

# Wait for DB
until mysqladmin ping -h"$DB_HOST" --skip-ssl --silent 2>/dev/null; do
  echo "Waiting for MySQL..."
  sleep 3
done

# Clone Pressbooks Bedrock if missing (volume might be empty on first run)
if [ ! -f "$APP_ROOT/composer.json" ]; then
  echo "Pressbooks Bedrock not found, cloning..."
  git clone https://github.com/pressbooks/pressbooksoss-bedrock.git "$APP_ROOT"
  cd "$APP_ROOT"
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  chown -R www-data:www-data "$APP_ROOT"
else
  cd "$APP_ROOT"
fi

# Initialize .env if missing
if [ ! -f .env ]; then
  echo "Initializing .env"
  wp dotenv init --allow-root || true
  wp dotenv salts generate --allow-root || true

  wp dotenv set DB_NAME "$DB_NAME" --allow-root
  wp dotenv set DB_USER "$DB_USER" --allow-root
  wp dotenv set DB_PASSWORD "$DB_PASSWORD" --allow-root
  wp dotenv set DB_HOST "$DB_HOST" --allow-root

  wp dotenv set WP_HOME "$WP_HOME" --allow-root
  wp dotenv set WP_SITEURL "\${WP_HOME}/wp" --allow-root
  wp dotenv set MULTISITE "true" --allow-root
  wp dotenv set SUBDOMAIN_INSTALL "false" --allow-root
  wp dotenv set DOMAIN_CURRENT_SITE "$DOMAIN_CURRENT_SITE" --allow-root
  wp dotenv set WP_ENV "development" --allow-root
fi

# Install multisite if needed
if ! wp core is-installed --path="$WP_PATH" --allow-root; then
  echo "Installing WordPress Multisite"
  wp core multisite-install \
    --path="$WP_PATH" \
    --url="$WP_HOME" \
    --title="Pressbooks Network" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --allow-root
fi

# Network activate Pressbooks
wp plugin activate pressbooks --network --path="$WP_PATH" --allow-root || true

# Network-enable all Pressbooks themes
echo "Enabling Pressbooks themes network-wide..."
for theme in pressbooks-aldine pressbooks-book pressbooks-clarke pressbooks-donham pressbooks-jacobs; do
  wp theme enable "$theme" --network --url="$WP_HOME" --path="$WP_PATH" --allow-root 2>/dev/null || true
done

# Install LTI plugin Composer dependencies
LTI_PLUGIN_PATH="${APP_ROOT}/web/app/plugins/pressbooks-lti-platform"
if [ -d "$LTI_PLUGIN_PATH" ] && [ -f "$LTI_PLUGIN_PATH/composer.json" ]; then
  if [ ! -d "$LTI_PLUGIN_PATH/vendor" ]; then
    echo "Installing LTI plugin dependencies..."
    cd "$LTI_PLUGIN_PATH"
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | grep -v "dubious ownership" || true
    cd "$APP_ROOT"
    echo "✓ LTI plugin dependencies installed"
  fi
fi

exec "$@"
