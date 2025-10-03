<?php
/**
 * My Learning Page - Trainee Course Dashboard
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/my_learning.php');
$PAGE->set_title('My Learning - Riyada Trainings');
$PAGE->set_heading('My Learning');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get enrolled courses with detailed information
$enrolled_courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname, c.summary, c.startdate, c.enddate,
           c.timecreated, c.timemodified, c.category, c.format,
           cc.timeenrolled, cc.timestarted, cc.timecompleted,
           CASE WHEN cc.timecompleted IS NOT NULL THEN 'completed'
                WHEN cc.timestarted IS NOT NULL THEN 'in_progress'
                ELSE 'not_started' END as status,
           cat.name as categoryname
    FROM {enrol} e
    JOIN {user_enrolments} ue ON e.id = ue.enrolid
    JOIN {course} c ON e.courseid = c.id
    LEFT JOIN {course_completions} cc ON c.id = cc.course AND cc.userid = ?
    LEFT JOIN {course_categories} cat ON c.category = cat.id
    WHERE ue.userid = ? AND e.status = ? AND c.visible = ?
    ORDER BY cc.timeenrolled DESC, c.fullname ASC
", array($userid, $userid, 0, 1));

// Calculate progress for each course
$courses_with_progress = array();
foreach ($enrolled_courses as $course) {
    // Get course completion data using correct API
    $total_activities = 0;
    $completed_activities = 0;
    $progress_percentage = 0;
    
    // Simplified progress calculation using course completion data
    $total_activities = 10; // Default total activities
    $completed_activities = 0;
    $progress_percentage = 0;
    
    // Method 1: Use course completion record if available
    if ($course->timecompleted) {
        // Course is completed
        $progress_percentage = 100;
        $completed_activities = $total_activities;
    } else {
        // Method 2: Use course status for progress estimation
        switch ($course->status) {
            case 'completed':
                $progress_percentage = 100;
                $completed_activities = $total_activities;
                break;
            case 'in_progress':
                // Estimate progress based on time started
                if ($course->timestarted) {
                    $time_elapsed = time() - $course->timestarted;
                    $time_weeks = $time_elapsed / (7 * 24 * 60 * 60);
                    // Assume 52 week course, calculate rough progress
                    $progress_percentage = min(95, round(($time_weeks / 52) * 100));
                    $completed_activities = round(($progress_percentage / 100) * $total_activities);
                } else {
                    $progress_percentage = 5; // Just started
                    $completed_activities = 1;
                }
                break;
            case 'not_started':
            default:
                $progress_percentage = 0;
                $completed_activities = 0;
                break;
        }
    }
    
    // Method 3: Try to get real completion data if available
    try {
        if (class_exists('completion_info')) {
            $completion = new completion_info($course);
            if ($completion->is_enabled()) {
                // Get course completion percentage from Moodle
                $completion_data = $completion->get_completions($userid);
                if (!empty($completion_data)) {
                    $total_found = count($completion_data);
                    $completed_found = 0;
                    
                    foreach ($completion_data as $completion_item) {
                        if ($completion_item->is_complete()) {
                            $completed_found++;
                        }
                    }
                    
                    if ($total_found > 0) {
                        $progress_percentage = round(($completed_found / $total_found) * 100);
                        $completed_activities = $completed_found;
                        $total_activities = $total_found;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Keep the status-based progress if completion API fails
        // This is already set above
    }
    
    // Get course image (simplified approach)
    $course_image = $CFG->wwwroot . '/theme/remui_kids/pix/default_course.svg';
    $course_image_url = $course_image;
    
    // Try to get course image from course files if available
    try {
        $fs = get_file_storage();
        $context = context_course::instance($course->id);
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'timemodified DESC', false);
        
        if (!empty($files)) {
            // Use our custom image handler
            $course_image = $CFG->wwwroot . '/theme/remui_kids/course_image.php?courseid=' . $course->id;
            $course_image_url = $course_image;
        }
    } catch (Exception $e) {
        // If there's any error, use default image
        $course_image = $CFG->wwwroot . '/theme/remui_kids/pix/default_course.svg';
    }
    
    // Get course duration (approximate)
    $course_duration = '52 weeks'; // Default
    if ($course->enddate && $course->startdate) {
        $duration_weeks = round(($course->enddate - $course->startdate) / (7 * 24 * 60 * 60));
        $course_duration = $duration_weeks . ' weeks';
    }
    
    // Get course level (based on category or default)
    $course_level = 'Beginner'; // Default
    if ($course->categoryname) {
        if (stripos($course->categoryname, 'advanced') !== false) {
            $course_level = 'Advanced';
        } elseif (stripos($course->categoryname, 'intermediate') !== false) {
            $course_level = 'Intermediate';
        }
    }
    
    // Get course format
    $course_format = 'ILT'; // Default
    if ($course->format) {
        switch ($course->format) {
            case 'online':
                $course_format = 'VILT';
                break;
            case 'blended':
                $course_format = 'ILT';
                break;
            default:
                $course_format = 'ILT';
        }
    }
    
    $courses_with_progress[] = array(
        'id' => $course->id,
        'fullname' => $course->fullname,
        'shortname' => $course->shortname,
        'summary' => $course->summary,
        'status' => $course->status,
        'progress_percentage' => $progress_percentage,
        'completed_activities' => $completed_activities,
        'total_activities' => $total_activities,
        'course_image' => $course_image,
        'course_image_url' => $course_image_url,
        'course_duration' => $course_duration,
        'course_level' => $course_level,
        'course_format' => $course_format,
        'categoryname' => $course->categoryname,
        'timeenrolled' => $course->timeenrolled,
        'timestarted' => $course->timestarted,
        'timecompleted' => $course->timecompleted,
        'course_url' => $CFG->wwwroot . '/course/view.php?id=' . $course->id
    );
}

// Get course statistics
$total_courses = count($courses_with_progress);
$completed_courses = count(array_filter($courses_with_progress, function($course) {
    return $course['status'] === 'completed';
}));
$in_progress_courses = count(array_filter($courses_with_progress, function($course) {
    return $course['status'] === 'in_progress';
}));
$not_started_courses = count(array_filter($courses_with_progress, function($course) {
    return $course['status'] === 'not_started';
}));

// Calculate average progress
$total_progress = array_sum(array_column($courses_with_progress, 'progress_percentage'));
$average_progress = $total_courses > 0 ? round($total_progress / $total_courses) : 0;

// Prepare template data
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'courses' => $courses_with_progress,
    'total_courses' => $total_courses,
    'completed_courses' => $completed_courses,
    'in_progress_courses' => $in_progress_courses,
    'not_started_courses' => $not_started_courses,
    'average_progress' => $average_progress,
    'has_courses' => count($courses_with_progress) > 0
);

// Output the page
echo $OUTPUT->header();

// Include learning template directly
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/my_learning.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">My Learning template not found.</div>';
}

echo $OUTPUT->footer();
