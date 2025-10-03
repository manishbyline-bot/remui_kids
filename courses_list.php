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
 * Courses List Page
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

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/courses_list.php');
$PAGE->add_body_classes(['page-courseslist', 'fullwidth-layout']);
$PAGE->set_pagelayout('mycourses');
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

$PAGE->set_pagetype('courseslist-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('All Courses - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Add custom CSS for courses list
$PAGE->requires->css('/theme/remui_kids/style/courses.css');

echo $OUTPUT->header();

if (core_userfeedback::should_display_reminder()) {
    core_userfeedback::print_reminder_block();
}

// Get all courses from database
global $DB;
$coursesdata = new stdClass();

try {
    // Get all courses except site course with course image
    $courses = $DB->get_records_sql("
        SELECT c.*, cc.name as categoryname, 
               (SELECT COUNT(*) FROM {user_enrolments} ue 
                JOIN {enrol} e ON e.id = ue.enrolid 
                WHERE e.courseid = c.id) as enrollment_count,
               (SELECT f.filename FROM {files} f 
                WHERE f.component = 'course' AND f.filearea = 'overviewfiles' 
                AND f.itemid = c.id AND f.filesize > 0 
                ORDER BY f.id DESC LIMIT 1) as courseimage
        FROM {course} c
        LEFT JOIN {course_categories} cc ON cc.id = c.category
        WHERE c.id > 1
        ORDER BY c.fullname ASC
    ");
    
    // Process courses to add image URLs
    foreach ($courses as $course) {
        if (!empty($course->courseimage)) {
            // Get course context for file URL
            $coursecontext = context_course::instance($course->id);
            $fs = get_file_storage();
            $files = $fs->get_area_files($coursecontext->id, 'course', 'overviewfiles', 0, 'id DESC', false);
            if (!empty($files)) {
                $file = reset($files);
                $course->courseimage = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                )->out();
            } else {
                $course->courseimage = null;
            }
        }
    }
    
    $coursesdata->courses = array_values($courses);
    $coursesdata->totalcourses = count($courses);
    
} catch (Exception $e) {
    $coursesdata->courses = [];
    $coursesdata->totalcourses = 0;
}

// Add wwwroot for links and session key
$coursesdata->wwwroot = $CFG->wwwroot;
$coursesdata->sesskey = sesskey();

// Render courses list
echo $OUTPUT->render_from_template('theme_remui_kids/courses_list', $coursesdata);

echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();

// Trigger event
$eventparams = array('context' => $context);
$event = \core\event\course_viewed::create($eventparams);
$event->trigger();
?>
