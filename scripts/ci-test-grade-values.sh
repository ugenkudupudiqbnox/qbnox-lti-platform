
#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

MOODLE_CONTAINER=$($SUDO_DOCKER docker ps --filter "name=moodle" --format "{{.ID}}")
$SUDO_DOCKER docker exec "$MOODLE_CONTAINER" bash -c "
php -r '
require "config.php";
global \$DB;
\$g=\$DB->get_field_sql("SELECT MAX(rawgrade) FROM {grade_grades}");
if(\$g<50){exit(1);}
echo "Grade value OK: \$g";
'
"
