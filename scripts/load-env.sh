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
    echo "⚠️  Warning: .env file not found, using defaults"
    export MOODLE_DOMAIN=${MOODLE_DOMAIN:-moodle.local}
    export PRESSBOOKS_DOMAIN=${PRESSBOOKS_DOMAIN:-pressbooks.local}
    export PROTOCOL=${PROTOCOL:-https}
fi

# Construct full URLs
export MOODLE_URL="${PROTOCOL}://${MOODLE_DOMAIN}"
export PRESSBOOKS_URL="${PROTOCOL}://${PRESSBOOKS_DOMAIN}"
