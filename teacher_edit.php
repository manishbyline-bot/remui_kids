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
 * Teacher Edit Page
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
$action = optional_param('action', 'edit', PARAM_ALPHA);

// Set up the page exactly like schools.php
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/teacher_edit.php', array('id' => $teacherid));
$PAGE->add_body_classes(['limitedwidth', 'page-myteacheredit']);
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('teacheredit-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Edit Teacher - Riyada Trainings');
$PAGE->set_heading('Edit Teacher');

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    try {
        global $DB;
        
        // Get form data
        $firstname = required_param('firstname', PARAM_TEXT);
        $lastname = required_param('lastname', PARAM_TEXT);
        $email = required_param('email', PARAM_EMAIL);
        $phone1 = optional_param('phone1', '', PARAM_TEXT);
        $phone2 = optional_param('phone2', '', PARAM_TEXT);
        $city = optional_param('city', '', PARAM_TEXT);
        $country = optional_param('country', '', PARAM_TEXT);
        $department = optional_param('department', '', PARAM_TEXT);
        $specialization = optional_param('specialization', '', PARAM_TEXT);
        
        // Validate email uniqueness
        $existing_user = $DB->get_record('user', array('email' => $email), 'id');
        if ($existing_user && $existing_user->id != $teacherid) {
            throw new moodle_exception('emailalreadytaken', 'theme_remui_kids');
        }
        
        // Update teacher information
        $teacher_data = array(
            'id' => $teacherid,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'phone1' => $phone1,
            'phone2' => $phone2,
            'city' => $city,
            'country' => $country
        );
        
        $DB->update_record('user', $teacher_data);
        
        // Update custom profile fields
        if ($department) {
            $field = $DB->get_record('user_info_field', array('shortname' => 'department'));
            if ($field) {
                $existing_data = $DB->get_record('user_info_data', 
                    array('userid' => $teacherid, 'fieldid' => $field->id));
                
                if ($existing_data) {
                    $existing_data->data = $department;
                    $DB->update_record('user_info_data', $existing_data);
                } else {
                    $new_data = new stdClass();
                    $new_data->userid = $teacherid;
                    $new_data->fieldid = $field->id;
                    $new_data->data = $department;
                    $DB->insert_record('user_info_data', $new_data);
                }
            }
        }
        
        if ($specialization) {
            $field = $DB->get_record('user_info_field', array('shortname' => 'specialization'));
            if ($field) {
                $existing_data = $DB->get_record('user_info_data', 
                    array('userid' => $teacherid, 'fieldid' => $field->id));
                
                if ($existing_data) {
                    $existing_data->data = $specialization;
                    $DB->update_record('user_info_data', $existing_data);
                } else {
                    $new_data = new stdClass();
                    $new_data->userid = $teacherid;
                    $new_data->fieldid = $field->id;
                    $new_data->data = $specialization;
                    $DB->insert_record('user_info_data', $new_data);
                }
            }
        }
        
        $success_message = 'Teacher information updated successfully!';
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get teacher information from database
try {
    global $DB;
    
    // Get basic teacher information
    $teacher = $DB->get_record('user', array('id' => $teacherid), 
        'id, username, firstname, lastname, email, phone1, phone2, city, country');
    
    if (!$teacher) {
        throw new moodle_exception('teachernotfound', 'theme_remui_kids');
    }
    
    // Get custom profile fields (with error handling)
    $department_field = null;
    $specialization_field = null;
    
    try {
        $department_field = $DB->get_record_sql(
            "SELECT uid.data
             FROM {user_info_data} uid
             JOIN {user_info_field} uif ON uid.fieldid = uif.id
             WHERE uid.userid = ? AND uif.shortname = 'department'",
            array($teacherid)
        );
    } catch (Exception $e) {
        // Department field is optional
        $department_field = null;
    }
    
    try {
        $specialization_field = $DB->get_record_sql(
            "SELECT uid.data
             FROM {user_info_data} uid
             JOIN {user_info_field} uif ON uid.fieldid = uif.id
             WHERE uid.userid = ? AND uif.shortname = 'specialization'",
            array($teacherid)
        );
    } catch (Exception $e) {
        // Specialization field is optional
        $specialization_field = null;
    }
    
    // Get countries list and format for template
    $countries_raw = get_string_manager()->get_list_of_countries();
    $countries = array();
    foreach ($countries_raw as $code => $name) {
        $countries[] = array(
            'code' => $code,
            'name' => $name,
            'selected' => ($teacher->country == $code)
        );
    }
    
    // Prepare data for template
    $template_data = array(
        'teacher' => $teacher,
        'wwwroot' => $CFG->wwwroot,
        'teacherid' => $teacherid,
        'department' => $department_field ? $department_field->data : '',
        'specialization' => $specialization_field ? $specialization_field->data : '',
        'countries' => $countries,
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
echo $OUTPUT->render_from_template('theme_remui_kids/teacher_edit', $template_data);
echo $OUTPUT->footer();
?>
