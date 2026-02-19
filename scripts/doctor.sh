#!/usr/bin/env bash
# Pressbooks LTI Platform - Pre-flight Diagnostics
set -e

# Load environment
source "$(dirname "$0")/load-env.sh"

echo "🩺 Diagnosing Pressbooks LTI Platform Setup"
echo "=========================================="
echo ""

# 1. Check OpenSSL
echo "🔐 Checking Crypto Capabilities..."
if command -v openssl >/dev/null; then
    OPENSSL_VER=$(openssl version)
    echo "✅ OpenSSL found: $OPENSSL_VER"
else
    echo "❌ OpenSSL not found. Required for RSA key generation."
fi

# 2. Check Database Prefix Alignment
echo -e "\n🗄 Checking Database Schema..."
if [ -f "$(dirname "$0")/../plugin/db/schema.php" ]; then
    if grep -q "lti_platforms" "$(dirname "$0")/../plugin/db/schema.php"; then
        echo "✅ Schema table 'lti_platforms' found in code."
    else
        echo "⚠️  Schema might be missing LTI tables. Check plugin/db/schema.php"
    fi
fi

# 3. Check Connectivity
echo -e "\n🌐 Checking Connectivity..."
echo "Targeting MOODLE: $MOODLE_URL"
echo "Targeting PRESSBOOKS: $PRESSBOOKS_URL"

check_url() {
    local url=$1
    local name=$2
    if curl -k -s -I "$url" | grep -q "HTTP/1.1 200\|HTTP/2 200\|HTTP/1.1 301\|HTTP/1.1 302"; then
        echo "✅ $name is reachable"
    else
        echo "❌ $name is NOT reachable ($url)"
    fi
}

check_url "$MOODLE_URL" "Moodle"
check_url "$PRESSBOOKS_URL" "Pressbooks"
check_url "$PRESSBOOKS_URL/wp-json/pb-lti/v1/keyset" "LTI Keyset Endpoint"

# 4. Check Environment Consistency
echo -e "\n📝 Checking Environment Consistency..."
if [[ "$PROTOCOL" == "https" && "$MOODLE_URL" != https://* ]]; then
    echo "❌ PROTOCOL=https but MOODLE_URL starts with http!"
elif [[ "$PROTOCOL" == "http" && "$MOODLE_URL" == https://* ]]; then
    echo "❌ PROTOCOL=http but MOODLE_URL starts with https!"
else
    echo "✅ Protocol and URL schemes match."
fi

# 5. Check Docker Status
echo -e "\n🐳 Checking Docker Containers..."
for container in moodle pressbooks mysql; do
    if sudo docker ps --format '{{.Names}}' | grep -q "^$container$"; then
        echo "✅ Container '$container' is running."
    else
        echo "❌ Container '$container' is NOT running."
    fi
done

# 6. Check H5P Write Access
echo -e "\n📂 Checking H5P Data Directories..."
for path in "/var/www/pressbooks/web/app/uploads/h5p" "/var/www/pressbooks/web/app/uploads/sites/2/h5p"; do
    if sudo docker exec pressbooks [ -d "$path" ]; then
        if sudo docker exec -u www-data pressbooks [ -w "$path" ]; then
            echo "✅ $path exists and is writeable by www-data."
        else
            echo "❌ $path is NOT writeable by www-data (Current: $(sudo docker exec pressbooks stat -c '%U:%G %a' "$path"))."
        fi
    else
        echo "⚠️  $path does not exist. Run 'make install-h5p' or 'bash scripts/fix-h5p-data.sh'."
    fi
done

echo -e "\n=========================================="
echo "🩺 Diagnostics Complete"
