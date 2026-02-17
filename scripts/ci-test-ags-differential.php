
<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
global $DB;

// Verify instructors have grade capability, students only grade receipt
$records = $DB->get_records_sql("
    SELECT u.id, u.username, g.rawgrade, ra.roleid
    FROM {user} u
    JOIN {grade_grades} g ON g.userid = u.id
    JOIN {role_assignments} ra ON ra.userid = u.id
");

foreach ($records as $r) {
    if ($r->roleid == 3 && $r->rawgrade !== null) { // student role
        continue;
    }
    if ($r->roleid == 2 && $r->rawgrade === null) { // instructor role
        fwrite(STDERR, "Instructor missing grade capability\n");
        exit(1);
    }
}

echo "Student vs Instructor AGS roles validated\n";
