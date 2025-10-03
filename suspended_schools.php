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
 * Suspended Schools Page - Shows only suspended schools with search functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

redirect_if_major_upgrade_required();

require_login();

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Set up the page exactly like schools.php
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/suspended_schools.php');
$PAGE->add_body_classes(['fullwidth-layout', 'page-mysuspendedschools']);
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('suspendedschools-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Suspended Schools - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Add jQuery and custom CSS
$PAGE->requires->jquery();

// Handle AJAX request for filtered suspended school data
if (isset($_GET['action']) && $_GET['action'] === 'get_suspended_schools') {
    header('Content-Type: application/json');
    
    $search = optional_param('search', '', PARAM_TEXT);
    $page = optional_param('page', 0, PARAM_INT);
    $perpage = 20;
    
    // Debug mode - set to true to get detailed error information
    $debug_mode = false;
    
    try {
        // Check if company table exists
        $company_table_exists = $DB->get_manager()->table_exists('company');
        
        if ($company_table_exists) {
            // Test if we can actually query the table
            try {
                $test_count = $DB->count_records('company');
            } catch (Exception $e) {
                throw new Exception("Company table exists but cannot be queried: " . $e->getMessage());
            }
            
            // Build the query for suspended schools only
            $where_conditions = array('suspended = 1');
            $params = array();

            if (!empty($search)) {
                $search_param = '%' . $search . '%';
                $where_conditions[] = "(name LIKE ? OR shortname LIKE ? OR city LIKE ?)";
                $params = array_merge($params, array($search_param, $search_param, $search_param));
            }

            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

            // Get total count for pagination
            $count_sql = "SELECT COUNT(*) FROM {company} " . $where_clause;
            $total_count = $DB->count_records_sql($count_sql, $params);

            // Get paginated results - simplified query to avoid complex joins
            $sql = "SELECT c.* FROM {company} c $where_clause ORDER BY c.name ASC";
            $schools = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
            
            // Get user counts separately for each school and handle timestamps
            foreach ($schools as $school) {
                try {
                    if ($DB->get_manager()->table_exists('company_users')) {
                        $user_count = $DB->count_records('company_users', array('companyid' => $school->id));
                        $school->user_count = $user_count;
                    } else {
                        $school->user_count = 0;
                    }
                } catch (Exception $e) {
                    $school->user_count = 0;
                }
                
                try {
                    if ($DB->get_manager()->table_exists('company_departments')) {
                        $dept_count = $DB->count_records('company_departments', array('companyid' => $school->id));
                        $school->department_count = $dept_count;
                    } else {
                        $school->department_count = 0;
                    }
                } catch (Exception $e) {
                    $school->department_count = 0;
                }
                
                // Handle timestamps properly
                if (!$school->timecreated || $school->timecreated == 0) {
                    if ($school->timemodified && $school->timemodified > 0) {
                        $school->timecreated = $school->timemodified;
                    } else {
                        $school->timecreated = time();
                    }
                }
                
                if (!$school->timemodified || $school->timemodified == 0) {
                    if ($school->timecreated && $school->timecreated > 0) {
                        $school->timemodified = $school->timecreated;
                    } else {
                        $school->timemodified = time();
                    }
                }
            }
            
            // Calculate statistics
            $active_schools = $DB->count_records('company', array('suspended' => 0));
            $suspended_schools = $DB->count_records('company', array('suspended' => 1));
            
            // Prepare response data
            $response = array(
                'schools' => array(),
                'total_count' => $total_count,
                'current_page' => $page,
                'per_page' => $perpage,
                'total_pages' => ceil($total_count / $perpage),
                'filters' => array(
                    'search' => $search
                ),
                'statistics' => array(
                    'active' => $active_schools,
                    'suspended' => $suspended_schools,
                    'total' => $active_schools + $suspended_schools
                )
            );
            
            foreach ($schools as $school) {
                $response['schools'][] = array(
                    'id' => $school->id,
                    'name' => $school->name,
                    'shortname' => $school->shortname,
                    'city' => $school->city,
                    'country' => $school->country,
                    'suspended' => $school->suspended,
                    'status' => 'suspended',
                    'status_text' => 'Suspended',
                    'user_count' => $school->user_count,
                    'department_count' => $school->department_count,
                    'timecreated' => $school->timecreated,
                    'timemodified' => $school->timemodified
                );
            }
            
            echo json_encode($response);
            
        } else {
            // Fallback to course categories if company table doesn't exist
            try {
                $where_conditions = array();
                $params = array();

                if (!empty($search)) {
                    $search_param = '%' . $search . '%';
                    $where_conditions[] = "(name LIKE ? OR idnumber LIKE ?)";
                    $params = array_merge($params, array($search_param, $search_param));
                }

                $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                
                $count_sql = "SELECT COUNT(*) FROM {course_categories} " . $where_clause;
                $total_count = $DB->count_records_sql($count_sql, $params);
                
                $sql = "SELECT * FROM {course_categories} $where_clause ORDER BY name ASC";
                $schools = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
                
                foreach ($schools as $school) {
                    $school->user_count = 0;
                    $school->department_count = 0;
                    $school->suspended = 1; // Mark as suspended for display
                    $school->city = '';
                    $school->country = '';
                    
                    // Handle timestamps properly
                    if (!$school->timecreated || $school->timecreated == 0) {
                        if ($school->timemodified && $school->timemodified > 0) {
                            $school->timecreated = $school->timemodified;
                        } else {
                            $school->timecreated = time();
                        }
                    }
                    
                    if (!$school->timemodified || $school->timemodified == 0) {
                        if ($school->timecreated && $school->timecreated > 0) {
                            $school->timemodified = $school->timecreated;
                        } else {
                            $school->timemodified = time();
                        }
                    }
                }
                
                $active_schools = 0;
                $suspended_schools = $total_count;
                
                $response = array(
                    'schools' => array_values($schools),
                    'total_count' => $total_count,
                    'current_page' => $page,
                    'per_page' => $perpage,
                    'total_pages' => ceil($total_count / $perpage),
                    'filters' => array(
                        'search' => $search
                    ),
                    'statistics' => array(
                        'active' => $active_schools,
                        'suspended' => $suspended_schools,
                        'total' => $active_schools + $suspended_schools
                    )
                );
                
                echo json_encode($response);
                
            } catch (Exception $e) {
                echo json_encode(array(
                    'error' => 'No school data available - IOMAD not installed and course categories not accessible',
                    'schools' => array(),
                    'total_count' => 0,
                    'statistics' => array(
                        'active' => 0,
                        'suspended' => 0,
                        'total' => 0
                    )
                ));
            }
        }
        
    } catch (Exception $e) {
        // Log the detailed error for debugging
        error_log("Suspended schools AJAX error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $error_message = 'Database error: ' . $e->getMessage();
        if ($debug_mode) {
            $error_message .= ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine();
        }
        
        echo json_encode(array(
            'error' => $error_message,
            'schools' => array(),
            'total_count' => 0,
            'statistics' => array(
                'active' => 0,
                'suspended' => 0,
                'total' => 0
            )
        ));
    }
    exit;
}

// Get initial suspended school statistics
try {
    $company_table_exists = $DB->get_manager()->table_exists('company');
    
    if ($company_table_exists) {
        $total_schools = $DB->count_records('company');
        $active_schools = $DB->count_records('company', array('suspended' => 0));
        $suspended_schools = $DB->count_records('company', array('suspended' => 1));
    } else {
        // Fallback to course categories
        try {
            $total_schools = $DB->count_records('course_categories');
            $active_schools = 0;
            $suspended_schools = $total_schools;
        } catch (Exception $e) {
            $total_schools = 0;
            $active_schools = 0;
            $suspended_schools = 0;
        }
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Suspended schools page error: " . $e->getMessage());
    $total_schools = 0;
    $active_schools = 0;
    $suspended_schools = 0;
}

// Get search parameters
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

echo $OUTPUT->header();
?>

<style>
.suspended-schools-container {
    max-width: calc(100% - 40px) !important;
    width: 100% !important;
    margin: 0 auto !important;
    padding: 20px;
    min-height: 100vh;
}

.header-section {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    border: 1px solid #bd2130;
    box-shadow: 0 8px 32px rgba(220, 53, 69, 0.3);
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

.header-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
    position: relative;
    z-index: 2;
    color: white;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.header-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    position: relative;
    z-index: 2;
    color: rgba(255, 255, 255, 0.9);
}

.back-btn {
    background: #6c757d;
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 1px solid #5a6268;
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
    white-space: nowrap;
}

.back-btn:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
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
    background: linear-gradient(90deg, #dc3545, #c82333);
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
    color: #dc3545;
}

.stat-card:nth-child(2) .stat-number {
    color: #28a745;
}

.stat-card:nth-child(3) .stat-number {
    color: #6c757d;
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
    border-color: #dc3545;
    background: white;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
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
    border-top: 3px solid #dc3545;
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

.results-info {
    background: #f8d7da;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #dc3545;
    font-weight: 500;
    color: #721c24;
}

.table-container {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.schools-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.schools-table th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
    z-index: 10;
}

.schools-table td {
    padding: 12px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
}

.schools-table tbody tr:hover {
    background: #f8f9fa;
}

.school-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #dc3545, #c82333);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.school-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.school-details h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}

.school-details p {
    margin: 0;
    font-size: 12px;
    color: #6c757d;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-suspended {
    background: #f8d7da;
    color: #721c24;
}

.user-count {
    background: #e3f2fd;
    color: #1565c0;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.date-display {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

.date-not-set {
    color: #dc3545;
    font-style: italic;
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
    color: #dc3545;
    border: 1px solid #e9ecef;
}

.pagination a:hover {
    background: #dc3545;
    color: white;
    transform: translateY(-2px);
}

.pagination .current {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    border: 1px solid #bd2130;
}

/* Responsive Design */
@media (max-width: 768px) {
    .suspended-schools-container {
        padding: 10px;
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
    
    .schools-table {
        font-size: 12px;
    }
    
    .schools-table th,
    .schools-table td {
        padding: 8px 6px;
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .schools-table th:nth-child(4),
    .schools-table td:nth-child(4),
    .schools-table th:nth-child(5),
    .schools-table td:nth-child(5) {
        display: none;
    }
}
</style>

<div class="suspended-schools-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="header-text">
                <h1 class="header-title">Suspended Schools</h1>
                <p class="header-subtitle">View and manage all suspended schools in the system</p>
            </div>
            <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/schools.php" class="back-btn" title="Go back to Schools Management">
                ← Back to Schools
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">⏸️</div>
            <div class="stat-number" id="suspendedCount"><?php echo number_format($suspended_schools); ?></div>
            <div class="stat-title">Suspended Schools</div>
            <div class="stat-description">Currently suspended schools</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-number" id="activeCount"><?php echo number_format($active_schools); ?></div>
            <div class="stat-title">Active Schools</div>
            <div class="stat-description">Active and operational schools</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-number" id="totalCount"><?php echo number_format($total_schools); ?></div>
            <div class="stat-title">Total Schools</div>
            <div class="stat-description">All schools in the system</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-form">
            <div class="search-input-group">
                <input type="text" name="search" id="searchInput" class="search-input" 
                       placeholder="Search suspended schools by name, shortname, or city..." 
                       value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loadingIndicator" class="loading-indicator" style="display: none;">
        <div class="loading-spinner"></div>
        <span>Loading suspended schools...</span>
    </div>

    <!-- Schools Table -->
    <div class="table-container">
        <table class="schools-table">
            <thead>
                <tr>
                    <th>School</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Users</th>
                    <th>Departments</th>
                    <th>Created Date</th>
                </tr>
            </thead>
            <tbody id="schoolsTableBody">
                <!-- Content will be loaded via AJAX -->
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="paginationContainer">
        <!-- Pagination will be loaded via AJAX -->
    </div>
</div>

<script>
// AJAX Search functionality for suspended schools
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('schoolsTableBody');
    const paginationContainer = document.getElementById('paginationContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    
    if (!searchInput) return;
    
    let filterTimeout;
    let currentPage = 0;
    
    // AJAX function to fetch suspended school data
    function fetchSuspendedSchools(page = 0) {
        showLoading();
        
        const params = new URLSearchParams();
        params.set('action', 'get_suspended_schools');
        params.set('page', page);
        
        if (searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }
        
        fetch(`<?php echo $CFG->wwwroot; ?>/theme/remui_kids/suspended_schools.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.error) {
                    console.error('Error:', data.error);
                    showError('Error loading suspended schools: ' + data.error);
                    return;
                }
                
                currentPage = data.current_page;
                updateTable(data.schools);
                updateSummaryCards(data);
                updatePagination(data);
                updateURL(data.filters);
            })
            .catch(error => {
                hideLoading();
                console.error('Fetch error:', error);
                showError('Network error: Unable to load suspended schools data');
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
    
    // Show error message
    function showError(message) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #dc3545;">
                    <div style="background: #f8d7da; padding: 20px; border-radius: 8px; border: 1px solid #f5c6cb;">
                        <strong>Error:</strong> ${message}
                    </div>
                </td>
            </tr>
        `;
    }
    
    // Format date properly
    function formatDate(timestamp) {
        if (!timestamp || timestamp == 0) {
            return '<span class="date-not-set">Not set</span>';
        }
        
        // Handle both Unix timestamp and Moodle timestamp formats
        let date;
        if (timestamp < 10000000000) {
            // Unix timestamp (seconds since epoch)
            date = new Date(timestamp * 1000);
        } else {
            // Already in milliseconds
            date = new Date(timestamp);
        }
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            return '<span class="date-not-set">Invalid date</span>';
        }
        
        // Check if it's the epoch date (Jan 1, 1970)
        if (date.getFullYear() === 1970 && date.getMonth() === 0 && date.getDate() === 1) {
            return '<span class="date-not-set">Not set</span>';
        }
        
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }
    
    // Update table with new data
    function updateTable(schools) {
        if (schools.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #6c757d;">
                        No suspended schools found matching your search criteria.
                    </td>
                </tr>
            `;
            return;
        }
        
        tableBody.innerHTML = schools.map(school => `
            <tr>
                <td>
                    <div class="school-info">
                        <div class="school-avatar">
                            ${school.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="school-details">
                            <h4>${school.name}</h4>
                            <p>${school.shortname}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <strong>${school.city || 'N/A'}</strong><br>
                        <small>${school.country || 'N/A'}</small>
                    </div>
                </td>
                <td>
                    <span class="status-badge status-suspended">
                        ${school.status_text}
                    </span>
                </td>
                <td>
                    <span class="user-count">${school.user_count} users</span>
                </td>
                <td>
                    <span class="user-count">${school.department_count} depts</span>
                </td>
                <td class="date-display">${formatDate(school.timecreated)}</td>
            </tr>
        `).join('');
    }
    
    // Update summary cards
    function updateSummaryCards(data) {
        if (data.statistics) {
            const totalCount = document.getElementById('totalCount');
            const activeCount = document.getElementById('activeCount');
            const suspendedCount = document.getElementById('suspendedCount');
            
            if (totalCount) {
                totalCount.textContent = data.statistics.total.toLocaleString();
            }
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
        if (currentPage > 0) params.set('page', currentPage);
        
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.pushState({}, '', newUrl);
    }
    
    // Global function for pagination
    window.changePage = function(page) {
        currentPage = page;
        fetchSuspendedSchools(page);
    };
    
    // Auto-search functionality
    function performAutoSearch() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            currentPage = 0; // Reset to first page on new search
            fetchSuspendedSchools(0);
        }, 500); // 500ms delay for auto-search
    }
    
    searchInput.addEventListener('input', function() {
        performAutoSearch();
    });
    
    // Load initial data
    fetchSuspendedSchools(0);
});
</script>

<?php
echo $OUTPUT->footer();
?>
