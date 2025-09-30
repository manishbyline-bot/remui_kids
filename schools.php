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
 * Schools Management Page
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

redirect_if_major_upgrade_required();

require_login();

// Check if user is admin - restrict access to admins only
$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if (!$hassiteconfig) {
    // User is not an admin, redirect to dashboard
    redirect(new moodle_url('/my/'), 'Access denied. This page is only available to administrators.', null, \core\output\notification::NOTIFY_ERROR);
}

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Start setting up the page exactly like courses.php
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/schools.php');
$PAGE->add_body_classes(['limitedwidth', 'page-myschools']);
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('schools-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Schools Management - Riyada Trainings');
$PAGE->set_heading('Schools Management');

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Add custom CSS for schools dashboard
$PAGE->requires->css('/theme/remui_kids/style/schools.css');


echo $OUTPUT->header();

if (core_userfeedback::should_display_reminder()) {
    core_userfeedback::print_reminder_block();
}

// Add dashboard cards data exactly like courses.php
global $DB;
$dashboarddata = new stdClass();

// Get real school data from IOMAD company tables
try {
    // Check if IOMAD company table exists
    $company_table_exists = $DB->get_manager()->table_exists('company');
    
    if ($company_table_exists) {
        // Get total schools count from company table
        $dashboarddata->totalschools = $DB->count_records('company');
        
        // Get active schools (companies that are not suspended)
        $dashboarddata->activeschools = $DB->count_records('company', array('suspended' => 0));
        
        // Get suspended schools (companies that are suspended)
        $dashboarddata->suspendedschools = $DB->count_records('company', array('suspended' => 1));
        
        // Get average users per school
        $avg_users = $DB->get_record_sql(
            "SELECT AVG(user_count) as average 
             FROM (
                 SELECT COUNT(cu.userid) as user_count 
                 FROM {company} c 
                 LEFT JOIN {company_users} cu ON c.id = cu.companyid
                 GROUP BY c.id
             ) as school_users"
        );
        $dashboarddata->averagecourses = round($avg_users->average, 1);
        
    } else {
        // Fallback to course categories if company table doesn't exist
        $dashboarddata->totalschools = $DB->count_records('course_categories', array('parent' => 0));
        $dashboarddata->activeschools = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.category) 
             FROM {course} c 
             JOIN {course_categories} cc ON c.category = cc.id 
             WHERE cc.parent = 0 AND c.visible = 1"
        );
        $dashboarddata->suspendedschools = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.id) 
             FROM {course_categories} cc 
             LEFT JOIN {course} c ON cc.id = c.category AND c.visible = 1
             WHERE cc.parent = 0 AND c.id IS NULL"
        );
        $avg_courses = $DB->get_record_sql(
            "SELECT AVG(course_count) as average 
             FROM (
                 SELECT COUNT(c.id) as course_count 
                 FROM {course_categories} cc 
                 LEFT JOIN {course} c ON cc.id = c.category AND c.visible = 1
                 WHERE cc.parent = 0 
                 GROUP BY cc.id
             ) as school_courses"
        );
        $dashboarddata->averagecourses = round($avg_courses->average, 1);
    }
    
} catch (Exception $e) {
    // Fallback values if there are any database errors
    $dashboarddata->totalschools = 0;
    $dashboarddata->activeschools = 0;
    $dashboarddata->suspendedschools = 0;
    $dashboarddata->averagecourses = 0;
}

// Add wwwroot for links
$dashboarddata->wwwroot = $CFG->wwwroot;

// Render dashboard cards using the same template structure as courses
echo $OUTPUT->render_from_template('theme_remui_kids/schools_dashboard', $dashboarddata);

echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();

// Trigger schools has been viewed event.
$eventparams = array('context' => $context);
$event = \core\event\mycourses_viewed::create($eventparams);
$event->trigger();
?>
