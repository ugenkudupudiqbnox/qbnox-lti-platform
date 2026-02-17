<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->libdir.'/gradelib.php');

// Load environment
$pressbooks_url = getenv('PRESSBOOKS_URL') ?: 'http://pressbooks.local:8081';
$pressbooks_url = rtrim($pressbooks_url, '/');

echo "=== Creating AGS Graded Assignment ===\n\n";

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
    [$course->id, 'AGS Graded Assignment', $module->id]
);

if ($existing_cm) {
    echo "✓ AGS activity already exists\n";
    echo "  URL: " . $CFG->wwwroot . "/mod/lti/view.php?id={$existing_cm->id}\n";
    exit(0);
}

// Create LTI instance
$lti = new stdClass();
$lti->course = $course->id;
$lti->name = 'AGS Graded Assignment';
$lti->intro = 'Test Assignment & Grade Services grade passback';
$lti->introformat = FORMAT_HTML;
$lti->typeid = $tool->id;
$lti->toolurl = $pressbooks_url . '/';
$lti->instructorchoicesendname = 1;
$lti->instructorchoicesendemailaddr = 1;
$lti->instructorchoiceacceptgrades = 1; // Enable grade passback
$lti->grade = 100; // Maximum grade
$lti->showtitlelaunch = 1;
$lti->showdescriptionlaunch = 1;
$lti->servicesalt = uniqid('', true);
$lti->timecreated = time();
$lti->timemodified = time();

// Check if lti instance already exists
$existing_lti = $DB->get_record('lti', ['course' => $course->id, 'name' => 'AGS Graded Assignment']);
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

// Ensure grade item exists with correct itemnumber to avoid Moodle exceptions
echo "✓ Initializing grade item...\n";
$grade_item = $DB->get_record('grade_items', [
    'courseid' => $course->id,
    'itemtype' => 'mod',
    'itemmodule' => 'lti',
    'iteminstance' => $lti_id
]);

if (!$grade_item) {
    $grade_item = new grade_item();
    $grade_item->courseid = $course->id;
    $grade_item->itemtype = 'mod';
    $grade_item->itemmodule = 'lti';
    $grade_item->iteminstance = $lti_id;
    $grade_item->itemnumber = 0;
    $grade_item->itemname = $lti->name;
    $grade_item->gradetype = GRADE_TYPE_VALUE;
    $grade_item->grademax = $lti->grade;
    $grade_item->grademin = 0;
    $grade_item->insert();
    echo "  ✓ Grade item created with itemnumber 0\n";
} else if ($grade_item->itemnumber === null) {
    $grade_item->itemnumber = 0;
    $grade_item->update();
    echo "  ✓ Grade item updated with itemnumber 0\n";
}

echo "\n=== Success! ===\n";
echo "AGS Test URL: " . $CFG->wwwroot . "/mod/lti/view.php?id={$cmid}\n";
