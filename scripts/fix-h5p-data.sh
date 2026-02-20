#!/usr/bin/env bash
# Pressbooks LTI Platform - Fix H5P Permissions and Folders
set -euo pipefail

source "$(dirname "$0")/load-env.sh"

COMPOSE_FILE="lti-local-lab/docker-compose.yml"
if docker compose version >/dev/null 2>&1; then
  DC="sudo docker compose -f $COMPOSE_FILE"
elif command -v docker-compose >/dev/null 2>&1; then
  DC="sudo docker-compose -f $COMPOSE_FILE"
fi

echo "🔧 Ensuring H5P data folders exist and have correct permissions..."

# Blogs to fix (Site 1 and Site 2)
BLOGS=("" "/sites/2")

for BLOG in "${BLOGS[@]}"; do
    PATH_ROOT="/var/www/pressbooks/web/app/uploads${BLOG}/h5p"
    echo "Creating $PATH_ROOT ..."
    $DC exec -T pressbooks mkdir -p "$PATH_ROOT/temp" "$PATH_ROOT/libraries" "$PATH_ROOT/content" "$PATH_ROOT/exports"
done

echo "🔧 Fixing ownership to www-data..."
$DC exec -T pressbooks chown -R www-data:www-data /var/www/pressbooks/web/app/uploads

echo "🔧 Setting folder permissions to 775..."
$DC exec -T pressbooks find /var/www/pressbooks/web/app/uploads -type d -exec chmod 775 {} +

echo "✅ H5P permissions and folders fixed."
