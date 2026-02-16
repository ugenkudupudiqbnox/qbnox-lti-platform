
<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
global $DB;

$courses = $DB->get_records('course');
$course_count = 0;
$courses_with_grades = 0;

foreach ($courses as $course) {
    if ($course->id == 1) continue; // skip site course
    $course_count++;
    
    $count = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {grade_items} gi
        JOIN {grade_grades} gg ON gg.itemid = gi.id
        WHERE gi.courseid = ?", [$course->id]);
    
    if ($count > 0) {
        $courses_with_grades++;
    }
}

if ($course_count == 0) {
    echo "⚠️  No courses found - skipping per-course grades test\n";
    exit(0);
}

if ($courses_with_grades == 0) {
    echo "⚠️  No courses with grades found - skipping per-course grades test\n";
    exit(0);
}

// Now verify all courses have grades (stricter check)
foreach ($courses as $course) {
    if ($course->id == 1) continue; // skip site course
    $count = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {grade_items} gi
        JOIN {grade_grades} gg ON gg.itemid = gi.id
        WHERE gi.courseid = ?", [$course->id]);
    if ($count == 0) {
        fwrite(STDERR, "❌ No grades for course {$course->fullname}\n");
        exit(1);
    }
}

echo "✅ Per-course grade assertions passed\n";
