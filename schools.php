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

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
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

// Get total schools count (using course categories as schools)
$dashboarddata->totalschools = $DB->count_records('course_categories', array('parent' => 0));

// Get active schools (categories with visible courses)
$dashboarddata->activeschools = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT c.category) 
     FROM {course} c 
     JOIN {course_categories} cc ON c.category = cc.id 
     WHERE cc.parent = 0 AND c.visible = 1"
);

// Get suspended schools (categories without visible courses)
$dashboarddata->suspendedschools = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT cc.id) 
     FROM {course_categories} cc 
     LEFT JOIN {course} c ON cc.id = c.category AND c.visible = 1
     WHERE cc.parent = 0 AND c.id IS NULL"
);

// Get average courses per school
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
