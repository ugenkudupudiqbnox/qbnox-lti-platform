<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');

$book_path = $argv[1] ?? 'test-book';
$pressbooks_url = getenv('PRESSBOOKS_URL') ?: 'http://pressbooks.local';
$pressbooks_url = rtrim($pressbooks_url, '/');
$target_url = $pressbooks_url . '/' . $book_path . '/';

echo "=== Creating Direct Book Launcher ===\n";
echo "Target: $target_url\n\n";

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

$activity_name = "Read: LTI Test Book";

// Create LTI instance
$lti = new stdClass();
$lti->course = $course->id;
$lti->name = $activity_name;
$lti->intro = 'Direct link to the Pressbooks test book.';
$lti->introformat = FORMAT_HTML;
$lti->typeid = $tool->id;
$lti->toolurl = $target_url;
$lti->instructorchoicesendname = 1;
$lti->instructorchoicesendemailaddr = 1;
$lti->instructorchoiceacceptgrades = 0;
$lti->grade = 0;
$lti->launchcontainer = 3; // Embed without blocks
$lti->showtitlelaunch = 0;
$lti->showdescriptionlaunch = 0;
$lti->servicesalt = uniqid('', true);
$lti->timecreated = time();
$lti->timemodified = time();

$lti_id = $DB->insert_record('lti', $lti);
echo "✓ Created LTI instance (ID: {$lti_id})\n";

// Create course module
$cm = new stdClass();
$cm->course = $course->id;
$cm->module = $module->id;
$cm->instance = $lti_id;
$cm->section = 0;
$cm->visible = 1;
$cm->groupmode = 0;
$cm->groupingid = 0;
$cm->added = time();

$cmid = add_course_module($cm);
echo "✓ Created course module (ID: {$cmid})\n";

// Add to section
course_add_cm_to_section($course->id, $cmid, 0);
echo "✓ Added to course section\n";

// Rebuild course cache
rebuild_course_cache($course->id, true);
echo "✓ Course cache rebuilt\n";

echo "\n=== Success! ===\n";
echo "Book Launcher URL: " . $CFG->wwwroot . "/mod/lti/view.php?id={$cmid}\n";
