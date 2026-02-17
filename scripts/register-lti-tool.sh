#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "Auto-registering LTI 1.3 tool in Moodle"

# Copy the registration script into the container
sudo docker cp "$(dirname "$0")/moodle-register-tool.php" moodle:/var/www/html/moodle-register-tool.php

# Ensure Moodle is not in "upgrade needed" state
echo "🔄 Checking Moodle database status..."
sudo docker exec moodle php admin/cli/upgrade.php --non-interactive || true

# Allow Moodle to communicate with other local containers (Pressbooks)
echo "🔐 Unblocking local network requests in Moodle..."
sudo docker exec moodle php admin/cli/cfg.php --name=curlsecurityblockedhosts --set=""

# Run the registration script and capture output
OUTPUT=$(sudo docker exec moodle php /var/www/html/moodle-register-tool.php \
  --name='Pressbooks LTI Platform' \
  --baseurl="${PRESSBOOKS_URL}" \
  --initiate_login_url="${PRESSBOOKS_URL}/wp-json/pb-lti/v1/login" \
  --redirect_uri="${PRESSBOOKS_URL}/wp-json/pb-lti/v1/launch" \
  --jwks_url="${PRESSBOOKS_URL}/wp-json/pb-lti/v1/keyset" \
  --content_selection_url="${PRESSBOOKS_URL}/wp-json/pb-lti/v1/deep-link")

echo "$OUTPUT"

# Extract Client ID and Tool ID (Deployment ID)
CLIENT_ID=$(echo "$OUTPUT" | grep 'CLIENT_ID:' | cut -d' ' -f2)
TOOL_ID=$(echo "$OUTPUT" | grep 'Tool registered successfully with ID:' | awk '{print $NF}')

if [ -z "$CLIENT_ID" ]; then
  echo "❌ Error: Failed to extract Client ID from Moodle registration"
  exit 1
fi

if [ -z "$TOOL_ID" ]; then
  echo "⚠️ Warning: Failed to extract Tool ID, defaulting to deployment ID 1"
  TOOL_ID="1"
fi

# Register the Moodle platform in Pressbooks
echo "🛠 Registering Moodle platform in Pressbooks (Deployment: $TOOL_ID)"
sudo docker cp "$(dirname "$0")/pressbooks-register-platform.php" pressbooks:/var/www/pressbooks/pressbooks-register-platform.php
sudo docker exec pressbooks php /var/www/pressbooks/pressbooks-register-platform.php "${MOODLE_URL}" "${CLIENT_ID}" "${TOOL_ID}"
sudo docker exec pressbooks rm /var/www/pressbooks/pressbooks-register-platform.php

# Cleanup
sudo docker exec moodle rm /var/www/html/moodle-register-tool.php
