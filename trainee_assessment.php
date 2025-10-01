<?php
/**
 * Trainee Assessment Page - Shows all assessments, grades, and feedback
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_assessment.php');
$PAGE->set_title('My Assessments - Riyada Trainings');
$PAGE->set_heading('My Assessments');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get all enrolled courses
$enrolled_courses = enrol_get_users_courses($userid, true, 'id, fullname, shortname');

// Collect all assessments data
$assessments_data = array();
$total_assessments = 0;
$completed_assessments = 0;
$pending_assessments = 0;
$overdue_assessments = 0;
$average_grade = 0;
$total_grades = 0;
$grade_count = 0;

foreach ($enrolled_courses as $course) {
    // Get all assignments in this course
    $assignments = $DB->get_records('assign', array('course' => $course->id));
    
    foreach ($assignments as $assignment) {
        $cm = get_coursemodule_from_instance('assign', $assignment->id);
        if (!$cm || !$cm->visible) {
            continue;
        }
        
        // Get submission status
        $submission = $DB->get_record('assign_submission', array(
            'assignment' => $assignment->id,
            'userid' => $userid,
            'latest' => 1
        ));
        
        // Get grade
        $grade = $DB->get_record('assign_grades', array(
            'assignment' => $assignment->id,
            'userid' => $userid
        ));
        
        $status = 'not_submitted';
        $status_text = 'Not Submitted';
        $is_completed = false;
        $is_overdue = false;
        $grade_percentage = null;
        $grade_text = 'Not Graded';
        
        // Determine status
        if ($submission && $submission->status === 'submitted') {
            if ($grade && $grade->grade >= 0) {
                $status = 'graded';
                $status_text = 'Graded';
                $is_completed = true;
                
                // Calculate percentage
                if ($assignment->grade > 0) {
                    $grade_percentage = round(($grade->grade / $assignment->grade) * 100);
                    $grade_text = $grade_percentage . '%';
                    $total_grades += $grade_percentage;
                    $grade_count++;
                }
            } else {
                $status = 'submitted';
                $status_text = 'Awaiting Grade';
                $is_completed = true;
            }
        } else {
            // Check if overdue
            if ($assignment->duedate > 0 && $assignment->duedate < time()) {
                $status = 'overdue';
                $status_text = 'Overdue';
                $is_overdue = true;
            }
        }
        
        $total_assessments++;
        
        if ($is_completed) {
            $completed_assessments++;
        } elseif ($is_overdue) {
            $overdue_assessments++;
        } else {
            $pending_assessments++;
        }
        
        $assessments_data[] = array(
            'id' => $assignment->id,
            'cmid' => $cm->id,
            'name' => $assignment->name,
            'course_name' => $course->fullname,
            'course_id' => $course->id,
            'intro' => strip_tags($assignment->intro),
            'status' => $status,
            'status_text' => $status_text,
            'is_completed' => $is_completed,
            'is_overdue' => $is_overdue,
            'is_pending' => !$is_completed && !$is_overdue,
            'grade_text' => $grade_text,
            'grade_percentage' => $grade_percentage,
            'due_date' => $assignment->duedate > 0 ? userdate($assignment->duedate, '%d %b %Y') : 'No Due Date',
            'time_remaining' => $assignment->duedate > 0 && $assignment->duedate > time() ? 
                theme_remui_kids_time_remaining($assignment->duedate) : null,
            'url' => new moodle_url('/mod/assign/view.php', array('id' => $cm->id)),
            'submission_date' => $submission && $submission->timemodified ? 
                userdate($submission->timemodified, '%d %b %Y') : null
        );
    }
    
    // Get all quizzes in this course
    $quizzes = $DB->get_records('quiz', array('course' => $course->id));
    
    foreach ($quizzes as $quiz) {
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        if (!$cm || !$cm->visible) {
            continue;
        }
        
        // Get quiz attempts
        $attempts = $DB->get_records('quiz_attempts', array(
            'quiz' => $quiz->id,
            'userid' => $userid
        ), 'attempt DESC');
        
        $best_attempt = null;
        $status = 'not_attempted';
        $status_text = 'Not Attempted';
        $is_completed = false;
        $grade_percentage = null;
        $grade_text = 'Not Graded';
        
        if (!empty($attempts)) {
            $best_grade = 0;
            foreach ($attempts as $attempt) {
                if ($attempt->state === 'finished') {
                    $is_completed = true;
                    $status = 'completed';
                    $status_text = 'Completed';
                    
                    if ($attempt->sumgrades !== null && $quiz->sumgrades > 0) {
                        $attempt_grade = ($attempt->sumgrades / $quiz->sumgrades) * 100;
                        if ($attempt_grade > $best_grade) {
                            $best_grade = $attempt_grade;
                            $best_attempt = $attempt;
                        }
                    }
                }
            }
            
            if ($best_grade > 0) {
                $grade_percentage = round($best_grade);
                $grade_text = $grade_percentage . '%';
                $total_grades += $grade_percentage;
                $grade_count++;
            }
        }
        
        $total_assessments++;
        
        if ($is_completed) {
            $completed_assessments++;
        } else {
            $pending_assessments++;
        }
        
        $assessments_data[] = array(
            'id' => $quiz->id,
            'cmid' => $cm->id,
            'name' => $quiz->name,
            'course_name' => $course->fullname,
            'course_id' => $course->id,
            'intro' => strip_tags($quiz->intro),
            'status' => $status,
            'status_text' => $status_text,
            'is_completed' => $is_completed,
            'is_overdue' => false,
            'is_pending' => !$is_completed,
            'grade_text' => $grade_text,
            'grade_percentage' => $grade_percentage,
            'due_date' => $quiz->timeclose > 0 ? userdate($quiz->timeclose, '%d %b %Y') : 'No Due Date',
            'time_remaining' => $quiz->timeclose > 0 && $quiz->timeclose > time() ? 
                theme_remui_kids_time_remaining($quiz->timeclose) : null,
            'url' => new moodle_url('/mod/quiz/view.php', array('id' => $cm->id)),
            'attempts_count' => count($attempts),
            'is_quiz' => true
        );
    }
}

// Calculate average grade
if ($grade_count > 0) {
    $average_grade = round($total_grades / $grade_count);
}

// Sort assessments by due date
usort($assessments_data, function($a, $b) {
    if ($a['is_overdue'] != $b['is_overdue']) {
        return $a['is_overdue'] ? -1 : 1;
    }
    if ($a['is_pending'] != $b['is_pending']) {
        return $a['is_pending'] ? -1 : 1;
    }
    return strcmp($a['name'], $b['name']);
});

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'total_assessments' => $total_assessments,
    'completed_assessments' => $completed_assessments,
    'pending_assessments' => $pending_assessments,
    'overdue_assessments' => $overdue_assessments,
    'average_grade' => $average_grade,
    'completion_rate' => $total_assessments > 0 ? round(($completed_assessments / $total_assessments) * 100) : 0,
    'assessments' => $assessments_data,
    'has_assessments' => count($assessments_data) > 0,
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include assessment template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_assessment.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Assessment template not found.</div>';
}

echo $OUTPUT->footer();

/**
 * Calculate time remaining until deadline
 * 
 * @param int $timestamp Deadline timestamp
 * @return string Human-readable time remaining
 */
function theme_remui_kids_time_remaining($timestamp) {
    $diff = $timestamp - time();
    
    if ($diff < 0) {
        return 'Overdue';
    }
    
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
    
    if ($days > 0) {
        return $days . ' day' . ($days > 1 ? 's' : '') . ' remaining';
    } elseif ($hours > 0) {
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' remaining';
    } else {
        return 'Due soon';
    }
}

