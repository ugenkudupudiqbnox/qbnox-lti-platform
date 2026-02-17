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
# We use simple sed here instead of wp-cli to avoid loading WordPress before it is installed or when tables are missing
echo "Updating .env from environment variables..."
function update_env_var() {
  local key=$1
  local value=$2
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    echo "${key}=${value}" >> .env
  fi
}

update_env_var DB_NAME "$DB_NAME"
update_env_var DB_USER "$DB_USER"
update_env_var DB_PASSWORD "$DB_PASSWORD"
update_env_var DB_HOST "$DB_HOST"

# Strip trailing slashes from domain variables
WP_HOME=${WP_HOME%/}
DOMAIN_CURRENT_SITE=${DOMAIN_CURRENT_SITE%/}

update_env_var WP_HOME "$WP_HOME"

# Install multisite if needed
if ! mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "SHOW TABLES LIKE 'wp_users';" 2>/dev/null | grep -q 'wp_users'; then
  echo "Installing WordPress Multisite"
  
  # CRITICAL: Prevent Bedrock from loading multisite logic during installation
  # We must unset these in the environment AND ensure they are false in .env
  # Bedrock's env() helper will pick up container env variables if they exist
  OLD_MS=$MULTISITE
  OLD_DCS=$DOMAIN_CURRENT_SITE
  unset MULTISITE
  unset DOMAIN_CURRENT_SITE
  unset WP_ALLOW_MULTISITE
  
  update_env_var MULTISITE false
  update_env_var WP_ALLOW_MULTISITE false

  # Run installation using a URL override to ensure the correct site is created
  # We use --skip-plugins and --skip-themes to minimize bootstrap overhead.
  # We also explicitly set the --url to WP_HOME to ensure the first site has the correct domain
  wp core multisite-install \
    --url="$WP_HOME" \
    --title="Pressbooks Network" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --skip-plugins \
    --allow-root

  # Restore variables for subsequent logic
  export MULTISITE=$OLD_MS
  export DOMAIN_CURRENT_SITE=$OLD_DCS
fi

# Ensure multisite is disabled during domain migration to hide multisite logic from WP-CLI bootstrap
# We'll re-enable it AFTER we ensure the database is synchronized with the current environment domain.
OLD_MS=$MULTISITE
OLD_DCS=$DOMAIN_CURRENT_SITE
unset MULTISITE
unset DOMAIN_CURRENT_SITE
update_env_var MULTISITE false
export MULTISITE=false

# If the database exists but the domain has changed, we must update the database to match DOMAIN_CURRENT_SITE
# This avoids the "Site not found" error when switching from localhost to a production domain
if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "SHOW TABLES LIKE 'wp_site';" 2>/dev/null | grep -q 'wp_site'; then
  CURRENT_DB_DOMAIN=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -N -s -e "SELECT domain FROM wp_site WHERE id=1;")
  if [ -n "$OLD_DCS" ] && [ "$CURRENT_DB_DOMAIN" != "$OLD_DCS" ]; then
    echo "🌍 Domain change detected ($CURRENT_DB_DOMAIN -> $OLD_DCS). Updating database..."
    
    # Extract protocol from WP_HOME or default to https
    PROTO="https"
    if [[ "$WP_HOME" == http://* ]]; then PROTO="http"; fi
    
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "UPDATE wp_site SET domain='$OLD_DCS' WHERE id=1;"
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "UPDATE wp_blogs SET domain='$OLD_DCS' WHERE blog_id=1;"
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "UPDATE wp_options SET option_value='$WP_HOME' WHERE option_name IN ('siteurl', 'home');"
    # For Bedrock /subdirectory installs
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "UPDATE wp_sitemeta SET meta_value='$WP_HOME' WHERE meta_key='siteurl';"
    # Clear caches that might hold the old domain
    echo "🧹 Wiping cached object data..."
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=DISABLED "$DB_NAME" -e "DELETE FROM wp_options WHERE option_name LIKE '_transient_%';"
  fi
fi

# Ensure multisite is enabled in .env
echo "🚀 Configuring Multisite environment variables..."
update_env_var MULTISITE true
update_env_var WP_ALLOW_MULTISITE true
update_env_var SUBDOMAIN_INSTALL false
update_env_var DOMAIN_CURRENT_SITE "$OLD_DCS"
update_env_var PATH_CURRENT_SITE /
update_env_var SITE_ID_CURRENT_SITE 1
update_env_var BLOG_ID_CURRENT_SITE 1

# Export it so subsequent wp commands in this session use multisite mode
export MULTISITE=true
export DOMAIN_CURRENT_SITE=$OLD_DCS

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
