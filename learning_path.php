<?php
/**
 * Learning Path Page - Display course sections and activities
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Get course ID from URL parameter
$courseid = optional_param('courseid', 0, PARAM_INT);

if (!$courseid) {
    // If no course ID provided, redirect to course listing
    redirect($CFG->wwwroot . '/course/index.php');
}

// Get course record
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Set page context
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/learning_path.php', array('courseid' => $courseid));
$PAGE->set_title($course->fullname . ' - Learning Path');
$PAGE->set_heading($course->fullname);

// Check if user is enrolled in the course
if (!is_enrolled($context, $USER->id)) {
    throw new moodle_exception('notenrolled', 'theme_remui_kids');
}

// Get course sections from database
try {
    $sections = $DB->get_records('course_sections', 
        array('course' => $courseid), 
        'section ASC'
    );
} catch (Exception $e) {
    error_log("Error fetching course sections: " . $e->getMessage());
    $sections = array();
}

// Get course image
$course_image = '';
$course_image_url = '';
$fs = get_file_storage();
$context = context_course::instance($courseid);
$files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder', false);

if (!empty($files)) {
    $file = reset($files);
    $course_image = $CFG->wwwroot . '/theme/remui_kids/course_image.php?courseid=' . $courseid;
    $course_image_url = $course_image;
} else {
    // Use default course image
    $course_image = $CFG->wwwroot . '/theme/remui_kids/pix/default_course.svg';
    $course_image_url = $course_image;
}

// Prepare sections data with completion logic
$sections_data = array();
$previous_section_completed = true; // First section is always unlocked

// Check if we have any sections
if (empty($sections)) {
    // If no sections found, create a default message
    $sections_data = array();
} else {
    foreach ($sections as $section) {
    if ($section->section == 0) {
        continue; // Skip general section
    }
    
    if ($section->visible == 0) {
        continue; // Skip hidden sections
    }
    
    // Get activities for this section
    try {
        $activities = $DB->get_records_sql("
            SELECT cm.*, m.name as modname, m.icon
            FROM {course_modules} cm
            JOIN {modules} m ON cm.module = m.id
            WHERE cm.course = ? AND cm.section = ? AND cm.visible = 1
            ORDER BY cm.section, cm.sectionsequence
        ", array($courseid, $section->section));
    } catch (Exception $e) {
        error_log("Error fetching activities for section {$section->section}: " . $e->getMessage());
        $activities = array();
    }
    
    // Check completion status for each activity
    $activities_with_completion = array();
    $all_activities_completed = true;
    $completed_count = 0;
    
    foreach ($activities as $activity) {
        // Check if activity is completed
        try {
            $completion = $DB->get_record('course_modules_completion', array(
                'coursemoduleid' => $activity->id,
                'userid' => $USER->id
            ));
            
            $is_completed = $completion && $completion->completionstate > 0;
        } catch (Exception $e) {
            error_log("Error checking completion for activity {$activity->id}: " . $e->getMessage());
            $is_completed = false;
        }
        if ($is_completed) {
            $completed_count++;
        } else {
            $all_activities_completed = false;
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
        }
        
        $activities_with_completion[] = array(
            'id' => $activity->id,
            'name' => $activity->name,
            'modname' => $activity->modname,
            'icon' => $icon,
            'completed' => $is_completed,
            'url' => $CFG->wwwroot . '/mod/' . $activity->modname . '/view.php?id=' . $activity->id
        );
    }
    
    // Determine if this section is unlocked
    $is_unlocked = $previous_section_completed;
    $is_completed = $all_activities_completed && count($activities) > 0;
    
    // Update previous section completion status for next iteration
    $previous_section_completed = $is_completed;
    
    $sections_data[] = array(
        'section' => $section->section,
        'name' => $section->name ?: 'Day ' . $section->section,
        'summary' => $section->summary,
        'activities' => $activities_with_completion,
        'activity_count' => count($activities_with_completion),
        'completed_count' => $completed_count,
        'completion_percentage' => count($activities_with_completion) > 0 ? 
            round(($completed_count / count($activities_with_completion)) * 100) : 0,
        'is_unlocked' => $is_unlocked,
        'is_completed' => $is_completed,
        'is_locked' => !$is_unlocked,
        'courseid' => $courseid,
        'last' => false // Will be set later
    );
    }
}

// Set last property for the final section
if (!empty($sections_data)) {
    $sections_data[count($sections_data) - 1]['last'] = true;
}

// Get current section (first section by default)
$current_section = null;
$next_section = null;

if (!empty($sections_data)) {
    $current_section = $sections_data[0];
    
    if (count($sections_data) > 1) {
        $next_section = $sections_data[1];
    }
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'course' => $course,
    'courseid' => $courseid,
    'course_image' => $course_image,
    'course_image_url' => $course_image_url,
    'sections' => $sections_data,
    'current_section' => $current_section,
    'next_section' => $next_section,
    'has_sections' => count($sections_data) > 0,
    'back_url' => $CFG->wwwroot . '/course/index.php'
);

// Output the page
echo $OUTPUT->header();

// Include learning path template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/learning_path.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Learning path template not found.</div>';
}

echo $OUTPUT->footer();