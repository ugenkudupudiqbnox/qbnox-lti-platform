#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

MOODLE_CONTAINER=$($SUDO_DOCKER docker ps --filter "name=moodle" --format "{{.ID}}")

echo "📊 Verifying AGS grade in Moodle database"

COUNT=$(sudo docker exec moodle php -r '
define("CLI_SCRIPT", true);
require "config.php";
global $DB;
echo $DB->count_records_select("grade_grades", "itemid IN (SELECT id FROM {grade_items} WHERE itemtype = \"mod\" AND itemmodule = \"lti\")");
')

if [ "$COUNT" -eq "0" ]; then
  echo "❌ No grades found in Moodle for LTI activities"
  exit 1
fi

echo "✅ Moodle grade record exists ($COUNT)"


