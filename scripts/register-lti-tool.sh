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

# Run the registration script
sudo docker exec moodle php /var/www/html/moodle-register-tool.php \
  --name='Pressbooks LTI Platform' \
  --baseurl='${PRESSBOOKS_URL}' \
  --initiate_login_url='${PRESSBOOKS_URL}/wp-json/pb-lti/v1/login' \
  --redirect_uri='${PRESSBOOKS_URL}/wp-json/pb-lti/v1/launch' \
  --jwks_url='${PRESSBOOKS_URL}/wp-json/pb-lti/v1/keyset'

# Cleanup
sudo docker exec moodle rm /var/www/html/moodle-register-tool.php
