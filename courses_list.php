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
    // Get all courses except site course - include course image field
    $courses = $DB->get_records_sql("
        SELECT c.*, cc.name as categoryname, 
               (SELECT COUNT(*) FROM {user_enrolments} ue 
                JOIN {enrol} e ON e.id = ue.enrolid 
                WHERE e.courseid = c.id) as enrollment_count
        FROM {course} c
        LEFT JOIN {course_categories} cc ON cc.id = c.category
        WHERE c.id > 1
        ORDER BY c.fullname ASC
    ");
    
    // Debug: Log course data
    error_log("=== COURSE DATA DEBUG ===");
    foreach ($courses as $course) {
        error_log("Course ID: {$course->id}, Name: {$course->fullname}");
        error_log("Course fields: " . print_r(array_keys((array)$course), true));
    }
    
    // Process courses to add image URLs
    foreach ($courses as $course) {
        $course->courseimage = null; // Initialize as null
        
        // Get course context for file URL
        $coursecontext = context_course::instance($course->id);
        $fs = get_file_storage();
        
        // Debug: Log all files in course context
        error_log("=== DEBUGGING COURSE {$course->id} ({$course->fullname}) ===");
        
        // Check all possible file areas for course images
        $file_areas = [
            'overviewfiles' => 'Course Overview Files',
            'summary' => 'Course Summary Files', 
            'section' => 'Course Section Files',
            'intro' => 'Course Intro Files',
            'courseimage' => 'Course Image Files',
            'courseoverviewfiles' => 'Course Overview Files (alt)'
        ];
        
        foreach ($file_areas as $area => $description) {
            error_log("Checking {$description} ({$area})...");
            $files = $fs->get_area_files($coursecontext->id, 'course', $area, 0, 'id DESC', false);
            error_log("Found " . count($files) . " files in {$area}");
            
            if (!empty($files)) {
                foreach ($files as $file) {
                    if ($file && $file->get_filesize() > 0) {
                        $filename = $file->get_filename();
                        $filesize = $file->get_filesize();
                        error_log("File: {$filename} (Size: {$filesize} bytes)");
                        
                        // Check if it's an image file
                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (in_array($extension, $image_extensions)) {
                            $image_url = moodle_url::make_pluginfile_url(
                                $file->get_contextid(),
                                $file->get_component(),
                                $file->get_filearea(),
                                $file->get_itemid(),
                                $file->get_filepath(),
                                $file->get_filename()
                            )->out();
                            
                            error_log("Found image: {$filename} -> {$image_url}");
                            $course->courseimage = $image_url;
                            break 2; // Found an image, stop looking in all areas
                        }
                    }
                }
            }
        }
        
        // Also check if course has a course image field in the database
        if (empty($course->courseimage) && !empty($course->courseimage)) {
            error_log("Course has courseimage field: {$course->courseimage}");
            // This might be a direct URL or file path
            if (filter_var($course->courseimage, FILTER_VALIDATE_URL)) {
                $course->courseimage = $course->courseimage;
            }
        }
        
        // Check for course image in course settings/configuration
        if (empty($course->courseimage)) {
            // Try to get course image from course settings
            $course_image_setting = get_config('core', 'courseimage');
            if (!empty($course_image_setting)) {
                error_log("Found course image setting: {$course_image_setting}");
                $course->courseimage = $course_image_setting;
            }
        }
        
        // Debug: Log course image status (remove in production)
        if (empty($course->courseimage)) {
            error_log("Course {$course->id} ({$course->fullname}) has no image");
        } else {
            error_log("Course {$course->id} ({$course->fullname}) has image: {$course->courseimage}");
        }
        
        // Additional debugging: Check if the image URL is accessible
        if (!empty($course->courseimage)) {
            // Try to access the image URL with a simple check
            $headers = @get_headers($course->courseimage);
            if (!$headers || strpos($headers[0], '200') === false) {
                error_log("Course {$course->id} image URL not accessible: {$course->courseimage}");
                error_log("Headers response: " . print_r($headers, true));
                
                // Don't set to null - keep the URL and let the browser handle it
                // The issue might be with the URL generation, but the file exists
                error_log("Keeping image URL despite accessibility check failure");
            } else {
                error_log("Course {$course->id} image URL is accessible: {$course->courseimage}");
            }
        }
        
        // Remove debug info - no longer needed
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
