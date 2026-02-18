#!/usr/bin/env bash
# Source this file in scripts that need domain configuration
# Usage: source "$(dirname "$0")/load-env.sh"

# Load .env file from project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [ -f "$PROJECT_ROOT/.env" ]; then
    set -a
    source "$PROJECT_ROOT/.env"
    set +a
else
	echo ".env missing"
	exit 1
fi

# Set defaults (and strip trailing slashes if present)
export MOODLE_DOMAIN=${MOODLE_DOMAIN:-moodle.local}
export MOODLE_DOMAIN=${MOODLE_DOMAIN%/}
export PRESSBOOKS_DOMAIN=${PRESSBOOKS_DOMAIN:-pressbooks.local}
export PRESSBOOKS_DOMAIN=${PRESSBOOKS_DOMAIN%/}
export MOODLE_VERSION=${MOODLE_VERSION:-4.4}
export PROTOCOL=${PROTOCOL:-http}
export PB_ADMIN_USER=${PB_ADMIN_USER:-admin}
export PB_ADMIN_PASSWORD=${PB_ADMIN_PASSWORD:-admin}
export PB_ADMIN_EMAIL=${PB_ADMIN_EMAIL:-admin@example.com}
export MOODLE_ADMIN_USER=${MOODLE_ADMIN_USER:-admin}
export MOODLE_ADMIN_PASSWORD=${MOODLE_ADMIN_PASSWORD:-Moodle123!}
export MOODLE_ADMIN_EMAIL=${MOODLE_ADMIN_EMAIL:-admin@example.com}

# With Nginx proxy on host, we use standard port 80/443
export MOODLE_URL="${PROTOCOL}://${MOODLE_DOMAIN}"
export PRESSBOOKS_URL="${PROTOCOL}://${PRESSBOOKS_DOMAIN}"
export MOODLE_INTERNAL_DOMAIN="${MOODLE_DOMAIN}"
export PRESSBOOKS_INTERNAL_DOMAIN="${PRESSBOOKS_DOMAIN}"

# Check if sudo is needed for Docker
if command -v docker &> /dev/null && ! docker ps &> /dev/null; then
    export SUDO_DOCKER="sudo -E"
else
    export SUDO_DOCKER=""
fi
