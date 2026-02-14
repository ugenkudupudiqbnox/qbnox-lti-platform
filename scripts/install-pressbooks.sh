#!/usr/bin/env bash
set -Eeuo pipefail

#############################################
# Logging helpers
#############################################
log()  { echo -e "🔹 $*"; }
ok()   { echo -e "✅ $*"; }
warn() { echo -e "⚠️  $*"; }
err()  { echo -e "❌ $*" >&2; }

#############################################
# Retry helper with exponential backoff
#############################################
retry() {
  local retries=$1
  shift
  local count=0
  local delay=3

  until "$@"; do
    exit_code=$?
    count=$((count + 1))

    if [ "$count" -ge "$retries" ]; then
      err "Command failed after $count attempts."
      return "$exit_code"
    fi

    warn "Retry $count/$retries failed. Retrying in ${delay}s..."
    sleep "$delay"
    delay=$((delay * 2))
  done
}

#############################################
# Detect docker compose command
#############################################
if command -v docker-compose >/dev/null 2>&1; then
  DC="docker-compose"
else
  DC="docker compose"
fi

#############################################
# Defaults (CI safe)
#############################################
DB_CONTAINER="${DB_CONTAINER:-mysql}"
WP_CONTAINER="${WP_CONTAINER:-wordpress}"

DB_NAME="${DB_NAME:-pressbooks}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-root}"
DB_HOST="${DB_HOST:-mysql}"

WP_HOME="${WP_HOME:-http://localhost:8000}"
WP_SITEURL="${WP_SITEURL:-http://localhost:8000/wp}"
WP_TITLE="${WP_TITLE:-Pressbooks}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

log "Starting hardened Pressbooks installation"

#############################################
# Ensure containers are running
#############################################
retry 10 $DC ps >/dev/null

#############################################
# Wait for MySQL container to exist
#############################################
log "Waiting for MySQL container..."
retry 15 $DC exec -T "$DB_CONTAINER" mysqladmin ping -h"localhost" --silent
ok "MySQL is healthy"

#############################################
# Wait for WordPress container readiness
#############################################
log "Waiting for WordPress container..."
retry 15 $DC exec -T "$WP_CONTAINER" wp --info >/dev/null
ok "WordPress container ready"

#############################################
# Generate .env safely
#############################################
log "Creating .env configuration"

cat > .env <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_HOST=${DB_HOST}

WP_HOME=${WP_HOME}
WP_SITEURL=${WP_SITEURL}
EOF

ok ".env created"

#############################################
# Verify DB connectivity from WP container
#############################################
log "Verifying DB connectivity from WordPress container"

retry 10 $DC exec -T "$WP_CONTAINER" wp db check --allow-root

ok "Database connectivity verified"

#############################################
# Install WordPress (idempotent)
#############################################
if ! $DC exec -T "$WP_CONTAINER" wp core is-installed --allow-root >/dev/null 2>&1; then
  log "Installing WordPress core..."

  retry 5 $DC exec -T "$WP_CONTAINER" wp core install \
    --url="${WP_HOME}" \
    --title="${WP_TITLE}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email \
    --allow-root

  ok "WordPress installed"
else
  ok "WordPress already installed"
fi

#############################################
# Enable Multisite (required for Pressbooks)
#############################################
if ! $DC exec -T "$WP_CONTAINER" wp core is-installed --network --allow-root >/dev/null 2>&1; then
  log "Enabling Multisite..."

  retry 5 $DC exec -T "$WP_CONTAINER" wp core multisite-convert --allow-root

  ok "Multisite enabled"
else
  ok "Multisite already enabled"
fi

#############################################
# Install & Activate Pressbooks
#############################################
log "Installing Pressbooks plugin"

retry 5 $DC exec -T "$WP_CONTAINER" wp plugin install pressbooks --activate --allow-root || true

ok "Pressbooks installed & activated"

#############################################
# Final Health Check
#############################################
log "Running final health checks"

retry 5 $DC exec -T "$WP_CONTAINER" wp core version --allow-root >/dev/null

ok "Pressbooks platform ready 🎉"

echo ""
echo "========================================="
echo "🚀 Pressbooks Installation Complete"
echo "URL: ${WP_HOME}"
echo "Admin: ${WP_ADMIN_USER}"
echo "========================================="
