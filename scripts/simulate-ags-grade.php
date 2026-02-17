<?php
/**
 * Simulate AGS Grade Posting
 *
 * This script posts a grade directly to Moodle's gradebook,
 * simulating what the AGS API would do.
 */

define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->libdir.'/gradelib.php');

echo "=== AGS Grade Posting Simulation ===\n\n";

// Get a student (try 'student' first, then generator users)
$student = $DB->get_record('user', ['username' => 'student']);
if (!$student) {
    $student = $DB->get_record_sql("SELECT * FROM {user} WHERE username LIKE 'tool_generator_%' LIMIT 1");
}
if (!$student) {
    echo "✗ No student users found\n";
    exit(1);
}
echo "✓ Student: {$student->firstname} {$student->lastname} (ID: {$student->id}, Username: {$student->username})\n";

// Get the course
$course = $DB->get_record('course', ['shortname' => 'LTI101']);
if (!$course) {
    echo "✗ Course not found\n";
    exit(1);
}
echo "✓ Course: {$course->fullname} (ID: {$course->id})\n";

// Get the LTI activity
$lti = $DB->get_record('lti', ['course' => $course->id, 'name' => 'AGS Graded Assignment']);
if (!$lti) {
    echo "✗ AGS activity not found\n";
    exit(1);
}
echo "✓ Activity: {$lti->name} (ID: {$lti->id})\n";
echo "  Max Grade: {$lti->grade}\n\n";

// Get or create grade item
$grade_item = grade_item::fetch([
    'courseid' => $course->id,
    'itemtype' => 'mod',
    'itemmodule' => 'lti',
    'iteminstance' => $lti->id
]);

if (!$grade_item) {
    echo "Creating grade item...\n";
    $grade_item = new grade_item();
    $grade_item->courseid = $course->id;
    $grade_item->itemtype = 'mod';
    $grade_item->itemmodule = 'lti';
    $grade_item->iteminstance = $lti->id;
    $grade_item->itemnumber = 0;
    $grade_item->itemname = $lti->name;
    $grade_item->grademax = $lti->grade;
    $grade_item->grademin = 0;
    $grade_item->insert();
}

echo "✓ Grade Item: {$grade_item->itemname} (ID: {$grade_item->id})\n";
echo "  Scale: {$grade_item->grademin} - {$grade_item->grademax}\n\n";

// Post the grade
$test_score = 85.5;
echo "Posting grade: {$test_score} / {$grade_item->grademax}\n";

$grade = new grade_grade();
$grade->itemid = $grade_item->id;
$grade->userid = $student->id;
$grade->rawgrade = $test_score;
$grade->finalgrade = $test_score;
$grade->timecreated = time();
$grade->timemodified = time();

// Check if grade already exists
$existing = $DB->get_record('grade_grades', [
    'itemid' => $grade_item->id,
    'userid' => $student->id
]);

if ($existing) {
    echo "Updating existing grade...\n";
    $grade->id = $existing->id;
    $grade->update();
} else {
    echo "Creating new grade...\n";
    $grade->insert();
}

echo "\n✓ Grade posted successfully!\n\n";

// Verify the grade
$posted_grade = $DB->get_record('grade_grades', [
    'itemid' => $grade_item->id,
    'userid' => $student->id
]);

echo "=== Verification ===\n";
echo "Student: {$student->username}\n";
echo "Activity: {$lti->name}\n";
echo "Grade: {$posted_grade->finalgrade} / {$grade_item->grademax}\n";
echo "Time: " . date('Y-m-d H:i:s', $posted_grade->timemodified) . "\n\n";

echo "✓ Check Moodle gradebook to see the grade!\n";
echo "  1. Log in as instructor\n";
echo "  2. Go to LTI Test Course\n";
echo "  3. Click 'Grades' in course menu\n";
echo "  4. Look for student's grade in 'AGS Graded Assignment' column\n";
