<?php
/**
 * Trainee Dashboard Page
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/badgeslib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_dashboard.php');
$PAGE->set_title('My Dashboard - Riyada Trainings');
$PAGE->set_heading('My Dashboard');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get competency data
$competency_score = 0;
$competency_data = array();
$competency_records = $DB->get_records_sql("
    SELECT cc.id, cc.competencyid, c.shortname, c.description, cc.proficiency
    FROM {competency_usercomp} cc
    JOIN {competency} c ON cc.competencyid = c.id
    WHERE cc.userid = ? AND cc.status = ?
", array($userid, 1));

$total_competencies = count($competency_records);
if ($total_competencies > 0) {
    $total_score = 0;
    foreach ($competency_records as $comp) {
        $total_score += $comp->proficiency ?? 0;
        $competency_data[] = array(
            'name' => $comp->shortname,
            'score' => $comp->proficiency ?? 0,
            'color' => get_competency_color($comp->shortname)
        );
    }
    $competency_score = round($total_score / $total_competencies);
} else {
    // Provide default competency data if none exists
    $competency_data = array(
        array('name' => 'Pedagogy', 'score' => 20, 'color' => '#3b82f6'),
        array('name' => 'Assessment', 'score' => 20, 'color' => '#10b981'),
        array('name' => 'Technology', 'score' => 20, 'color' => '#ef4444'),
        array('name' => 'Management', 'score' => 20, 'color' => '#8b5cf6'),
        array('name' => 'Content', 'score' => 20, 'color' => '#f59e0b')
    );
    $competency_score = 20;
}

// Get learning hours (approximate from course completion)
$learning_hours = $DB->get_field_sql("
    SELECT COALESCE(SUM(cc.timestarted), 0) / 3600
    FROM {course_completions} cc
    WHERE cc.userid = ? AND cc.timecompleted IS NOT NULL
", array($userid));

// Get certifications/badges
$badges = $DB->get_records_sql("
    SELECT b.id, b.name, b.description, b.imageauthorname, b.imageauthoremail
    FROM {badge_issued} bi
    JOIN {badge} b ON bi.badgeid = b.id
    WHERE bi.userid = ? AND b.status = ?
    ORDER BY bi.dateissued DESC
    LIMIT 10
", array($userid, 1));

$total_badges = count($badges);
$total_available_badges = $DB->count_records_sql("
    SELECT COUNT(*)
    FROM {badge} b
    WHERE b.status = ? AND b.id NOT IN (
        SELECT badgeid FROM {badge_issued} WHERE userid = ?
    )
", array(1, $userid));

// Get user's enrolled courses
$enrolled_courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, c.summary, c.startdate, c.enddate,
           cc.timeenrolled, cc.timestarted, cc.timecompleted,
           CASE WHEN cc.timecompleted IS NOT NULL THEN 'completed'
                WHEN cc.timestarted IS NOT NULL THEN 'in_progress'
                ELSE 'not_started' END as status
    FROM {enrol} e
    JOIN {user_enrolments} ue ON e.id = ue.enrolid
    JOIN {course} c ON e.courseid = c.id
    LEFT JOIN {course_completions} cc ON c.id = cc.course AND cc.userid = ?
    WHERE ue.userid = ? AND e.status = ? AND c.visible = ?
    ORDER BY cc.timeenrolled DESC
    LIMIT 10
", array($userid, $userid, 0, 1));

// Get recent activity
$recent_activity = $DB->get_records_sql("
    SELECT la.id, la.courseid, c.fullname as coursename, la.action, la.target, 
           FROM_UNIXTIME(la.timecreated) as activity_time
    FROM {logstore_standard_log} la
    JOIN {course} c ON la.courseid = c.id
    WHERE la.userid = ? AND la.action IN ('viewed', 'completed', 'submitted')
    ORDER BY la.timecreated DESC
    LIMIT 5
", array($userid));

// Get upcoming events (calendar events)
$upcoming_events = $DB->get_records_sql("
    SELECT e.id, e.name, e.description, e.timestart, e.timeduration
    FROM {event} e
    WHERE e.userid = ? AND e.timestart > ?
    ORDER BY e.timestart ASC
    LIMIT 5
", array($userid, time()));

// Get available mentors (users with teacher role)
$mentors = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess,
           CASE WHEN u.lastaccess > ? THEN 'online' ELSE 'offline' END as status
    FROM {user} u
    JOIN {role_assignments} ra ON u.id = ra.userid
    JOIN {role} r ON ra.roleid = r.id
    JOIN {context} ctx ON ra.contextid = ctx.id
    WHERE r.shortname = 'teachers' AND ctx.contextlevel = ? AND u.deleted = ?
    ORDER BY u.lastaccess DESC
    LIMIT 4
", array(time() - 300, CONTEXT_SYSTEM, 0));

// Helper function to get competency color
function get_competency_color($name) {
    $colors = array(
        'Pedagogy' => '#3b82f6',
        'Assessment' => '#10b981', 
        'Technology' => '#ef4444',
        'Management' => '#8b5cf6',
        'Content' => '#f59e0b'
    );
    return $colors[$name] ?? '#6b7280';
}

// Prepare template data
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'competency_score' => $competency_score,
    'learning_hours' => round($learning_hours),
    'certifications_earned' => $total_badges,
    'certifications_total' => $total_badges + $total_available_badges,
    'achievements_earned' => $total_badges,
    'achievements_total' => $total_badges + $total_available_badges,
    'competency_data' => $competency_data,
    'competency_data_json' => json_encode($competency_data),
    'enrolled_courses' => array_values($enrolled_courses),
    'recent_activity' => array_values($recent_activity),
    'upcoming_events' => array_values($upcoming_events),
    'mentors' => array_values($mentors),
    'has_recent_activity' => count($recent_activity) > 0,
    'has_upcoming_events' => count($upcoming_events) > 0,
    'has_mentors' => count($mentors) > 0
);

// Output the page
echo $OUTPUT->header();

// Include dashboard template directly
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_dashboard.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Dashboard template not found.</div>';
}

echo $OUTPUT->footer();
