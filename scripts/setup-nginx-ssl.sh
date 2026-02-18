#!/usr/bin/env bash
# DEPRECATED: This script has been merged into setup-nginx.sh
# Redirecting to unified script...

echo "ℹ️  Note: setup-nginx-ssl.sh is deprecated"
echo "   Using unified script: setup-nginx.sh"
echo ""

# Call the unified script
exec "$(dirname "$0")/setup-nginx.sh" "$@"
