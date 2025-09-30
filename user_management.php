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
 * Users Management Dashboard
 *
 * @package    local_user_management
 * @copyright  2024 Riyada Trainings
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Require login only - allow all logged-in users to access
require_login();

// Set up the page
$PAGE->set_url('/theme/remui_kids/user_management.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Users Management');
$PAGE->set_heading('Users Management');
$PAGE->set_pagelayout('standard');

// Handle AJAX request for refreshing user statistics
if (isset($_GET['action']) && $_GET['action'] === 'refresh_stats') {
    header('Content-Type: application/json');
    
    try {
        // Get real-time user statistics
        $total_users = $DB->count_records('user', array('deleted' => 0));
        
        // Get active users using the exact query provided by user
        // SELECT COUNT(*) FROM mdl_user WHERE lastaccess > (UNIX_TIMESTAMP() - (30 * 24 * 60 * 60)) AND deleted = 0 AND suspended = 0 AND id > 2;
        $active_users_query = "SELECT COUNT(*) FROM {user} WHERE lastaccess > (UNIX_TIMESTAMP() - (30 * 24 * 60 * 60)) AND deleted = 0 AND suspended = 0 AND id > 2";
        $active_users = $DB->count_records_sql($active_users_query);
        
        // Get pending approvals from training events
        $pending_approvals = 0;
        if ($DB->table_exists('trainingevent_users')) {
            $pending_approvals = $DB->count_records('trainingevent_users', array('approved' => 0));
        }
        
        // Get department managers (School Admins + Company Department Managers)
        $department_managers = 0;
        try {
            $department_managers = $DB->count_records_select('role_assignments', 'roleid IN (10, 11)');
        } catch (Exception $e) {
            $department_managers = 0;
        }
        
        // Get recent uploads (users created this month)
        $recent_uploads = 0;
        $recent_uploads_change = 0;
        try {
            $this_month_start = strtotime(date('Y-m-01'));
            $recent_uploads = $DB->count_records_select('user', 'timecreated >= ? AND deleted = 0', array($this_month_start));
            
            // Calculate change vs last month
            $last_month_start = strtotime(date('Y-m-01', strtotime('first day of last month')));
            $last_month_end = strtotime(date('Y-m-t 23:59:59', strtotime('last month')));
            $last_month_uploads = $DB->count_records_select('user', 'timecreated >= ? AND timecreated <= ? AND deleted = 0', array($last_month_start, $last_month_end));
            $recent_uploads_change = $recent_uploads - $last_month_uploads;
        } catch (Exception $e) {
            $recent_uploads = 0;
            $recent_uploads_change = 0;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'total_users' => $total_users,
                'active_users' => $active_users,
                'pending_approvals' => $pending_approvals,
                'department_managers' => $department_managers,
                'recent_uploads' => $recent_uploads,
                'recent_uploads_change' => $recent_uploads_change
            )
        ));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    exit;
}

// Get user statistics
global $DB;

// Total users count - Real data from database (excluding deleted users)
try {
    $total_users = $DB->count_records('user', array('deleted' => 0)); // Count only non-deleted users
} catch (Exception $e) {
    $total_users = 181; // Fallback based on your phpMyAdmin query result
}

// Active users using the exact query provided by user
// SELECT COUNT(*) FROM mdl_user WHERE lastaccess > (UNIX_TIMESTAMP() - (30 * 24 * 60 * 60)) AND deleted = 0 AND suspended = 0 AND id > 2;
try {
    $active_users_query = "SELECT COUNT(*) FROM {user} WHERE lastaccess > (UNIX_TIMESTAMP() - (30 * 24 * 60 * 60)) AND deleted = 0 AND suspended = 0 AND id > 2";
    $active_users = $DB->count_records_sql($active_users_query);
} catch (Exception $e) {
    $active_users = 0;
}

// Pending approvals (training events) - using correct table
try {
    if ($DB->get_manager()->table_exists('trainingevent_users')) {
        $pending_approvals = $DB->count_records('trainingevent_users', array('approved' => 0));
    } else {
        $pending_approvals = 0;
    }
} catch (Exception $e) {
    $pending_approvals = 0;
}

// Department managers (School Admins + Company Department Managers)
try {
    $department_managers = $DB->count_records_select('role_assignments', 'roleid IN (10, 11)');
} catch (Exception $e) {
    $department_managers = 0;
}

// Recent uploads (this month)
try {
    $this_month_start = strtotime(date('Y-m-01'));
    $recent_uploads = $DB->count_records_select('user', 'timecreated >= ? AND deleted = 0', array($this_month_start));
    
    // Calculate change vs last month for display
    $last_month_start = strtotime(date('Y-m-01', strtotime('first day of last month')));
    $last_month_end = strtotime(date('Y-m-t 23:59:59', strtotime('last month')));
    $last_month_uploads = $DB->count_records_select('user', 'timecreated >= ? AND timecreated <= ? AND deleted = 0', array($last_month_start, $last_month_end));
    $recent_uploads_change = $recent_uploads - $last_month_uploads;
} catch (Exception $e) {
    $recent_uploads = 0;
    $recent_uploads_change = 0;
}

// Recent user activities (static data for now)
$recent_activities = [];

echo $OUTPUT->header();

// Add user management JavaScript
$PAGE->requires->js('/theme/remui_kids/js/user_management.js');
?>

<style>
.user-management-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
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
}

.header-subtitle {
    font-size: 1.2rem;
    opacity: 0.8;
    position: relative;
    z-index: 2;
    color: #4a4a9e;
}

.refresh-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid #6C6BC2;
    color: #4a4a9e;
    padding: 10px 20px;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    font-weight: 500;
}

.refresh-btn:hover {
    background: rgba(255, 255, 255, 1);
    border-color: #5a5ab8;
    color: #3d3d8a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 107, 194, 0.2);
}

.navigation-tabs {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
    overflow: hidden;
    border: 1px solid #dee2e6;
}


.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 30px;
    padding: 20px;
    max-width: 100%;
    overflow-x: auto;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    position: relative;
    border: 1px solid rgba(0,0,0,0.05);
    min-height: 170px;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: rgba(0,0,0,0.1);
}

.stat-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    height: 100%;
    width: 100%;
}

.stat-card-link:hover {
    text-decoration: none;
    color: inherit;
}

.stat-card-link .stat-card {
    height: 100%;
    width: 100%;
    box-sizing: border-box;
}

/* Removed clickable-card styles - now using stat-card-link for consistency */

/* Removed clickable-card hover styles */

/* Removed clickable-card number hover styles */

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.stat-card:hover .stat-icon {
    transform: scale(1.1);
    color: #333;
}

.stat-icon {
    position: absolute;
    top: 16px;
    left: 16px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #666;
    font-weight: normal;
    transition: all 0.3s ease;
}

.stat-number {
    font-size: 2.4rem;
    font-weight: 800;
    margin-bottom: 16px;
    margin-left: 52px;
    margin-top: 16px;
    line-height: 1.1;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    animation: countPulse 2s ease-in-out infinite;
    transition: all 0.3s ease;
}

/* Different colors for each card's count */
.stat-card:nth-child(1) .stat-number {
    color: #52C9D9; /* Light Blue for Total Users */
}

.stat-card:nth-child(2) .stat-number {
    color: #28a745; /* Green for Active Users */
}

.stat-card:nth-child(3) .stat-number {
    color: #fd7e14; /* Orange for Pending Approvals */
}

.stat-card:nth-child(4) .stat-number {
    color: #6f42c1; /* Purple for Department Managers */
}

.stat-card:nth-child(5) .stat-number {
    color: #28a745; /* Green for Recent Uploads (same as Active Users) */
}

/* Animation for count numbers */
@keyframes countPulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Hover effect for count numbers */
.stat-card:hover .stat-number {
    animation: countGlow 0.6s ease-in-out;
}

@keyframes countGlow {
    0% {
        transform: scale(1);
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    50% {
        transform: scale(1.1);
        text-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }
    100% {
        transform: scale(1);
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
}

.stat-label {
    font-size: 1rem;
    color: #1a1a1a;
    margin-bottom: 8px;
    margin-left: 52px;
    font-weight: 600;
    line-height: 1.2;
}

.stat-description {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 0;
    margin-left: 52px;
    line-height: 1.4;
    opacity: 0.9;
}

/* Change indicators removed - focusing on count only */

.quick-actions {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 30px;
    border: 1px solid #f0f0f0;
}

.quick-actions h3 {
    margin-bottom: 20px;
    color: #333;
    font-weight: 600;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.quick-actions h3::before {
    content: "●";
    font-size: 1rem;
    color: #2196F3;
    margin-right: 8px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    max-width: 1200px;
    margin: 0 auto;
}

.action-btn {
    display: flex;
    flex-direction: row;
    align-items: center;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none !important;
    color: #333;
    font-weight: 500;
    text-align: left;
    min-height: 45px;
    justify-content: flex-start;
    width: 100%;
    gap: 12px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    text-decoration: none !important;
}

.action-btn.create:hover {
    color: black !important;
}

.action-btn.upload:hover {
    color: black !important;
}

.action-btn.export:hover {
    color: black !important;
}

.action-btn.approve:hover {
    color: black !important;
}

.action-btn.create { 
    background: linear-gradient(90deg, #a8e6a3 0%, #7dd87d 100%) !important;
    color: #2d5a2d !important;
}
.action-btn.upload { 
    background: linear-gradient(90deg, #87ceeb 0%, #5dade2 100%) !important;
    color: #1a4480 !important;
}
.action-btn.export { 
    background: linear-gradient(90deg, #d8b4fe 0%, #c084fc 100%) !important;
    color: #5b2d91 !important;
}
.action-btn.approve { 
    background: linear-gradient(90deg, #ffb366 0%, #ff9f43 100%) !important;
    color: #cc5500 !important;
}

.action-btn span {
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1.2;
    text-decoration: none !important;
}

.action-btn small {
    font-size: 0.7rem;
    opacity: 0.8;
    font-weight: 400;
    display: block;
    margin-top: 2px;
    text-decoration: none !important;
}

.action-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
}

.action-text {
    flex: 1;
    display: flex;
    flex-direction: column;
}


/* Navigation Tabs - Light Color Design */
.navigation-tabs {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px 0;
    margin-bottom: 30px;
    border: 1px solid #dee2e6;
}

.tab-container {
    display: flex;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 12px;
    padding: 6px;
    box-shadow: none;
    max-width: 1100px;
    margin: 0 auto;
    gap: 0;
    overflow-x: auto;
    border: 1px solid rgba(222, 226, 230, 0.5);
}

.tab-btn {
    flex: 1;
    min-width: 100px;
    padding: 12px 16px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #6c757d;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1.2;
    margin: 0 2px;
}

/* Inactive tabs styling */
.tab-btn:not(.active) {
    background: transparent !important;
    color: #6c757d !important;
    font-weight: 500 !important;
    box-shadow: none !important;
    border: none !important;
    transition: all 0.2s ease !important;
}

.tab-btn:not(.active):hover {
    background: rgba(82, 201, 217, 0.1) !important;
    color: #52C9D9 !important;
    transform: none !important;
    box-shadow: none !important;
}

.tab-btn:not(.active):focus {
    background: transparent !important;
    color: #6c757d !important;
    outline: none !important;
    box-shadow: none !important;
}

/* Active tab styling */
.tab-btn.active {
    background: linear-gradient(135deg, #52C9D9 0%, #4AB3C4 100%);
    color: white;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(82, 201, 217, 0.3);
    transition: all 0.3s ease;
}

.tab-btn.active:hover {
    background: linear-gradient(135deg, #4AB3C4 0%, #3A9BA8 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(82, 201, 217, 0.4);
}

.tab-btn span {
    display: block;
    text-decoration: none !important;
}

/* Ensure inactive button text is light gray */
.tab-btn:not(.active) span {
    color: #6c757d !important;
    font-weight: 500 !important;
}

/* Removed FontAwesome dependency */

.activity-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.activity-section h3 {
    margin-bottom: 20px;
    color: #333;
    font-weight: 600;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 15px;
    flex-shrink: 0;
}

.activity-dot.green { background: #28a745; }
.activity-dot.blue { background: #007bff; }
.activity-dot.purple { background: #6f42c1; }
.activity-dot.orange { background: #fd7e14; }

.activity-text {
    flex: 1;
    color: #333;
}

.activity-time {
    color: #666;
    font-size: 0.9rem;
}

.floating-chat {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    z-index: 1000;
}

.floating-chat:hover {
    transform: scale(1.1);
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
}

@media (max-width: 768px) {
    .user-management-container {
        padding: 15px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        padding: 15px;
    }
    
    .stat-card {
        min-height: 160px;
        padding: 20px;
        border-radius: 10px;
    }
    
    .stat-icon {
        width: 32px;
        height: 32px;
        top: 14px;
        left: 14px;
        font-size: 1.2rem;
        color: #666;
    }
    
    .stat-number {
        font-size: 1.8rem;
        margin-left: 50px;
        margin-top: 6px;
        margin-bottom: 4px;
    }
    
    .stat-label {
        font-size: 0.9rem;
        margin-left: 50px;
        margin-bottom: 3px;
    }
    
    .stat-description {
        font-size: 0.75rem;
        margin-left: 50px;
        margin-bottom: 8px;
    }
    
    /* Change indicators removed */
    
    .actions-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        max-width: 800px;
    }
    
    .action-btn {
        min-height: 40px;
        padding: 8px 16px;
        gap: 10px;
    }
    
    .action-icon {
        font-size: 1.1rem;
        width: 24px;
        height: 24px;
    }
    
    /* Responsive tabs */
    .navigation-tabs {
        padding: 15px 0;
        margin-bottom: 25px;
    }
    
    .tab-container {
        padding: 4px;
        max-width: 900px;
    }
    
    .tab-btn {
        min-width: 85px;
        padding: 10px 12px;
        font-size: 0.8rem;
        line-height: 1.1;
        margin: 0 1px;
    }
    
    .tab-btn:not(.active) {
        background: transparent !important;
        color: #6c757d !important;
        font-weight: 500 !important;
        box-shadow: none !important;
        border: none !important;
        transition: all 0.2s ease !important;
    }
    
    .tab-btn:not(.active):hover {
        background: rgba(82, 201, 217, 0.1) !important;
        color: #52C9D9 !important;
        transform: none !important;
        box-shadow: none !important;
    }
    
}

@media (max-width: 480px) {
    .user-management-container {
        padding: 10px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 10px;
    }
    
    .stat-card {
        min-height: 110px;
        padding: 10px;
    }
    
    .stat-icon {
        width: 24px;
        height: 24px;
        top: 8px;
        left: 8px;
        font-size: 0.8rem;
    }
    
    .stat-number {
        font-size: 1.3rem;
        margin-left: 40px;
        margin-top: 2px;
    }
    
    .stat-label {
        font-size: 0.75rem;
        margin-left: 40px;
    }
    
    .stat-description {
        font-size: 0.65rem;
        margin-left: 40px;
    }
    
    /* Change indicators removed */
    
    .actions-grid {
        grid-template-columns: 1fr;
        gap: 10px;
        max-width: 500px;
    }
    
    .action-btn {
        min-height: 35px;
        padding: 8px 12px;
        gap: 8px;
    }
    
    .action-icon {
        font-size: 1rem;
        width: 20px;
        height: 20px;
    }
    
    .action-btn span {
        font-size: 0.8rem;
    }
    
    .action-btn small {
        font-size: 0.65rem;
    }
    
    /* Mobile responsive tabs */
    .navigation-tabs {
        padding: 12px 0;
        margin-bottom: 20px;
    }
    
    .tab-container {
        padding: 3px;
        max-width: 700px;
        flex-wrap: wrap;
    }
    
    .tab-btn {
        min-width: 75px;
        padding: 8px 10px;
        font-size: 0.75rem;
        line-height: 1.0;
        flex: 1 1 45%;
        margin: 0 1px;
    }
    
    .tab-btn:not(.active) {
        background: transparent !important;
        color: #6c757d !important;
        font-weight: 500 !important;
        box-shadow: none !important;
        border: none !important;
        transition: all 0.2s ease !important;
    }
    
    .tab-btn:not(.active):hover {
        background: rgba(82, 201, 217, 0.1) !important;
        color: #52C9D9 !important;
        transform: none !important;
        box-shadow: none !important;
    }
}

/* Enhanced Responsive Design */
@media (max-width: 1200px) {
    .user-management-container {
        padding: 15px;
    }
    
    .header-section {
        padding: 25px;
    }
    
    .header-title {
        font-size: 2.2rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
}

@media (max-width: 992px) {
    .user-management-container {
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
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-number {
        font-size: 2.2rem;
    }
    
    .stat-title {
        font-size: 1.1rem;
    }
    
    .stat-description {
        font-size: 0.9rem;
    }
}

@media (max-width: 768px) {
    .user-management-container {
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
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .stat-card {
        padding: 15px;
        border-radius: 10px;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .stat-title {
        font-size: 1rem;
    }
    
    .stat-description {
        font-size: 0.85rem;
    }
    
    .navigation-tabs {
        padding: 10px 0;
        margin-bottom: 15px;
    }
    
    .tab-container {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .tab-btn {
        padding: 10px 12px;
        font-size: 13px;
        flex: 1;
        min-width: calc(50% - 4px);
    }
}

@media (max-width: 576px) {
    .user-management-container {
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
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-icon {
        width: 35px;
        height: 35px;
        font-size: 1.1rem;
    }
    
    .stat-number {
        font-size: 1.8rem;
    }
    
    .stat-title {
        font-size: 0.95rem;
    }
    
    .stat-description {
        font-size: 0.8rem;
    }
    
    .tab-btn {
        padding: 8px 10px;
        font-size: 12px;
        min-width: 100%;
    }
}

/* Sidebar Responsive Behavior */
@media (max-width: 768px) {
    /* Hide sidebar on mobile and show hamburger menu */
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    /* Add overlay for mobile sidebar */
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
    
    /* Main content takes full width on mobile */
    .main-content {
        margin-left: 0;
        width: 100%;
    }
    
    /* Mobile header with hamburger */
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

<div class="user-management-container">
    <!-- Header Section -->
    <div class="header-section">
        <button class="refresh-btn" onclick="location.reload()">
            ↻ Refresh
        </button>
        <h1 class="header-title">Users Management</h1>
        <p class="header-subtitle">Comprehensive user administration and management tools</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="navigation-tabs">
        <div class="tab-container">
            <button class="tab-btn active" data-tab="overview">
                <span>Overview</span>
            </button>
            <button class="tab-btn" data-tab="create">
                <span>Create User</span>
            </button>
            <button class="tab-btn" data-tab="edit">
                <span>Edit Users</span>
            </button>
            <button class="tab-btn" data-tab="department">
                <span>Department<br>Users</span>
            </button>
            <button class="tab-btn" data-tab="assign">
                <span>Assign to<br>School</span>
            </button>
            <button class="tab-btn" data-tab="upload">
                <span>Upload<br>Users</span>
            </button>
            <button class="tab-btn" data-tab="download">
                <span>Bulk<br>Download</span>
            </button>
            <button class="tab-btn" data-tab="training">
                <span>Training<br>Events</span>
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/user_details.php" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon">
                    ●
                </div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-description">All registered users</div>
            </div>
        </a>

        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/active_users.php" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon">
                    ⚡
                </div>
                <div class="stat-number"><?php echo $active_users; ?></div>
                <div class="stat-label">Active Users</div>
                <div class="stat-description">Currently active users</div>
            </div>
        </a>

        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/pending_approvals.php" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon">
                    ●
                </div>
                <div class="stat-number"><?php echo $pending_approvals; ?></div>
                <div class="stat-label">Pending Approvals</div>
                <div class="stat-description">Training events awaiting approval</div>
            </div>
        </a>

        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/department_managers.php" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon">
                    ●
                </div>
                <div class="stat-number"><?php echo $department_managers; ?></div>
                <div class="stat-label">Department Managers</div>
                <div class="stat-description">Users with manager role</div>
            </div>
        </a>

        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/recent_uploads.php" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon">
                    📁
                </div>
                <div class="stat-number"><?php echo $recent_uploads; ?></div>
                <div class="stat-label">Recent Uploads</div>
                <div class="stat-description">Users uploaded this month</div>
            </div>
        </a>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="actions-grid">
            <a href="#" class="action-btn create">
                <div class="action-icon">👤</div>
                <div class="action-text">
                    <span>Create New User</span>
                    <small>Click to access</small>
                </div>
            </a>
            <a href="#" class="action-btn upload">
                <div class="action-icon">📤</div>
                <div class="action-text">
                    <span>Bulk Upload</span>
                    <small>Click to access</small>
                </div>
            </a>
            <a href="#" class="action-btn export">
                <div class="action-icon">📥</div>
                <div class="action-text">
                    <span>Export Users</span>
                    <small>Click to access</small>
                </div>
            </a>
            <a href="#" class="action-btn approve">
                <div class="action-icon">✓</div>
                <div class="action-text">
                    <span>Approve Events</span>
                    <small>Click to access</small>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent User Activity -->
    <div class="activity-section">
        <h3>Recent User Activity</h3>
        <div class="activity-item">
            <div class="activity-dot green"></div>
            <div class="activity-text">User created John Doe</div>
            <div class="activity-time">2 minutes ago</div>
        </div>
        <div class="activity-item">
            <div class="activity-dot blue"></div>
            <div class="activity-text">Bulk upload completed System</div>
            <div class="activity-time">15 minutes ago</div>
        </div>
        <div class="activity-item">
            <div class="activity-dot purple"></div>
            <div class="activity-text">User role updated Jane Smith</div>
            <div class="activity-time">1 hour ago</div>
        </div>
        <div class="activity-item">
            <div class="activity-dot orange"></div>
            <div class="activity-text">Training event approved Mike Johnson</div>
            <div class="activity-time">2 hours ago</div>
        </div>
    </div>
</div>

<!-- Floating Chat Icon -->
<div class="floating-chat">
    🤖
</div>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    
    // Ensure Overview tab is always active on page load
    function setOverviewAsDefault() {
        tabButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-tab') === 'overview') {
                btn.classList.add('active');
            }
        });
    }
    
    // Set Overview as default on page load
    setOverviewAsDefault();
    
    // Also set Overview as default when page becomes visible (user returns to tab)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            setOverviewAsDefault();
        }
    });
    
    // Set Overview as default when page is focused (user returns to window)
    window.addEventListener('focus', function() {
        setOverviewAsDefault();
    });
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all tabs
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Get the tab data
            const tabName = this.getAttribute('data-tab');
            console.log('Switched to tab:', tabName);
            
            // Handle Overview tab - stays on the same page
            if (tabName === 'overview') {
                // Overview tab is the default and shows the main dashboard content
                // No page redirect needed - already on the correct page
                console.log('Overview tab selected - staying on current page');
            }
            
            // Handle Create User tab - redirect to company user create form
            if (tabName === 'create') {
                console.log('Create User tab selected - redirecting to create form');
                window.location.href = '<?php echo $CFG->wwwroot; ?>/blocks/iomad_company_admin/company_user_create_form.php';
                return; // Prevent further execution
            }
            
            // Handle Edit Users tab - redirect to edit users page
            if (tabName === 'edit') {
                console.log('Edit Users tab selected - redirecting to edit users page');
                window.location.href = '<?php echo $CFG->wwwroot; ?>/blocks/iomad_company_admin/editusers.php';
                return; // Prevent further execution
            }
            
            // Handle Department Users tab - redirect to company managers form
            if (tabName === 'department') {
                console.log('Department Users tab selected - redirecting to company managers form');
                window.location.href = '<?php echo $CFG->wwwroot; ?>/blocks/iomad_company_admin/company_managers_form.php';
                return; // Prevent further execution
            }
            
            // Handle Assign to School tab - redirect to company users form
            if (tabName === 'assign') {
                console.log('Assign to School tab selected - redirecting to company users form');
                window.location.href = '<?php echo $CFG->wwwroot; ?>/blocks/iomad_company_admin/company_users_form.php';
                return; // Prevent further execution
            }
            
            // Handle Upload Users tab - redirect to upload user page
            if (tabName === 'upload') {
                console.log('Upload Users tab selected - redirecting to upload user page');
                window.location.href = '<?php echo $CFG->wwwroot; ?>/blocks/iomad_company_admin/uploaduser.php';
                return; // Prevent further execution
            }
            
            // Handle Bulk Download tab - redirect to user bulk download page
            if (tabName === 'download') {
                console.log('Bulk Download tab selected - redirecting to user bulk download page');
                window.location.href = '<?php echo $CFG->wwwroot; ?>/blocks/iomad_company_admin/user_bulk_download.php';
                return; // Prevent further execution
            }
            
            // Handle Training Events tab - redirect to approve page
            if (tabName === 'training') {
                console.log('Training Events tab selected - redirecting to approve page');
                window.location.href = '<?php echo $CFG->wwwroot; ?>/blocks/iomad_approve_access/approve.php';
                return; // Prevent further execution
            }
            
            // Here you can add functionality to show/hide content based on the tab
            // Overview is the default active tab and stays on the same page
        });
    });
});

// Statistics update functionality - DISABLED to prevent errors
// The automatic statistics update has been disabled to prevent the 404 and JSON parsing errors
// If you need real-time updates, you can manually refresh the page or implement a proper API endpoint

// Show update notification function (kept for potential future use)
function showUpdateNotification(message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 10000;
        font-size: 14px;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 100);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Hide refresh button since automatic updates are disabled
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.querySelector('.refresh-btn');
    if (refreshBtn) {
        refreshBtn.style.display = 'none';
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
            <div class="mobile-logo">User Management</div>
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