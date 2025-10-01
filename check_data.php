<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER;

echo "<h2>Real Data Check for Dashboard</h2>";
echo "<p><strong>User:</strong> " . $USER->firstname . " " . $USER->lastname . " (ID: " . $USER->id . ")</p>";

// Check competency data
echo "<h3>Competency Data</h3>";
$competency_records = $DB->get_records_sql("
    SELECT cc.id, cc.competencyid, c.shortname, c.description, cc.proficiency
    FROM {competency_usercomp} cc
    JOIN {competency} c ON cc.competencyid = c.id
    WHERE cc.userid = ? AND cc.status = ?
    LIMIT 10
", array($USER->id, 1));

echo "<p><strong>Competency Records Found:</strong> " . count($competency_records) . "</p>";
if (count($competency_records) > 0) {
    echo "<ul>";
    foreach ($competency_records as $comp) {
        echo "<li>" . $comp->shortname . " - Score: " . ($comp->proficiency ?? 0) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No competency data found for this user.</p>";
}

// Check enrolled courses
echo "<h3>Enrolled Courses</h3>";
$enrolled_courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, c.summary,
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
", array($USER->id, $USER->id, 0, 1));

echo "<p><strong>Enrolled Courses:</strong> " . count($enrolled_courses) . "</p>";
if (count($enrolled_courses) > 0) {
    echo "<ul>";
    foreach ($enrolled_courses as $course) {
        echo "<li>" . $course->fullname . " - Status: " . $course->status . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No enrolled courses found for this user.</p>";
}

// Check badges
echo "<h3>Earned Badges</h3>";
$badges = $DB->get_records_sql("
    SELECT b.id, b.name, b.description
    FROM {badge_issued} bi
    JOIN {badge} b ON bi.badgeid = b.id
    WHERE bi.userid = ? AND b.status = ?
    ORDER BY bi.dateissued DESC
    LIMIT 10
", array($USER->id, 1));

echo "<p><strong>Earned Badges:</strong> " . count($badges) . "</p>";
if (count($badges) > 0) {
    echo "<ul>";
    foreach ($badges as $badge) {
        echo "<li>" . $badge->name . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No badges earned by this user.</p>";
}

// Check recent activity
echo "<h3>Recent Activity</h3>";
$recent_activity = $DB->get_records_sql("
    SELECT la.id, la.courseid, c.fullname as coursename, la.action, la.target, 
           FROM_UNIXTIME(la.timecreated) as activity_time
    FROM {logstore_standard_log} la
    JOIN {course} c ON la.courseid = c.id
    WHERE la.userid = ? AND la.action IN ('viewed', 'completed', 'submitted')
    ORDER BY la.timecreated DESC
    LIMIT 5
", array($USER->id));

echo "<p><strong>Recent Activity Records:</strong> " . count($recent_activity) . "</p>";
if (count($recent_activity) > 0) {
    echo "<ul>";
    foreach ($recent_activity as $activity) {
        echo "<li>" . $activity->action . " " . $activity->target . " in " . $activity->coursename . " at " . $activity->activity_time . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No recent activity found for this user.</p>";
}

// Check available mentors/trainers
echo "<h3>Available Trainers/Mentors</h3>";
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

echo "<p><strong>Available Trainers:</strong> " . count($mentors) . "</p>";
if (count($mentors) > 0) {
    echo "<ul>";
    foreach ($mentors as $mentor) {
        echo "<li>" . $mentor->firstname . " " . $mentor->lastname . " - Status: " . $mentor->status . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No trainers found in the system.</p>";
}

echo "<hr>";
echo "<p><strong>Summary:</strong> The dashboard will show real data from the database. If some sections show 'No data' or default values, it means there's no actual data in those database tables for the current user.</p>";
?>
