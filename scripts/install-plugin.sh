#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

DC="docker compose -f lti-local-lab/docker-compose.yml"
if ! command -v docker-compose >/dev/null 2>&1; then
  DC="docker compose -f lti-local-lab/docker-compose.yml"
else
  DC="docker-compose -f lti-local-lab/docker-compose.yml"
fi

echo "📦 Activating Pressbooks LTI platform plugin"

# Activation is handled network-wide for multisite
sudo -E $DC exec -T pressbooks wp plugin activate pressbooks-lti-platform --network --url="$PRESSBOOKS_URL" --allow-root

echo "🗄️ Running LTI database migrations"
sudo -E $DC exec -T pressbooks wp eval "require_once '/var/www/pressbooks/web/app/plugins/pressbooks-lti-platform/db/schema.php'; require_once '/var/www/pressbooks/web/app/plugins/pressbooks-lti-platform/db/migrate.php'; pb_lti_run_migrations();" --allow-root --url="$PRESSBOOKS_URL"

echo "🔑 Generating RSA keys"
sudo docker cp "$(dirname "$0")/generate-rsa-keys.php" pressbooks:/var/www/pressbooks/generate-rsa-keys.php
# Use full URL to avoid site not found error
sudo -E $DC exec -T pressbooks php /var/www/pressbooks/generate-rsa-keys.php
sudo -E $DC exec -T pressbooks rm /var/www/pressbooks/generate-rsa-keys.php

echo "✅ Plugin and database ready"
