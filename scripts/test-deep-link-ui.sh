#!/bin/bash
set -e

# Test Deep Linking Content Picker UI

echo "🧪 Testing Deep Linking Content Picker"
echo "======================================"

# Get test parameters
PRESSBOOKS_URL="https://pb.lti.qbnox.com"
CLIENT_ID="pressbooks-lti-client"
RETURN_URL="https://moodle.lti.qbnox.com/mod/lti/contentitem_return.php"
DEPLOYMENT_ID="1"

# Construct Deep Link URL
DEEP_LINK_URL="${PRESSBOOKS_URL}/wp-json/pb-lti/v1/deep-link"
DEEP_LINK_URL="${DEEP_LINK_URL}?client_id=${CLIENT_ID}"
DEEP_LINK_URL="${DEEP_LINK_URL}&deep_link_return_url=${RETURN_URL}"
DEEP_LINK_URL="${DEEP_LINK_URL}&deployment_id=${DEPLOYMENT_ID}"

echo ""
echo "📋 Deep Link URL:"
echo "$DEEP_LINK_URL"
echo ""
echo "✅ Open this URL in your browser to test the content picker"
echo ""
echo "Expected behavior:"
echo "  1. Shows list of books in Pressbooks network"
echo "  2. Click 'View Chapters' to expand book structure"
echo "  3. Select a book or chapter"
echo "  4. Click 'Select This Content' to complete selection"
echo ""
echo "🔗 To test manually, visit:"
echo "$DEEP_LINK_URL"
