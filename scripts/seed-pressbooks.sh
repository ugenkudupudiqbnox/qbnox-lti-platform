#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "📘 Seeding Pressbooks with test book"

# Use docker compose v2 (plugin) preferentially over legacy v1
if docker compose version &>/dev/null 2>&1; then
    DC="docker compose -f lti-local-lab/docker-compose.yml"
else
    DC="docker-compose -f lti-local-lab/docker-compose.yml"
fi

# Create book site if none exists
if ! sudo -E $DC exec -T pressbooks wp site list --url="$PRESSBOOKS_URL" --allow-root | grep -q 'test-book'; then
    sudo -E $DC exec -T pressbooks wp site create \
        --slug=test-book \
        --title='LTI Test Book' \
        --email=admin@example.com \
        --url="$PRESSBOOKS_URL" \
        --allow-root
    
    # extract PB_DOMAIN and PB_PORT for URL construction
    PB_DOMAIN=$(echo "$PRESSBOOKS_URL" | sed -e 's|^[^/]*//||' -e 's|/.*$||')
    
    # Use search-replace to fix URL structures instead of direct SQL
    # This avoids TLS certificate issues with the raw mysql client in older PHP-based containers
    sudo -E $DC exec -T pressbooks wp search-replace "http://pressbooks.local" "$PRESSBOOKS_URL" --url="$PRESSBOOKS_URL" --allow-root --network || true
fi

# Get book URL
BOOK_URL="${PRESSBOOKS_URL}/test-book"

echo "📝 Creating test chapters in $BOOK_URL"

# Create some chapters
for i in {1..3}; do
  sudo -E $DC exec -T pressbooks wp post create \
    --post_type=chapter \
    --post_title="Chapter $i - LTI Content" \
    --post_content="This is chapter $i for LTI testing." \
    --post_status=publish \
    --url="$BOOK_URL" \
    --allow-root
done

# Final permission fix for all seeded content
echo "🔧 Fixing all permissions in uploads..."
sudo -E $DC exec -T pressbooks chown -R www-data:www-data /var/www/pressbooks/web/app/uploads

# H5P configuration - MUST BE ENABLED for all sites to support testing
echo "🛠️ Configuring H5P upload options for $BOOK_URL"
sudo -E $DC exec -T pressbooks wp option update h5p_upload_libraries 1 --url="$BOOK_URL" --allow-root
sudo -E $DC exec -T pressbooks wp option update h5p_hub_is_enabled 1 --url="$BOOK_URL" --allow-root
sudo -E $DC exec -T pressbooks wp option update h5p_track_user 1 --url="$BOOK_URL" --allow-root

# Run H5P library setup script to ensure H5P is ready
echo "📥 Syncing H5P libraries and importing artifacts..."
# Copy all H5P files from artifacts to container
sudo docker exec pressbooks mkdir -p /tmp/h5p_imports
sudo docker cp artifacts/h5p/. pressbooks:/tmp/h5p_imports/
sudo docker exec pressbooks chown -R www-data:www-data /tmp/h5p_imports

sudo docker cp "$(dirname "$0")/h5p-setup-libraries.php" pressbooks:/var/www/html/h5p-setup-libraries.php
sudo -E $DC exec -T pressbooks wp eval-file /var/www/html/h5p-setup-libraries.php --url="$BOOK_URL" --allow-root || echo "⚠️ Warning: H5P Hub sync failed (site may still need manual consent in UI)"

# cleanup
sudo docker exec pressbooks rm -rf /tmp/h5p_imports

echo "✅ Pressbooks book & chapters created"
