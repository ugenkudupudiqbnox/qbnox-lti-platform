<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->libdir.'/clilib.php');

echo "=== Testing Deep Linking 2.0 ===\n\n";

// Find the Pressbooks LTI tool
$tool = $DB->get_record('lti_types', ['name' => 'Pressbooks LTI Platform']);
if (!$tool) {
    echo "✗ Pressbooks tool not found\n";
    exit(1);
}

echo "✓ Found Pressbooks tool (ID: {$tool->id})\n";

// Enable Deep Linking support
$tool->lti_contentitem = 1; // LTI_SETTING_DELEGATE
$DB->update_record('lti_types', $tool);
echo "✓ Deep Linking enabled on tool\n";

// Find test course
$course = $DB->get_record('course', ['shortname' => 'LTI101']);
if (!$course) {
    echo "✗ Test course not found\n";
    exit(1);
}

echo "✓ Found test course (ID: {$course->id})\n";

// Check if Deep Linking test activity already exists
$existing = $DB->get_record('lti', [
    'course' => $course->id,
    'name' => 'Deep Linking Test'
]);

if ($existing) {
    echo "✓ Deep Linking test activity already exists (cmid: {$existing->id})\n";
    $lti_id = $existing->id;
} else {
    // Create a new LTI activity with Deep Linking enabled
    $lti = new stdClass();
    $lti->course = $course->id;
    $lti->name = 'Deep Linking Test';
    $lti->intro = 'Test Deep Linking 2.0 content selection';
    $lti->introformat = FORMAT_HTML;
    $lti->typeid = $tool->id;
    $lti->toolurl = ''; // Empty for Deep Linking - will be populated after selection
    $lti->instructorchoicesendname = 1;
    $lti->instructorchoicesendemailaddr = 1;
    $lti->instructorchoiceacceptgrades = 1;
    $lti->grade = 100;
    $lti->showtitlelaunch = 1;
    $lti->showdescriptionlaunch = 1;
    $lti->servicesalt = uniqid('', true);
    $lti->timecreated = time();
    $lti->timemodified = time();

    $lti_id = $DB->insert_record('lti', $lti);
    echo "✓ Created Deep Linking test activity (ID: {$lti_id})\n";

    // Get course module
    $cm = $DB->get_record_sql(
        "SELECT cm.* FROM {course_modules} cm
         JOIN {modules} m ON m.id = cm.module
         WHERE m.name = 'lti' AND cm.instance = ?",
        [$lti_id]
    );

    if ($cm) {
        echo "✓ Activity visible in course (cmid: {$cm->id})\n";
    }
}

echo "\n=== Deep Linking Test Ready ===\n";
$moodle_url = getenv('MOODLE_URL') ?: 'https://moodle.lti.qbnox.com';
echo "1. Log in to Moodle: {$moodle_url}/\n";
echo "2. Go to course: LTI Test Course\n";
echo "3. Click: 'Deep Linking Test' activity\n";
echo "4. You should see Pressbooks content selection\n";
echo "5. Select content and confirm\n";
echo "6. Content link should be stored in Moodle\n";

// Display tool configuration for verification
$pb_url = getenv('PRESSBOOKS_URL') ?: 'https://pb.lti.qbnox.com';
echo "\n=== Tool Configuration ===\n";
echo "Deep Linking Endpoint: {$pb_url}/wp-json/pb-lti/v1/deep-link\n";
echo "Content Item supported: " . ($tool->lti_contentitem ? "YES" : "NO") . "\n";
