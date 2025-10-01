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
$PAGE->set_url('/theme/remui_kids/recent_uploads.php');
$PAGE->add_body_classes(['fullwidth-layout', 'page-myrecentuploads']);
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('recentuploads-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Recent Uploads - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Handle AJAX request for search suggestions
if (isset($_GET['action']) && $_GET['action'] === 'search_suggestions') {
    header('Content-Type: application/json');
    
    $query = optional_param('q', '', PARAM_TEXT);
    error_log("Recent uploads search suggestions request for: " . $query);
    
    if (strlen($query) >= 2) {
        try {
            $this_month_start = strtotime(date('Y-m-01'));
            $search_param = '%' . $query . '%';
            $sql = "SELECT id, username, email, firstname, lastname, timecreated, lastaccess, suspended 
                    FROM {user} 
                    WHERE deleted = 0 
                    AND id > 1
                    AND timecreated >= ?
                    AND (username LIKE ? OR email LIKE ? OR firstname LIKE ? OR lastname LIKE ?)
                    ORDER BY 
                        CASE 
                            WHEN username LIKE ? THEN 1
                            WHEN email LIKE ? THEN 2
                            WHEN firstname LIKE ? THEN 3
                            WHEN lastname LIKE ? THEN 4
                            ELSE 5
                        END,
                        username ASC
                    LIMIT 10";
            
            $params = array(
                $this_month_start,
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
                    'timecreated' => $suggestion->timecreated,
                    'lastaccess' => $suggestion->lastaccess
                );
            }
            
            echo json_encode($results);
        } catch (Exception $e) {
            error_log("Error in recent uploads search suggestions: " . $e->getMessage());
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
$sort = optional_param('sort', 'timecreated', PARAM_TEXT);
$order = optional_param('order', 'DESC', PARAM_TEXT);

// Get this month's start timestamp
$this_month_start = strtotime(date('Y-m-01'));

// Build the SQL query for recent uploads
$where_conditions = array("u.deleted = 0", "u.id > 1", "u.timecreated >= ?"); // Users created this month
$params = array($this_month_start);

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, array($search_param, $search_param, $search_param, $search_param));
}

$where_clause = implode(' AND ', $where_conditions);

// Count total recent uploads
$count_sql = "SELECT COUNT(*) 
              FROM {user} u 
              WHERE $where_clause";
$total_uploads = $DB->count_records_sql($count_sql, $params);

// Get recent uploads with pagination
$offset = $page * $perpage;
$uploads_sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname, u.timecreated, u.lastaccess, u.suspended
                FROM {user} u 
                WHERE $where_clause
                ORDER BY u.$sort $order
                LIMIT $perpage OFFSET $offset";

$uploads = $DB->get_records_sql($uploads_sql, $params);

// Calculate pagination
$total_pages = ceil($total_uploads / $perpage);

echo $OUTPUT->header();

// Add recent uploads JavaScript
$PAGE->requires->js('/theme/remui_kids/js/recent_uploads.js');
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

.uploads-table-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e8ecf0;
}

.uploads-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.uploads-table th {
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

.uploads-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
}

.uploads-table tbody tr:hover {
    background: #f8f9fa;
    transition: background 0.2s ease;
}

.uploads-table tbody tr:last-child td {
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

.new-badge {
    background: #cce5ff;
    color: #004085;
    border: 1px solid #b3d7ff;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 8px;
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
    
    .uploads-table {
        font-size: 12px;
    }
    
    .uploads-table th,
    .uploads-table td {
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
    
    .uploads-table {
        font-size: 11px;
    }
    
    .uploads-table th,
    .uploads-table td {
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
</style>

<div class="user-details-container">
    <!-- Header Section -->
    <div class="header-section">
        <a href="user_management.php" class="back-btn" title="Go back to User Management Dashboard" onclick="console.log('Back button clicked'); window.location.href='user_management.php'; return false;">
            ← Back to Dashboard
        </a>
        <h1 class="header-title">Recent Uploads</h1>
        <p class="header-subtitle">Users uploaded this month (<?php echo date('F Y'); ?>)</p>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <!-- Main Search Row -->
            <div class="search-main-row">
                <input type="text" name="search" class="search-input" placeholder="Search by User ID, Username, Email, or Name" value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
                
                <div class="filter-group">
                    <select name="sort" class="filter-select">
                        <option value="timecreated" <?php echo $sort === 'timecreated' ? 'selected' : ''; ?>>Sort by Upload Date</option>
                        <option value="firstname" <?php echo $sort === 'firstname' ? 'selected' : ''; ?>>Sort by First Name</option>
                        <option value="lastname" <?php echo $sort === 'lastname' ? 'selected' : ''; ?>>Sort by Last Name</option>
                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Sort by Username</option>
                        <option value="lastaccess" <?php echo $sort === 'lastaccess' ? 'selected' : ''; ?>>Sort by Last Access</option>
                    </select>
                    
                    <select name="order" class="filter-select">
                        <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
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
                <button type="button" class="quick-search-btn" data-search="today">Today</button>
                <button type="button" class="quick-search-btn" data-search="week">This Week</button>
                <button type="button" class="quick-search-btn" data-search="active">Active Users</button>
                <button type="button" class="quick-search-btn" data-search="new">New Users</button>
            </div>
        </form>
    </div>

    <!-- Results Information -->
    <?php if (!empty($search)): ?>
        <div class="results-info">
            🔍 Search results for "<?php echo htmlspecialchars($search); ?>" - 
            Found <?php echo $total_uploads; ?> recent upload(s)
        </div>
    <?php else: ?>
        <div class="results-info">
            ● Showing users uploaded this month (<?php echo date('F Y'); ?>) - Total: <?php echo $total_uploads; ?> user(s)
        </div>
    <?php endif; ?>

    <!-- Uploads Table / Suggestions Container -->
    <div class="uploads-table-container">
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

        <!-- Uploads Table -->
        <div class="uploads-table-wrapper" id="uploadsTableWrapper">
            <?php if (!empty($uploads)): ?>
                <table class="uploads-table">
                    <thead>
                        <tr>
                            <th class="sortable <?php echo $sort === 'timecreated' ? strtolower($order) : ''; ?>" data-sort="timecreated">Upload Date</th>
                            <th class="sortable <?php echo $sort === 'firstname' ? strtolower($order) : ''; ?>" data-sort="firstname">Name</th>
                            <th class="sortable <?php echo $sort === 'username' ? strtolower($order) : ''; ?>" data-sort="username">Username</th>
                            <th>Email</th>
                            <th class="sortable <?php echo $sort === 'lastaccess' ? strtolower($order) : ''; ?>" data-sort="lastaccess">Last Access</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploads as $upload): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $upload_date = date('M j, Y', $upload->timecreated);
                                    $upload_time = date('g:i A', $upload->timecreated);
                                    $is_today = date('Y-m-d', $upload->timecreated) === date('Y-m-d');
                                    $is_this_week = $upload->timecreated >= strtotime('monday this week');
                                    ?>
                                    <div>
                                        <?php echo $upload_date; ?>
                                        <?php if ($is_today): ?>
                                            <span class="new-badge">Today</span>
                                        <?php elseif ($is_this_week): ?>
                                            <span class="new-badge">This Week</span>
                                        <?php endif; ?>
                                    </div>
                                    <small style="color: #6c757d;"><?php echo $upload_time; ?></small>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($upload->firstname . ' ' . $upload->lastname); ?></div>
                                        <div style="font-size: 11px; color: #6c757d;">ID: <?php echo $upload->id; ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: #2c3e50;">@<?php echo htmlspecialchars($upload->username); ?></div>
                                </td>
                                <td>
                                    <div class="user-email"><?php echo htmlspecialchars($upload->email); ?></div>
                                </td>
                                <td>
                                    <?php if ($upload->lastaccess > 0): ?>
                                        <?php echo date('M j, Y', $upload->lastaccess); ?>
                                        <br>
                                        <small style="color: #6c757d;"><?php echo date('g:i A', $upload->lastaccess); ?></small>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($upload->suspended == 0): ?>
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
                    <div style="font-size: 48px; margin-bottom: 20px;">📁</div>
                    <h3 style="margin: 0 0 10px 0; color: #495057;">No Recent Uploads</h3>
                    <p style="margin: 0;">No users have been uploaded this month (<?php echo date('F Y'); ?>).</p>
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
    const uploadsTableWrapper = document.getElementById('uploadsTableWrapper');
    
    let searchTimeout;
    
    // Search suggestions functionality
    function fetchSuggestions(query) {
        if (query.length < 2) {
            hideSuggestions();
            return;
        }
        
        showLoadingSuggestions();
        
        fetch(`recent_uploads.php?action=search_suggestions&q=${encodeURIComponent(query)}`)
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
            suggestionsList.innerHTML = '<div class="loading-suggestions">No matching uploads found</div>';
            suggestionsCount.textContent = '0';
        } else {
            suggestionsList.innerHTML = suggestions.map(suggestion => `
                <div class="suggestion-item" onclick="selectSuggestion('${suggestion.username}')">
                    <div class="suggestion-info">
                        <div class="suggestion-name">${highlightText(suggestion.firstname + ' ' + suggestion.lastname, searchInput.value)}</div>
                        <div class="suggestion-details">${suggestion.email} • @${suggestion.username}</div>
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
        showUploadsTable();
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
        showUploadsTable();
    }
    
    function showSuggestions() {
        suggestionsSection.style.display = 'block';
        hideUploadsTable();
    }
    
    function showUploadsTable() {
        uploadsTableWrapper.style.display = 'block';
    }
    
    function hideUploadsTable() {
        uploadsTableWrapper.style.display = 'none';
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
        hideUploadsTable();
        fetchSuggestions(searchInput.value.trim());
    }
    
    // Back button is now handled by onclick attribute for guaranteed functionality
});
</script>

<?php
echo $OUTPUT->footer();
?>
