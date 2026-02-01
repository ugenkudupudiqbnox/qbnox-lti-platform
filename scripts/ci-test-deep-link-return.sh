
#!/usr/bin/env bash
set -e

PB_URL=${PRESSBOOKS_URL:-https://pressbooks.local}
RETURN_URL="https://example.com/deep-link-return"

RESPONSE=$(curl -sk -X POST "$PB_URL/wp-json/pb-lti/v1/deep-link"   -d "deep_link_return_url=$RETURN_URL"   -d "client_id=ci-test-client")

echo "$RESPONSE" | grep -q "$RETURN_URL" || {
  echo "❌ Deep Linking return URL missing"
  exit 1
}

echo "✅ Deep Linking return URL verified"
