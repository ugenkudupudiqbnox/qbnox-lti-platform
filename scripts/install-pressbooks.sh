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
# Defaults (CI-safe)
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
# Retry helper with backoff
#############################################
retry() {
  local retries=$1; shift
  local n=0
  local delay=3

  until "$@"; do
    n=$((n + 1))
    if [ "$n" -ge "$retries" ]; then
      err "Command failed after $n attempts"
      return 1
    fi
    warn "Retry $n/$retries failed — waiting ${delay}s"
    sleep "$delay"
    delay=$((delay * 2))
  done
}

#############################################
# Docker Compose file
#############################################
if command -v docker-compose &>/dev/null; then
  DC="docker-compose"
else
  DC="docker compose"
fi

COMPOSE_FILE="lti-local-lab/docker-compose.yml"
if [ ! -f "$COMPOSE_FILE" ]; then
  err "Compose file not found at $COMPOSE_FILE"
  exit 1
fi

DC="$DC -f $COMPOSE_FILE"

#############################################
# Start containers
#############################################
log "Bringing services up..."
retry 3 $DC up -d --build
ok "Containers started"

#############################################
# Wait for WordPress container
#############################################
log "Waiting for WordPress to be running..."
retry 15 bash -c "$DC ps --filter status=running --services | grep -q '^${WP_CONTAINER}\$'"

ok "WordPress container is running"

#############################################
# Wait for MySQL to be reachable
#############################################
log "Waiting for MySQL ping..."
retry 15 $DC exec -T "$DB_CONTAINER" mysqladmin ping -h "localhost" --silent

ok "MySQL is healthy"

#############################################
# Ensure WP CLI ready
#############################################
log "Waiting for WP-CLI readiness..."
retry 15 $DC exec -T "$WP_CONTAINER" wp --info >/dev/null

ok "WP-CLI ready"

#############################################
# Create .env
#############################################
log "Writing .env"
cat > .env <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_HOST=${DB_HOST}

WP_HOME=${WP_HOME}
WP_SITEURL=${WP_SITEURL}
EOF

ok ".env written"

#############################################
# Install WordPress if needed
#############################################
if ! $DC exec -T "$WP_CONTAINER" wp core is-installed --allow-root >/dev/null 2>&1; then
  log "Installing WordPress core"
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
# Enable Multisite for Pressbooks
#############################################
if ! $DC exec -T "$WP_CONTAINER" wp core is-installed --network --allow-root >/dev/null 2>&1; then
  log "Enabling WordPress multisite"
  retry 5 $DC exec -T "$WP_CONTAINER" wp core multisite-convert --allow-root
  ok "Multisite enabled"
else
  ok "Multisite already enabled"
fi

#############################################
# Install & activate Pressbooks
#############################################
log "Installing Pressbooks plugin"
retry 5 $DC exec -T "$WP_CONTAINER" wp plugin install pressbooks --activate --allow-root
ok "Pressbooks installed & activated"

#############################################
# Completion
#############################################
ok "Pressbooks platform setup complete!"
echo ""
echo "URL: $WP_HOME"
echo "Admin: $WP_ADMIN_USER"
