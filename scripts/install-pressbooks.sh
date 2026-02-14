#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

PB_CONTAINER=$(docker ps --filter "name=pressbooks" --format "{{.ID}}")

if [ -z "$PB_CONTAINER" ]; then
  echo "❌ Pressbooks container not running"
  exit 1
fi

# Wait for MySQL container to be healthy
echo "⏳ Waiting for MySQL to be healthy..."
TIMEOUT=120
ELAPSED=0
until docker ps --filter "name=mysql" --format "{{.Status}}" | grep -q "healthy"; do
  if [ $ELAPSED -ge $TIMEOUT ]; then
    echo "❌ Timeout waiting for MySQL container to be healthy"
    echo "📊 MySQL container status:"
    docker ps --filter "name=mysql" --format "table {{.Names}}\t{{.Status}}"
    exit 1
  fi
  echo "  ⏳ MySQL not healthy yet... (${ELAPSED}s/${TIMEOUT}s)"
  sleep 5
  ELAPSED=$((ELAPSED + 5))
done
echo "✅ MySQL container is healthy"

echo "📚 Installing WordPress multisite + Pressbooks"

docker exec "$PB_CONTAINER" bash -c "
set -e

cd /var/www/html

# Install WP-CLI if not present
if ! command -v wp &> /dev/null; then
  echo '📥 Installing WP-CLI...'
  curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod +x wp-cli.phar
  mv wp-cli.phar /usr/local/bin/wp
  echo '✅ WP-CLI installed'
fi

# Install MySQL client and unzip if not present
if ! command -v mysql &> /dev/null || ! command -v unzip &> /dev/null; then
  echo '📥 Installing required packages...'
  apt-get update -qq >/dev/null 2>&1
  apt-get install -y -qq default-mysql-client unzip curl >/dev/null 2>&1
  echo '✅ Required packages installed'
fi

# Wait for MySQL and create database if needed
echo '🔧 Checking database...'
DB_NAME=\${WORDPRESS_DB_NAME:-wordpress}
DB_USER=\${WORDPRESS_DB_USER:-root}
DB_PASS=\${WORDPRESS_DB_PASSWORD:-root}
DB_HOST=\${WORDPRESS_DB_HOST:-mysql}

echo \"📊 Database config: \$DB_USER@\$DB_HOST/\$DB_NAME\"

# Check if MySQL host is reachable
echo '🔍 Checking MySQL connectivity...'
if ! ping -c 1 \$DB_HOST >/dev/null 2>&1 && ! nc -z \$DB_HOST 3306 >/dev/null 2>&1; then
  echo \"⚠️  Warning: Cannot reach MySQL host '\$DB_HOST' - but will keep trying...\"
fi

# Wait for MySQL to be ready (max 2 minutes)
# Note: Using --skip-ssl for development environment (self-signed certs)
TIMEOUT=120
ELAPSED=0
until mysql -h\$DB_HOST -u\$DB_USER -p\$DB_PASS --skip-ssl -e 'SELECT 1' >/dev/null 2>&1; do
  if [ \$ELAPSED -ge \$TIMEOUT ]; then
    echo '❌ Timeout waiting for MySQL to be ready'
    echo '🔍 Troubleshooting:'
    echo \"  - Host: \$DB_HOST\"
    echo \"  - User: \$DB_USER\"
    echo \"  - Testing connection...\"
    mysql -h\$DB_HOST -u\$DB_USER -p\$DB_PASS --skip-ssl -e 'SELECT 1' 2>&1 || true
    exit 1
  fi
  echo \"  ⏳ Waiting for MySQL to be ready... (\${ELAPSED}s/\${TIMEOUT}s)\"
  sleep 5
  ELAPSED=\$((ELAPSED + 5))
done
echo '✅ MySQL is ready'

# Create database if it doesn't exist
if ! mysql -h\$DB_HOST -u\$DB_USER -p\$DB_PASS --skip-ssl -e \"USE \$DB_NAME\" >/dev/null 2>&1; then
  echo \"📦 Creating database '\$DB_NAME'...\"
  mysql -h\$DB_HOST -u\$DB_USER -p\$DB_PASS --skip-ssl -e \"CREATE DATABASE IF NOT EXISTS \$DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"
  echo '✅ Database created'
else
  echo '✅ Database already exists'
fi

# Check if WordPress is already installed
echo '🔧 Checking WordPress installation...'
if wp core is-installed --allow-root >/dev/null 2>&1; then
  echo '✅ WordPress is already installed'
else
  echo '⏳ Installing WordPress core...'
  # Download WordPress if not present
  if [ ! -f wp-config.php ]; then
    echo '📥 Downloading WordPress...'
    wp core download --allow-root --force
  fi

  # Create wp-config.php if missing
  if [ ! -f wp-config.php ]; then
    echo '⚙️  Creating wp-config.php...'
    wp config create \
      --dbname=\${WORDPRESS_DB_NAME:-wordpress} \
      --dbuser=\${WORDPRESS_DB_USER:-root} \
      --dbpass=\${WORDPRESS_DB_PASSWORD:-root} \
      --dbhost=\${WORDPRESS_DB_HOST:-mysql} \
      --allow-root
  fi

  # Install WordPress
  echo '🚀 Installing WordPress...'
  wp core install \
    --url=\"${PRESSBOOKS_URL}\" \
    --title='Pressbooks LTI Platform' \
    --admin_user=admin \
    --admin_password=admin123 \
    --admin_email=admin@example.com \
    --skip-email \
    --allow-root

  echo '✅ WordPress installed successfully'
fi

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

# Install Pressbooks from GitHub
echo '📦 Installing Pressbooks plugin...'
if ! wp plugin is-installed pressbooks --allow-root 2>/dev/null; then
  echo '⏳ Downloading Pressbooks from GitHub (this may take 1-2 minutes)...'

  # Install Pressbooks from GitHub releases
  # Latest stable: https://github.com/pressbooks/pressbooks/releases
  cd /var/www/html/wp-content/plugins
  curl -L -o pressbooks.zip https://github.com/pressbooks/pressbooks/releases/download/6.32.0/pressbooks-6.32.0.zip
  unzip -q pressbooks.zip
  rm pressbooks.zip
  chown -R www-data:www-data pressbooks

  # Activate network-wide
  wp plugin activate pressbooks --network --allow-root
  echo '✅ Pressbooks installed and activated'
else
  echo '✅ Pressbooks already installed'
  # Make sure it's activated network-wide
  if ! wp plugin is-active-for-network pressbooks --allow-root 2>/dev/null; then
    wp plugin activate pressbooks --network --allow-root
    echo '✅ Pressbooks activated network-wide'
  fi
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

