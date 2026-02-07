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

# Wait for WordPress files
until wp core is-installed --allow-root >/dev/null 2>&1; do
  sleep 5
done

# Install multisite if not already installed
if ! wp site list --allow-root >/dev/null 2>&1; then
  wp core multisite-install \
    --url=\"${PRESSBOOKS_URL}\" \
    --title='Pressbooks LTI Platform' \
    --admin_user=admin \
    --admin_password=admin123 \
    --admin_email=admin@example.com \
    --allow-root
fi

# Install Pressbooks plugin if missing
if ! wp plugin is-installed pressbooks --allow-root; then
  wp plugin install pressbooks --activate-network --allow-root
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

echo '✅ Pressbooks installed and active'
"

