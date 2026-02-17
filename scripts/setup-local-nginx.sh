#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

echo "🔧 Configuring Nginx for local development (${PROTOCOL}://port 80)"

# Function to check if a domain is already configured in Nginx
is_domain_configured() {
    local domain=$1
    if grep -rql "server_name.*${domain}" /etc/nginx/sites-enabled/ &>/dev/null; then
        return 0
    fi
    return 1
}

# Installation check
if ! command -v nginx &> /dev/null; then
    echo "📦 Nginx not found. Installing..."
    apt-get update && apt-get install -y nginx
fi

# Moodle Configuration
MOODLE_CONF="/etc/nginx/sites-available/${MOODLE_DOMAIN}"
if is_domain_configured "${MOODLE_DOMAIN}"; then
    echo "✅ Nginx is already configured for ${MOODLE_DOMAIN}. Skipping..."
else
    echo "📝 Creating Nginx config for ${MOODLE_DOMAIN}..."
    cat > "$MOODLE_CONF" <<EOF
server {
    listen 80;
    server_name ${MOODLE_DOMAIN};

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        
        # Increase timeouts for long-running Moodle tasks
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }
}
EOF
    ln -sf "$MOODLE_CONF" /etc/nginx/sites-enabled/
    echo "✅ Enabled ${MOODLE_DOMAIN}"
fi

# Pressbooks Configuration
PRESSBOOKS_CONF="/etc/nginx/sites-available/${PRESSBOOKS_DOMAIN}"
if is_domain_configured "${PRESSBOOKS_DOMAIN}"; then
    echo "✅ Nginx is already configured for ${PRESSBOOKS_DOMAIN}. Skipping..."
else
    echo "📝 Creating Nginx config for ${PRESSBOOKS_DOMAIN}..."
    cat > "$PRESSBOOKS_CONF" <<EOF
server {
    listen 80;
    server_name ${PRESSBOOKS_DOMAIN};

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Host \$host;
        
        # Max upload size for Pressbooks imports
        client_max_body_size 128M;

        # Increase timeouts
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }
}
EOF
    ln -sf "$PRESSBOOKS_CONF" /etc/nginx/sites-enabled/
    echo "✅ Enabled ${PRESSBOOKS_DOMAIN}"
fi

# Remove default if it exists
if [ -L /etc/nginx/sites-enabled/default ] || [ -f /etc/nginx/sites-enabled/default ]; then
    echo "🗑 Removing default Nginx site..."
    rm -f /etc/nginx/sites-enabled/default
fi

# Test and reload
echo "🧪 Testing nginx configuration..."
nginx -t

echo "🔄 Restarting nginx..."
systemctl start nginx || true
systemctl restart nginx

echo "✅ Nginx configured successfully!"
echo "   ${MOODLE_URL} -> http://127.0.0.1:8080"
echo "   ${PRESSBOOKS_URL} -> http://127.0.0.1:8081"
