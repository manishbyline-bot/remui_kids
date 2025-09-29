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
 * Teacher View Page
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Require login
require_login();

// Get teacher ID from URL parameter
$teacherid = required_param('id', PARAM_INT);

// Set up the page
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/theme/remui_kids/teacher_view.php', array('id' => $teacherid));
$PAGE->set_title('Teacher Details - Riyada Trainings');
$PAGE->set_heading('Teacher Details');

// Get teacher information from database
try {
    global $DB;
    
    // Get basic teacher information
    $teacher = $DB->get_record('user', array('id' => $teacherid), 
        'id, username, firstname, lastname, email, phone1, phone2, city, country, 
         lastaccess, timecreated, lastlogin, suspended, deleted');
    
    if (!$teacher) {
        throw new moodle_exception('teachernotfound', 'theme_remui_kids');
    }
    
    // Initialize empty arrays for optional data
    $role_assignments = array();
    $courses = array();
    $profile_fields = array();
    
    // Try to get teacher's role assignments (simplified)
    try {
        $role_assignments = $DB->get_records_sql(
            "SELECT ra.id, r.shortname, r.name
             FROM {role_assignments} ra
             JOIN {role} r ON ra.roleid = r.id
             WHERE ra.userid = ? AND ra.component = ''
             LIMIT 10",
            array($teacherid)
        );
    } catch (Exception $e) {
        // Role assignments are optional, continue without them
        $role_assignments = array();
    }
    
    // Try to get teacher's courses (simplified)
    try {
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.timecreated
             FROM {course} c
             JOIN {context} ctx ON c.id = ctx.instanceid
             JOIN {role_assignments} ra ON ctx.id = ra.contextid
             WHERE ra.userid = ? AND ctx.contextlevel = 50
             LIMIT 10",
            array($teacherid)
        );
    } catch (Exception $e) {
        // Courses are optional, continue without them
        $courses = array();
    }
    
    // Try to get basic profile information
    try {
        $profile_fields = $DB->get_records_sql(
            "SELECT uif.shortname, uif.name, uid.data
             FROM {user_info_field} uif
             LEFT JOIN {user_info_data} uid ON uif.id = uid.fieldid AND uid.userid = ?
             LIMIT 10",
            array($teacherid)
        );
    } catch (Exception $e) {
        // Profile fields are optional, continue without them
        $profile_fields = array();
    }
    
    // Prepare data for template
    $template_data = array(
        'teacher' => $teacher,
        'role_assignments' => array_values($role_assignments),
        'courses' => array_values($courses),
        'profile_fields' => array_values($profile_fields),
        'wwwroot' => $CFG->wwwroot,
        'teacherid' => $teacherid,
        'status_class' => $teacher->suspended ? 'suspended' : 'active',
        'status_text' => $teacher->suspended ? 'Suspended' : 'Active',
        'last_access' => $teacher->lastaccess ? date('M d, Y H:i', $teacher->lastaccess) : 'Never',
        'created_date' => date('M d, Y', $teacher->timecreated),
        'last_login' => $teacher->lastlogin ? date('M d, Y H:i', $teacher->lastlogin) : 'Never'
    );
    
} catch (Exception $e) {
    $template_data = array(
        'error' => $e->getMessage(),
        'wwwroot' => $CFG->wwwroot
    );
}

// Output the page
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_remui_kids/teacher_view', $template_data);
echo $OUTPUT->footer();
?>
