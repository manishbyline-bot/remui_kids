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

require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/completionlib.php');
require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/lib/badgeslib.php');
require_once($CFG->dirroot . '/lib/modinfolib.php');
require_once(__DIR__ . '/lib.php');

// Get parameters
$courseid = required_param('courseid', PARAM_INT);
$section = optional_param('section', 1, PARAM_INT);

// Get course and section
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$section_record = $DB->get_record('course_sections', array('course' => $courseid, 'section' => $section), '*', MUST_EXIST);

// Require login and course access
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

// Set page context
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/section_viewer.php', array('courseid' => $courseid, 'section' => $section));
$PAGE->set_title($course->fullname . ' - ' . $section_record->name);
$PAGE->set_heading($course->fullname);

// Get course image
$course_image = null;
$course_image_url = $CFG->wwwroot . '/theme/remui_kids/pix/default_course.svg';

try {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder', false);
    if (!empty($files)) {
        $file = reset($files);
        $course_image_url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }
} catch (Exception $e) {
    error_log("Error getting course image: " . $e->getMessage());
}

// Get section activities using the theme function
$section_data = theme_remui_kids_get_section_activities($course, $section);
$activities = $section_data['activities'];

// Debug: Log activities data
error_log("Section activities count: " . count($activities));
if (!empty($activities)) {
    error_log("First activity: " . print_r($activities[0], true));
}

// Get completion info
$completion = new completion_info($course);

// Process activities with completion status
$activities_with_completion = array();
$completed_count = 0;

foreach ($activities as $activity) {
    // Use completion status from theme function
    $is_completed = $activity['is_completed'] ?? false;
    
    if ($is_completed) {
        $completed_count++;
    }
    
    // Get activity icon
    $icon = 'file';
    if ($activity['modname'] == 'quiz') {
        $icon = 'question-circle';
    } elseif ($activity['modname'] == 'assign') {
        $icon = 'edit';
    } elseif ($activity['modname'] == 'forum') {
        $icon = 'comments';
    } elseif ($activity['modname'] == 'resource') {
        $icon = 'file-alt';
    } elseif ($activity['modname'] == 'url') {
        $icon = 'link';
    } elseif ($activity['modname'] == 'page') {
        $icon = 'file-text';
    } elseif ($activity['modname'] == 'label') {
        $icon = 'tag';
    }
    
    // Get activity URL
    $activity_url = $CFG->wwwroot . '/mod/' . $activity['modname'] . '/view.php?id=' . $activity['id'];
    
    $activities_with_completion[] = array(
        'id' => $activity['id'],
        'name' => $activity['name'],
        'modname' => $activity['modname'],
        'icon' => $icon,
        'completed' => $is_completed,
        'url' => $activity_url,
        'last' => false // Will be set later
    );
}

// Set last property for the final activity
if (!empty($activities_with_completion)) {
    $activities_with_completion[count($activities_with_completion) - 1]['last'] = true;
}

// Calculate progress
$total_activities = count($activities_with_completion);
$progress_percentage = $total_activities > 0 ? round(($completed_count / $total_activities) * 100) : 0;

// Debug: Log processed activities
error_log("Processed activities count: " . count($activities_with_completion));
if (!empty($activities_with_completion)) {
    error_log("First processed activity: " . print_r($activities_with_completion[0], true));
}

// Get navigation sections
$all_sections = $DB->get_records('course_sections', array('course' => $courseid), 'section ASC');
$current_index = null;
$prev_section = null;
$next_section = null;

foreach ($all_sections as $index => $section_record) {
    if ($section_record->section == $section) {
        $current_index = $index;
        break;
    }
}

if ($current_index !== null) {
    $section_array = array_values($all_sections);
    
    if ($current_index > 0) {
        $prev_section = $section_array[$current_index - 1];
        $prev_section['courseid'] = $courseid;
    }
    
    if ($current_index < count($section_array) - 1) {
        $next_section = $section_array[$current_index + 1];
        $next_section['courseid'] = $courseid;
    }
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'course' => $course,
    'courseid' => $courseid,
    'section' => $section,
    'section_name' => $section_record->name ?: 'Section ' . $section,
    'section_summary' => $section_record->summary,
    'activities' => $activities_with_completion,
    'total_activities' => $total_activities,
    'completed_count' => $completed_count,
    'progress_percentage' => $progress_percentage,
    'prev_section' => $prev_section,
    'next_section' => $next_section,
    'course_image_url' => $course_image_url,
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/learning_path.php?courseid=' . $courseid
);

// Output the page
echo $OUTPUT->header();

// Include section viewer template directly
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/section_viewer.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Section viewer template not found.</div>';
}

echo $OUTPUT->footer();