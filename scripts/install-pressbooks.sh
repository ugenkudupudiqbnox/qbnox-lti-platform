#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

PB_CONTAINER=$(docker ps --filter "name=pressbooks" --format "{{.ID}}")

if [ -z "$PB_CONTAINER" ]; then
  echo "❌ Pressbooks container not running"
  exit 1
fi

echo "📚 Installing WordPress multisite + Pressbooks"

docker exec "$PB_CONTAINER" bash -c "
set -e

cd /var/www/html

# Wait for WordPress files (max 5 minutes)
echo '⏳ Waiting for WordPress to be installed...'
TIMEOUT=300
ELAPSED=0
until wp core is-installed --allow-root >/dev/null 2>&1; do
  if [ \$ELAPSED -ge \$TIMEOUT ]; then
    echo '❌ Timeout waiting for WordPress installation'
    exit 1
  fi
  echo \"  Still waiting... (\${ELAPSED}s/\${TIMEOUT}s)\"
  sleep 10
  ELAPSED=\$((ELAPSED + 10))
done
echo '✅ WordPress is installed'

# Install multisite if not already installed
echo '🔧 Checking WordPress multisite...'
if ! wp site list --allow-root >/dev/null 2>&1; then
  echo '⏳ Converting to multisite...'
  wp core multisite-install \
    --url=\"${PRESSBOOKS_URL}\" \
    --title='Pressbooks LTI Platform' \
    --admin_user=admin \
    --admin_password=admin123 \
    --admin_email=admin@example.com \
    --allow-root
  echo '✅ Multisite installed'
else
  echo '✅ Multisite already configured'
fi

# Install Pressbooks plugin if missing
echo '📦 Checking Pressbooks plugin...'
if ! wp plugin is-installed pressbooks --allow-root; then
  echo '⏳ Downloading and installing Pressbooks (this may take 1-2 minutes)...'
  wp plugin install pressbooks --activate-network --allow-root
  echo '✅ Pressbooks installed and activated'
else
  echo '✅ Pressbooks already installed'
fi

# Install H5P for interactive content
echo '📦 Installing H5P plugin for interactive content...'
if ! wp plugin is-installed h5p --allow-root; then
  wp plugin install h5p --activate-network --allow-root
  echo '✅ H5P plugin installed and activated'
else
  echo '✅ H5P plugin already installed'
fi

# Configure .htaccess for Bedrock multisite (subdirectory)
echo '🔧 Configuring .htaccess for Bedrock multisite...'
cat > /var/www/html/web/.htaccess << 'HTACCESS_EOF'
# BEGIN WordPress Multisite
# Using subdirectory network type: https://wordpress.org/support/article/htaccess/

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]

# add a trailing slash to /wp-admin
RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ \$1wp-admin/ [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) wp/\$2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ wp/\$2 [L]
RewriteRule . index.php [L]
</IfModule>

# END WordPress Multisite
HTACCESS_EOF

if [ -f /var/www/html/web/.htaccess ]; then
  echo '✅ .htaccess configured for Bedrock multisite'
else
  echo '❌ Failed to create .htaccess'
  exit 1
fi

# Set WP_ENV to development
echo '🔧 Configuring WP_ENV to development...'
if [ -f /var/www/html/.env ]; then
  sed -i \"s/WP_ENV='production'/WP_ENV='development'/\" /var/www/html/.env
  sed -i \"s/WP_ENV='staging'/WP_ENV='development'/\" /var/www/html/.env
  echo '✅ WP_ENV set to development'
else
  echo '⚠️  .env file not found, skipping WP_ENV configuration'
fi

# Fix file permissions for WordPress/Bedrock
echo '🔧 Setting correct file permissions...'
chown -R www-data:www-data /var/www/html/web/app/
find /var/www/html/web/app -type d -exec chmod 755 {} \\;
find /var/www/html/web/app -type f -exec chmod 644 {} \\;
echo '✅ File permissions set correctly'

echo '✅ Pressbooks installed and active'
"

