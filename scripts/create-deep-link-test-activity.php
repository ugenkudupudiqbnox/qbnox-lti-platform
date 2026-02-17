#!/usr/bin/env php
<?php
/**
 * Create a test External Tool activity with Deep Linking enabled
 * This bypasses the Moodle UI to create an activity ready for content selection
 */

define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');

$course_id = 2;
$course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);

// Get LTI module
$module = $DB->get_record('modules', ['name' => 'lti'], '*', MUST_EXIST);

// Get Pressbooks tool type
$tool = $DB->get_record('lti_types', ['name' => 'Pressbooks LTI Platform'], '*', MUST_EXIST);

echo "🔧 Creating Deep Linking test activity\n";
echo "======================================\n\n";

// Create LTI instance
$lti = new stdClass();
$lti->course = $course_id;
$lti->name = 'Deep Linking - Content Selection Test';
$lti->intro = 'This activity uses Deep Linking to select specific Pressbooks content.';
$lti->introformat = FORMAT_HTML;
$lti->typeid = $tool->id; // Use preconfigured tool
$lti->toolurl = ''; // Empty = content selection required
$lti->instructorchoicesendname = 1;
$lti->instructorchoicesendemailaddr = 1;
$lti->instructorchoiceacceptgrades = 1;
$lti->grade = 100;
$lti->launchcontainer = 1; // Open in new window
$lti->showtitlelaunch = 1;
$lti->showdescriptionlaunch = 1;
$lti->timecreated = time();
$lti->timemodified = time();

$lti->id = $DB->insert_record('lti', $lti);

echo "✅ LTI instance created (ID: {$lti->id})\n";

// Create course module
$cm = new stdClass();
$cm->course = $course_id;
$cm->module = $module->id;
$cm->instance = $lti->id;
$cm->section = 0;
$cm->visible = 1;
$cm->visibleoncoursepage = 1;
$cm->groupmode = 0;
$cm->groupingid = 0;

$cm->id = add_course_module($cm);

echo "✅ Course module created (ID: {$cm->id})\n";

// Add to section
course_add_cm_to_section($course_id, $cm->id, 0);

// Rebuild course cache
rebuild_course_cache($course_id, true);

echo "✅ Activity added to course\n\n";

echo "🎉 Success!\n\n";
echo "Activity Details:\n";
echo "  Name: {$lti->name}\n";
echo "  Tool URL: " . ($lti->toolurl ?: '(empty - requires content selection)') . "\n";
echo "  Grade: {$lti->grade}\n\n";

echo "📝 Next Steps:\n";
echo "  1. Go to course: " . $CFG->wwwroot . "/course/view.php?id={$course_id}\n";
echo "  2. Find activity: '{$lti->name}'\n";
echo "  3. Edit it (gear icon → Edit settings)\n";
echo "  4. Look for 'Select content' button\n";
echo "  5. Click it to open content picker!\n\n";

echo "Or edit directly:\n";
echo $CFG->wwwroot . "/course/modedit.php?update={$cm->id}\n";
