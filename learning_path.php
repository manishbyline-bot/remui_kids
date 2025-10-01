<?php
/**
 * Learning Path Page - Shows course structure and progression
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Get course ID from URL
$courseid = required_param('courseid', PARAM_INT);

// Set page context
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/learning_path.php', array('courseid' => $courseid));
$PAGE->set_title('Learning Path - Riyada Trainings');
$PAGE->set_heading('Learning Path');

// Get course information
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Check if user is enrolled in this course
$enrolment = $DB->get_record_sql("
    SELECT e.*, ue.timeenrolled, ue.timestarted, ue.timecompleted
    FROM {enrol} e
    JOIN {user_enrolments} ue ON e.id = ue.enrolid
    WHERE e.courseid = ? AND ue.userid = ? AND e.status = ?
", array($courseid, $USER->id, 0));

if (!$enrolment) {
    throw new moodle_exception('notenrolled', 'theme_remui_kids', $CFG->wwwroot);
}

// Get course sections data using our custom function
try {
    $sections_data = theme_remui_kids_get_course_sections_data($course);
} catch (Exception $e) {
    error_log("Learning Path Error: " . $e->getMessage());
    $sections_data = array();
}

// Calculate overall course progress
$total_sections = count($sections_data);
$completed_sections = 0;
$total_activities = 0;
$completed_activities = 0;

foreach ($sections_data as $section) {
    $total_activities += $section['total_activities'];
    $completed_activities += $section['completed_activities'];
    if ($section['is_completed']) {
        $completed_sections++;
    }
}

$course_progress = $total_activities > 0 ? round(($completed_activities / $total_activities) * 100) : 0;
$section_progress = $total_sections > 0 ? round(($completed_sections / $total_sections) * 100) : 0;

// Get course completion info
$completion = new completion_info($course);
$is_completion_enabled = $completion->is_enabled();

// Get course image
$course_image = $CFG->wwwroot . '/theme/remui_kids/pix/default_course.svg';
try {
    $fs = get_file_storage();
    $context = context_course::instance($courseid);
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'timemodified DESC', false);
    
    if (!empty($files)) {
        $file = reset($files);
        $course_image = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }
} catch (Exception $e) {
    // Use default image
}

// Get course duration and schedule info
$course_duration = 'Self-paced';
if ($course->enddate && $course->startdate) {
    $duration_days = round(($course->enddate - $course->startdate) / (24 * 60 * 60));
    if ($duration_days >= 7) {
        $duration_weeks = round($duration_days / 7);
        $course_duration = $duration_weeks . ' weeks';
    } else {
        $course_duration = $duration_days . ' days';
    }
}

// Add courseid to each section FIRST, before copying them
foreach ($sections_data as &$section) {
    $section['courseid'] = $courseid;
}
unset($section);

// Debug: Log the courseid and first section data
error_log("DEBUG Learning Path - courseid: " . $courseid);
if (!empty($sections_data)) {
    error_log("DEBUG Learning Path - First section courseid: " . ($sections_data[0]['courseid'] ?? 'NOT SET'));
}

// Get next section to work on
$next_section = null;
$current_section = null;
foreach ($sections_data as $section) {
    if (!$section['is_completed']) {
        if (!$current_section && $section['has_started']) {
            $current_section = $section;
        } elseif (!$next_section && !$section['has_started']) {
            $next_section = $section;
        }
    }
}

if (!$current_section && !$next_section) {
    // All sections completed
    $current_section = end($sections_data);
    $current_section['is_final'] = true;
} elseif (!$current_section) {
    $current_section = $next_section;
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'course' => $course,
    'courseid' => $courseid,
    'course_image' => $course_image,
    'course_duration' => $course_duration,
    'sections' => $sections_data,
    'total_sections' => $total_sections,
    'completed_sections' => $completed_sections,
    'total_activities' => $total_activities,
    'completed_activities' => $completed_activities,
    'course_progress' => $course_progress,
    'section_progress' => $section_progress,
    'is_completion_enabled' => $is_completion_enabled,
    'current_section' => $current_section,
    'next_section' => $next_section,
    'user_name' => fullname($USER),
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/my_learning.php'
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
    echo '<div class="alert alert-warning">Learning Path template not found.</div>';
}

echo $OUTPUT->footer();
