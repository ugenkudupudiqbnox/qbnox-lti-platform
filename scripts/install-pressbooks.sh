#!/usr/bin/env bash
set -Eeuo pipefail

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

COMPOSE_FILE="lti-local-lab/docker-compose.yml"
DB_CONTAINER="mysql"
WP_CONTAINER="pressbooks"

WP_HOME="${PRESSBOOKS_URL}"
WP_TITLE="Pressbooks Network"
WP_ADMIN_USER="${PB_ADMIN_USER}"
WP_ADMIN_PASSWORD="${PB_ADMIN_PASSWORD}"
WP_ADMIN_EMAIL="${PB_ADMIN_EMAIL}"

# Generate a random WordPress salt value
generate_salt() {
    openssl rand -base64 48 | tr -d "=+/\n" | cut -c1-64
}

# Ensure WordPress salts are present in .env; generate and append if missing
ensure_wp_salts() {
    local env_file="$PROJECT_ROOT/.env"
    local salt_keys=(AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT)
    local missing=false

    for key in "${salt_keys[@]}"; do
        if ! grep -q "^${key}=" "$env_file" 2>/dev/null; then
            missing=true
            break
        fi
    done

    if [ "$missing" = true ]; then
        echo "🔐 Generating WordPress security keys (salts)..."
        echo "" >> "$env_file"
        echo "### --- 🔒 WordPress Security Keys (auto-generated) ---" >> "$env_file"
        for key in "${salt_keys[@]}"; do
            if ! grep -q "^${key}=" "$env_file" 2>/dev/null; then
                echo "${key}='$(generate_salt)'" >> "$env_file"
            fi
        done
        echo "✅ WordPress security keys written to .env"
        # Re-export the newly generated values into the current shell
        set -a
        source "$env_file"
        set +a
    else
        echo "✅ WordPress security keys already present in .env"
    fi
}

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

ensure_wp_salts

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
