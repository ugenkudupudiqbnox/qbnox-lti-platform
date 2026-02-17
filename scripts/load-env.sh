#!/usr/bin/env bash
# Source this file in scripts that need domain configuration
# Usage: source "$(dirname "$0")/load-env.sh"

# Load .env file from project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [ -f "$PROJECT_ROOT/.env" ]; then
    set -a
    source "$PROJECT_ROOT/.env"
    set +a
fi

# Set defaults (and strip trailing slashes if present)
export MOODLE_DOMAIN=${MOODLE_DOMAIN:-moodle.local}
export MOODLE_DOMAIN=${MOODLE_DOMAIN%/}
export PRESSBOOKS_DOMAIN=${PRESSBOOKS_DOMAIN:-pressbooks.local}
export PRESSBOOKS_DOMAIN=${PRESSBOOKS_DOMAIN%/}
export MOODLE_VERSION=${MOODLE_VERSION:-4.4}

# Logic for PROTOCOL: Use http for local domains, otherwise default to https
if [[ "$MOODLE_DOMAIN" == "moodle.local" && "$PRESSBOOKS_DOMAIN" == "pressbooks.local" ]]; then
    export PROTOCOL="http"
else
    export PROTOCOL=${PROTOCOL:-https}
fi

# Construct full URLs
if [[ "$MOODLE_DOMAIN" == "moodle.local" && "$PRESSBOOKS_DOMAIN" == "pressbooks.local" ]]; then
    # With Nginx proxy on host, we use standard port 80/443
    export MOODLE_URL="${PROTOCOL}://${MOODLE_DOMAIN}"
    export PRESSBOOKS_URL="${PROTOCOL}://${PRESSBOOKS_DOMAIN}"
    export MOODLE_INTERNAL_DOMAIN="${MOODLE_DOMAIN}"
    export PRESSBOOKS_INTERNAL_DOMAIN="${PRESSBOOKS_DOMAIN}"
else
    export MOODLE_URL="${PROTOCOL}://${MOODLE_DOMAIN}"
    export PRESSBOOKS_URL="${PROTOCOL}://${PRESSBOOKS_DOMAIN}"
    export MOODLE_INTERNAL_DOMAIN="${MOODLE_DOMAIN}"
    export PRESSBOOKS_INTERNAL_DOMAIN="${PRESSBOOKS_DOMAIN}"
fi

echo $MOODLE_URL
echo $PRESSBOOKS_URL

# Check if sudo is needed for Docker
if command -v docker &> /dev/null && ! docker ps &> /dev/null; then
    export SUDO_DOCKER="sudo -E"
else
    export SUDO_DOCKER=""
fi
