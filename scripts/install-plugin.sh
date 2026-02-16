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

echo "✅ Plugin activated"

