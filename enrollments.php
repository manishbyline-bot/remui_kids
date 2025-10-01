<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

redirect_if_major_upgrade_required();

require_login();

// Check if user is admin - restrict access to admins only
$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if (!$hassiteconfig) {
    // User is not an admin, redirect to dashboard
    redirect(new moodle_url('/my/'), 'Access denied. This page is only available to administrators.', null, \core\output\notification::NOTIFY_ERROR);
}

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Set up the page exactly like schools.php
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/enrollments.php');
$PAGE->add_body_classes(['limitedwidth', 'page-myenrollments']);
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('enrollments-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Enrollments Management - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Include full width CSS - MUST be before header output
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

// Include JavaScript file
$PAGE->requires->js('/theme/remui_kids/js/enrollments.js');


// Handle AJAX request for filtered enrollment data
if (isset($_GET['action']) && $_GET['action'] === 'get_enrollments') {
    header('Content-Type: application/json');
    
    $search = optional_param('search', '', PARAM_TEXT);
    $course_filter = optional_param('course', '', PARAM_INT);
    $status_filter = optional_param('status', '', PARAM_TEXT);
    $page = optional_param('page', 0, PARAM_INT);
    $perpage = 20;
    
    // Debug logging
    error_log("Enrollments AJAX request - Search: '$search', Course: '$course_filter', Status: '$status_filter', Page: '$page'");
    
    try {
        // Check if enrollment tables exist
        $enrollment_table_exists = $DB->get_manager()->table_exists('user_enrolments');
        $enrol_table_exists = $DB->get_manager()->table_exists('enrol');
        $course_table_exists = $DB->get_manager()->table_exists('course');
        
        // Build the query for enrolled users
        $where_conditions = array('u.deleted = 0', 'u.id > 2');
        $params = array();

        if (!empty($search)) {
            $search_param = '%' . $search . '%';
            $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR c.fullname LIKE ?)";
            $params = array_merge($params, array($search_param, $search_param, $search_param, $search_param, $search_param));
        }

        // Add course filter
        if (!empty($course_filter)) {
            $where_conditions[] = "c.id = ?";
            $params[] = $course_filter;
        }

        // Add status filter
        if (!empty($status_filter)) {
            if ($status_filter === 'active') {
                $where_conditions[] = "u.suspended = 0";
            } elseif ($status_filter === 'suspended') {
                $where_conditions[] = "u.suspended = 1";
            }
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Check if enrollment tables exist for proper query
        if ($enrollment_table_exists && $enrol_table_exists && $course_table_exists) {
            $sql = "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname, u.suspended, u.deleted, u.timecreated,
                           c.visible, e.status as enrol_status,
                           GROUP_CONCAT(c.fullname SEPARATOR ', ') as courses,
                           COUNT(DISTINCT c.id) as course_count
                    FROM {user} u
                    JOIN {user_enrolments} ue ON u.id = ue.userid
                    JOIN {enrol} e ON ue.enrolid = e.id
                    JOIN {course} c ON e.courseid = c.id
                    WHERE $where_clause
                    AND c.visible = 1 AND e.status = 0
                    GROUP BY u.id, u.username, u.email, u.firstname, u.lastname, u.suspended, u.deleted, u.timecreated
                    ORDER BY u.firstname ASC, u.lastname ASC";
        } else {
            // Fallback query if enrollment tables don't exist
            $sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname, u.suspended, u.timecreated,
                           'No enrollment data available' as courses,
                           0 as course_count
                    FROM {user} u
                    WHERE $where_clause
                    ORDER BY u.firstname ASC, u.lastname ASC";
        }

        // Get total count for pagination
        if ($enrollment_table_exists && $enrol_table_exists && $course_table_exists) {
            $count_sql = "SELECT COUNT(DISTINCT u.id) 
                          FROM {user} u
                          JOIN {user_enrolments} ue ON u.id = ue.userid
                          JOIN {enrol} e ON ue.enrolid = e.id
                          JOIN {course} c ON e.courseid = c.id
                          WHERE $where_clause
                          AND c.visible = 1 AND e.status = 0";
        } else {
            $count_sql = "SELECT COUNT(u.id) 
                          FROM {user} u
                          WHERE $where_clause";
        }

        $total_count = $DB->count_records_sql($count_sql, $params);

        // Get paginated results
        $users = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        
        // Get available courses for filter dropdown
        $available_courses_list = array();
        if ($enrollment_table_exists && $enrol_table_exists && $course_table_exists) {
            $courses_sql = "SELECT DISTINCT c.id, c.fullname 
                            FROM {course} c
                            JOIN {enrol} e ON c.id = e.courseid
                            WHERE c.visible = 1 AND e.status = 0
                            ORDER BY c.fullname ASC";
            $available_courses_list = $DB->get_records_sql($courses_sql);
        }
        
        // Calculate filtered statistics based on current filters
        $filtered_active = 0;
        $filtered_suspended = 0;
        
        // Build the base WHERE clause for current filters (excluding status)
        $base_where = array();
        $base_params = array();
        
        if (!empty($search)) {
            $base_where[] = "(u.username LIKE ? OR u.email LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?)";
            $search_param = '%' . $search . '%';
            $base_params = array_merge($base_params, array($search_param, $search_param, $search_param));
        }
        
        if (!empty($course_filter)) {
            $base_where[] = "ue.enrolid IN (SELECT id FROM {enrol} WHERE courseid = ?)";
            $base_params[] = $course_filter;
        }
        
        $base_where_sql = !empty($base_where) ? ' AND ' . implode(' AND ', $base_where) : '';
        
        if (empty($status_filter)) {
            // If no status filter, show all enrollments as active
            $filtered_active = $total_count;
            $filtered_suspended = 0;
        } elseif ($status_filter === 'active') {
            // If active filter, show only active users with current filters
            $filtered_active = $total_count;
            $filtered_suspended = 0;
        } elseif ($status_filter === 'suspended') {
            // If suspended filter, show only suspended users with current filters
            $filtered_active = 0;
            $filtered_suspended = $total_count;
        }
        
        // Prepare response data
        $response = array(
            'users' => array(),
            'total_count' => $total_count,
            'current_page' => $page,
            'per_page' => $perpage,
            'total_pages' => ceil($total_count / $perpage),
            'filters' => array(
                'search' => $search,
                'course' => $course_filter,
                'status' => $status_filter
            ),
            'courses' => $available_courses_list,
            'statistics' => array(
                'active' => $filtered_active,
                'suspended' => $filtered_suspended,
                'total' => $filtered_active + $filtered_suspended
            )
        );
        
        foreach ($users as $user) {
            // All users are considered active (no suspended enrollments)
            $status = 'active';
            
            $response['users'][] = array(
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'suspended' => $user->suspended,
                'deleted' => $user->deleted,
                'user_id' => $user->id,
                'course_visible' => $user->visible,
                'enrol_status' => $user->enrol_status,
                'status' => $status,
                'courses' => $user->courses,
                'course_count' => $user->course_count,
                'timecreated' => $user->timecreated
            );
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("Enrollments AJAX error: " . $e->getMessage());
        echo json_encode(array(
            'error' => 'Database error: ' . $e->getMessage(),
            'users' => array(),
            'total_count' => 0,
            'debug' => array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            )
        ));
    }
    exit;
}

// Get enrollment statistics
try {
    // Check if enrollment tables exist
    $enrollment_table_exists = $DB->get_manager()->table_exists('user_enrolments');
    $enrol_table_exists = $DB->get_manager()->table_exists('enrol');
    $course_table_exists = $DB->get_manager()->table_exists('course');
    
    if ($enrollment_table_exists && $enrol_table_exists && $course_table_exists) {
        // Total Enrollments - count all enrollment records from database
        $total_enrollments = $DB->count_records('user_enrolments');
        
        // Active Enrollments (same as total - all enrollments are considered active)
        $active_enrollments = $total_enrollments;
        
        // Suspended Enrollments (set to 0 - no suspended enrollments)
        $suspended_enrollments = 0;
        
        // Available Courses (visible and active)
        $available_courses = $DB->count_records_sql("
            SELECT COUNT(DISTINCT c.id)
            FROM {course} c
            JOIN {enrol} e ON c.id = e.courseid
            WHERE c.visible = 1 AND e.status = 0
        ");
    } else {
        // Fallback: if enrollment tables don't exist, show user-based stats
        $total_enrollments = $DB->count_records('user', array('deleted' => 0));
        $active_enrollments = $DB->count_records('user', array('deleted' => 0, 'suspended' => 0));
        $suspended_enrollments = $DB->count_records('user', array('deleted' => 0, 'suspended' => 1));
        $available_courses = $DB->count_records('course', array('visible' => 1));
    }
    
} catch (Exception $e) {
    // Fallback values if there are any database errors
    $total_enrollments = 0;
    $active_enrollments = 0;
    $suspended_enrollments = 0;
    $available_courses = 0;
}

// Get search parameters
$search = optional_param('search', '', PARAM_TEXT);
$course_filter = optional_param('course', '', PARAM_INT);
$status_filter = optional_param('status', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;


// Build the query for enrolled users
$where_conditions = array('u.deleted = 0', 'u.id > 2');
$params = array();

if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR c.fullname LIKE ?)";
    $params = array_merge($params, array($search_param, $search_param, $search_param, $search_param, $search_param));
}

// Add course filter
if (!empty($course_filter)) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

// Add status filter
if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "u.suspended = 0";
    } elseif ($status_filter === 'suspended') {
        $where_conditions[] = "u.suspended = 1";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Check if enrollment tables exist for proper query
if ($enrollment_table_exists && $enrol_table_exists && $course_table_exists) {
    $sql = "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname, u.suspended, u.deleted, u.timecreated,
                   c.visible, e.status as enrol_status,
                   GROUP_CONCAT(c.fullname SEPARATOR ', ') as courses,
                   COUNT(DISTINCT c.id) as course_count
            FROM {user} u
            JOIN {user_enrolments} ue ON u.id = ue.userid
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            WHERE $where_clause
            AND c.visible = 1 AND e.status = 0
            GROUP BY u.id, u.username, u.email, u.firstname, u.lastname, u.suspended, u.deleted, u.timecreated, c.visible, e.status
            ORDER BY u.firstname ASC, u.lastname ASC";
} else {
    // Fallback query if enrollment tables don't exist
    $sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname, u.suspended, u.timecreated,
                   'No enrollment data available' as courses,
                   0 as course_count
            FROM {user} u
            WHERE $where_clause
            ORDER BY u.firstname ASC, u.lastname ASC";
}

// Get total count for pagination
if ($enrollment_table_exists && $enrol_table_exists && $course_table_exists) {
    $count_sql = "SELECT COUNT(DISTINCT u.id) 
                  FROM {user} u
                  JOIN {user_enrolments} ue ON u.id = ue.userid
                  JOIN {enrol} e ON ue.enrolid = e.id
                  JOIN {course} c ON e.courseid = c.id
                  WHERE $where_clause
                  AND c.visible = 1 AND e.status = 0";
} else {
    $count_sql = "SELECT COUNT(u.id) 
                  FROM {user} u
                  WHERE $where_clause";
}

$total_count = $DB->count_records_sql($count_sql, $params);

// Get paginated results
$users = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Get available courses for the filter dropdown
$available_courses_list = array();
if ($enrollment_table_exists && $enrol_table_exists && $course_table_exists) {
    $courses_sql = "SELECT DISTINCT c.id, c.fullname 
                    FROM {course} c
                    JOIN {enrol} e ON c.id = e.courseid
                    WHERE c.visible = 1 AND e.status = 0
                    ORDER BY c.fullname ASC";
    $available_courses_list = $DB->get_records_sql($courses_sql);
}

echo $OUTPUT->header();
?>

<style>
.enrollments-container {
    max-width: 100%;
    width: 100%;
    margin: 0;
    padding: 0 20px;
    min-height: 100vh;
}

.header-section {
    background: linear-gradient(135deg, #52C9D9 0%, #4AB3C4 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    border: 1px solid #3A9BA8;
    box-shadow: 0 8px 32px rgba(82, 201, 217, 0.3);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 10;
}

.header-text {
    flex: 1;
}

.header-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.header-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
    position: relative;
    z-index: 2;
    color: #3d3d8a;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.header-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    position: relative;
    z-index: 2;
    color: #2c3e50;
}

.back-btn {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 1px solid #d63031;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
    white-space: nowrap;
}

.back-btn:hover {
    background: linear-gradient(135deg, #ee5a52 0%, #d63031 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
    color: white;
    text-decoration: none;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #52C9D9, #4AB3C4);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 15px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 5px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    animation: countPulse 2s ease-in-out infinite;
    transition: all 0.3s ease;
}

.stat-card:nth-child(1) .stat-number {
    color: #52C9D9;
}

.stat-card:nth-child(2) .stat-number {
    color: #28a745;
}

.stat-card:nth-child(3) .stat-number {
    color: #dc3545;
}

.stat-card:nth-child(4) .stat-number {
    color: #6f42c1;
}

@keyframes countPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.stat-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.stat-description {
    font-size: 0.9rem;
    color: #6c757d;
    line-height: 1.4;
}

.search-section {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
    border: 1px solid #e9ecef;
}

.search-form {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input-group {
    flex: 1;
    min-width: 300px;
    position: relative;
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-select {
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    color: #2c3e50;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: #52C9D9;
    box-shadow: 0 0 0 3px rgba(82, 201, 217, 0.1);
}


.loading-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    background: white;
    border-radius: 10px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.loading-spinner {
    width: 24px;
    height: 24px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #52C9D9;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 12px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-indicator span {
    color: #6c757d;
    font-weight: 500;
}

.search-input {
    width: 100%;
    padding: 15px 20px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.search-input:focus {
    outline: none;
    border-color: #52C9D9;
    background: white;
    box-shadow: 0 0 0 3px rgba(82, 201, 217, 0.1);
}


.results-info {
    background: #e3f2fd;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #52C9D9;
    font-weight: 500;
    color: #1565c0;
}

.table-container {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
    margin-bottom: 30px;
}

.enrollments-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.enrollments-table th {
    background: #fafbfc;
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: #6c757d;
    border-bottom: 1px solid #e1e4e8;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.enrollments-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f3f5;
    vertical-align: middle;
    background: #ffffff;
    transition: background 0.2s ease;
}

.enrollments-table tbody tr {
    background: white;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
    border-bottom: 1px solid #f1f3f5;
}

.enrollments-table tbody tr:hover {
    background: #f6f8fa;
    box-shadow: inset 0 0 0 1px #e1e4e8;
}

.enrollments-table tbody tr:hover td {
    background: #f6f8fa;
}

.enrollments-table tbody tr:last-child {
    border-bottom: none;
}

.enrollments-table th:first-child,
.enrollments-table td:first-child {
    padding-left: 24px;
}

.enrollments-table th:last-child,
.enrollments-table td:last-child {
    padding-right: 24px;
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #17a2b8;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
    object-fit: cover;
    border: 2px solid #e9ecef;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-details h4 {
    margin: 0 0 3px 0;
    font-size: 15px;
    font-weight: 600;
    color: #24292e;
}

.user-details p {
    margin: 0;
    font-size: 12px;
    color: #586069;
}

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-suspended {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.course-count {
    display: inline-block;
    background: #e7f3ff;
    color: #0056b3;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
    padding: 20px;
}

.pagination a,
.pagination span {
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination a {
    background: #f8f9fa;
    color: #52C9D9;
    border: 1px solid #e9ecef;
}

.pagination a:hover {
    background: #52C9D9;
    color: white;
    transform: translateY(-2px);
}

.pagination .current {
    background: linear-gradient(135deg, #52C9D9 0%, #4AB3C4 100%);
    color: white;
    border: 1px solid #3A9BA8;
}

/* Responsive Design */
@media (max-width: 768px) {
    .enrollments-container {
        padding: 0 15px;
    }
    
    .header-section {
        padding: 20px;
    }
    
    .header-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .search-form {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .search-input-group {
        min-width: auto;
    }
    
    .filter-group {
        flex-direction: column;
        gap: 10px;
    }
    
    .filter-select {
        min-width: auto;
        width: 100%;
    }
    
    .enrollments-table {
        font-size: 12px;
    }
    
    .enrollments-table th,
    .enrollments-table td {
        padding: 8px 6px;
    }
    
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .enrollments-table th:nth-child(4),
    .enrollments-table td:nth-child(4),
    .enrollments-table th:nth-child(5),
    .enrollments-table td:nth-child(5) {
        display: none;
    }
}

/* User Detail Modal/Panel Styles - Centered Modal */
.user-detail-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    z-index: 9998;
    display: none;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.user-detail-overlay.show {
    display: flex !important;
}

.user-detail-panel {
    position: relative;
    width: 500px;
    max-width: 90%;
    max-height: 85vh;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    overflow: hidden;
    transform: scale(0.9);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #e1e4e8;
}

.user-detail-panel.show {
    transform: scale(1);
    opacity: 1;
}

.user-detail-header {
    background: #fafbfc;
    padding: 20px 24px;
    position: relative;
    color: #24292e;
    text-align: center;
    border-bottom: 1px solid #e1e4e8;
}

.user-detail-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: transparent;
    border: none;
    color: #6c757d;
    font-size: 24px;
    cursor: pointer;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    line-height: 1;
    padding: 0;
}

.user-detail-close:hover {
    background: #f1f3f4;
    color: #24292e;
    transform: scale(1.1);
}

.user-detail-avatar-large {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #17a2b8;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    font-weight: 600;
    margin: 0 auto 12px;
    border: 2px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.user-detail-name {
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: #24292e;
    margin-bottom: 5px;
}

.user-detail-role {
    text-align: center;
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 0;
    font-weight: 500;
}

.user-detail-body {
    padding: 24px;
    overflow-y: auto;
    max-height: calc(85vh - 200px);
}

.user-detail-section {
    background: #f8f9fa;
    padding: 16px;
    margin-bottom: 16px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.user-detail-section-title {
    font-size: 12px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e1e4e8;
}

.user-detail-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f5;
}

.user-detail-item:last-child {
    border-bottom: none;
}

.user-detail-item-icon {
    width: 28px;
    height: 28px;
    background: #f1f3f4;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 14px;
    margin-right: 12px;
    flex-shrink: 0;
}

.user-detail-item-content {
    flex: 1;
}

.user-detail-item-label {
    font-size: 11px;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}

.user-detail-item-value {
    font-size: 14px;
    color: #24292e;
    font-weight: 500;
}

.user-detail-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.user-detail-badge.customer {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.user-detail-badge.employee {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.user-detail-badge.personal {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.user-detail-courses {
    margin-top: 0;
}

.user-detail-course-item {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
    border-left: 3px solid #17a2b8;
    transition: all 0.2s ease;
}

.user-detail-course-item:hover {
    background: #e9ecef;
    border-left-color: #138496;
}

.user-detail-course-name {
    font-weight: 500;
    color: #24292e;
    margin-bottom: 3px;
    font-size: 14px;
}

.user-detail-course-date {
    font-size: 11px;
    color: #586069;
}

.enrollments-table tbody tr {
    cursor: pointer;
    position: relative;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@media (max-width: 768px) {
    .user-detail-panel {
        width: 95%;
        max-height: 90vh;
    }
    
    .user-detail-body {
        max-height: calc(90vh - 180px);
    }
    
    .user-detail-avatar-large {
        width: 70px;
        height: 70px;
        font-size: 28px;
    }
    
    .user-detail-name {
        font-size: 20px;
    }
}
</style>

<div class="enrollments-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="header-text">
                <h1 class="header-title">Enrollments Management</h1>
                <p class="header-subtitle">Manage user enrollments and course assignments</p>
            </div>
            <a href="<?php echo $CFG->wwwroot; ?>/my/" class="back-btn" title="Go back to Home Dashboard">
                ← Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">●</div>
            <div class="stat-number"><?php echo number_format($total_enrollments); ?></div>
            <div class="stat-title">Total Enrollments</div>
            <div class="stat-description">All user course enrollments</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-number" id="activeCount"><?php echo number_format($active_enrollments); ?></div>
            <div class="stat-title">Active Enrollments</div>
            <div class="stat-description">Non-suspended user enrollments</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">●</div>
            <div class="stat-number" id="suspendedCount"><?php echo number_format($suspended_enrollments); ?></div>
            <div class="stat-title">Suspended Enrollments</div>
            <div class="stat-description">Suspended and inactive user enrollments</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-number"><?php echo number_format($available_courses); ?></div>
            <div class="stat-title">Available Courses</div>
            <div class="stat-description">Visible courses in the system</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-form">
            <div class="search-input-group">
                <input type="text" name="search" id="searchInput" class="search-input" 
                       placeholder="Search by username, email, name, or course..." 
                       value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
            </div>
            
            <div class="filter-group">
                <select name="course" id="courseFilter" class="filter-select">
                    <option value="">All Courses</option>
                    <?php foreach ($available_courses_list as $course): ?>
                        <option value="<?php echo $course->id; ?>" 
                                <?php echo ($course_filter == $course->id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course->fullname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" id="statusFilter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active Enrollments</option>
                    <option value="suspended" <?php echo ($status_filter === 'suspended') ? 'selected' : ''; ?>>Suspended Enrollments</option>
                </select>
            </div>
        </div>
    </div>


    <!-- Loading Indicator -->
    <div id="loadingIndicator" class="loading-indicator" style="display: none;">
        <div class="loading-spinner"></div>
        <span>Loading enrollments...</span>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <table class="enrollments-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Courses</th>
                    <th>Course Count</th>
                    <th>Enrolled Date</th>
                </tr>
            </thead>
            <tbody id="enrollmentsTableBody">
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #6c757d;">
                            <?php if (!empty($search)): ?>
                                No enrolled users found matching your search criteria.
                            <?php else: ?>
                                No enrolled users found.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user->firstname, 0, 1) . substr($user->lastname, 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <h4><?php echo htmlspecialchars($user->firstname . ' ' . $user->lastname); ?></h4>
                                        <p>@<?php echo htmlspecialchars($user->username); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user->email); ?></td>
                            <td>
                                <?php 
                                // All users are considered active (no suspended enrollments)
                                $status_class = 'status-active';
                                $status_text = 'Active';
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($user->courses); ?>
                                </div>
                            </td>
                            <td>
                                <span class="course-count"><?php echo $user->course_count; ?> courses</span>
                            </td>
                            <td><?php echo date('M j, Y', $user->timecreated); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="paginationContainer">
        <?php if ($total_count > $perpage): ?>
            <div class="pagination">
                <?php
                $total_pages = ceil($total_count / $perpage);
                $current_page = $page + 1;
                
                // Previous page
                if ($page > 0) {
                    $prev_url = http_build_query(array_merge($_GET, array('page' => $page - 1)));
                    echo '<a href="?' . $prev_url . '">← Previous</a>';
                }
                
                // Page numbers
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    $url = http_build_query(array_merge($_GET, array('page' => 0)));
                    echo '<a href="?' . $url . '">1</a>';
                    if ($start_page > 2) {
                        echo '<span>...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $page_num = $i - 1;
                    if ($page_num == $page) {
                        echo '<span class="current">' . $i . '</span>';
                    } else {
                        $url = http_build_query(array_merge($_GET, array('page' => $page_num)));
                        echo '<a href="?' . $url . '">' . $i . '</a>';
                    }
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span>...</span>';
                    }
                    $url = http_build_query(array_merge($_GET, array('page' => $total_pages - 1)));
                    echo '<a href="?' . $url . '">' . $total_pages . '</a>';
                }
                
                // Next page
                if ($page < $total_pages - 1) {
                    $next_url = http_build_query(array_merge($_GET, array('page' => $page + 1)));
                    echo '<a href="?' . $next_url . '">Next →</a>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Detail Panel - Centered Modal -->
<div class="user-detail-overlay" id="userDetailOverlay" onclick="closeUserDetail()">
    <div class="user-detail-panel" id="userDetailPanel" onclick="event.stopPropagation()">
        <div class="user-detail-header">
            <button class="user-detail-close" onclick="closeUserDetail()">×</button>
            <div class="user-detail-avatar-large" id="userDetailAvatarLarge"></div>
            <h2 class="user-detail-name" id="userDetailName"></h2>
            <p class="user-detail-role" id="userDetailRole">Enrolled Student</p>
        </div>
        
        <div class="user-detail-body">
            <!-- Details Section -->
            <div class="user-detail-section">
                <h3 class="user-detail-section-title">Details</h3>
                
                <div class="user-detail-item">
                    <div class="user-detail-item-icon">👤</div>
                    <div class="user-detail-item-content">
                        <div class="user-detail-item-label">Username</div>
                        <div class="user-detail-item-value" id="userDetailUsername"></div>
                    </div>
                </div>
                
                <div class="user-detail-item">
                    <div class="user-detail-item-icon">✉</div>
                    <div class="user-detail-item-content">
                        <div class="user-detail-item-label">Email</div>
                        <div class="user-detail-item-value" id="userDetailEmail"></div>
                    </div>
                </div>
                
                <div class="user-detail-item">
                    <div class="user-detail-item-icon">●</div>
                    <div class="user-detail-item-content">
                        <div class="user-detail-item-label">Status</div>
                        <div class="user-detail-item-value">
                            <span class="user-detail-badge customer" id="userDetailStatus"></span>
                        </div>
                    </div>
                </div>
                
                <div class="user-detail-item">
                    <div class="user-detail-item-icon">📅</div>
                    <div class="user-detail-item-content">
                        <div class="user-detail-item-label">Enrolled Date</div>
                        <div class="user-detail-item-value" id="userDetailEnrolledDate"></div>
                    </div>
                </div>
            </div>
            
            <!-- Enrolled Courses Section -->
            <div class="user-detail-section">
                <h3 class="user-detail-section-title">Enrolled Courses</h3>
                <div class="user-detail-courses" id="userDetailCourses">
                    <!-- Courses will be populated here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// AJAX Search and Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const courseFilter = document.getElementById('courseFilter');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody = document.getElementById('enrollmentsTableBody');
    const paginationContainer = document.getElementById('paginationContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    
    if (!searchInput) return;
    
    let filterTimeout;
    let currentPage = 0;
    
    // AJAX function to fetch enrollment data
    function fetchEnrollments(page = 0) {
        showLoading();
        
        const params = new URLSearchParams();
        params.set('action', 'get_enrollments');
        params.set('page', page);
        
        if (searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }
        if (courseFilter && courseFilter.value) {
            params.set('course', courseFilter.value);
        }
        if (statusFilter && statusFilter.value) {
            params.set('status', statusFilter.value);
        }
        
        fetch(`<?php echo $CFG->wwwroot; ?>/theme/remui_kids/enrollments.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                
                currentPage = data.current_page;
                updateTable(data.users);
                updateSummaryCards(data);
                updatePagination(data);
                updateURL(data.filters);
            })
            .catch(error => {
                hideLoading();
                console.error('Fetch error:', error);
            });
    }
    
    // Show loading indicator
    function showLoading() {
        loadingIndicator.style.display = 'flex';
        tableBody.style.opacity = '0.5';
    }
    
    // Hide loading indicator
    function hideLoading() {
        loadingIndicator.style.display = 'none';
        tableBody.style.opacity = '1';
    }
    
    // Update table with new data
    function updateTable(users) {
        if (users.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #6c757d;">
                        No enrolled users found matching your search criteria.
                    </td>
                </tr>
            `;
            // Still call addRowClickHandlers to clean up any existing handlers
            addRowClickHandlers();
            return;
        }
        
        tableBody.innerHTML = users.map(user => `
            <tr>
                <td>
                    <div class="user-info">
                        <div class="user-avatar">
                            ${user.firstname.charAt(0).toUpperCase()}${user.lastname.charAt(0).toUpperCase()}
                        </div>
                        <div class="user-details">
                            <h4>${user.firstname} ${user.lastname}</h4>
                            <p>@${user.username}</p>
                        </div>
                    </div>
                </td>
                <td>${user.email}</td>
                <td>
                    <span class="status-badge ${user.status === 'suspended' ? 'status-suspended' : 'status-active'}">
                        ${user.status === 'suspended' ? 'Suspended' : 'Active'}
                    </span>
                </td>
                <td>
                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${user.courses}
                    </div>
                </td>
                <td>
                    <span class="course-count">${user.course_count} courses</span>
                </td>
                <td>${new Date(user.timecreated * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
            </tr>
        `).join('');
        
        // Re-add click handlers after updating table
        addRowClickHandlers();
    }
    
    // Update summary cards
    function updateSummaryCards(data) {
        // Update summary cards if statistics are provided
        if (data.statistics) {
            const activeCount = document.getElementById('activeCount');
            const suspendedCount = document.getElementById('suspendedCount');
            
            if (activeCount) {
                activeCount.textContent = data.statistics.active.toLocaleString();
            }
            if (suspendedCount) {
                suspendedCount.textContent = data.statistics.suspended.toLocaleString();
            }
        }
    }
    
    // Update pagination
    function updatePagination(data) {
        if (data.total_pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        const currentPage = data.current_page + 1;
        const totalPages = data.total_pages;
        let paginationHTML = '';
        
        // Previous page
        if (data.current_page > 0) {
            paginationHTML += `<a href="#" onclick="changePage(${data.current_page - 1}); return false;">← Previous</a>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            paginationHTML += `<a href="#" onclick="changePage(0); return false;">1</a>`;
            if (startPage > 2) {
                paginationHTML += '<span>...</span>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageNum = i - 1;
            if (pageNum === data.current_page) {
                paginationHTML += `<span class="current">${i}</span>`;
            } else {
                paginationHTML += `<a href="#" onclick="changePage(${pageNum}); return false;">${i}</a>`;
            }
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHTML += '<span>...</span>';
            }
            paginationHTML += `<a href="#" onclick="changePage(${totalPages - 1}); return false;">${totalPages}</a>`;
        }
        
        // Next page
        if (data.current_page < totalPages - 1) {
            paginationHTML += `<a href="#" onclick="changePage(${data.current_page + 1}); return false;">Next →</a>`;
        }
        
        paginationContainer.innerHTML = `<div class="pagination">${paginationHTML}</div>`;
    }
    
    // Update URL without page reload
    function updateURL(filters) {
        const params = new URLSearchParams();
        
        if (filters.search) params.set('search', filters.search);
        if (filters.course) params.set('course', filters.course);
        if (filters.status) params.set('status', filters.status);
        if (currentPage > 0) params.set('page', currentPage);
        
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.pushState({}, '', newUrl);
    }
    
    // Global function for pagination
    window.changePage = function(page) {
        currentPage = page;
        fetchEnrollments(page);
    };
    
    // Auto-search functionality
    function performAutoSearch() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            currentPage = 0; // Reset to first page on new search
            fetchEnrollments(0);
        }, 500); // 500ms delay for auto-search
    }
    
    searchInput.addEventListener('input', function() {
        // Trigger auto-search
        performAutoSearch();
    });
    
    // Add event listeners for filter changes
    if (courseFilter) {
        courseFilter.addEventListener('change', performAutoSearch);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', performAutoSearch);
    }
    
});

// Mobile sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        createMobileHeader();
    }
    
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            createMobileHeader();
        } else {
            removeMobileHeader();
        }
    });
    
    function createMobileHeader() {
        if (document.querySelector('.mobile-header')) return;
        
        const mobileHeader = document.createElement('div');
        mobileHeader.className = 'mobile-header';
        mobileHeader.innerHTML = `
            <button class="hamburger-menu" onclick="toggleSidebar()">
                ☰
            </button>
            <div class="mobile-logo">Enrollments</div>
        `;
        
        document.body.insertBefore(mobileHeader, document.body.firstChild);
        
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.onclick = closeSidebar;
        document.body.appendChild(overlay);
    }
    
    function removeMobileHeader() {
        const mobileHeader = document.querySelector('.mobile-header');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (mobileHeader) mobileHeader.remove();
        if (overlay) overlay.remove();
    }    
    // Add click handlers to table rows
    addRowClickHandlers();
});

// User Detail Panel Functions
function openUserDetail(userData) {
    const panel = document.getElementById('userDetailPanel');
    const overlay = document.getElementById('userDetailOverlay');
    
    // Populate user data
    const initials = userData.firstname.charAt(0).toUpperCase() + userData.lastname.charAt(0).toUpperCase();
    document.getElementById('userDetailAvatarLarge').textContent = initials;
    document.getElementById('userDetailName').textContent = userData.firstname + ' ' + userData.lastname;
    document.getElementById('userDetailUsername').textContent = '@' + userData.username;
    document.getElementById('userDetailEmail').textContent = userData.email;
    document.getElementById('userDetailStatus').textContent = userData.status === 'suspended' ? 'Suspended' : 'Active';
    document.getElementById('userDetailEnrolledDate').textContent = userData.enrolledDate;
    
    // Update status badge style
    const statusBadge = document.getElementById('userDetailStatus');
    statusBadge.className = 'user-detail-badge ' + (userData.status === 'suspended' ? 'personal' : 'customer');
    
    // Populate courses
    const coursesContainer = document.getElementById('userDetailCourses');
    if (userData.courses && userData.courses.length > 0) {
        coursesContainer.innerHTML = userData.courses.map(course => `
            <div class="user-detail-course-item">
                <div class="user-detail-course-name">${course}</div>
                <div class="user-detail-course-date">Enrolled: ${userData.enrolledDate}</div>
            </div>
        `).join('');
    } else {
        coursesContainer.innerHTML = '<p style="color: #6c757d; text-align: center; padding: 15px; font-size: 13px;">No courses enrolled</p>';
    }
    
    // Show modal with animation
    overlay.classList.add('show');
    setTimeout(() => {
        panel.classList.add('show');
    }, 50);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closeUserDetail() {
    const panel = document.getElementById('userDetailPanel');
    const overlay = document.getElementById('userDetailOverlay');
    
    panel.classList.remove('show');
    setTimeout(() => {
        overlay.classList.remove('show');
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }, 300);
}

function addRowClickHandlers() {
    const tableBody = document.getElementById('enrollmentsTableBody');
    if (!tableBody) return;
    
    // Remove existing event listeners by cloning and replacing the table body
    const newTableBody = tableBody.cloneNode(true);
    tableBody.parentNode.replaceChild(newTableBody, tableBody);
    
    const rows = newTableBody.getElementsByTagName('tr');
    for (let row of rows) {
        // Skip if this is the "no results" row
        if (row.cells.length === 1) continue;
        
        row.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const cells = this.cells;
            if (!cells || cells.length < 6) return;
            
            // Extract user data from row
            const userData = {
                firstname: cells[0].textContent.trim().split(/\s+/)[0],
                lastname: cells[0].textContent.trim().split(/\s+/)[1] || '',
                username: cells[0].textContent.match(/@(\w+)/)?.[1] || 'unknown',
                email: cells[1].textContent.trim(),
                status: cells[2].textContent.trim().toLowerCase(),
                courses: [cells[3].textContent.trim()],
                courseCount: cells[4].textContent.trim(),
                enrolledDate: cells[5].textContent.trim()
            };
            
            openUserDetail(userData);
        });
        
        // Add hover effect
        row.style.cursor = 'pointer';
    }
}

// Close panel with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUserDetail();
    }
});

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar, #riyada-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
    }
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar, #riyada-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('open');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('show');
    }
}
</script>

<?php
echo $OUTPUT->footer();
?>

