#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "=== Enabling CORS for Moodle → Pressbooks Session Monitoring ==="
echo ""

# Check if nginx config exists
NGINX_CONFIG="/etc/nginx/sites-available/${MOODLE_DOMAIN}"

if [ ! -f "$NGINX_CONFIG" ]; then
    echo "❌ Nginx config not found: $NGINX_CONFIG"
    echo "Please configure CORS manually"
    exit 1
fi

echo "Found Nginx config: $NGINX_CONFIG"
echo ""

# Check if CORS is already configured
if grep -q "Access-Control-Allow-Origin" "$NGINX_CONFIG"; then
    echo "⚠️  CORS headers already present in config"
    echo "Skipping..."
    exit 0
fi

echo "Adding CORS configuration for /lib/ajax/service.php..."
echo ""

# Create CORS configuration
CORS_CONFIG=$(cat <<EOF
    # Allow Pressbooks to check Moodle session status
    location /lib/ajax/service.php {
        add_header 'Access-Control-Allow-Origin' '${PRESSBOOKS_URL}' always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type' always;

        if (\$request_method = 'OPTIONS') {
            return 204;
        }

        try_files \$uri =404;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
EOF
)

# Backup original config
cp "$NGINX_CONFIG" "${NGINX_CONFIG}.backup-$(date +%Y%m%d-%H%M%S)"
echo "✅ Backed up original config"

# Add CORS config before the first location block
sudo sed -i "/location \/ {/i\\
$CORS_CONFIG" "$NGINX_CONFIG"

echo "✅ Added CORS configuration"
echo ""

# Test nginx config
echo "Testing Nginx configuration..."
if sudo nginx -t; then
    echo "✅ Nginx config valid"
    echo ""

    echo "Reloading Nginx..."
    sudo systemctl reload nginx
    echo "✅ Nginx reloaded"
    echo ""

    echo "========================================="
    echo "CORS enabled successfully!"
    echo ""
    echo "Pressbooks can now check Moodle session status"
    echo "Session monitoring will work automatically"
    echo ""
    echo "Test it:"
    echo "1. Launch Pressbooks from Moodle"
    echo "2. Log out of Moodle in another tab"
    echo "3. Pressbooks should auto-logout within 60 seconds"
    echo "========================================="
else
    echo "❌ Nginx config test failed"
    echo "Restoring backup..."
    sudo cp "${NGINX_CONFIG}.backup-$(date +%Y%m%d-%H%M%S)" "$NGINX_CONFIG"
    echo "Config restored, no changes made"
    exit 1
fi
