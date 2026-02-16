#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

MOODLE_CONTAINER=$($SUDO_DOCKER docker ps --filter "name=moodle" --format "{{.ID}}")

echo "📊 Verifying AGS grade in Moodle database"

$SUDO_DOCKER docker exec "$MOODLE_CONTAINER" bash -c "
set -e

# Ensure at least one grade exists
COUNT=\$(php -r '
require \"config.php\";
global \$DB;
echo \$DB->count_records(\"grade_grades\");
')

if [ \"\$COUNT\" -eq \"0\" ]; then
  echo \"❌ No grades found in Moodle\"
  exit 1
fi

echo \"✅ Moodle grade record exists (\$COUNT)\"
"

