<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Custom Admin Dashboard - IOMAD Style
 *
 * @package    theme_remui_kids
 * @copyright  2024 Riyada Trainings
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require login
require_login();

// Check if user is admin - redirect non-admins
$context = context_system::instance();
if (!has_capability('moodle/site:config', $context)) {
    redirect(new moodle_url('/my/'));
    exit;
}

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/remui_kids/admin_dashboard.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Admin Dashboard');
$PAGE->set_heading('Admin Dashboard');
$PAGE->navbar->add('Admin Dashboard');

// Hide the admin navigation tabs
$PAGE->set_blocks_editing_capability('moodle/site:config');

// Add custom CSS
$PAGE->requires->css('/theme/remui_kids/style/admin_dashboard.css');

// Get comprehensive dashboard data
$dashboarddata = get_admin_dashboard_data();

// Set up template context
$templatecontext = [
    'wwwroot' => $CFG->wwwroot,
    'sitename' => $SITE->fullname,
    'dashboard' => $dashboarddata,
    'user' => $USER,
    'currenttime' => userdate(time(), '%d %B %Y, %H:%M')
];

// Output the page
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_remui_kids/admin_dashboard', $templatecontext);
echo $OUTPUT->footer();

/**
 * Get comprehensive admin dashboard data
 *
 * @return array Dashboard data
 */
function get_admin_dashboard_data() {
    global $DB, $CFG;
    
    $data = [];
    
    // ============ TOP STATISTICS CARDS ============
    
    // Total Users (excluding guest and admin)
    $data['total_users'] = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {user} WHERE deleted = 0 AND id > 2"
    );
    
    // Role Assignments
    $data['role_assignments'] = $DB->count_records('role_assignments');
    
    // Enrollments
    $data['enrollments'] = $DB->count_records('user_enrolments');
    
    // Assignments (activities)
    $data['assignments'] = $DB->count_records('assign');
    
    // Active Users (last 30 days)
    $data['active_users'] = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {user} 
         WHERE deleted = 0 AND lastaccess > :time",
        ['time' => time() - (30 * 24 * 60 * 60)]
    );
    
    // Completed Activities
    $data['completed_activities'] = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course_modules_completion} 
         WHERE completionstate > 0"
    );
    
    // Total Courses
    $data['total_courses'] = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course} WHERE id > 1"
    );
    
    // ============ ROLE ASSIGNMENTS BREAKDOWN ============
    
    $roles = $DB->get_records_sql(
        "SELECT r.id, r.shortname, r.name, COUNT(ra.id) as count
         FROM {role} r
         LEFT JOIN {role_assignments} ra ON r.id = ra.roleid
         GROUP BY r.id, r.shortname, r.name
         ORDER BY count DESC"
    );
    
    $data['role_breakdown'] = [];
    foreach ($roles as $role) {
        $data['role_breakdown'][] = [
            'name' => $role->name ?: ucfirst($role->shortname),
            'count' => $role->count,
            'percentage' => $data['role_assignments'] > 0 ? round(($role->count / $data['role_assignments']) * 100, 1) : 0
        ];
    }
    
    // ============ TEACHER ANALYTICS ============
    
    $data['teacher_analytics'] = [
        'active_teachers' => $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id) FROM {user} u
             JOIN {role_assignments} ra ON u.id = ra.userid
             JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname IN ('editingteacher', 'teacher')
             AND u.deleted = 0 AND u.lastaccess > :time",
            ['time' => time() - (30 * 24 * 60 * 60)]
        ),
        'total_assignments' => $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.id) FROM {role_assignments} ra
             JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname IN ('editingteacher', 'teacher')"
        ),
        'completion_rate' => get_teacher_completion_rate()
    ];
    
    // ============ TRAINEE ANALYTICS ============
    
    $data['trainee_analytics'] = [
        'active_trainees' => $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id) FROM {user} u
             JOIN {role_assignments} ra ON u.id = ra.userid
             JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname = 'student'
             AND u.deleted = 0 AND u.lastaccess > :time",
            ['time' => time() - (30 * 24 * 60 * 60)]
        ),
        'total_assignments' => $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.id) FROM {role_assignments} ra
             JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname = 'student'"
        ),
        'active_time' => '80%'
    ];
    
    // ============ COURSE ANALYTICS ============
    
    $data['course_analytics'] = [
        'solo_assignments' => $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course} WHERE id > 1"
        ),
        'completion_rate' => get_course_completion_rate()
    ];
    
    // ============ STUDENT ANALYTICS ============
    
    $data['student_analytics'] = [
        'total_students' => $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id) FROM {user} u
             JOIN {role_assignments} ra ON u.id = ra.userid
             JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname = 'student' AND u.deleted = 0"
        ),
        'active_this_month' => $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id) FROM {user} u
             JOIN {role_assignments} ra ON u.id = ra.userid
             JOIN {role} r ON ra.roleid = r.id
             WHERE r.shortname = 'student'
             AND u.deleted = 0 AND u.lastaccess > :time",
            ['time' => time() - (30 * 24 * 60 * 60)]
        ),
        'avg_progress' => get_student_average_progress()
    ];
    
    // ============ MANAGEMENT CARDS ============
    
    $data['management_cards'] = [
        [
            'title' => 'User Management',
            'description' => 'Manage users and permissions',
            'icon' => 'fa-users',
            'color' => 'blue',
            'stats' => [
                ['label' => 'Total Users', 'value' => $data['total_users']],
                ['label' => 'Active Users', 'value' => $data['active_users']]
            ],
            'url' => new moodle_url('/theme/remui_kids/user_management.php'),
            'button_text' => 'Manage Users'
        ],
        [
            'title' => 'Course Management',
            'description' => 'Create and manage courses',
            'icon' => 'fa-book',
            'color' => 'green',
            'stats' => [
                ['label' => 'Total Courses', 'value' => $data['total_courses']],
                ['label' => 'Enrollments', 'value' => $data['enrollments']]
            ],
            'url' => new moodle_url('/course/management.php'),
            'button_text' => 'Manage Courses'
        ],
        [
            'title' => 'School Management',
            'description' => 'Manage school settings',
            'icon' => 'fa-school',
            'color' => 'purple',
            'stats' => [
                ['label' => 'Schools', 'value' => get_schools_count()],
                ['label' => 'Departments', 'value' => get_departments_count()]
            ],
            'url' => new moodle_url('/theme/remui_kids/schools.php'),
            'button_text' => 'Manage Schools'
        ],
        [
            'title' => 'Reports & Analytics',
            'description' => 'View detailed reports',
            'icon' => 'fa-chart-bar',
            'color' => 'orange',
            'stats' => [
                ['label' => 'Completion Rate', 'value' => get_overall_completion_rate() . '%'],
                ['label' => 'Active Rate', 'value' => '85%']
            ],
            'url' => new moodle_url('/admin/category.php?category=reports'),
            'button_text' => 'View Reports'
        ]
    ];
    
    // ============ QUICK ACTIONS ============
    
    $data['quick_actions'] = [
        ['icon' => 'fa-user-plus', 'label' => 'Add User', 'url' => new moodle_url('/user/editadvanced.php', ['id' => -1])],
        ['icon' => 'fa-book-medical', 'label' => 'Create Course', 'url' => new moodle_url('/course/edit.php', ['category' => 1])],
        ['icon' => 'fa-plug', 'label' => 'Plugins', 'url' => new moodle_url('/admin/plugins.php')],
        ['icon' => 'fa-cog', 'label' => 'Settings', 'url' => new moodle_url('/admin/settings.php')],
        ['icon' => 'fa-upload', 'label' => 'Imports', 'url' => new moodle_url('/admin/tool/uploaduser/index.php')],
        ['icon' => 'fa-redo', 'label' => 'IOMAD', 'url' => new moodle_url('/local/iomad_dashboard/index.php')]
    ];
    
    return $data;
}

/**
 * Get teacher completion rate
 */
function get_teacher_completion_rate() {
    global $DB;
    
    $total = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course_modules} WHERE visible = 1"
    );
    
    if ($total == 0) return '0%';
    
    $completed = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT cm.id) FROM {course_modules} cm
         JOIN {course_modules_completion} cmc ON cm.id = cmc.coursemoduleid
         WHERE cmc.completionstate > 0"
    );
    
    return round(($completed / $total) * 100) . '%';
}

/**
 * Get course completion rate
 */
function get_course_completion_rate() {
    global $DB;
    
    $total_enrollments = $DB->count_records('user_enrolments');
    if ($total_enrollments == 0) return '0%';
    
    $completions = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course_completions} WHERE timecompleted IS NOT NULL"
    );
    
    return round(($completions / $total_enrollments) * 100) . '%';
}

/**
 * Get student average progress
 */
function get_student_average_progress() {
    global $DB;
    
    $avg = $DB->get_field_sql(
        "SELECT AVG(progress) FROM (
            SELECT (COUNT(CASE WHEN cmc.completionstate > 0 THEN 1 END) * 100.0 / COUNT(*)) as progress
            FROM {user} u
            JOIN {user_enrolments} ue ON u.id = ue.userid
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course_modules} cm ON e.courseid = cm.course
            LEFT JOIN {course_modules_completion} cmc ON cm.id = cmc.coursemoduleid AND cmc.userid = u.id
            WHERE u.deleted = 0
            GROUP BY u.id
            HAVING COUNT(*) > 0
        ) as subquery"
    );
    
    return $avg ? round($avg) . '%' : '0%';
}

/**
 * Get schools count
 */
function get_schools_count() {
    global $DB;
    
    // Try to get from IOMAD tables if available
    if ($DB->get_manager()->table_exists('company')) {
        return $DB->count_records('company');
    }
    
    return 0;
}

/**
 * Get departments count
 */
function get_departments_count() {
    global $DB;
    
    // Try to get from IOMAD tables if available
    if ($DB->get_manager()->table_exists('department')) {
        return $DB->count_records('department');
    }
    
    return 0;
}

/**
 * Get overall completion rate
 */
function get_overall_completion_rate() {
    global $DB;
    
    $total = $DB->count_records('user_enrolments');
    if ($total == 0) return 0;
    
    $completed = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course_completions} WHERE timecompleted IS NOT NULL"
    );
    
    return round(($completed / $total) * 100);
}
