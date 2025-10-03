<?php
/**
 * Trainee Discussion Forums Page - Browse and participate in forums
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_forums.php');
$PAGE->set_title('Discussion Forums - Riyada Trainings');
$PAGE->set_heading('Discussion Forums');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get all forums the user has access to
$sql = "SELECT f.id, f.name, f.intro, f.type, f.course, f.timemodified,
               c.fullname as coursename, c.shortname as courseshortname,
               (SELECT COUNT(*) FROM {forum_discussions} fd WHERE fd.forum = f.id) as discussions_count,
               (SELECT COUNT(*) FROM {forum_posts} fp 
                JOIN {forum_discussions} fd ON fp.discussion = fd.id 
                WHERE fd.forum = f.id) as posts_count,
               (SELECT MAX(fp.created) FROM {forum_posts} fp 
                JOIN {forum_discussions} fd ON fp.discussion = fd.id 
                WHERE fd.forum = f.id) as last_post_time
        FROM {forum} f
        JOIN {course} c ON f.course = c.id
        JOIN {enrol} e ON c.id = e.courseid
        JOIN {user_enrolments} ue ON e.id = ue.enrolid
        WHERE ue.userid = ? AND c.visible = 1
        ORDER BY last_post_time DESC, f.name ASC";

$forums = $DB->get_records_sql($sql, array($userid));

$forums_data = array();
$total_forums = 0;
$total_discussions = 0;
$total_posts = 0;
$user_posts_count = 0;

// Get user's post count
$user_posts_count = $DB->count_records('forum_posts', array('userid' => $userid));

foreach ($forums as $forum) {
    $total_forums++;
    $total_discussions += $forum->discussions_count;
    $total_posts += $forum->posts_count;
    
    // Get course module info
    $cm = get_coursemodule_from_instance('forum', $forum->id);
    if (!$cm || !$cm->visible) {
        continue;
    }
    
    // Check if user has posted in this forum
    $user_has_posted = $DB->record_exists_sql("
        SELECT 1 FROM {forum_posts} fp
        JOIN {forum_discussions} fd ON fp.discussion = fd.id
        WHERE fd.forum = ? AND fp.userid = ?
    ", array($forum->id, $userid));
    
    // Get unread posts count (if tracking is enabled)
    $unread_count = 0;
    if (forum_tp_can_track_forums($forum)) {
        $unread_count = forum_tp_count_forum_unread_posts($cm, $forum->course);
    }
    
    // Determine forum type icon and color
    $forum_icon = 'comments';
    $forum_color = 'blue';
    switch ($forum->type) {
        case 'news':
            $forum_icon = 'bullhorn';
            $forum_color = 'red';
            break;
        case 'qanda':
            $forum_icon = 'question-circle';
            $forum_color = 'purple';
            break;
        case 'single':
            $forum_icon = 'comment';
            $forum_color = 'green';
            break;
        default:
            $forum_icon = 'comments';
            $forum_color = 'blue';
    }
    
    // Format last activity
    $last_activity = 'No activity yet';
    if ($forum->last_post_time) {
        $time_diff = time() - $forum->last_post_time;
        if ($time_diff < 3600) {
            $last_activity = floor($time_diff / 60) . ' minutes ago';
        } elseif ($time_diff < 86400) {
            $last_activity = floor($time_diff / 3600) . ' hours ago';
        } else {
            $last_activity = floor($time_diff / 86400) . ' days ago';
        }
    }
    
    $forums_data[] = array(
        'id' => $forum->id,
        'name' => $forum->name,
        'intro' => strip_tags($forum->intro),
        'course_name' => $forum->coursename,
        'course_shortname' => $forum->courseshortname,
        'discussions_count' => $forum->discussions_count,
        'posts_count' => $forum->posts_count,
        'last_activity' => $last_activity,
        'has_unread' => $unread_count > 0,
        'unread_count' => $unread_count,
        'user_has_posted' => $user_has_posted,
        'forum_icon' => $forum_icon,
        'forum_color' => $forum_color,
        'forum_url' => (new moodle_url('/mod/forum/view.php', array('id' => $cm->id)))->out(false)
    );
}

// Get recent discussions across all forums
$recent_discussions = $DB->get_records_sql("
    SELECT fd.id, fd.name, fd.timemodified, fd.forum,
           f.name as forum_name, c.fullname as course_name,
           u.firstname, u.lastname,
           (SELECT COUNT(*) FROM {forum_posts} fp WHERE fp.discussion = fd.id) as replies
    FROM {forum_discussions} fd
    JOIN {forum} f ON fd.forum = f.id
    JOIN {course} c ON f.course = c.id
    JOIN {user} u ON fd.userid = u.id
    JOIN {enrol} e ON c.id = e.courseid
    JOIN {user_enrolments} ue ON e.id = ue.enrolid
    WHERE ue.userid = ?
    ORDER BY fd.timemodified DESC
    LIMIT 5
", array($userid));

$recent_discussions_data = array();
foreach ($recent_discussions as $discussion) {
    $time_ago = userdate($discussion->timemodified, '%d %b %Y');
    
    $recent_discussions_data[] = array(
        'id' => $discussion->id,
        'name' => $discussion->name,
        'forum_name' => $discussion->forum_name,
        'course_name' => $discussion->course_name,
        'author' => fullname($discussion),
        'replies' => $discussion->replies - 1, // Subtract 1 to exclude the original post
        'time_ago' => $time_ago,
        'discussion_url' => (new moodle_url('/mod/forum/discuss.php', array('d' => $discussion->id)))->out(false)
    );
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'total_forums' => $total_forums,
    'total_discussions' => $total_discussions,
    'total_posts' => $total_posts,
    'user_posts_count' => $user_posts_count,
    'forums' => $forums_data,
    'has_forums' => count($forums_data) > 0,
    'recent_discussions' => $recent_discussions_data,
    'has_recent_discussions' => count($recent_discussions_data) > 0,
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include forums template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_forums.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Discussion Forums template not found.</div>';
}

echo $OUTPUT->footer();

