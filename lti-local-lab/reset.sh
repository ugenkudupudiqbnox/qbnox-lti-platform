#!/usr/bin/env bash
set -e

# Load environment configuration to get SUDO_DOCKER
source "$(dirname "$0")/../scripts/load-env.sh"

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"

if command -v docker-compose >/dev/null 2>&1; then
  $SUDO_DOCKER docker-compose -f "$COMPOSE_FILE" down -v --remove-orphans
else
  $SUDO_DOCKER docker compose -f "$COMPOSE_FILE" down -v --remove-orphans
fi

# Clean up any CI artifacts or temporary test data on the host
echo "🧹 Cleaning up local artifacts..."
rm -rf "$(dirname "$0")/../ci-artifacts"
rm -f "$(dirname "$0")/../.lti-setup-complete"

