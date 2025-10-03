<?php
/**
 * AJAX endpoint to get course metrics
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Get course ID from request
$courseid = required_param('courseid', PARAM_INT);

// Get course data
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Check if user is enrolled in this course
$enrolled = is_enrolled(context_course::instance($courseid), $USER->id);
if (!$enrolled) {
    http_response_code(403);
    echo json_encode(array('error' => 'Not enrolled in this course'));
    exit;
}

// Initialize default values
$cefr_level = 'A2';
$hours_completed = 0;
$events_completed = 0;
$total_events = 0;
$next_event = null;

// Get course completion data
try {
    $completion = new completion_info($course);
    if ($completion->is_enabled()) {
        $completion_data = $completion->get_completions($USER->id);
        
        // Calculate events completed
        $total_events = count($completion_data);
        $events_completed = 0;
        
        foreach ($completion_data as $completion_item) {
            if ($completion_item->is_complete()) {
                $events_completed++;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting completion data: " . $e->getMessage());
}

// Get course enrollment data
$enrollment = $DB->get_record('user_enrolments', array(
    'userid' => $USER->id,
    'enrolid' => $DB->get_field('enrol', 'id', array('courseid' => $courseid, 'status' => 0))
));

// Calculate hours completed (estimate based on course progress)
if ($enrollment && $enrollment->timecompleted) {
    // Course completed - estimate total hours
    $hours_completed = 120; // Default total hours
} elseif ($enrollment && $enrollment->timestarted) {
    // Course in progress - estimate based on time elapsed
    $time_elapsed = time() - $enrollment->timestarted;
    $time_weeks = $time_elapsed / (7 * 24 * 60 * 60);
    $estimated_progress = min(95, ($time_weeks / 52) * 100); // Assume 52 week course
    $hours_completed = round((120 * $estimated_progress) / 100);
}

// Get CEFR level based on course category or progress
$category = $DB->get_record('course_categories', array('id' => $course->category));
if ($category) {
    if (stripos($category->name, 'advanced') !== false) {
        $cefr_level = 'C1';
    } elseif (stripos($category->name, 'intermediate') !== false) {
        $cefr_level = 'B2';
    } elseif (stripos($category->name, 'beginner') !== false) {
        $cefr_level = 'A1';
    }
}

// Determine CEFR level based on progress
if ($events_completed > 0 && $total_events > 0) {
    $progress_percentage = ($events_completed / $total_events) * 100;
    if ($progress_percentage >= 80) {
        $cefr_level = 'C1';
    } elseif ($progress_percentage >= 60) {
        $cefr_level = 'B2';
    } elseif ($progress_percentage >= 40) {
        $cefr_level = 'B1';
    } elseif ($progress_percentage >= 20) {
        $cefr_level = 'A2';
    } else {
        $cefr_level = 'A1';
    }
}

// Get next event (mock data for now - in real implementation, get from course modules)
if ($events_completed < $total_events) {
    $next_event = array(
        'title' => 'Next Learning Module',
        'date' => date('M d, Y', strtotime('+1 week')),
        'type' => 'eLearning'
    );
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(array(
    'cefr_level' => $cefr_level,
    'hours_completed' => $hours_completed,
    'total_hours' => 120,
    'events_completed' => $events_completed,
    'total_events' => $total_events,
    'next_event' => $next_event,
    'has_next_event' => !empty($next_event)
));
