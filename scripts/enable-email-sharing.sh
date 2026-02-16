#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

echo "=== Enabling Email & Name Sharing for LTI Tool ==="

MOODLE_CONTAINER=$($SUDO_DOCKER docker ps --filter "name=moodle" --format "{{.Names}}" | head -1)

if [ -z "$MOODLE_CONTAINER" ]; then
    echo "❌ Moodle container not found"
    exit 1
fi

$SUDO_DOCKER docker exec mysql mysql -uroot -proot moodle -e "
UPDATE mdl_lti_types_config
SET value = '1'
WHERE typeid = (SELECT id FROM mdl_lti_types WHERE name = 'Pressbooks LTI Platform')
AND name IN ('sendemailaddr', 'sendname');
" 2>&1 | grep -v "Warning" || true

echo "✅ Email and name sharing enabled"
echo ""
echo "Moodle will now send:"
echo "  - Student/instructor names"
echo "  - Email addresses"
echo ""
echo "Users will get real emails (e.g., student@example.com)"
echo "Instead of placeholders (e.g., 3@lti.local)"
