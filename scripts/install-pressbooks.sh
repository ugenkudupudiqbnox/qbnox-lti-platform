#!/usr/bin/env bash
set -Eeuo pipefail

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

COMPOSE_FILE="lti-local-lab/docker-compose.yml"
DB_CONTAINER="mysql"
WP_CONTAINER="pressbooks"

WP_HOME="${PRESSBOOKS_URL}"
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
  DC="$SUDO_DOCKER docker-compose -f $COMPOSE_FILE"
else
  DC="$SUDO_DOCKER docker compose -f $COMPOSE_FILE"
fi

echo "🚀 Starting Pressbooks (Bedrock-aligned) setup"

retry 3 $DC up -d --build

echo "⏳ Waiting for MySQL..."
retry 15 $DC exec -T "$DB_CONTAINER" mysqladmin ping -h localhost --silent

echo "⏳ Waiting for WordPress..."
retry 15 $DC exec -T "$WP_CONTAINER" wp --info --url="$WP_HOME" --allow-root >/dev/null

echo "📦 Verifying WordPress Multisite..."
if ! $DC exec -T "$WP_CONTAINER" wp core is-installed --url="$WP_HOME" --allow-root >/dev/null 2>&1; then
  echo "⚠️ WordPress not installed, but it should have been by the entrypoint. Retrying core install..."
  retry 5 $DC exec -T "$WP_CONTAINER" wp core multisite-install \
    --url="$WP_HOME" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --allow-root
fi

echo "🔌 Ensuring Pressbooks is network-activated..."
retry 5 $DC exec -T "$WP_CONTAINER" wp plugin activate pressbooks --network --url="$WP_HOME" --allow-root

echo "✅ Pressbooks setup complete"
