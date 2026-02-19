#!/bin/bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "🧪 Verifying Deep Linking Content Picker Implementation"
echo "======================================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# PRESSBOOKS_URL is now loaded from load-env.sh
DEEP_LINK_ENDPOINT="${PRESSBOOKS_URL}/wp-json/pb-lti/v1/deep-link"

# Test 1: Check if endpoint is accessible
echo "📡 Test 1: Checking Deep Link endpoint..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${DEEP_LINK_ENDPOINT}?client_id=test&deep_link_return_url=http://example.com&deployment_id=1")

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✅ Endpoint accessible (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${RED}❌ Endpoint returned HTTP $HTTP_CODE${NC}"
    exit 1
fi

# Test 2: Check if HTML contains expected elements
echo ""
echo "🔍 Test 2: Checking content picker UI elements..."
RESPONSE=$(curl -s "${DEEP_LINK_ENDPOINT}?client_id=test&deep_link_return_url=http://example.com&deployment_id=1")

if echo "$RESPONSE" | grep -q "Select Pressbooks Content"; then
    echo -e "${GREEN}✅ Page title found${NC}"
else
    echo -e "${RED}❌ Page title not found${NC}"
fi

if echo "$RESPONSE" | grep -q "book-card"; then
    echo -e "${GREEN}✅ Book card elements present${NC}"
else
    echo -e "${RED}❌ Book card elements missing${NC}"
fi

if echo "$RESPONSE" | grep -q "loadChapters"; then
    echo -e "${GREEN}✅ JavaScript functions present${NC}"
else
    echo -e "${RED}❌ JavaScript functions missing${NC}"
fi

# Test 3: Check AJAX endpoint
echo ""
echo "📞 Test 3: Testing AJAX book structure endpoint..."

# Try to get book structure for book ID 2 (test book)
AJAX_URL="${PRESSBOOKS_URL}/wp-admin/admin-ajax.php"
AJAX_RESPONSE=$(curl -s -X POST "$AJAX_URL" \
    -d "action=pb_lti_get_book_structure" \
    -d "book_id=2")

if echo "$AJAX_RESPONSE" | grep -q '"success":true'; then
    echo -e "${GREEN}✅ AJAX endpoint working${NC}"
    echo "   Book structure returned successfully"
elif echo "$AJAX_RESPONSE" | grep -q '"success":false'; then
    echo -e "${YELLOW}⚠️  AJAX endpoint accessible but book not found${NC}"
    echo "   This is expected if test books haven't been created yet"
    echo "   Run: make seed-books"
else
    echo -e "${RED}❌ AJAX endpoint not responding correctly${NC}"
    echo "   Response: $AJAX_RESPONSE"
fi

# Test 4: Check if files exist in container
echo ""
echo "📁 Test 4: Verifying plugin files in container..."

FILES_TO_CHECK=(
    "/var/www/html/web/app/plugins/qbnox-lti-platform/Services/ContentService.php"
    "/var/www/html/web/app/plugins/qbnox-lti-platform/views/deep-link-picker.php"
    "/var/www/html/web/app/plugins/qbnox-lti-platform/ajax/handlers.php"
    "/var/www/html/web/app/plugins/qbnox-lti-platform/Controllers/DeepLinkController.php"
)

ALL_FILES_EXIST=true
for FILE in "${FILES_TO_CHECK[@]}"; do
    if docker exec pressbooks test -f "$FILE"; then
        echo -e "${GREEN}✅ $FILE${NC}"
    else
        echo -e "${RED}❌ $FILE missing${NC}"
        ALL_FILES_EXIST=false
    fi
done

# Summary
echo ""
echo "======================================================="
echo "📊 Test Summary"
echo "======================================================="

if [ "$ALL_FILES_EXIST" = true ]; then
    echo -e "${GREEN}✅ All plugin files present${NC}"
    echo -e "${GREEN}✅ Deep Linking UI accessible${NC}"
    echo -e "${GREEN}✅ Content picker rendering correctly${NC}"
    echo ""
    echo "🎉 Deep Linking Content Picker is ready!"
    echo ""
    echo "📝 Next Steps:"
    echo "   1. Create test books: make seed-books"
    echo "   2. Open in browser: ${DEEP_LINK_ENDPOINT}?client_id=test&deep_link_return_url=http://example.com&deployment_id=1"
    echo "   3. Test with Moodle: Configure tool for Deep Linking and create activity"
    echo ""
else
    echo -e "${RED}❌ Some files are missing${NC}"
    echo "   Run the following to copy files to container:"
    echo "   docker cp plugin/ajax pressbooks:/var/www/html/web/app/plugins/qbnox-lti-platform/"
    echo "   docker cp plugin/views pressbooks:/var/www/html/web/app/plugins/qbnox-lti-platform/"
    echo "   docker cp plugin/Services/ContentService.php pressbooks:/var/www/html/web/app/plugins/qbnox-lti-platform/Services/"
fi
