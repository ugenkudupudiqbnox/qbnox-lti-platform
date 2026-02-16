#!/usr/bin/env bash
set -e

# Load environment configuration to get SUDO_DOCKER
source "$(dirname "$0")/../scripts/load-env.sh"

if command -v docker-compose >/dev/null 2>&1; then
  $SUDO_DOCKER docker-compose down -v
else
  $SUDO_DOCKER docker compose down -v
fi

