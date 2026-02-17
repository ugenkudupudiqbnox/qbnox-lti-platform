<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');

echo "=== Creating Deep Linking Activity ===\n\n";

// Get course
$course = $DB->get_record('course', ['shortname' => 'LTI101']);
if (!$course) {
    echo "✗ Course not found\n";
    exit(1);
}

// Get LTI module
$module = $DB->get_record('modules', ['name' => 'lti']);
if (!$module) {
    echo "✗ LTI module not found\n";
    exit(1);
}

// Get tool type
$tool = $DB->get_record('lti_types', ['name' => 'Pressbooks LTI Platform']);
if (!$tool) {
    echo "✗ Tool not found\n";
    exit(1);
}

// Check if activity already exists with course module
$existing_cm = $DB->get_record_sql(
    "SELECT cm.* FROM {course_modules} cm
     JOIN {lti} l ON l.id = cm.instance
     WHERE cm.course = ? AND l.name = ? AND cm.module = ?",
    [$course->id, 'Deep Linking Test', $module->id]
);

if ($existing_cm) {
    echo "✓ Deep Linking activity already exists\n";
    $moodle_url = getenv('MOODLE_URL') ?: 'http://moodle.local:8080';
    echo "  URL: {$moodle_url}/mod/lti/view.php?id={$existing_cm->id}\n";
    exit(0);
}

// Create LTI instance
$lti = new stdClass();
$lti->course = $course->id;
$lti->name = 'Deep Linking Test';
$lti->intro = 'Test Deep Linking 2.0 content selection';
$lti->introformat = FORMAT_HTML;
$lti->typeid = $tool->id;
$lti->toolurl = ''; // Empty for Deep Linking
$lti->instructorchoicesendname = 1;
$lti->instructorchoicesendemailaddr = 1;
$lti->instructorchoiceacceptgrades = 0;
$lti->grade = 0;
$lti->showtitlelaunch = 1;
$lti->showdescriptionlaunch = 1;
$lti->servicesalt = uniqid('', true);
$lti->timecreated = time();
$lti->timemodified = time();

// Check if lti instance already exists
$existing_lti = $DB->get_record('lti', ['course' => $course->id, 'name' => 'Deep Linking Test']);
if ($existing_lti) {
    $lti_id = $existing_lti->id;
    echo "✓ Using existing LTI instance (ID: {$lti_id})\n";
} else {
    $lti_id = $DB->insert_record('lti', $lti);
    echo "✓ Created LTI instance (ID: {$lti_id})\n";
}

// Create course module
$cm = new stdClass();
$cm->course = $course->id;
$cm->module = $module->id;
$cm->instance = $lti_id;
$cm->section = 0; // Add to first section
$cm->visible = 1;
$cm->groupmode = 0;
$cm->groupingid = 0;
$cm->added = time();

$cmid = add_course_module($cm);
echo "✓ Created course module (ID: {$cmid})\n";

// Add to section
$section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
if ($section) {
    course_add_cm_to_section($course->id, $cmid, 0);
    echo "✓ Added to course section\n";
}

// Rebuild course cache
rebuild_course_cache($course->id, true);
echo "✓ Course cache rebuilt\n";

echo "\n=== Success! ===\n";
$moodle_url = getenv('MOODLE_URL') ?: 'http://moodle.local:8080';
echo "Deep Linking Test URL: {$moodle_url}/mod/lti/view.php?id={$cmid}\n";
