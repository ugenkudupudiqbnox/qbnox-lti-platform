
#!/usr/bin/env bash
set -e
PB_URL=${PRESSBOOKS_URL:-https://pressbooks.local}
RESPONSE=$(curl -sk -X POST "$PB_URL/wp-json/pb-lti/v1/deep-link"   -d "deep_link_return_url=https://example.com/return"   -d "client_id=ci-test-client"   -d "multi=true")
JWT=$(echo "$RESPONSE" | sed -n 's/.*JWT=\([^&]*\).*/\1/p')
PAYLOAD=$(echo "$JWT" | cut -d. -f2 | base64 -d 2>/dev/null || true)
COUNT=$(echo "$PAYLOAD" | grep -o "ltiResourceLink" | wc -l)
[ "$COUNT" -ge 2 ] || exit 1
echo "Multiple content items OK"
