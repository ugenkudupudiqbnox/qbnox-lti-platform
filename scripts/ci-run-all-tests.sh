#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "🧪 Running all CI integration tests"

# 🔗 Step 1: Deep Linking (extracts JWT)
echo "🔗 Running Deep Linking test..."
export JWT=$(bash scripts/ci-test-deep-linking.sh | grep -v "🔗" | grep -v "Using Client ID" | grep -v "✅" | grep -v "JWT=" | head -n 1 || echo "")
# Wait, the script above doesn't output the JWT cleanly.
# Let's modify ci-test-deep-linking.sh to just print the JWT if asked.

# Actually, let's stick to the current logic but make it more reliable.

echo "📊 Running AGS grade verification..."
bash scripts/ci-test-ags-grade.sh

# Re-run deep linking to get JWT explicitly
echo "🔑 Extracting JWT for crypto verification..."
# We reuse the curl logic here to be sure
CLIENT_ID=$(sudo docker exec pressbooks mysql -h mysql -u root -proot --skip-ssl pressbooks -se "SELECT client_id FROM wp_lti_platforms LIMIT 1" || echo "ci-test-client")
RESPONSE=$(curl -k -s -X POST \
  "$PRESSBOOKS_URL/wp-json/pb-lti/v1/deep-link" \
  -d "deep_link_return_url=https://example.com/return" \
  -d "client_id=$CLIENT_ID" \
  -d "selected_book_id=2" \
  -d "selected_title=LTI Test Book" \
  -d "selected_url=${PRESSBOOKS_URL}/test-book")
JWT=$(echo "$RESPONSE" | sed -n 's/.*name="JWT" value="\([^"]*\)".*/\1/p')

if [ -z "$JWT" ]; then
    echo "❌ Failed to extract JWT in run-all-tests"
    exit 1
fi

echo "🛡️ Verifying JWT cryptography..."
php scripts/ci-verify-jwt-crypto.php "$JWT"

echo "📑 Running differential and per-course tests..."
php scripts/ci-test-ags-differential.php
php scripts/ci-test-per-course-grades.php

echo "✅ All tests passed"
