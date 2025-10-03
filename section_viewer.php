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

// Get completion info
$completion = new completion_info($course);

// Process activities with completion status
$activities_with_completion = array();
$completed_count = 0;

foreach ($activities as $activity) {
    // Check if activity is completed
    try {
        $completion_data = $completion->get_data($activity, false);
        $is_completed = $completion_data->completionstate > 0;
    } catch (Exception $e) {
        error_log("Error checking completion for activity {$activity->id}: " . $e->getMessage());
        $is_completed = false;
    }
    
    if ($is_completed) {
        $completed_count++;
    }
    
    // Get activity icon
    $icon = 'file';
    if ($activity->modname == 'quiz') {
        $icon = 'question-circle';
    } elseif ($activity->modname == 'assign') {
        $icon = 'edit';
    } elseif ($activity->modname == 'forum') {
        $icon = 'comments';
    } elseif ($activity->modname == 'resource') {
        $icon = 'file-alt';
    } elseif ($activity->modname == 'url') {
        $icon = 'link';
    } elseif ($activity->modname == 'page') {
        $icon = 'file-text';
    } elseif ($activity->modname == 'label') {
        $icon = 'tag';
    }
    
    // Get activity URL
    $activity_url = $CFG->wwwroot . '/mod/' . $activity->modname . '/view.php?id=' . $activity->id;
    
    $activities_with_completion[] = array(
        'id' => $activity->id,
        'name' => $activity->name,
        'modname' => $activity->modname,
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

// Render template
echo $OUTPUT->render_from_template('theme_remui_kids/section_viewer', $templatecontext);