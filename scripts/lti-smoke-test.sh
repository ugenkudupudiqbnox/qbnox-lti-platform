#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "🔍 Smoke testing endpoints"

curl -k ${MOODLE_URL} >/dev/null
curl -k ${PRESSBOOKS_URL} >/dev/null
curl -k ${PRESSBOOKS_URL}/wp-json/pb-lti/v1/keyset >/dev/null

echo "🎉 Smoke test passed"

