#!/usr/bin/env bash
set -e

PRESSBOOKS_URL=${PRESSBOOKS_URL:-https://pressbooks.local}

echo "🔗 Testing Deep Linking response"

# Simulate LMS deep linking request
RESPONSE=$(curl -k -s -X POST \
  "$PRESSBOOKS_URL/wp-json/pb-lti/v1/deep-link" \
  -d "deep_link_return_url=https://example.com/return" \
  -d "client_id=ci-test-client")

# Extract JWT
JWT=$(echo "$RESPONSE" | sed -n 's/.*JWT=\([^&]*\).*/\1/p')

if [ -z "$JWT" ]; then
  echo "❌ No JWT returned from Deep Linking"
  exit 1
fi

# Decode payload (no verification, structure check only)
PAYLOAD=$(echo "$JWT" | cut -d '.' -f2 | base64 -d 2>/dev/null || true)

echo "$PAYLOAD" | grep -q "content_items" || {
  echo "❌ content_items missing in Deep Linking JWT"
  exit 1
}

echo "✅ Deep Linking JWT structure valid"

