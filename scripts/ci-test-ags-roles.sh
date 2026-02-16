
#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

MOODLE_CONTAINER=$($SUDO_DOCKER docker ps --filter "name=moodle" --format "{{.ID}}")
$SUDO_DOCKER docker exec "$MOODLE_CONTAINER" bash -c "
php -r '
require "config.php";
global \$DB;
\$grades=\$DB->get_records("grade_grades");
foreach(\$grades as \$g){
 if(\$g->rawgrade<0||\$g->rawgrade>100){exit(1);}
}
echo "AGS grades valid";
'
"
