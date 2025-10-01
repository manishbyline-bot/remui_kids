<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

redirect_if_major_upgrade_required();

require_login();

// Check if user is admin - restrict access to admins only
$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if (!$hassiteconfig) {
    // User is not an admin, redirect to dashboard
    redirect(new moodle_url('/my/'), 'Access denied. This page is only available to administrators.', null, \core\output\notification::NOTIFY_ERROR);
}

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Set up the page exactly like schools.php
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/department_managers.php');
$PAGE->add_body_classes(['limitedwidth', 'page-mydepartmentmanagers']);
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('departmentmanagers-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Department Managers - Riyada Trainings');
$PAGE->set_heading('Department Managers');

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Handle AJAX request for search suggestions
if (isset($_GET['action']) && $_GET['action'] === 'search_suggestions') {
    header('Content-Type: application/json');
    
    $query = optional_param('q', '', PARAM_TEXT);
    error_log("Department managers search suggestions request for: " . $query);
    
    if (strlen($query) >= 2) {
        try {
            $search_param = '%' . $query . '%';
            $sql = "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname, u.timecreated, u.lastaccess, u.suspended,
                           r.shortname as role_name
                    FROM {user} u
                    INNER JOIN {role_assignments} ra ON u.id = ra.userid
                    INNER JOIN {role} r ON ra.roleid = r.id
                    WHERE u.deleted = 0 
                    AND u.id > 1
                    AND ra.roleid IN (10, 11)
                    AND (u.username LIKE ? OR u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)
                    ORDER BY 
                        CASE 
                            WHEN u.username LIKE ? THEN 1
                            WHEN u.email LIKE ? THEN 2
                            WHEN u.firstname LIKE ? THEN 3
                            WHEN u.lastname LIKE ? THEN 4
                            ELSE 5
                        END,
                        u.username ASC
                    LIMIT 10";
            
            $params = array(
                $search_param, $search_param, $search_param, $search_param,
                $search_param, $search_param, $search_param, $search_param
            );
            
            $suggestions = $DB->get_records_sql($sql, $params);
            
            $results = array();
            foreach ($suggestions as $suggestion) {
                $results[] = array(
                    'id' => $suggestion->id,
                    'username' => $suggestion->username,
                    'email' => $suggestion->email,
                    'firstname' => $suggestion->firstname,
                    'lastname' => $suggestion->lastname,
                    'role_name' => $suggestion->role_name,
                    'timecreated' => $suggestion->timecreated,
                    'lastaccess' => $suggestion->lastaccess
                );
            }
            
            echo json_encode($results);
        } catch (Exception $e) {
            error_log("Error in department managers search suggestions: " . $e->getMessage());
            echo json_encode(array());
        }
    } else {
        echo json_encode(array());
    }
    exit;
}

// Get search parameters
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$sort = optional_param('sort', 'firstname', PARAM_TEXT);
$order = optional_param('order', 'ASC', PARAM_TEXT);

// Build the SQL query for department managers
$where_conditions = array("u.deleted = 0", "u.id > 1", "ra.roleid IN (10, 11)"); // Department managers with roles 10 and 11
$params = array();

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, array($search_param, $search_param, $search_param, $search_param));
}

$where_clause = implode(' AND ', $where_conditions);

// Count total department managers
$count_sql = "SELECT COUNT(DISTINCT u.id) 
              FROM {user} u
              INNER JOIN {role_assignments} ra ON u.id = ra.userid
              WHERE $where_clause";
$total_managers = $DB->count_records_sql($count_sql, $params);

// Get department managers with pagination
$offset = $page * $perpage;
$managers_sql = "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname, u.timecreated, u.lastaccess, u.suspended,
                        GROUP_CONCAT(r.shortname SEPARATOR ', ') as roles
                 FROM {user} u
                 INNER JOIN {role_assignments} ra ON u.id = ra.userid
                 INNER JOIN {role} r ON ra.roleid = r.id
                 WHERE $where_clause
                 GROUP BY u.id
                 ORDER BY u.$sort $order
                 LIMIT $perpage OFFSET $offset";

$managers = $DB->get_records_sql($managers_sql, $params);

// Calculate pagination
$total_pages = ceil($total_managers / $perpage);

// Include full width CSS - MUST be before header output
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

echo $OUTPUT->header();

// Add department managers JavaScript
$PAGE->requires->js('/theme/remui_kids/js/department_managers.js');
?>

<style>
/* Copy all CSS from user_details.php */
.user-details-container {
    max-width: 100%;
    width: 100%;
    margin: 0;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    position: relative;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
    overflow: hidden;
}

.header-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
    pointer-events: none;
}

.header-title {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0 0 5px 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: relative;
    z-index: 2;
}

.header-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    position: relative;
    z-index: 2;
    margin-bottom: 0;
}

.back-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 1000;
    pointer-events: auto;
}

.back-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    text-decoration: none;
}

.search-section {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
    border: 1px solid #e8ecf0;
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.search-main-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 250px;
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-select {
    padding: 10px 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 13px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}

.search-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.search-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.quick-search-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.quick-search-btn {
    background: #f8f9fa;
    border: 2px solid #e1e5e9;
    color: #495057;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.quick-search-btn:hover {
    background: #667eea;
    border-color: #667eea;
    color: white;
    transform: translateY(-1px);
}

.quick-search-btn.active {
    background: #667eea;
    border-color: #667eea;
    color: white;
}

.results-info {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
    font-weight: 500;
    color: #495057;
}

.managers-table-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e8ecf0;
}

.managers-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.managers-table th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 13px;
    letter-spacing: 0.025em;
}

.managers-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
}

.managers-table tbody tr:hover {
    background: #f8f9fa;
    transition: background 0.2s ease;
}

.managers-table tbody tr:last-child td {
    border-bottom: none;
}

.sortable {
    cursor: pointer;
    position: relative;
    user-select: none;
    transition: color 0.3s ease;
}

.sortable:hover {
    color: #667eea;
}

.sortable::after {
    content: '↕';
    margin-left: 5px;
    opacity: 0.5;
    font-size: 12px;
}

.sortable.asc::after {
    content: '↑';
    opacity: 1;
    color: #667eea;
}

.sortable.desc::after {
    content: '↓';
    opacity: 1;
    color: #667eea;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.user-name {
    font-weight: 600;
    color: #2c3e50;
}

.user-email {
    font-size: 12px;
    color: #6c757d;
}

.role-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
    margin: 2px;
}

.role-admin {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.role-manager {
    background: #cce5ff;
    color: #004085;
    border: 1px solid #b3d7ff;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 30px;
    padding: 20px;
}

.pagination a, .pagination span {
    padding: 8px 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    text-decoration: none;
    color: #495057;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination a:hover {
    background: #667eea;
    border-color: #667eea;
    color: white;
    transform: translateY(-1px);
}

.pagination .current {
    background: #667eea;
    border-color: #667eea;
    color: white;
}

.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.suggestions-section {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
    border: 1px solid #e8ecf0;
    display: none;
}

.suggestions-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.suggestions-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #495057;
}

.suggestions-count {
    background: #667eea;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.suggestions-list {
    max-height: 300px;
    overflow-y: auto;
}

.suggestion-item {
    padding: 12px 20px;
    border-bottom: 1px solid #f1f3f4;
    cursor: pointer;
    transition: background 0.2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.suggestion-item:hover {
    background: #f8f9fa;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.suggestion-name {
    font-weight: 600;
    color: #2c3e50;
}

.suggestion-details {
    font-size: 12px;
    color: #6c757d;
}

.suggestion-role {
    font-size: 11px;
    color: #667eea;
    font-weight: 500;
}

.highlight {
    background: #fff3cd;
    padding: 1px 2px;
    border-radius: 2px;
}

.loading-suggestions {
    padding: 20px;
    text-align: center;
    color: #6c757d;
    font-style: italic;
}

.error-suggestions {
    padding: 20px;
    text-align: center;
    color: #dc3545;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 768px) {
    .user-details-container {
        padding: 15px;
    }
    
    .header-section {
        padding: 15px 20px;
    }
    
    .header-title {
        font-size: 1.8rem;
    }
    
    .search-main-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input {
        min-width: auto;
    }
    
    .filter-group {
        justify-content: space-between;
    }
    
    .back-btn {
        position: static;
        margin-top: 15px;
        display: inline-flex;
    }
    
    .search-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .managers-table {
        font-size: 12px;
    }
    
    .managers-table th,
    .managers-table td {
        padding: 10px 8px;
    }
}

@media (max-width: 480px) {
    .user-details-container {
        padding: 10px;
    }
    
    .header-section {
        padding: 12px 15px;
    }
    
    .header-title {
        font-size: 1.4rem;
    }
    
    .search-section {
        padding: 15px;
    }
    
    .managers-table {
        font-size: 11px;
    }
    
    .managers-table th,
    .managers-table td {
        padding: 8px 6px;
    }
    
    .quick-search-buttons {
        justify-content: center;
    }
    
    .quick-search-btn {
        font-size: 12px;
        padding: 6px 12px;
    }
}

/* Enhanced Responsive Design */
@media (max-width: 1200px) {
    .user-details-container {
        padding: 15px;
    }
    
    .header-section {
        padding: 25px;
    }
    
    .header-title {
        font-size: 2.2rem;
    }
}

@media (max-width: 992px) {
    .user-details-container {
        padding: 10px;
    }
    
    .header-section {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .header-title {
        font-size: 2rem;
    }
    
    .header-subtitle {
        font-size: 1rem;
    }
    
    .search-input {
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .managers-table {
        font-size: 14px;
    }
    
    .managers-table th,
    .managers-table td {
        padding: 8px 6px;
    }
}

/* Mobile sidebar behavior */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }
    
    .sidebar-overlay.show {
        display: block;
    }
    
    .main-content {
        margin-left: 0;
        width: 100%;
    }
    
    .mobile-header {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        background: white;
        border-bottom: 1px solid #e9ecef;
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    
    .hamburger-menu {
        display: block;
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
        margin-right: 15px;
    }
    
    .mobile-logo {
        font-size: 1.2rem;
        font-weight: 600;
        color: #52C9D9;
    }
}

@media (min-width: 769px) {
    .mobile-header {
        display: none;
    }
    
    .hamburger-menu {
        display: none;
    }
}
</style>

<div class="user-details-container">
    <!-- Header Section -->
    <div class="header-section">
        <a href="user_management.php" class="back-btn" title="Go back to User Management Dashboard" onclick="console.log('Back button clicked'); window.location.href='user_management.php'; return false;">
            ← Back to Dashboard
        </a>
        <h1 class="header-title">Department Managers</h1>
        <p class="header-subtitle">Users with manager roles in the system</p>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <!-- Main Search Row -->
            <div class="search-main-row">
                <input type="text" name="search" class="search-input" placeholder="Search by User ID, Username, Email, or Name" value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
                
                <div class="filter-group">
                    <select name="sort" class="filter-select">
                        <option value="firstname" <?php echo $sort === 'firstname' ? 'selected' : ''; ?>>Sort by First Name</option>
                        <option value="lastname" <?php echo $sort === 'lastname' ? 'selected' : ''; ?>>Sort by Last Name</option>
                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Sort by Username</option>
                        <option value="timecreated" <?php echo $sort === 'timecreated' ? 'selected' : ''; ?>>Sort by Join Date</option>
                        <option value="lastaccess" <?php echo $sort === 'lastaccess' ? 'selected' : ''; ?>>Sort by Last Access</option>
                    </select>
                    
                    <select name="order" class="filter-select">
                        <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                    
                    <select name="perpage" class="filter-select">
                        <option value="10" <?php echo $perpage === 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="20" <?php echo $perpage === 20 ? 'selected' : ''; ?>>20 per page</option>
                        <option value="50" <?php echo $perpage === 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $perpage === 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                </div>
                
                <button type="submit" class="search-btn">
                    🔍 Search
                </button>
            </div>
            
            <!-- Quick Search Buttons -->
            <div class="quick-search-buttons">
                <button type="button" class="quick-search-btn" data-search="admin">School Admins</button>
                <button type="button" class="quick-search-btn" data-search="manager">Department Managers</button>
                <button type="button" class="quick-search-btn" data-search="active">Active Users</button>
                <button type="button" class="quick-search-btn" data-search="recent">Recently Added</button>
            </div>
        </form>
    </div>

    <!-- Results Information -->
    <?php if (!empty($search)): ?>
        <div class="results-info">
            🔍 Search results for "<?php echo htmlspecialchars($search); ?>" - 
            Found <?php echo $total_managers; ?> department manager(s)
        </div>
    <?php else: ?>
        <div class="results-info">
            📊 Showing all department managers - Total: <?php echo $total_managers; ?> manager(s)
        </div>
    <?php endif; ?>

    <!-- Managers Table / Suggestions Container -->
    <div class="managers-table-container">
        <!-- Search Suggestions Section (hidden by default) -->
        <div class="suggestions-section" id="suggestionsSection" style="display: none;">
            <div class="suggestions-header">
                <h3 class="suggestions-title">🔍 Search Suggestions</h3>
                <span class="suggestions-count" id="suggestionsCount">0</span>
            </div>
            <div class="suggestions-list" id="suggestionsList">
                <!-- Suggestions will be populated here -->
            </div>
        </div>

        <!-- Managers Table -->
        <div class="managers-table-wrapper" id="managersTableWrapper">
            <?php if (!empty($managers)): ?>
                <table class="managers-table">
                    <thead>
                        <tr>
                            <th class="sortable <?php echo $sort === 'firstname' ? strtolower($order) : ''; ?>" data-sort="firstname">Name</th>
                            <th class="sortable <?php echo $sort === 'username' ? strtolower($order) : ''; ?>" data-sort="username">Username</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th class="sortable <?php echo $sort === 'timecreated' ? strtolower($order) : ''; ?>" data-sort="timecreated">Join Date</th>
                            <th class="sortable <?php echo $sort === 'lastaccess' ? strtolower($order) : ''; ?>" data-sort="lastaccess">Last Access</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($managers as $manager): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($manager->firstname . ' ' . $manager->lastname); ?></div>
                                        <div style="font-size: 11px; color: #6c757d;">ID: <?php echo $manager->id; ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: #2c3e50;">@<?php echo htmlspecialchars($manager->username); ?></div>
                                </td>
                                <td>
                                    <div class="user-email"><?php echo htmlspecialchars($manager->email); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $roles = explode(', ', $manager->roles);
                                    foreach ($roles as $role): 
                                        $role_class = (strpos(strtolower($role), 'admin') !== false) ? 'role-admin' : 'role-manager';
                                    ?>
                                        <span class="role-badge <?php echo $role_class; ?>"><?php echo htmlspecialchars(trim($role)); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', $manager->timecreated); ?>
                                    <br>
                                    <small style="color: #6c757d;"><?php echo date('g:i A', $manager->timecreated); ?></small>
                                </td>
                                <td>
                                    <?php if ($manager->lastaccess > 0): ?>
                                        <?php echo date('M j, Y', $manager->lastaccess); ?>
                                        <br>
                                        <small style="color: #6c757d;"><?php echo date('g:i A', $manager->lastaccess); ?></small>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($manager->suspended == 0): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-suspended">Suspended</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #6c757d;">
                    <div style="font-size: 48px; margin-bottom: 20px;">👥</div>
                    <h3 style="margin: 0 0 10px 0; color: #495057;">No Department Managers Found</h3>
                    <p style="margin: 0;">No users with manager roles found in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 0): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, array('page' => $page - 1))); ?>">« Previous</a>
            <?php else: ?>
                <span class="disabled">« Previous</span>
            <?php endif; ?>

            <?php
            $start_page = max(0, $page - 2);
            $end_page = min($total_pages - 1, $page + 2);
            
            if ($start_page > 0): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, array('page' => 0))); ?>">1</a>
                <?php if ($start_page > 1): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i + 1; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, array('page' => $i))); ?>"><?php echo $i + 1; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages - 1): ?>
                <?php if ($end_page < $total_pages - 2): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, array('page' => $total_pages - 1))); ?>"><?php echo $total_pages; ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages - 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, array('page' => $page + 1))); ?>">Next »</a>
            <?php else: ?>
                <span class="disabled">Next »</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsSection = document.getElementById('suggestionsSection');
    const suggestionsList = document.getElementById('suggestionsList');
    const suggestionsCount = document.getElementById('suggestionsCount');
    const managersTableWrapper = document.getElementById('managersTableWrapper');
    
    let searchTimeout;
    
    // Search suggestions functionality
    function fetchSuggestions(query) {
        if (query.length < 2) {
            hideSuggestions();
            return;
        }
        
        showLoadingSuggestions();
        
        fetch(`department_managers.php?action=search_suggestions&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                displaySuggestions(data);
            })
            .catch(error => {
                console.error('Error fetching suggestions:', error);
                showErrorSuggestions();
            });
    }
    
    function displaySuggestions(suggestions) {
        if (suggestions.length === 0) {
            suggestionsList.innerHTML = '<div class="loading-suggestions">No matching managers found</div>';
            suggestionsCount.textContent = '0';
        } else {
            suggestionsList.innerHTML = suggestions.map(suggestion => `
                <div class="suggestion-item" onclick="selectSuggestion('${suggestion.username}')">
                    <div class="suggestion-info">
                        <div class="suggestion-name">${highlightText(suggestion.firstname + ' ' + suggestion.lastname, searchInput.value)}</div>
                        <div class="suggestion-details">${suggestion.email} • @${suggestion.username}</div>
                        <div class="suggestion-role">${suggestion.role_name}</div>
                    </div>
                </div>
            `).join('');
            suggestionsCount.textContent = suggestions.length;
        }
        showSuggestions();
    }
    
    function highlightText(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<span class="highlight">$1</span>');
    }
    
    function selectSuggestion(username) {
        searchInput.value = username;
        hideSuggestions();
        showManagersTable();
        searchInput.form.submit();
    }
    
    function showLoadingSuggestions() {
        suggestionsList.innerHTML = '<div class="loading-suggestions">Loading suggestions...</div>';
        suggestionsCount.textContent = '...';
        showSuggestions();
    }
    
    function showErrorSuggestions() {
        suggestionsList.innerHTML = '<div class="error-suggestions">Error loading suggestions</div>';
        suggestionsCount.textContent = '!';
        showSuggestions();
    }
    
    function hideSuggestions() {
        suggestionsSection.style.display = 'none';
        showManagersTable();
    }
    
    function showSuggestions() {
        suggestionsSection.style.display = 'block';
        hideManagersTable();
    }
    
    function showManagersTable() {
        managersTableWrapper.style.display = 'block';
    }
    
    function hideManagersTable() {
        managersTableWrapper.style.display = 'none';
    }
    
    // Event listeners
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                fetchSuggestions(query);
            }, 300);
        } else {
            hideSuggestions();
        }
    });
    
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            fetchSuggestions(this.value.trim());
        }
    });
    
    searchInput.addEventListener('blur', function() {
        // Delay hiding to allow clicking on suggestions
        setTimeout(() => {
            hideSuggestions();
        }, 200);
    });
    
    // Filter change handlers
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', function() {
            searchInput.form.submit();
        });
    });
    
    // Sortable headers
    document.querySelectorAll('.sortable').forEach(header => {
        header.addEventListener('click', function() {
            const sort = this.dataset.sort;
            const currentSort = new URLSearchParams(window.location.search).get('sort');
            const currentOrder = new URLSearchParams(window.location.search).get('order');
            
            let newOrder = 'ASC';
            if (currentSort === sort && currentOrder === 'ASC') {
                newOrder = 'DESC';
            }
            
            const url = new URL(window.location);
            url.searchParams.set('sort', sort);
            url.searchParams.set('order', newOrder);
            window.location.href = url.toString();
        });
    });
    
    // Quick search buttons
    document.querySelectorAll('.quick-search-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const searchTerm = this.dataset.search;
            searchInput.value = searchTerm;
            
            // Remove active class from all buttons
            document.querySelectorAll('.quick-search-btn').forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            // Submit the form
            searchInput.form.submit();
        });
    });
    
    // Test: Show suggestions immediately if there's a search term
    if (searchInput.value.trim().length >= 2) {
        console.log('Page loaded with search term:', searchInput.value);
        showSuggestions();
        hideManagersTable();
        fetchSuggestions(searchInput.value.trim());
    }
    
    // Back button is now handled by onclick attribute for guaranteed functionality
});

// Mobile sidebar functionality - using vanilla JavaScript to avoid conflicts
document.addEventListener('DOMContentLoaded', function() {
    // Create mobile header if it doesn't exist
    if (window.innerWidth <= 768) {
        createMobileHeader();
    }
    
    // Handle window resize
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
            <div class="mobile-logo">Department Managers</div>
        `;
        
        document.body.insertBefore(mobileHeader, document.body.firstChild);
        
        // Create sidebar overlay
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
});

// Global functions for sidebar - using vanilla JS to avoid jQuery conflicts
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
