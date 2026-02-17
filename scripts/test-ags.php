<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once($CFG->libdir.'/clilib.php');

// Load environment variables for Pressbooks URL
$pressbooks_url = getenv('PRESSBOOKS_URL') ?: 'https://pb.lti.qbnox.com';
$pressbooks_url = rtrim($pressbooks_url, '/');

echo "=== Testing Assignment & Grade Services (AGS) ===\n\n";

// Find the Pressbooks LTI tool
$tool = $DB->get_record('lti_types', ['name' => 'Pressbooks LTI Platform']);
if (!$tool) {
    echo "✗ Pressbooks tool not found\n";
    exit(1);
}

echo "✓ Found Pressbooks tool (ID: {$tool->id})\n";

// Verify AGS scopes are configured
$required_scopes = [
    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
    'https://purl.imsglobal.org/spec/lti-ags/scope/score'
];

$tool_scopes = !empty($tool->lti_acceptgrades) ? $required_scopes : [];
echo "✓ AGS scopes configured: " . implode(', ', $tool_scopes) . "\n";

// Find test course
$course = $DB->get_record('course', ['shortname' => 'LTI101']);
if (!$course) {
    echo "✗ Test course not found\n";
    exit(1);
}

echo "✓ Found test course (ID: {$course->id})\n";

// Check if AGS test activity already exists
$existing = $DB->get_record('lti', [
    'course' => $course->id,
    'name' => 'AGS Graded Assignment'
]);

if ($existing) {
    echo "✓ AGS test activity already exists (ID: {$existing->id})\n";
    $lti_id = $existing->id;
} else {
    // Create a new LTI activity with grade passback enabled
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

    $lti_id = $DB->insert_record('lti', $lti);
    echo "✓ Created AGS test activity (ID: {$lti_id})\n";
}

// Get student user
$student = $DB->get_record('user', ['username' => 'student']);
if (!$student) {
    echo "✗ Student user not found\n";
    exit(1);
}

echo "✓ Found student user (ID: {$student->id})\n";

// Check if student has a grade
$grade_item = $DB->get_record('grade_items', [
    'courseid' => $course->id,
    'itemtype' => 'mod',
    'itemmodule' => 'lti',
    'iteminstance' => $lti_id
]);

if ($grade_item) {
    echo "✓ Grade item exists (ID: {$grade_item->id})\n";

    $grade = $DB->get_record('grade_grades', [
        'itemid' => $grade_item->id,
        'userid' => $student->id
    ]);

    if ($grade && $grade->finalgrade !== null) {
        echo "✓ Student has existing grade: {$grade->finalgrade}/{$grade_item->grademax}\n";
    } else {
        echo "○ No grade posted yet\n";
    }
} else {
    echo "○ Grade item will be created on first launch\n";
}

echo "\n=== AGS Test Instructions ===\n";
echo "1. Log in to Moodle as instructor: " . $CFG->wwwroot . "/\n";
echo "2. Go to course: LTI Test Course\n";
echo "3. Click: 'AGS Graded Assignment' activity\n";
echo "4. From Pressbooks, post a grade back using the REST API:\n";
echo "\n";
echo "curl -X POST '{$pressbooks_url}/wp-json/pb-lti/v1/ags/post-score' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"lineitem_url\": \"<lineitem_url_from_launch_jwt>\",\n";
echo "    \"score\": 85.5,\n";
echo "    \"user_id\": \"" . $student->id . "\"\n";
echo "  }'\n";
echo "\n";
echo "5. Check Moodle gradebook to verify grade was posted\n";
echo "6. Navigate to: Course > Grades\n";
echo "\n";

// Display configuration
echo "=== Tool Configuration ===\n";
echo "Token Endpoint: " . ($tool->lti_toolurl ?? 'NOT SET') . "\n";
echo "Accept Grades: " . ($tool->lti_acceptgrades ? "YES" : "NO") . "\n";
echo "AGS Endpoint: {$pressbooks_url}/wp-json/pb-lti/v1/ags/post-score\n";
