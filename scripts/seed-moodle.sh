#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

MOODLE_CONTAINER=$($SUDO_DOCKER docker ps --filter "name=moodle" --format "{{.ID}}")

echo "🌱 Seeding Moodle"

sudo docker exec moodle bash -c "
php admin/tool/generator/cli/maketestcourse.php --size=S --shortname=LTI101 --fullname='LTI Testing Course' --bypasscheck
"

echo "👤 Creating specific 'instructor' and 'student' accounts..."
sudo docker exec moodle php -r '
define("CLI_SCRIPT", true);
require "config.php";
require_once($CFG->dirroot . "/user/lib.php");
require_once($CFG->dirroot . "/lib/enrollib.php");

function create_user($username, $firstname, $lastname, $email, $password) {
    global $DB, $CFG;
    if ($existing = $DB->get_record("user", ["username" => $username])) {
        echo "User $username already exists.\n";
        return $existing->id;
    }
    $user = new stdClass();
    $user->username = $username;
    $user->firstname = $firstname;
    $user->lastname = $lastname;
    $user->email = $email;
    $user->password = password_hash($password, PASSWORD_DEFAULT);
    $user->auth = "manual";
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->confirmed = 1;
    $user->lang = "en";
    $user->timecreated = time();
    $id = $DB->insert_record("user", $user);
    echo "Created user $username (ID: $id)\n";
    return $id;
}

function enroll_user($userid, $courseshortname, $rolename) {
    global $DB;
    $course = $DB->get_record("course", ["shortname" => $courseshortname], "*", MUST_EXIST);
    $role = $DB->get_record("role", ["shortname" => $rolename], "*", MUST_EXIST);
    $instance = $DB->get_record("enrol", ["courseid" => $course->id, "enrol" => "manual"], "*", MUST_EXIST);
    $plugin = enrol_get_plugin("manual");
    $plugin->enrol_user($instance, $userid, $role->id);
    echo "Enrolled user in $courseshortname as $rolename\n";
}

$instructor_id = create_user("instructor", "Lab", "Instructor", "instructor@example.com", "moodle");
enroll_user($instructor_id, "LTI101", "editingteacher");

$student_id = create_user("student", "Lab", "Student", "student@example.com", "moodle");
enroll_user($student_id, "LTI101", "student");
'

echo "✅ Users & course created"

