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
$PAGE->set_url('/theme/remui_kids/pending_approvals.php');
$PAGE->add_body_classes(['limitedwidth', 'page-mypendingapprovals']);
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('pendingapprovals-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Pending Approvals - Riyada Trainings');
$PAGE->set_heading('Pending Approvals');

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Handle AJAX request for search suggestions
if (isset($_GET['action']) && $_GET['action'] === 'search_suggestions') {
    header('Content-Type: application/json');
    echo json_encode(array());
    exit;
}

// Get search parameters
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$sort = optional_param('sort', 'timecreated', PARAM_TEXT);
$order = optional_param('order', 'DESC', PARAM_TEXT);

// Check if the required tables exist
$table_exists = $DB->get_manager()->table_exists('trainingevent_users');
$total_approvals = 0;
$approvals = array();

if ($table_exists) {
    try {
        $total_approvals = $DB->count_records('trainingevent_users', array('approved' => 0));
        
        if ($total_approvals > 0) {
            $offset = $page * $perpage;
            $approvals = $DB->get_records('trainingevent_users', array('approved' => 0), $sort . ' ' . $order, '*', $offset, $perpage);
        }
    } catch (Exception $e) {
        error_log("Error in pending approvals: " . $e->getMessage());
        $table_exists = false;
    }
}

// Calculate pagination
$total_pages = ceil($total_approvals / $perpage);

// Include full width CSS - MUST be before header output
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

echo $OUTPUT->header();

// Add pending approvals JavaScript
$PAGE->requires->js('/theme/remui_kids/js/pending_approvals.js');
?>

<style>
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
}

.header-title {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0 0 5px 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.header-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
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

.results-info {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
    font-weight: 500;
    color: #495057;
}

.approvals-table-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e8ecf0;
}

.approvals-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.approvals-table th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.approvals-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
}

.approvals-table tbody tr:hover {
    background: #f8f9fa;
    transition: background 0.2s ease;
}

.approvals-table tbody tr:last-child td {
    border-bottom: none;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-approve {
    background: #28a745;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-approve:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn-reject {
    background: #dc3545;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-reject:hover {
    background: #c82333;
    transform: translateY(-1px);
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
    
    .search-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input {
        min-width: auto;
    }
    
    .back-btn {
        position: static;
        margin-top: 15px;
        display: inline-flex;
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
    
    .search-input {
        padding: 10px 12px;
        font-size: 16px;
    }
    
    .search-btn {
        padding: 10px 16px;
        font-size: 13px;
    }
}

/* Mobile sidebar behavior */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
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
        <h1 class="header-title">Pending Approvals</h1>
        <p class="header-subtitle">Training events awaiting approval</p>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <input type="text" name="search" class="search-input" placeholder="Search by User ID, Username, Email, Name, or Event Title" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">
                🔍 Search
            </button>
        </form>
    </div>

    <!-- Results Information -->
    <?php if (!$table_exists): ?>
        <div class="results-info" style="background: #fff3cd; border-left-color: #ffc107; color: #856404;">
            ⚠️ Training Events module is not installed or configured. The trainingevent_users table does not exist.
        </div>
    <?php elseif (!empty($search)): ?>
        <div class="results-info">
            🔍 Search results for "<?php echo htmlspecialchars($search); ?>" - 
            Found <?php echo $total_approvals; ?> pending approval(s)
        </div>
    <?php else: ?>
        <div class="results-info">
            📊 Showing all pending approvals - Total: <?php echo $total_approvals; ?> approval(s)
        </div>
    <?php endif; ?>

    <!-- Approvals Table -->
    <div class="approvals-table-container">
        <?php if (!$table_exists): ?>
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
                <h3 style="margin: 0 0 10px 0; color: #495057;">Training Events Module Not Available</h3>
                <p style="margin: 0;">The training events module is not installed or configured. Please contact your administrator to set up the training events functionality.</p>
            </div>
        <?php elseif (!empty($approvals)): ?>
            <table class="approvals-table">
                <thead>
                    <tr>
                        <th>Request Date</th>
                        <th>User ID</th>
                        <th>Training Event ID</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approvals as $approval): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i A', $approval->timecreated); ?></td>
                            <td><?php echo $approval->userid; ?></td>
                            <td><?php echo $approval->trainingeventid; ?></td>
                            <td>
                                <span class="status-badge status-pending">Pending</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-approve" onclick="approveRequest(<?php echo $approval->id; ?>)">✓ Approve</button>
                                    <button class="btn-reject" onclick="rejectRequest(<?php echo $approval->id; ?>)">✗ Reject</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <div style="font-size: 48px; margin-bottom: 20px;">📋</div>
                <h3 style="margin: 0 0 10px 0; color: #495057;">No Pending Approvals</h3>
                <p style="margin: 0;">All training event requests have been processed.</p>
            </div>
        <?php endif; ?>
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
// Approval/Rejection functions
function approveRequest(approvalId) {
    if (confirm('Are you sure you want to approve this training event request?')) {
        console.log('Approving request:', approvalId);
        // Here you would typically make an AJAX call to update the approval status
        // For now, just reload the page
        window.location.reload();
    }
}

function rejectRequest(approvalId) {
    if (confirm('Are you sure you want to reject this training event request?')) {
        console.log('Rejecting request:', approvalId);
        // Here you would typically make an AJAX call to update the approval status
        // For now, just reload the page
        window.location.reload();
    }
}

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
            <div class="mobile-logo">Pending Approvals</div>
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
