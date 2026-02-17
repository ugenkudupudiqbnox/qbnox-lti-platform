
<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
global $DB;

$courses = $DB->get_records('course');
foreach ($courses as $course) {
    if ($course->id == 1) continue; // skip site course
    $count = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {grade_items} gi
        JOIN {grade_grades} gg ON gg.itemid = gi.id
        WHERE gi.courseid = ?", [$course->id]);
    if ($count == 0) {
        fwrite(STDERR, "No grades for course {$course->fullname}\n");
        exit(1);
    }
}
echo "Per-course grade assertions passed\n";
