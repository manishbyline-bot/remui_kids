<?php
/**
 * Trainee Leaderboards Page - Rankings and top performers
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_leaderboards.php');
$PAGE->set_title('Leaderboards - Riyada Trainings');
$PAGE->set_heading('Leaderboards');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get filter parameters
$leaderboard_type = optional_param('type', 'overall', PARAM_ALPHA);
$course_filter = optional_param('course', 0, PARAM_INT);

// Initialize leaderboard data
$leaderboard_data = array();
$user_rank = 0;
$user_score = 0;
$total_participants = 0;

// Get all active users (excluding admin and guest)
$sql_base = "SELECT u.id, u.firstname, u.lastname, u.email, u.institution, u.city, u.country";

if ($leaderboard_type === 'overall' || $leaderboard_type === 'grades') {
    // Overall leaderboard based on average grades
    $sql = $sql_base . ",
            (SELECT AVG(gg.finalgrade / gi.grademax * 100)
             FROM {grade_grades} gg
             JOIN {grade_items} gi ON gg.itemid = gi.id
             WHERE gg.userid = u.id AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL) as score
            FROM {user} u
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 2
            HAVING score IS NOT NULL
            ORDER BY score DESC, u.lastname ASC
            LIMIT 100";
    
    $leaderboard_users = $DB->get_records_sql($sql);
    
} elseif ($leaderboard_type === 'badges') {
    // Leaderboard based on badge count
    $sql = $sql_base . ",
            (SELECT COUNT(*)
             FROM {badge_issued} bi
             WHERE bi.userid = u.id) as score
            FROM {user} u
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 2
            HAVING score > 0
            ORDER BY score DESC, u.lastname ASC
            LIMIT 100";
    
    $leaderboard_users = $DB->get_records_sql($sql);
    
} elseif ($leaderboard_type === 'courses') {
    // Leaderboard based on completed courses
    $sql = $sql_base . ",
            (SELECT COUNT(*)
             FROM {course_completions} cc
             WHERE cc.userid = u.id AND cc.timecompleted IS NOT NULL) as score
            FROM {user} u
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 2
            HAVING score > 0
            ORDER BY score DESC, u.lastname ASC
            LIMIT 100";
    
    $leaderboard_users = $DB->get_records_sql($sql);
    
} else {
    // Default to overall
    $leaderboard_users = array();
}

// Process leaderboard data
$rank = 1;
$previous_score = null;
$actual_rank = 1;

foreach ($leaderboard_users as $luser) {
    $total_participants++;
    
    // Handle tied scores
    if ($previous_score !== null && $luser->score == $previous_score) {
        // Same score, same rank
    } else {
        $actual_rank = $rank;
    }
    
    $is_current_user = ($luser->id == $userid);
    
    if ($is_current_user) {
        $user_rank = $actual_rank;
        $user_score = round($luser->score, 1);
    }
    
    // Determine medal/badge
    $medal = '';
    $badge_class = '';
    if ($actual_rank == 1) {
        $medal = '🥇';
        $badge_class = 'gold';
    } elseif ($actual_rank == 2) {
        $medal = '🥈';
        $badge_class = 'silver';
    } elseif ($actual_rank == 3) {
        $medal = '🥉';
        $badge_class = 'bronze';
    }
    
    $leaderboard_data[] = array(
        'rank' => $actual_rank,
        'user_id' => $luser->id,
        'fullname' => fullname($luser),
        'institution' => $luser->institution ?? 'Not specified',
        'location' => ($luser->city ? $luser->city . ', ' : '') . ($luser->country ?? ''),
        'score' => round($luser->score, 1),
        'is_current_user' => $is_current_user,
        'medal' => $medal,
        'badge_class' => $badge_class,
        'has_medal' => !empty($medal),
        'profile_url' => (new moodle_url('/user/profile.php', array('id' => $luser->id)))->out(false)
    );
    
    $rank++;
    $previous_score = $luser->score;
}

// Get user's percentile
$user_percentile = 0;
if ($total_participants > 0 && $user_rank > 0) {
    $user_percentile = round((($total_participants - $user_rank + 1) / $total_participants) * 100);
}

// Get available courses for filter
$courses = $DB->get_records('course', array('visible' => 1), 'fullname ASC', 'id, fullname');
$courses_list = array();
foreach ($courses as $course) {
    if ($course->id > 1) { // Skip site course
        $courses_list[] = array(
            'id' => $course->id,
            'name' => $course->fullname
        );
    }
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'user_rank' => $user_rank,
    'user_score' => $user_score,
    'user_percentile' => $user_percentile,
    'total_participants' => $total_participants,
    'leaderboard_data' => $leaderboard_data,
    'has_leaderboard' => count($leaderboard_data) > 0,
    'leaderboard_type' => $leaderboard_type,
    'is_overall' => ($leaderboard_type === 'overall' || $leaderboard_type === 'grades'),
    'is_badges' => ($leaderboard_type === 'badges'),
    'is_courses' => ($leaderboard_type === 'courses'),
    'courses' => $courses_list,
    'has_courses' => count($courses_list) > 0,
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include leaderboards template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_leaderboards.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Leaderboards template not found.</div>';
}

echo $OUTPUT->footer();
