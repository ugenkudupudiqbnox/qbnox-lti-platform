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
  cat > .env <<EOF
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
DB_HOST=$DB_HOST
WP_HOME=$WP_HOME
WP_SITEURL=\${WP_HOME}/wp
WP_ENV=development
EOF
fi

# Ensure basic vars are set/updated in .env
echo "Updating .env from environment variables..."
wp dotenv set DB_NAME "$DB_NAME" --allow-root || true
wp dotenv set DB_USER "$DB_USER" --allow-root || true
wp dotenv set DB_PASSWORD "$DB_PASSWORD" --allow-root || true
wp dotenv set DB_HOST "$DB_HOST" --allow-root || true
wp dotenv set WP_HOME "$WP_HOME" --allow-root || true

# Install multisite if needed
if ! mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "SHOW TABLES LIKE 'wp_users';" 2>/dev/null | grep -q 'wp_users'; then
  echo "Installing WordPress Multisite"
  # Temporarily disable MULTISITE in case it's in .env to allow installer to run
  sed -i 's/^MULTISITE=true/MULTISITE=false/' .env || true

  wp core multisite-install \
    --url="$WP_HOME" \
    --title="Pressbooks Network" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --allow-root
fi

# Ensure multisite is enabled in .env
echo "Configuring Multisite environment variables..."
wp dotenv set MULTISITE true --allow-root
wp dotenv set WP_ALLOW_MULTISITE true --allow-root
wp dotenv set SUBDOMAIN_INSTALL false --allow-root
wp dotenv set DOMAIN_CURRENT_SITE "$DOMAIN_CURRENT_SITE" --allow-root
wp dotenv set PATH_CURRENT_SITE / --allow-root
wp dotenv set SITE_ID_CURRENT_SITE 1 --allow-root
wp dotenv set BLOG_ID_CURRENT_SITE 1 --allow-root

# Network activate Pressbooks
wp plugin activate pressbooks --network --url="$WP_HOME" --path="$WP_PATH" --allow-root || true

# Network-enable all Pressbooks themes
echo "Enabling Pressbooks themes network-wide..."
for theme in pressbooks-aldine pressbooks-book pressbooks-clarke pressbooks-donham pressbooks-jacobs; do
  wp theme enable "$theme" --network --url="$WP_HOME" --path="$WP_PATH" --allow-root 2>/dev/null || true
done

# Ensure .htaccess exists for Bedrock
if [ ! -f web/.htaccess ]; then
  echo "Creating .htaccess..."
  cat > web/.htaccess <<EOF
# BEGIN WordPress (Pressbooks / Bedrock)
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ \$1wp-admin/ [R=301,L]
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) wp/\$2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ wp/\$2 [L]
RewriteRule . index.php [L]
</IfModule>
# END WordPress
EOF
  chown www-data:www-data web/.htaccess
fi

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

echo "Creating installation marker..."
touch /var/www/pressbooks/.installation_complete
ls -la /var/www/pressbooks/.installation_complete

exec "$@"
