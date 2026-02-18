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

# Get the Main Body part ID (Pressbooks creates this automatically on site creation)
PART_ID=$(sudo -E $DC exec -T pressbooks wp post list \
  --post_type=part --fields=ID --format=csv --url="$BOOK_URL" --allow-root 2>/dev/null \
  | tail -1 | tr -d '[:space:]')

# Create chapters assigned to Main Body part so they appear in the book
for i in {1..3}; do
  sudo -E $DC exec -T pressbooks wp post create \
    --post_type=chapter \
    --post_title="Chapter $i - LTI Content" \
    --post_content="This is chapter $i for LTI testing." \
    --post_status=publish \
    --post_parent="$PART_ID" \
    --url="$BOOK_URL" \
    --allow-root
done

# Final permission fix for all seeded content
echo "🔧 Fixing all permissions in uploads..."
sudo -E $DC exec -T pressbooks chown -R www-data:www-data /var/www/pressbooks/web/app/uploads

echo "✅ Pressbooks book & chapters created"
