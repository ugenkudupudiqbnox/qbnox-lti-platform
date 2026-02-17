#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "🔗 Testing Deep Linking response"

# Fetch registered client ID from Pressbooks
CLIENT_ID=$(sudo docker exec pressbooks mysql -h mysql -u root -proot --skip-ssl pressbooks -se "SELECT client_id FROM wp_lti_platforms LIMIT 1" || echo "ci-test-client")
echo "Using Client ID: $CLIENT_ID"

# Simulate LMS deep linking request (Step 1: Get selection UI)
# Then Step 2: Post selection to get JWT
# Use HTTP directly to port 8081 to bypass nginx HTTPS issues in local dev
RESPONSE=$(curl -s -X POST \
  -H "Host: pb.lti.qbnox.com" \
  "http://127.0.0.1:8081/wp-json/pb-lti/v1/deep-link" \
  -d "deep_link_return_url=https://example.com/return" \
  -d "client_id=$CLIENT_ID" \
  -d "selected_book_id=2" \
  -d "selected_title=LTI Test Book" \
  -d "selected_url=${PRESSBOOKS_URL}/test-book")

# Extract JWT from the auto-post form
JWT=$(echo "$RESPONSE" | sed -n 's/.*name="JWT" value="\([^"]*\)".*/\1/p')

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

if [ -n "$GITHUB_ENV" ]; then
  echo "JWT=$JWT" >> "$GITHUB_ENV"
fi

# Save to local file for shell-based scripts that might be run in the same step
echo "$JWT" > "$(dirname "$0")/.jwt_output"

echo "✅ Deep Linking JWT structure valid"

