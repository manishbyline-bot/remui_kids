<?php
/**
 * Trainee Peer Network Page - Connect with fellow trainees
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_peer_network.php');
$PAGE->set_title('Peer Network - Riyada Trainings');
$PAGE->set_heading('Peer Network');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get filter parameters
$role_filter = optional_param('role', 'all', PARAM_ALPHA);
$search_query = optional_param('search', '', PARAM_TEXT);

// Get all users (peers) - excluding current user and admin
$sql_conditions = "u.deleted = 0 AND u.suspended = 0 AND u.id != ? AND u.id != 1";
$params = array($userid);

// Role filter
$role_join = "";
if ($role_filter !== 'all') {
    $role_join = "JOIN {role_assignments} ra ON u.id = ra.userid
                  JOIN {role} r ON ra.roleid = r.id";
    $sql_conditions .= " AND r.shortname = ?";
    $params[] = $role_filter;
}

// Search filter
if (!empty($search_query)) {
    $sql_conditions .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Get peers with their course enrollments
$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess, 
               u.city, u.country, u.institution, u.picture, u.imagealt,
               (SELECT COUNT(*) FROM {user_enrolments} ue 
                JOIN {enrol} e ON ue.enrolid = e.id 
                WHERE ue.userid = u.id) as course_count
        FROM {user} u
        $role_join
        WHERE $sql_conditions
        ORDER BY u.lastaccess DESC
        LIMIT 50";

$peers = $DB->get_records_sql($sql, $params);

$peers_data = array();
$total_peers = 0;
$online_peers = 0;
$teachers_count = 0;
$trainees_count = 0;

foreach ($peers as $peer) {
    $total_peers++;
    
    // Check if user is online (active in last 5 minutes)
    $is_online = ($peer->lastaccess > (time() - 300));
    if ($is_online) {
        $online_peers++;
    }
    
    // Get user's role
    $role_name = 'Trainee';
    $roles = get_user_roles($context, $peer->id);
    if (!empty($roles)) {
        $role = reset($roles);
        $role_name = $role->name;
        if (strpos(strtolower($role_name), 'teacher') !== false) {
            $teachers_count++;
        } else {
            $trainees_count++;
        }
    }
    
    // Get shared courses with current user
    $shared_courses = $DB->get_records_sql("
        SELECT DISTINCT c.id, c.fullname
        FROM {course} c
        JOIN {enrol} e1 ON c.id = e1.courseid
        JOIN {user_enrolments} ue1 ON e1.id = ue1.enrolid
        JOIN {enrol} e2 ON c.id = e2.courseid
        JOIN {user_enrolments} ue2 ON e2.id = ue2.enrolid
        WHERE ue1.userid = ? AND ue2.userid = ?
        LIMIT 3
    ", array($userid, $peer->id));
    
    $shared_courses_list = array();
    foreach ($shared_courses as $course) {
        $shared_courses_list[] = array(
            'id' => $course->id,
            'name' => $course->fullname
        );
    }
    
    // Format last access
    $last_seen = 'Never';
    if ($peer->lastaccess > 0) {
        $time_diff = time() - $peer->lastaccess;
        if ($time_diff < 60) {
            $last_seen = 'Just now';
        } elseif ($time_diff < 3600) {
            $last_seen = floor($time_diff / 60) . ' minutes ago';
        } elseif ($time_diff < 86400) {
            $last_seen = floor($time_diff / 3600) . ' hours ago';
        } else {
            $last_seen = floor($time_diff / 86400) . ' days ago';
        }
    }
    
    $peers_data[] = array(
        'id' => $peer->id,
        'firstname' => $peer->firstname,
        'lastname' => $peer->lastname,
        'fullname' => fullname($peer),
        'email' => $peer->email,
        'institution' => $peer->institution ?? 'Not specified',
        'city' => $peer->city ?? '',
        'country' => $peer->country ?? '',
        'location' => ($peer->city ? $peer->city . ', ' : '') . ($peer->country ?? 'Not specified'),
        'role' => $role_name,
        'is_online' => $is_online,
        'last_seen' => $last_seen,
        'course_count' => $peer->course_count,
        'shared_courses' => $shared_courses_list,
        'has_shared_courses' => count($shared_courses_list) > 0,
        'profile_url' => (new moodle_url('/user/profile.php', array('id' => $peer->id)))->out(false),
        'message_url' => (new moodle_url('/message/index.php', array('id' => $peer->id)))->out(false)
    );
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'total_peers' => $total_peers,
    'online_peers' => $online_peers,
    'teachers_count' => $teachers_count,
    'trainees_count' => $trainees_count,
    'peers' => $peers_data,
    'has_peers' => count($peers_data) > 0,
    'search_query' => $search_query,
    'role_filter' => $role_filter,
    'is_all_filter' => ($role_filter === 'all'),
    'is_teacher_filter' => ($role_filter === 'teacher'),
    'is_trainee_filter' => ($role_filter === 'trainee'),
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include peer network template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_peer_network.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Peer Network template not found.</div>';
}

echo $OUTPUT->footer();

