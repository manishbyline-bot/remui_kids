<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Get Enrolled Users AJAX Endpoint
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
require_login();

// Get parameters
$courseid = required_param('courseid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key.']);
    die;
}

// Check if course exists
$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Course not found.']);
    die;
}

// Check if user has permission to view course enrollments
$context = context_course::instance($courseid);
if (!has_capability('moodle/course:view', $context) && !has_capability('moodle/course:viewhiddencourses', $context)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to view this course.']);
    die;
}

try {
    // Get enrolled users
    $enrolled_users = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.picture, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.firstname, u.lastname
        FROM {user} u
        INNER JOIN {user_enrolments} ue ON ue.userid = u.id
        INNER JOIN {enrol} e ON e.id = ue.enrolid
        WHERE e.courseid = ? 
        AND u.deleted = 0 
        AND u.suspended = 0
        AND ue.status = 0
        ORDER BY u.firstname ASC, u.lastname ASC
    ", [$courseid]);

    // Format user data
    $users = [];
    foreach ($enrolled_users as $user) {
        $users[] = [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'fullname' => fullname($user)
        ];
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving enrolled users: ' . $e->getMessage()
    ]);
}
?>
