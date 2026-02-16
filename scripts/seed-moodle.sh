#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

MOODLE_CONTAINER=$($SUDO_DOCKER docker ps --filter "name=moodle" --format "{{.ID}}")

echo "🌱 Seeding Moodle"

sudo docker exec moodle bash -c "
php admin/tool/generator/cli/maketestcourse.php --size=S --shortname=LTI101 --fullname='LTI Testing Course' --bypasscheck
"

echo "✅ Users & course created"

