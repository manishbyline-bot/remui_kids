<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

// Require login
require_login();

// Check if user is admin - restrict access to admins only
$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if (!$hassiteconfig) {
    // User is not an admin, redirect to dashboard
    redirect(new moodle_url('/my/'), 'Access denied. This page is only available to administrators.', null, \core\output\notification::NOTIFY_ERROR);
}

// Set up the page
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/theme/remui_kids/active_users.php');
$PAGE->set_title('Active Users');
$PAGE->set_heading(''); // Empty heading - using custom header instead


// Handle AJAX request for search suggestions
if (isset($_GET['action']) && $_GET['action'] === 'search_suggestions') {
    header('Content-Type: application/json');
    
    $query = optional_param('q', '', PARAM_TEXT);
    error_log("Active users search suggestions request for: " . $query);
    
    if (strlen($query) >= 2) {
        try {
            $search_param = '%' . $query . '%';
            $sql = "SELECT id, username, email, firstname, lastname, timecreated, lastaccess, suspended 
                    FROM {user} 
                    WHERE deleted = 0 
                    AND suspended = 0 
                    AND id > 2
                    AND lastaccess > (UNIX_TIMESTAMP() - (30 * 24 * 60 * 60))
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
                $search_param, $search_param, $search_param, $search_param,
                $search_param, $search_param, $search_param, $search_param
            );
            
            $suggestions = $DB->get_records_sql($sql, $params);
            error_log("Found " . count($suggestions) . " active user suggestions");
            
            $results = array();
            foreach ($suggestions as $user) {
                $fullname = trim($user->firstname . ' ' . $user->lastname);
                error_log("Active User {$user->username}: suspended = {$user->suspended} (type: " . gettype($user->suspended) . ")");
                $results[] = array(
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'fullname' => $fullname ?: 'N/A',
                    'timecreated' => $user->timecreated,
                    'lastaccess' => $user->lastaccess,
                    'suspended' => (int)$user->suspended, // Ensure it's an integer
                    'display' => $user->username . ' (' . ($fullname ?: $user->email) . ')'
                );
            }
            
            echo json_encode(array('success' => true, 'suggestions' => $results));
        } catch (Exception $e) {
            error_log("Error in active users search suggestions: " . $e->getMessage());
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
    } else {
        echo json_encode(array('success' => true, 'suggestions' => array()));
    }
    exit;
}

// Get search parameters
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$sort = optional_param('sort', 'firstname', PARAM_TEXT);
$order = optional_param('order', 'ASC', PARAM_TEXT);

// Build the SQL query for active users only (users active in last 30 days)
$where_conditions = array("u.suspended = 0", "u.lastaccess > (UNIX_TIMESTAMP() - (30 * 24 * 60 * 60))"); // Only active users (suspended = 0 and active in last 30 days)
$params = array();

if (!empty($search)) {
    $where_conditions[] = "(u.id LIKE ? OR u.username LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, array($search_param, $search_param, $search_param, $search_param, $search_param));
}

$where_clause = implode(' AND ', $where_conditions);

// Count total active users
$count_sql = "SELECT COUNT(*) 
              FROM {user} u 
              WHERE $where_clause AND u.deleted = 0 AND u.id > 2";
$total_users = $DB->count_records_sql($count_sql, $params);

// Get active users with pagination
$offset = $page * $perpage;
$users_sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.suspended, u.firstaccess, u.lastaccess
              FROM {user} u 
              WHERE $where_clause AND u.deleted = 0 AND u.id > 2
              ORDER BY u.$sort $order
              LIMIT $perpage OFFSET $offset";

$users = $DB->get_records_sql($users_sql, $params);

// Calculate pagination
$total_pages = ceil($total_users / $perpage);

// Include full width CSS - MUST be before header output
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

echo $OUTPUT->header();

// Add active users JavaScript
$PAGE->requires->js('/theme/remui_kids/js/active_users.js');
?>

<div class="user-details-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <a href="user_management.php" class="back-btn" title="Go back to User Management Dashboard">
                ← Back to Dashboard
            </a>
            <h1>Active Users</h1>
            <p class="header-subtitle">Users active in the last 30 days</p>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <!-- Main Search Row -->
            <div class="search-main-row">
                <div class="search-input-container">
                    <input type="text" 
                           name="search" 
                           id="searchInput"
                           class="search-input" 
                           placeholder="Search by User ID, Username, Email, or Name..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           autocomplete="off">
                </div>
                
                <div class="search-filters">
                    <select name="sort" class="filter-select">
                        <option value="firstname" <?php echo $sort === 'firstname' ? 'selected' : ''; ?>>Sort by First Name</option>
                        <option value="lastname" <?php echo $sort === 'lastname' ? 'selected' : ''; ?>>Sort by Last Name</option>
                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Sort by Username</option>
                        <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Sort by Email</option>
                        <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>Sort by ID</option>
                        <option value="lastaccess" <?php echo $sort === 'lastaccess' ? 'selected' : ''; ?>>Sort by Last Access</option>
                    </select>
                    
                    <select name="order" class="filter-select">
                        <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                    
                    <select name="perpage" class="filter-select">
                        <option value="10" <?php echo $perpage == 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="20" <?php echo $perpage == 20 ? 'selected' : ''; ?>>20 per page</option>
                        <option value="50" <?php echo $perpage == 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $perpage == 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                </div>
                
                <div class="search-buttons">
                    <button type="submit" class="search-btn">
                        🔍 Search
                    </button>
                    <?php if (!empty($search) || $sort !== 'firstname' || $order !== 'ASC' || $perpage !== 20): ?>
                        <a href="active_users.php" class="clear-btn">
                            ✕ Clear All
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Search Row -->
            <div class="quick-search-row">
                <span class="quick-search-label">Quick Search:</span>
                <div class="quick-search-buttons">
                    <button type="button" class="quick-search-btn" data-search="admin">Admins</button>
                    <button type="button" class="quick-search-btn" data-search="teacher">Teachers</button>
                    <button type="button" class="quick-search-btn" data-search="student">Students</button>
                    <button type="button" class="quick-search-btn" data-search="@gmail.com">Gmail Users</button>
                </div>
            </div>
        </form>
    </div>


    <!-- Results Info -->
    <?php if (!empty($search)): ?>
        <div class="results-info">
            🔍 Search results for "<?php echo htmlspecialchars($search); ?>" - 
            Found <?php echo $total_users; ?> active user(s)
        </div>
    <?php else: ?>
        <div class="results-info">
            ● Showing users active in the last 30 days - Total: <?php echo $total_users; ?> user(s)
        </div>
    <?php endif; ?>

    <!-- Users Table / Suggestions Container -->
    <div class="users-table-container">
        <!-- Search Suggestions Section (hidden by default) -->
        <div class="suggestions-section" id="suggestionsSection" style="display: none;">
            <div class="suggestions-header">
                <h3 class="suggestions-title">🔍 Search Suggestions</h3>
                <span class="suggestions-count" id="suggestionsCount">0</span>
            </div>
            <div class="suggestions-table-container">
                <table class="suggestions-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Full Name</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Access</th>
                        </tr>
                    </thead>
                    <tbody id="suggestionsTableBody">
                        <!-- Suggestions will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Users Table (shown by default) -->
        <div id="usersTableSection">
            <table class="users-table">
            <thead>
                <tr>
                    <th class="sortable" data-sort="id">User ID</th>
                    <th class="sortable" data-sort="username">Username</th>
                    <th class="sortable" data-sort="firstname">First Name</th>
                    <th class="sortable" data-sort="lastname">Last Name</th>
                    <th class="sortable" data-sort="email">Email</th>
                    <th>Status</th>
                    <th class="sortable" data-sort="lastaccess">Last Access</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user->id; ?></td>
                            <td><?php echo htmlspecialchars($user->username); ?></td>
                            <td><?php echo htmlspecialchars($user->firstname); ?></td>
                            <td><?php echo htmlspecialchars($user->lastname); ?></td>
                            <td><?php echo htmlspecialchars($user->email); ?></td>
                            <td>
                                <span class="status-badge status-active">Active</span>
                            </td>
                            <td>
                                <?php 
                                if ($user->lastaccess > 0) {
                                    echo date('M j, Y g:i A', $user->lastaccess);
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-results">
                            <?php if (!empty($search)): ?>
                                No active users found matching "<?php echo htmlspecialchars($search); ?>"
                            <?php else: ?>
                                No active users found
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            </table>
        </div> <!-- End usersTableSection -->
    </div> <!-- End users-table-container -->

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination">
                <?php if ($page > 0): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, array('page' => $page - 1))); ?>" class="pagination-btn">
                        <i class="fa fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <span class="pagination-info">
                    Page <?php echo $page + 1; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages - 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, array('page' => $page + 1))); ?>" class="pagination-btn">
                        Next <i class="fa fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Active Users Page Styles */
.user-details-container {
    max-width: 100%;
    width: 100%;
    margin: 0;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f8fafc;
    min-height: 100vh;
}

.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.header-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.header-content {
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    z-index: 2;
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
    font-size: 14px;
}

.back-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    text-decoration: none;
}

.header-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
    position: relative;
    z-index: 2;
    color: white;
}

.header-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    position: relative;
    z-index: 2;
    margin-bottom: 20px;
    color: white;
}

.search-section {
    background: white;
    padding: 18px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
}

.search-main-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input-container {
    flex: 1;
    min-width: 280px;
    position: relative;
}

.search-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.search-filters {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.search-btn {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-btn:hover {
    background: #5a67d8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.clear-btn {
    background: #e53e3e;
    color: white;
    text-decoration: none;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.clear-btn:hover {
    background: #c53030;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
}

/* Suggestions Section */
.suggestions-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
    display: none;
}

.suggestions-section.show {
    display: block !important;
    animation: fadeIn 0.3s ease-out;
}

#usersTableSection {
    display: block;
}

#usersTableSection.hidden {
    display: none;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.suggestions-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f3f4;
}

.suggestions-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.suggestions-count {
    background: #667eea;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.suggestions-table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    margin-top: 20px;
}

.suggestions-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    background: white;
}

.suggestions-table th {
    background: #f9fafb;
    color: #374151;
    font-weight: 600;
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 13px;
    letter-spacing: 0.025em;
    text-transform: none;
}

.suggestions-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
    transition: background-color 0.15s ease;
}

.suggestions-table tbody tr {
    transition: background-color 0.15s ease;
    cursor: pointer;
}

.suggestions-table tbody tr:hover {
    background: #f9fafb;
}

.suggestions-table tbody tr:nth-child(even) {
    background: #fafbfc;
}

.suggestions-table tbody tr:nth-child(even):hover {
    background: #f3f4f6;
}

.suggestion-user-id {
    font-weight: 500;
    color: #374151;
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 13px;
    background: #f3f4f6;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    min-width: 32px;
    text-align: center;
}

.suggestion-username {
    font-weight: 500;
    color: #111827;
    font-size: 14px;
}

.suggestion-email {
    color: #6b7280;
    word-break: break-all;
    font-size: 13px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.suggestion-fullname {
    font-weight: 500;
    color: #111827;
    font-size: 14px;
}

.suggestion-date-info {
    color: #6b7280;
    font-size: 12px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.suggestion-highlight {
    background: #fff3cd;
    padding: 1px 3px;
    border-radius: 3px;
    font-weight: 600;
}

.no-suggestions {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
}

.loading-suggestions {
    text-align: center;
    padding: 40px 20px;
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.loading-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #e9ecef;
    border-top: 2px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}


/* Quick Search */
.quick-search-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 8px;
    border-top: 1px solid #f1f3f4;
}

.quick-search-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #495057;
    white-space: nowrap;
}

.quick-search-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.quick-search-btn {
    background: #f8f9fa;
    color: #495057;
    border: 1px solid #e9ecef;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.quick-search-btn:hover {
    background: #e9ecef;
    border-color: #667eea;
    color: #667eea;
    transform: translateY(-1px);
}

.search-filters {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.results-info {
    background: white;
    padding: 15px 25px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.results-info p {
    margin: 0;
    color: #4a5568;
    font-weight: 500;
}

.users-table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    margin-bottom: 25px;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.users-table thead {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}

.users-table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: #2d3748;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.users-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #4a5568;
    vertical-align: middle;
}

.users-table tbody tr:hover {
    background: #f8fafc;
    transition: background 0.2s ease;
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

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: #c6f6d5;
    color: #22543d;
}

.no-results {
    text-align: center;
    color: #718096;
    font-style: italic;
    padding: 40px 20px;
}

.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 15px;
    background: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

.pagination-btn {
    background: #667eea;
    color: white;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pagination-btn:hover {
    background: #5a67d8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.pagination-info {
    color: #4a5568;
    font-weight: 600;
    font-size: 14px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .user-details-container {
        padding: 15px;
    }
    
    .header-section {
        padding: 20px;
        text-align: center;
    }
    
    .header-section h1 {
        font-size: 2rem;
    }
    
    .back-btn {
        position: static;
        margin-top: 15px;
        display: inline-flex;
    }
    
    .search-main-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input-group {
        flex-direction: column;
    }
    
    .search-filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .users-table-container {
        overflow-x: auto;
    }
    
    .users-table {
        min-width: 600px;
    }
}

@media (max-width: 480px) {
    .user-details-container {
        padding: 10px;
    }
    
    .header-section {
        padding: 15px;
    }
    
    .header-section h1 {
        font-size: 1.5rem;
    }
    
    .search-section {
        padding: 15px;
    }
    
    .users-table th,
    .users-table td {
        padding: 12px 15px;
    }
}

/* Enhanced Responsive Design for Active Users */
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
    
    .search-form {
        flex-direction: column;
        gap: 15px;
    }
    
    .search-input-group {
        width: 100%;
    }
    
    .search-btn {
        width: 100%;
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
    
    .search-form {
        gap: 12px;
    }
    
    .search-input {
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .users-table {
        font-size: 14px;
    }
    
    .users-table th,
    .users-table td {
        padding: 8px 6px;
    }
}

@media (max-width: 768px) {
    .user-details-container {
        padding: 8px;
        margin: 0;
    }
    
    .header-section {
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 10px;
    }
    
    .header-title {
        font-size: 1.8rem;
        margin-bottom: 8px;
    }
    
    .header-subtitle {
        font-size: 0.95rem;
    }
    
    .back-btn {
        padding: 8px 12px;
        font-size: 14px;
    }
    
    .search-form {
        gap: 10px;
    }
    
    .search-input {
        padding: 12px 15px;
        font-size: 16px;
    }
    
    .search-btn {
        padding: 12px 20px;
        font-size: 14px;
    }
    
    /* Make table responsive */
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .users-table {
        min-width: 600px;
        font-size: 13px;
    }
    
    .users-table th,
    .users-table td {
        padding: 6px 4px;
        white-space: nowrap;
    }
    
    .users-table th:first-child,
    .users-table td:first-child {
        position: sticky;
        left: 0;
        background: white;
        z-index: 10;
    }
    
    .pagination {
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .pagination a,
    .pagination span {
        padding: 8px 12px;
        font-size: 14px;
    }
    
    .results-info {
        font-size: 14px;
        margin-bottom: 15px;
    }
}

@media (max-width: 576px) {
    .user-details-container {
        padding: 5px;
    }
    
    .header-section {
        padding: 12px;
        margin-bottom: 12px;
    }
    
    .header-title {
        font-size: 1.6rem;
    }
    
    .header-subtitle {
        font-size: 0.9rem;
    }
    
    .back-btn {
        padding: 6px 10px;
        font-size: 13px;
    }
    
    .search-input {
        padding: 10px 12px;
        font-size: 16px;
    }
    
    .search-btn {
        padding: 10px 16px;
        font-size: 13px;
    }
    
    .users-table {
        min-width: 500px;
        font-size: 12px;
    }
    
    .users-table th,
    .users-table td {
        padding: 4px 2px;
    }
    
    .pagination a,
    .pagination span {
        padding: 6px 8px;
        font-size: 12px;
    }
    
    .results-info {
        font-size: 13px;
    }
    
    /* Hide some columns on very small screens */
    .users-table th:nth-child(4),
    .users-table td:nth-child(4),
    .users-table th:nth-child(5),
    .users-table td:nth-child(5) {
        display: none;
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

<script>
// Search suggestions functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsSection = document.getElementById('suggestionsSection');
    const suggestionsTableBody = document.getElementById('suggestionsTableBody');
    const suggestionsCount = document.getElementById('suggestionsCount');
    const usersTableSection = document.getElementById('usersTableSection');
    let currentSuggestions = [];
    let searchTimeout;

    // Auto-focus search input
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }

    // Real-time search suggestions
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        console.log('Search input changed:', query);
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Hide suggestions if query is too short
        if (query.length < 2) {
            hideSuggestions();
            showUsersTable();
            return;
        }
        
        // Show suggestions section and hide users table
        showSuggestions();
        hideUsersTable();
        
        // Debounce search requests
        searchTimeout = setTimeout(() => {
            console.log('Fetching suggestions for:', query);
            fetchSuggestions(query);
        }, 300);
    });

    // Auto-submit form when filters change
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Small delay to allow user to see the change
            setTimeout(() => {
                searchInput.form.submit();
            }, 100);
        });
    });

    // Handle Enter key to submit search
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.form.submit();
        }
    });

    // Show suggestions when input is focused and has content
    searchInput.addEventListener('focus', function() {
        const query = this.value.trim();
        if (query.length >= 2) {
            showSuggestions();
            hideUsersTable();
            fetchSuggestions(query);
        }
    });

    // Hide suggestions when input loses focus (with delay to allow clicking)
    searchInput.addEventListener('blur', function() {
        setTimeout(() => {
            if (!suggestionsSection.contains(document.activeElement)) {
                hideSuggestions();
                showUsersTable();
            }
        }, 200);
    });

    // Fetch suggestions from server
    function fetchSuggestions(query) {
        console.log('fetchSuggestions called with:', query);
        showLoadingSuggestions();
        
        fetch(`active_users.php?action=search_suggestions&q=${encodeURIComponent(query)}`)
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Suggestions data:', data);
                if (data.success) {
                    currentSuggestions = data.suggestions;
                    displaySuggestions(data.suggestions, query);
                } else {
                    console.error('Suggestions failed:', data.error);
                    showErrorSuggestions();
                }
            })
            .catch(error => {
                console.error('Error fetching suggestions:', error);
                showErrorSuggestions();
            });
    }

    // Display suggestions
    function displaySuggestions(suggestions, query) {
        console.log('displaySuggestions called with:', suggestions.length, 'suggestions');
        currentSuggestions = suggestions;
        suggestionsCount.textContent = suggestions.length;
        
        if (suggestions.length === 0) {
            suggestionsTableBody.innerHTML = '<tr><td colspan="7" class="no-suggestions">No matching active users found</td></tr>';
        } else {
            suggestionsTableBody.innerHTML = suggestions.map((suggestion, index) => {
                const highlightedUsername = highlightText(suggestion.username, query);
                const highlightedEmail = highlightText(suggestion.email, query);
                const highlightedFullname = highlightText(suggestion.fullname, query);
                
                // All users in active users page are active
                const status = 'Active';
                const statusClass = 'status-active';
                
                // Format dates
                const createdDate = suggestion.timecreated ? 
                    new Date(suggestion.timecreated * 1000).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    }) : 'N/A';
                
                const lastAccess = suggestion.lastaccess > 0 ? 
                    new Date(suggestion.lastaccess * 1000).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    }) : 'Never';
                
                return `
                    <tr class="suggestion-row" data-index="${index}" data-username="${suggestion.username}">
                        <td class="suggestion-user-id">${suggestion.id}</td>
                        <td class="suggestion-username">${highlightedUsername}</td>
                        <td class="suggestion-email">${highlightedEmail}</td>
                        <td class="suggestion-fullname">${highlightedFullname}</td>
                        <td><span class="status-badge ${statusClass}">${status}</span></td>
                        <td class="suggestion-date-info">${createdDate}</td>
                        <td class="suggestion-date-info">${lastAccess}</td>
                    </tr>
                `;
            }).join('');
            
            // Add click handlers to suggestion rows
            suggestionsTableBody.querySelectorAll('.suggestion-row').forEach((row, index) => {
                row.addEventListener('click', () => {
                    selectSuggestion(suggestions[index]);
                });
            });
        }
        
        console.log('Suggestions displayed, section should be visible');
    }

    // Highlight matching text
    function highlightText(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<span class="suggestion-highlight">$1</span>');
    }

    // Select a suggestion
    function selectSuggestion(suggestion) {
        searchInput.value = suggestion.username;
        hideSuggestions();
        showUsersTable();
        // Submit the form to search for the selected user
        searchInput.form.submit();
    }

    // Show loading state
    function showLoadingSuggestions() {
        suggestionsTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="loading-suggestions">
                    <div class="loading-spinner"></div>
                    <span>Searching...</span>
                </td>
            </tr>
        `;
    }

    // Show error state
    function showErrorSuggestions() {
        suggestionsTableBody.innerHTML = '<tr><td colspan="7" class="no-suggestions">Error loading suggestions</td></tr>';
    }

    // Hide suggestions
    function hideSuggestions() {
        suggestionsSection.classList.remove('show');
    }

    // Show suggestions
    function showSuggestions() {
        console.log('Showing suggestions section');
        suggestionsSection.classList.add('show');
    }

    // Show users table
    function showUsersTable() {
        console.log('Showing users table');
        usersTableSection.classList.remove('hidden');
    }

    // Hide users table
    function hideUsersTable() {
        console.log('Hiding users table');
        usersTableSection.classList.add('hidden');
    }

    // Handle sortable headers
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
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
            url.searchParams.set('page', '0'); // Reset to first page
            window.location.href = url.toString();
        });
    });
    
    // Quick search buttons
    document.querySelectorAll('.quick-search-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const searchTerm = this.getAttribute('data-search');
            searchInput.value = searchTerm;
            searchInput.form.submit();
        });
    });
    
    // Add loading state to search button
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            const searchBtn = this.querySelector('.search-btn');
            if (searchBtn) {
                searchBtn.innerHTML = '⏳ Searching...';
                searchBtn.disabled = true;
            }
        });
    }
    
    // Test: Show suggestions immediately if there's a search term
    if (searchInput.value.trim().length >= 2) {
        console.log('Page loaded with search term:', searchInput.value);
        showSuggestions();
        hideUsersTable();
        fetchSuggestions(searchInput.value.trim());
    }
    
    // Enhanced back button functionality
    const backBtn = document.querySelector('.back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function(e) {
            // If the link fails, try browser back
            if (e.ctrlKey || e.metaKey) {
                // Allow normal link behavior for Ctrl+click
                return;
            }
            
            // Try to navigate to user management
            try {
                window.location.href = 'user_management.php';
            } catch (error) {
                console.log('Navigation failed, trying browser back');
                window.history.back();
            }
        });
    }
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
            <div class="mobile-logo">Active Users</div>
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
