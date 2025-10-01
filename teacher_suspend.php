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
 * Teacher Suspend Page
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

redirect_if_major_upgrade_required();

require_login();

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Get teacher ID from URL parameter
$teacherid = required_param('id', PARAM_INT);
$action = optional_param('action', 'suspend', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Set up the page exactly like schools.php
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/teacher_suspend.php', array('id' => $teacherid));
$PAGE->add_body_classes(['limitedwidth', 'page-myteachersuspend']);
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('teachersuspend-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Teacher Status Management - Riyada Trainings');
$PAGE->set_heading('Teacher Status Management');

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    try {
        global $DB;
        
        $action_type = required_param('action_type', PARAM_ALPHA);
        $reason = optional_param('reason', '', PARAM_TEXT);
        
        // Get teacher information
        $teacher = $DB->get_record('user', array('id' => $teacherid), 'id, firstname, lastname, suspended');
        
        if (!$teacher) {
            throw new moodle_exception('teachernotfound', 'theme_remui_kids');
        }
        
        // Prevent suspending/deactivating admin users
        if ($teacherid == $USER->id) {
            throw new moodle_exception('cannotsuspendself', 'theme_remui_kids');
        }
        
        if ($action_type === 'suspend') {
            // Suspend teacher
            $teacher->suspended = 1;
            $message = 'Teacher has been suspended successfully!';
            
            // Log the suspension
            $log_data = new stdClass();
            $log_data->userid = $teacherid;
            $log_data->action = 'suspended';
            $log_data->reason = $reason;
            $log_data->suspended_by = $USER->id;
            $log_data->timecreated = time();
            
            // Insert into custom suspension log table if it exists
            if ($DB->record_exists('user_suspension_log', array())) {
                $DB->insert_record('user_suspension_log', $log_data);
            }
            
        } elseif ($action_type === 'activate') {
            // Activate teacher
            $teacher->suspended = 0;
            $message = 'Teacher has been activated successfully!';
            
            // Log the activation
            $log_data = new stdClass();
            $log_data->userid = $teacherid;
            $log_data->action = 'activated';
            $log_data->reason = $reason;
            $log_data->activated_by = $USER->id;
            $log_data->timecreated = time();
            
            // Insert into custom activation log table if it exists
            if ($DB->record_exists('user_activation_log', array())) {
                $DB->insert_record('user_activation_log', $log_data);
            }
        }
        
        $DB->update_record('user', $teacher);
        $success_message = $message;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get teacher information from database
try {
    global $DB;
    
    // Get basic teacher information
    $teacher = $DB->get_record('user', array('id' => $teacherid), 
        'id, username, firstname, lastname, email, suspended, lastaccess');
    
    if (!$teacher) {
        throw new moodle_exception('teachernotfound', 'theme_remui_kids');
    }
    
    // Get teacher's courses count (with error handling)
    $courses_count = 0;
    try {
        $courses_count = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.id)
             FROM {course} c
             JOIN {context} ctx ON c.id = ctx.instanceid
             JOIN {role_assignments} ra ON ctx.id = ra.contextid
             WHERE ra.userid = ? AND ctx.contextlevel = 50",
            array($teacherid)
        );
    } catch (Exception $e) {
        // Courses count is optional
        $courses_count = 0;
    }
    
    // Get recent activity (with error handling)
    $recent_activity = array();
    try {
        $recent_activity = $DB->get_records_sql(
            "SELECT log.action, log.timecreated, c.fullname as coursename
             FROM {logstore_standard_log} log
             LEFT JOIN {course} c ON log.courseid = c.id
             WHERE log.userid = ? AND log.timecreated > ?
             ORDER BY log.timecreated DESC
             LIMIT 5",
            array($teacherid, time() - (30 * 24 * 60 * 60)) // Last 30 days
        );
    } catch (Exception $e) {
        // Recent activity is optional
        $recent_activity = array();
    }
    
    // Prepare data for template
    $template_data = array(
        'teacher' => $teacher,
        'wwwroot' => $CFG->wwwroot,
        'teacherid' => $teacherid,
        'courses_count' => $courses_count,
        'recent_activity' => array_values($recent_activity),
        'is_suspended' => $teacher->suspended,
        'status_class' => $teacher->suspended ? 'suspended' : 'active',
        'status_text' => $teacher->suspended ? 'Suspended' : 'Active',
        'last_access' => $teacher->lastaccess ? date('M d, Y H:i', $teacher->lastaccess) : 'Never',
        'success_message' => $success_message,
        'error_message' => $error_message,
        'sesskey' => sesskey()
    );
    
} catch (Exception $e) {
    $template_data = array(
        'error' => $e->getMessage(),
        'wwwroot' => $CFG->wwwroot
    );
}

// Output the page
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_remui_kids/teacher_suspend', $template_data);
echo $OUTPUT->footer();
?>
