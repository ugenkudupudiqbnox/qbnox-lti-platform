#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "🔧 Setting up nginx reverse proxy and Let's Encrypt SSL"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

# Get config directory
SCRATCHPAD="/tmp/claude-0/-root-pressbooks-lti-platform/5b91d56c-3e72-4487-8136-84369b629a75/scratchpad"

# Create certbot webroot
mkdir -p /var/www/certbot

echo "📝 Installing HTTP-only nginx configurations (for certbot challenge)..."

# Copy HTTP-only configs
cp "$SCRATCHPAD/moodle-lti-qbnox-http-only.conf" /etc/nginx/sites-available/moodle.lti.qbnox.com
cp "$SCRATCHPAD/pb-lti-qbnox-http-only.conf" /etc/nginx/sites-available/pb.lti.qbnox.com

# Enable sites
ln -sf /etc/nginx/sites-available/moodle.lti.qbnox.com /etc/nginx/sites-enabled/
ln -sf /etc/nginx/sites-available/pb.lti.qbnox.com /etc/nginx/sites-enabled/

# Test nginx configuration
echo "🧪 Testing nginx configuration..."
nginx -t

# Reload nginx
echo "🔄 Reloading nginx..."
systemctl reload nginx

# Check if Docker services are running
echo "🐳 Checking Docker services..."
if ! docker ps | grep -q moodle; then
    echo "⚠️  Moodle container not running. Start it with: make up"
fi
if ! docker ps | grep -q pressbooks; then
    echo "⚠️  Pressbooks container not running. Start it with: make up"
fi

echo ""
echo "✅ HTTP nginx configuration installed"
echo ""
echo "📋 Next steps:"
echo "1. Verify DNS records point to this server:"
echo "   - ${MOODLE_DOMAIN} → $(curl -s ifconfig.me 2>/dev/null || echo 'this server IP')"
echo "   - ${PRESSBOOKS_DOMAIN} → $(curl -s ifconfig.me 2>/dev/null || echo 'this server IP')"
echo ""
echo "2. Test HTTP access:"
echo "   curl -I http://${MOODLE_DOMAIN}"
echo "   curl -I http://${PRESSBOOKS_DOMAIN}"
echo ""
echo "3. Obtain SSL certificates:"
echo "   certbot --nginx -d ${MOODLE_DOMAIN} -d ${PRESSBOOKS_DOMAIN}"
echo ""
echo "4. After SSL is obtained, install HTTPS configs:"
echo "   sudo cp $SCRATCHPAD/moodle-lti-qbnox.conf /etc/nginx/sites-available/moodle.lti.qbnox.com"
echo "   sudo cp $SCRATCHPAD/pb-lti-qbnox.conf /etc/nginx/sites-available/pb.lti.qbnox.com"
echo "   sudo nginx -t && sudo systemctl reload nginx"
echo ""
