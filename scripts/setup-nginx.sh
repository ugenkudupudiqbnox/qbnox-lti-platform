#!/usr/bin/env bash
set -e

# Show usage if help requested
if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    cat << EOF
Usage: sudo bash scripts/setup-nginx.sh [OPTIONS]

Sets up nginx reverse proxy for Moodle and Pressbooks with automatic environment detection.

OPTIONS:
    --skip-ssl, --http-only    Force HTTP-only setup (skip SSL even for production)
    --help, -h                 Show this help message

BEHAVIOR:
    - Automatically detects local development (.local domains) vs production
    - Local development: HTTP-only on port 80
    - Production: Attempts SSL/HTTPS, falls back to HTTP if needed
    - Reads PROTOCOL setting from .env file
    - Checks if domains already configured (idempotent)

EXAMPLES:
    # Automatic setup (detects environment):
    sudo bash scripts/setup-nginx.sh

    # Force HTTP-only even for production:
    sudo bash scripts/setup-nginx.sh --skip-ssl

REQUIREMENTS:
    - Must be run as root (use sudo)
    - For SSL: DNS records must point to this server
    - For app config updates: Docker containers should be running

EOF
    exit 0
fi

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

# Function to check if a domain is already configured in Nginx
is_domain_configured() {
    local domain=$1
    if grep -rql "server_name.*${domain}" /etc/nginx/sites-enabled/ &>/dev/null; then
        return 0
    fi
    return 1
}

# Detect environment type
IS_LOCAL=false
if [[ "$MOODLE_DOMAIN" == *".local"* ]] || [[ "$PRESSBOOKS_DOMAIN" == *".local"* ]]; then
    IS_LOCAL=true
fi

# Determine SSL configuration
SKIP_SSL=false
if [ "$1" = "--skip-ssl" ] || [ "$1" = "--http-only" ]; then
    # Command-line override to skip SSL
    SKIP_SSL=true
    echo "🔧 Setting up nginx reverse proxy (HTTP only - command line override)"
elif [ "$IS_LOCAL" = true ]; then
    # Local development - always HTTP
    SKIP_SSL=true
    echo "🔧 Setting up nginx for local development (.local domains - HTTP only)"
elif [ "${PROTOCOL:-http}" = "http" ]; then
    # PROTOCOL set to http in .env
    SKIP_SSL=true
    echo "🔧 Setting up nginx reverse proxy (HTTP only - PROTOCOL=http in .env)"
else
    # Production with HTTPS
    echo "🔧 Setting up nginx reverse proxy with SSL/HTTPS (PROTOCOL=${PROTOCOL} in .env)"
fi

# Install nginx if not already installed
if ! command -v nginx &> /dev/null; then
    echo "📦 Nginx not found. Installing..."
    apt-get update && apt-get install -y nginx
fi

# Install certbot if needed (production with SSL)
if [ "$IS_LOCAL" = false ] && [ "$SKIP_SSL" = false ] && ! command -v certbot &> /dev/null; then
    echo "📦 Installing certbot for SSL certificates..."
    apt-get update
    apt-get install -y certbot python3-certbot-nginx
fi

# Create nginx config directories if needed
mkdir -p /etc/nginx/sites-available
mkdir -p /etc/nginx/sites-enabled

echo "📝 Creating nginx configurations..."

# Moodle Configuration
MOODLE_CONF="/etc/nginx/sites-available/${MOODLE_DOMAIN}"
if is_domain_configured "${MOODLE_DOMAIN}"; then
    echo "ℹ️  Nginx already configured for ${MOODLE_DOMAIN}, updating..."
fi

cat > "$MOODLE_CONF" <<EOF
# Moodle LTI Platform
server {
    listen 80;
    listen [::]:80;
    server_name ${MOODLE_DOMAIN};

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Host \$host;

        # Increase timeouts for long-running Moodle tasks
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }
}
EOF

ln -sf "$MOODLE_CONF" /etc/nginx/sites-enabled/
echo "✅ Configured ${MOODLE_DOMAIN}"

# Pressbooks Configuration
PRESSBOOKS_CONF="/etc/nginx/sites-available/${PRESSBOOKS_DOMAIN}"
if is_domain_configured "${PRESSBOOKS_DOMAIN}"; then
    echo "ℹ️  Nginx already configured for ${PRESSBOOKS_DOMAIN}, updating..."
fi

cat > "$PRESSBOOKS_CONF" <<EOF
# Pressbooks LTI Platform
server {
    listen 80;
    listen [::]:80;
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
echo "✅ Configured ${PRESSBOOKS_DOMAIN}"

# Remove default nginx site if it exists
if [ -L /etc/nginx/sites-enabled/default ] || [ -f /etc/nginx/sites-enabled/default ]; then
    echo "🗑  Removing default nginx site..."
    rm -f /etc/nginx/sites-enabled/default
fi

# Test nginx configuration
echo "🧪 Testing nginx configuration..."
nginx -t

# Reload nginx
echo "🔄 Reloading nginx..."
systemctl start nginx 2>/dev/null || true
systemctl reload nginx

# Check if Docker services are running
echo ""
echo "🐳 Checking Docker services..."
MOODLE_RUNNING=false
PRESSBOOKS_RUNNING=false

if docker ps 2>/dev/null | grep -q moodle; then
    MOODLE_RUNNING=true
    echo "✅ Moodle container is running"
else
    echo "⚠️  Moodle container not running. Start it with: make up"
fi

if docker ps 2>/dev/null | grep -q pressbooks; then
    PRESSBOOKS_RUNNING=true
    echo "✅ Pressbooks container is running"
else
    echo "⚠️  Pressbooks container not running. Start it with: make up"
fi

echo ""
echo "✅ HTTP nginx configuration installed"
echo ""

# SSL/HTTPS Configuration (only for production)
CERTS_EXIST=false
USE_HTTPS=false

if [ "$IS_LOCAL" = false ] && [ "$SKIP_SSL" = false ]; then
    # Check if certificates already exist
    if [ -f "/etc/letsencrypt/live/${MOODLE_DOMAIN}/fullchain.pem" ]; then
        echo "ℹ️  SSL certificates already exist for ${MOODLE_DOMAIN}"
        CERTS_EXIST=true
        USE_HTTPS=true
    else
        echo "📋 Obtaining SSL certificates from Let's Encrypt..."
        echo ""

        # Verify DNS before proceeding
        echo "🔍 Verifying DNS records..."
        SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
        echo "   This server IP: $SERVER_IP"

        MOODLE_IP=$(dig +short ${MOODLE_DOMAIN} | tail -1)
        PRESSBOOKS_IP=$(dig +short ${PRESSBOOKS_DOMAIN} | tail -1)

        if [ -z "$MOODLE_IP" ]; then
            echo "⚠️  DNS not configured for ${MOODLE_DOMAIN}"
            echo "   Please add A record: ${MOODLE_DOMAIN} → $SERVER_IP"
            echo "   Continuing with HTTP-only setup..."
        elif [ -z "$PRESSBOOKS_IP" ]; then
            echo "⚠️  DNS not configured for ${PRESSBOOKS_DOMAIN}"
            echo "   Please add A record: ${PRESSBOOKS_DOMAIN} → $SERVER_IP"
            echo "   Continuing with HTTP-only setup..."
        else
            echo "   ${MOODLE_DOMAIN} → $MOODLE_IP ✓"
            echo "   ${PRESSBOOKS_DOMAIN} → $PRESSBOOKS_IP ✓"
            echo ""

            # Obtain certificates
            echo "📜 Running certbot..."

            # Get email for certbot
            if [ -z "$SSL_EMAIL" ]; then
                SSL_EMAIL="admin@${MOODLE_DOMAIN}"
            fi

            # Try to get certificate for both domains
            if certbot certonly --nginx \
                -d ${MOODLE_DOMAIN} \
                -d ${PRESSBOOKS_DOMAIN} \
                --non-interactive \
                --agree-tos \
                --email "$SSL_EMAIL" \
                --redirect; then
                echo "✅ SSL certificates obtained successfully"
                CERTS_EXIST=true
                USE_HTTPS=true
            else
                echo "⚠️  Failed to obtain SSL certificates"
                echo "   Continuing with HTTP-only setup..."
                echo "   You can run this script again later to enable HTTPS"
            fi
        fi
    fi
fi

# Update nginx configs to HTTPS if certificates exist
if [ "$USE_HTTPS" = true ]; then
    echo ""
    echo "🔒 Configuring HTTPS..."

    # Determine certificate path
    if [ -f "/etc/letsencrypt/live/${MOODLE_DOMAIN}/fullchain.pem" ]; then
        CERT_PATH="/etc/letsencrypt/live/${MOODLE_DOMAIN}"
    else
        echo "❌ Certificate files not found"
        exit 1
    fi

    # Update Moodle config with HTTPS
    cat > "$MOODLE_CONF" <<EOF
# Moodle LTI Platform - HTTPS
# HTTP - redirect to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name ${MOODLE_DOMAIN};
    return 301 https://\$server_name\$request_uri;
}

# HTTPS - proxy to Docker
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name ${MOODLE_DOMAIN};

    ssl_certificate ${CERT_PATH}/fullchain.pem;
    ssl_certificate_key ${CERT_PATH}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Host \$host;

        # Increase timeouts for long-running Moodle tasks
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }
}
EOF

    # Update Pressbooks config with HTTPS
    cat > "$PRESSBOOKS_CONF" <<EOF
# Pressbooks LTI Platform - HTTPS
# HTTP - redirect to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name ${PRESSBOOKS_DOMAIN};
    return 301 https://\$server_name\$request_uri;
}

# HTTPS - proxy to Docker
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name ${PRESSBOOKS_DOMAIN};

    ssl_certificate ${CERT_PATH}/fullchain.pem;
    ssl_certificate_key ${CERT_PATH}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

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

    # Test and reload nginx
    echo "🧪 Testing nginx HTTPS configuration..."
    nginx -t

    echo "🔄 Reloading nginx with HTTPS..."
    systemctl reload nginx

    echo ""
    echo "✅ HTTPS configuration complete!"

    # Update application configurations to use HTTPS
    if [ "$MOODLE_RUNNING" = true ]; then
        echo ""
        echo "🔧 Updating Moodle configuration for HTTPS..."
        docker exec moodle sed -i "s|http://${MOODLE_DOMAIN}|https://${MOODLE_DOMAIN}|g" /var/www/html/config.php 2>/dev/null || true
        docker exec moodle sed -i "/\\\$CFG->directorypermissions/a \\\$CFG->sslproxy = true;" /var/www/html/config.php 2>/dev/null || true
        echo "✅ Moodle configured for HTTPS"
    fi

    if [ "$PRESSBOOKS_RUNNING" = true ]; then
        echo ""
        echo "🔧 Updating Pressbooks configuration for HTTPS..."
        docker exec pressbooks sed -i "s|http://${PRESSBOOKS_DOMAIN}|https://${PRESSBOOKS_DOMAIN}|g" /var/www/pressbooks/.env 2>/dev/null || true
        docker exec pressbooks wp option update home "https://${PRESSBOOKS_DOMAIN}" --allow-root 2>/dev/null || true
        docker exec pressbooks wp option update siteurl "https://${PRESSBOOKS_DOMAIN}/wp" --allow-root 2>/dev/null || true
        echo "✅ Pressbooks configured for HTTPS"
    fi
fi

# Final summary
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Setup Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Set protocol based on HTTPS status
if [ "$USE_HTTPS" = true ]; then
    PROTOCOL="https"
else
    PROTOCOL="http"
fi

echo "🌐 Your sites are now available at:"
echo "   Moodle:     ${PROTOCOL}://${MOODLE_DOMAIN}"
echo "   Pressbooks: ${PROTOCOL}://${PRESSBOOKS_DOMAIN}"
echo ""

if [ "$USE_HTTPS" = true ]; then
    echo "🔒 SSL certificates will auto-renew via certbot"
    echo "   (certbot renew runs automatically via systemd)"
    echo ""
elif [ "$IS_LOCAL" = false ]; then
    echo "⚠️  HTTP-only configuration (no SSL/HTTPS)"
    echo "   To enable HTTPS, run: sudo bash scripts/setup-nginx.sh"
    echo ""
fi

if [ "$IS_LOCAL" = true ]; then
    echo "💻 Local development mode"
    echo "   Start services with: make up"
else
    echo "📋 Production mode - Next steps:"
    echo "   1. Test Moodle: ${PROTOCOL}://${MOODLE_DOMAIN}"
    echo "   2. Test Pressbooks: ${PROTOCOL}://${PRESSBOOKS_DOMAIN}"
    echo "   3. Run LTI integration tests: make test"
fi
echo ""
