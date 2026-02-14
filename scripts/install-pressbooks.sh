#!/usr/bin/env bash
set -Eeuo pipefail

COMPOSE_FILE="lti-local-lab/docker-compose.yml"
DB_CONTAINER="mysql"
WP_CONTAINER="pressbooks"

WP_HOME="${WP_HOME:-http://localhost:8000}"
WP_TITLE="Pressbooks Network"
WP_ADMIN_USER="admin"
WP_ADMIN_PASSWORD="admin"
WP_ADMIN_EMAIL="admin@example.com"

retry() {
  local retries=$1; shift
  local count=0; local delay=3
  until "$@"; do
    count=$((count+1))
    if [ "$count" -ge "$retries" ]; then
      echo "❌ Failed after $count attempts"
      exit 1
    fi
    sleep "$delay"
    delay=$((delay*2))
  done
}

if command -v docker-compose >/dev/null 2>&1; then
  DC="docker-compose -f $COMPOSE_FILE"
else
  DC="docker compose -f $COMPOSE_FILE"
fi

echo "🚀 Starting Pressbooks (Bedrock-aligned) setup"

retry 3 $DC up -d --build

echo "⏳ Waiting for MySQL..."
retry 15 $DC exec -T "$DB_CONTAINER" mysqladmin ping -h localhost --silent

echo "⏳ Waiting for WordPress..."
retry 15 $DC exec -T "$WP_CONTAINER" wp --info >/dev/null

echo "🔐 Generating WordPress salts..."
retry 5 $DC exec -T "$WP_CONTAINER" wp config shuffle-salts --allow-root || true

echo "📦 Installing WordPress Multisite..."
if ! $DC exec -T "$WP_CONTAINER" wp core is-installed --allow-root >/dev/null 2>&1; then
  retry 5 $DC exec -T "$WP_CONTAINER" wp core multisite-install \
    --url="$WP_HOME" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --allow-root
fi

echo "📝 Writing Bedrock-compatible .htaccess..."

$DC exec -T "$WP_CONTAINER" bash -c "cat > /var/www/html/.htaccess" <<'EOF'
# BEGIN WordPress (Pressbooks / Bedrock)
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) wp/$2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ wp/$2 [L]
RewriteRule . index.php [L]
</IfModule>
# END WordPress
EOF

echo "🔌 Installing & Network-Activating Pressbooks..."

retry 5 $DC exec -T "$WP_CONTAINER" wp plugin install pressbooks --allow-root || true
retry 5 $DC exec -T "$WP_CONTAINER" wp plugin activate pressbooks --network --allow-root

echo "✅ Pressbooks setup complete"
