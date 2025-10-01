<?php
/**
 * Section Viewer Page - Shows individual course section with activities
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

// Get parameters
$courseid = required_param('courseid', PARAM_INT);
$sectionnum = required_param('section', PARAM_INT);

// Set page context
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/section_viewer.php', array('courseid' => $courseid, 'section' => $sectionnum));
$PAGE->set_title('Section - Riyada Trainings');
$PAGE->set_heading('Course Section');

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

// Get section information
$modinfo = get_fast_modinfo($course);
$section = $modinfo->get_section_info($sectionnum);

if (!$section || !$section->visible) {
    throw new moodle_exception('sectionnotavailable', 'theme_remui_kids');
}

// Get section activities
$section_data = theme_remui_kids_get_section_activities($course, $sectionnum);
$activities = $section_data['activities'];

// Get section name
$section_name = get_section_name($course, $section);

// Calculate section progress
$total_activities = count($activities);
$completed_activities = 0;
$started_activities = 0;

foreach ($activities as $activity) {
    if ($activity['is_completed']) {
        $completed_activities++;
    }
    if ($activity['has_started']) {
        $started_activities++;
    }
}

$section_progress = $total_activities > 0 ? round(($completed_activities / $total_activities) * 100) : 0;
$is_section_completed = ($section_progress == 100 && $total_activities > 0);

// Get completion info
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

// Get next and previous sections
$all_sections = theme_remui_kids_get_course_sections_data($course);
$current_index = null;
$prev_section = null;
$next_section = null;

foreach ($all_sections as $index => $section_data) {
    if ($section_data['section'] == $sectionnum) {
        $current_index = $index;
        break;
    }
}

if ($current_index !== null) {
    if ($current_index > 0) {
        $prev_section = $all_sections[$current_index - 1];
        $prev_section['courseid'] = $courseid;
    }
    if ($current_index < count($all_sections) - 1) {
        $next_section = $all_sections[$current_index + 1];
        $next_section['courseid'] = $courseid;
    }
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'course' => $course,
    'courseid' => $courseid,
    'course_image' => $course_image,
    'section' => array(
        'id' => $section->id,
        'section' => $sectionnum,
        'name' => $section_name,
        'summary' => $section->summary,
        'progress' => $section_progress,
        'is_completed' => $is_section_completed,
        'total_activities' => $total_activities,
        'completed_activities' => $completed_activities,
        'started_activities' => $started_activities
    ),
    'activities' => $activities,
    'is_completion_enabled' => $is_completion_enabled,
    'prev_section' => $prev_section,
    'next_section' => $next_section,
    'user_name' => fullname($USER),
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/learning_path.php?courseid=' . $courseid
);

// Output the page
echo $OUTPUT->header();

// Include section viewer template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/section_viewer.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Section Viewer template not found.</div>';
}

echo $OUTPUT->footer();

