
#!/usr/bin/env bash
set -e
JWT="$1"
JWKS_URL="${JWKS_URL:-https://pressbooks.local/wp-json/pb-lti/v1/keyset}"
[ -z "$JWT" ] && echo "JWT missing" && exit 1
HEADER=$(echo "$JWT" | cut -d. -f1 | base64 -d 2>/dev/null)
KID=$(echo "$HEADER" | grep -o '"kid":"[^"]*"' | cut -d: -f2 | tr -d '"')
JWKS=$(curl -sk "$JWKS_URL")
echo "$JWKS" | grep -q "$KID" || exit 1
echo "JWT kid present in JWKS"
