
#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "Auto-registering LTI 1.3 tool in Moodle"

MOODLE_CONTAINER=$(docker ps --filter "name=moodle" --format "{{.ID}}")

docker exec "$MOODLE_CONTAINER" bash -c "
php admin/tool/lti/cli/create_tool.php \
  --name='Pressbooks LTI Platform' \
  --baseurl='${PRESSBOOKS_URL}' \
  --initiate_login_url='${PRESSBOOKS_URL}/wp-json/pb-lti/v1/login' \
  --redirect_uri='${PRESSBOOKS_URL}/wp-json/pb-lti/v1/launch' \
  --jwks_url='${PRESSBOOKS_URL}/wp-json/pb-lti/v1/keyset'
"
