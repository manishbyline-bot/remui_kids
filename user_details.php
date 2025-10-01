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
 * User Details Page
 *
 * @package    local_user_management
 * @copyright  2024 Riyada Trainings
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Require login only - allow all logged-in users to access
require_login();

// Set up the page
$PAGE->set_url('/theme/remui_kids/user_details.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('User Details');
$PAGE->set_heading('User Details');
$PAGE->set_pagelayout('standard');

// Handle AJAX request for search suggestions
if (isset($_GET['action']) && $_GET['action'] === 'search_suggestions') {
    header('Content-Type: application/json');
    
    $query = optional_param('q', '', PARAM_TEXT);
    error_log("Search suggestions request for: " . $query);
    
    if (strlen($query) >= 2) {
        try {
            $search_param = '%' . $query . '%';
            $sql = "SELECT id, username, email, firstname, lastname, timecreated, lastaccess, suspended 
                    FROM {user} 
                    WHERE deleted = 0 
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
            error_log("Found " . count($suggestions) . " suggestions");
            
            $results = array();
            foreach ($suggestions as $user) {
                $fullname = trim($user->firstname . ' ' . $user->lastname);
                error_log("User {$user->username}: suspended = {$user->suspended} (type: " . gettype($user->suspended) . ")");
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
            error_log("Error in search suggestions: " . $e->getMessage());
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
$perpage = 20; // Users per page
$sort = optional_param('sort', 'id', PARAM_TEXT);
$order = optional_param('order', 'asc', PARAM_TEXT);
$status_filter = optional_param('status', '', PARAM_TEXT);

// Build the SQL query for user search
$where_conditions = array('deleted = 0');
$params = array();

if (!empty($search)) {
    $where_conditions[] = "(id LIKE ? OR username LIKE ? OR email LIKE ? OR firstname LIKE ? OR lastname LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "suspended = 0";
    } elseif ($status_filter === 'suspended') {
        $where_conditions[] = "suspended = 1";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Validate sort column
$allowed_sorts = array('id', 'username', 'email', 'firstname', 'lastname', 'timecreated', 'lastaccess');
$sort = in_array($sort, $allowed_sorts) ? $sort : 'id';
$order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

// Get total count for pagination
$total_users = $DB->count_records_select('user', $where_clause, $params);

// Get users for current page
$offset = $page * $perpage;
$order_by = $sort . ' ' . $order;
$users = $DB->get_records_select('user', $where_clause, $params, $order_by, 'id, username, email, firstname, lastname, timecreated, lastaccess, suspended', $offset, $perpage);

// Calculate pagination
$total_pages = ceil($total_users / $perpage);

echo $OUTPUT->header();

// Add user details JavaScript
$PAGE->requires->js('/theme/remui_kids/js/user_details.js');
?>

<style>
.user-details-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
    min-height: 100vh;
}

.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 30px;
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

.header-title {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 5px;
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
    border-radius: 15px;
    padding: 18px;
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

.search-filters {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.search-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-select {
    padding: 6px 10px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 0.85rem;
    background: white;
    color: #495057;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 110px;
}

.filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-select:hover {
    border-color: #667eea;
}

.search-input-container {
    flex: 1;
    min-width: 280px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 10px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
    box-sizing: border-box;
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
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.search-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.clear-btn {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
}

.clear-btn:hover {
    background: #5a6268;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
    text-decoration: none;
    color: white;
}

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

/* Main page suggestions styling */
.suggestions-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
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

.quick-search-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.quick-search-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.quick-search-btn {
    background: white;
    border: 2px solid #e9ecef;
    color: #495057;
    padding: 6px 12px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.quick-search-btn:hover {
    background: #667eea;
    border-color: #667eea;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.results-info {
    background: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    color: #1565c0;
    font-weight: 500;
}

.users-table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    margin-top: 20px;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    background: white;
}

.users-table th {
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

.sortable {
    cursor: pointer;
    transition: background-color 0.15s ease;
    position: relative;
}

.sortable:hover {
    background: #f3f4f6;
}

.sort-indicator {
    margin-left: 6px;
    font-weight: bold;
    color: #6b7280;
    font-size: 12px;
}

.users-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
    transition: background-color 0.15s ease;
}

.users-table tbody tr {
    transition: background-color 0.15s ease;
}

.users-table tbody tr:hover {
    background: #f9fafb;
}

.users-table tbody tr:nth-child(even) {
    background: #fafbfc;
}

.users-table tbody tr:nth-child(even):hover {
    background: #f3f4f6;
}

/* Clean scrollbar for table container */
.users-table-container::-webkit-scrollbar {
    height: 6px;
}

.users-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.users-table-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.users-table-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.user-id {
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

.username {
    font-weight: 500;
    color: #111827;
    font-size: 14px;
}

.email {
    color: #6b7280;
    word-break: break-all;
    font-size: 13px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.user-name {
    font-weight: 500;
    color: #111827;
    font-size: 14px;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    transition: opacity 0.15s ease;
}

.status-badge:hover {
    opacity: 0.8;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
    border: none;
}

.status-suspended {
    background: #fee2e2;
    color: #991b1b;
    border: none;
}

.date-info {
    color: #6b7280;
    font-size: 12px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.pagination-container {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 4px;
    margin-top: 0;
    padding: 16px 20px;
    background: white;
    border-top: 1px solid #f3f4f6;
}

.pagination-btn {
    background: white;
    border: 1px solid #e5e7eb;
    color: #374151;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 32px;
    text-align: center;
}

.pagination-btn:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #111827;
    text-decoration: none;
}

.pagination-btn.active {
    background: #6366f1;
    border-color: #6366f1;
    color: white;
}

.pagination-btn:disabled {
    background: #f8f9fa;
    border-color: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.pagination-info {
    color: #6c757d;
    font-size: 0.9rem;
    margin: 0 15px;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.no-results-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-results h3 {
    margin-bottom: 10px;
    color: #495057;
}

.no-results p {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .user-details-container {
        padding: 15px;
    }
    
    .header-section {
        padding: 15px 20px;
        text-align: center;
    }
    
    .header-title {
        font-size: 1.8rem;
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
    
    .search-input {
        min-width: auto;
        width: 100%;
    }
    
    .search-btn, .clear-btn {
        width: 100%;
        justify-content: center;
    }
    
    .search-filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-select {
        width: 100%;
        min-width: auto;
    }
    
    .suggestions-table-container {
        overflow-x: auto;
    }
    
    .suggestions-table {
        min-width: 600px;
    }
    
    .suggestions-table th {
        padding: 12px 16px;
        font-size: 12px;
    }
    
    .suggestions-table td {
        padding: 12px 16px;
        font-size: 13px;
    }
    
    .suggestion-user-id {
        font-size: 12px;
        padding: 3px 6px;
        min-width: 30px;
    }
    
    .suggestion-date-info {
        font-size: 11px;
    }
    
    .users-table-container {
        overflow-x: auto;
    }
    
    .users-table {
        min-width: 600px;
    }
    
    .pagination-container {
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .pagination-btn {
        padding: 8px 12px;
        font-size: 0.8rem;
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
        padding: 12px;
    }
    
    .search-main-row {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .search-input-container {
        min-width: auto;
    }
    
    .search-filters {
        justify-content: center;
    }
    
    .search-buttons {
        justify-content: center;
    }
    
    .quick-search-row {
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    
    .quick-search-buttons {
        justify-content: center;
    }
    
    .users-table th {
        padding: 12px 16px;
        font-size: 12px;
    }
    
    .users-table td {
        padding: 12px 16px;
        font-size: 13px;
    }
    
    .user-id {
        font-size: 12px;
        padding: 3px 6px;
        min-width: 30px;
    }
    
    .status-badge {
        padding: 3px 10px;
        font-size: 10px;
    }
    
    .date-info {
        font-size: 11px;
    }
    
    .pagination-container {
        justify-content: center;
        padding: 12px 16px;
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
    
    .users-table {
        font-size: 14px;
    }
    
    .users-table th,
    .users-table td {
        padding: 8px 6px;
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
        <h1 class="header-title">User Details</h1>
        <p class="header-subtitle">Comprehensive view of all registered users in the system</p>
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
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended Only</option>
                    </select>
                    
                    <select name="sort" class="filter-select">
                        <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>Sort by ID</option>
                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Sort by Username</option>
                        <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Sort by Email</option>
                        <option value="firstname" <?php echo $sort === 'firstname' ? 'selected' : ''; ?>>Sort by First Name</option>
                        <option value="lastname" <?php echo $sort === 'lastname' ? 'selected' : ''; ?>>Sort by Last Name</option>
                        <option value="timecreated" <?php echo $sort === 'timecreated' ? 'selected' : ''; ?>>Sort by Created Date</option>
                        <option value="lastaccess" <?php echo $sort === 'lastaccess' ? 'selected' : ''; ?>>Sort by Last Access</option>
                    </select>
                    
                    <select name="order" class="filter-select">
                        <option value="asc" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
                
                <div class="search-buttons">
                    <button type="submit" class="search-btn">
                        🔍 Search
                    </button>
                    <?php if (!empty($search) || !empty($status_filter) || $sort !== 'id' || $order !== 'ASC'): ?>
                        <a href="user_details.php" class="clear-btn">
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
            Found <?php echo $total_users; ?> user(s)
        </div>
    <?php else: ?>
        <div class="results-info">
            ● Showing all users - Total: <?php echo $total_users; ?> user(s)
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
        <?php if (!empty($users)): ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="id">
                            User ID
                            <?php if ($sort === 'id'): ?>
                                <span class="sort-indicator"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                            <?php endif; ?>
                        </th>
                        <th class="sortable" data-sort="username">
                            Username
                            <?php if ($sort === 'username'): ?>
                                <span class="sort-indicator"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                            <?php endif; ?>
                        </th>
                        <th class="sortable" data-sort="email">
                            Email
                            <?php if ($sort === 'email'): ?>
                                <span class="sort-indicator"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                            <?php endif; ?>
                        </th>
                        <th class="sortable" data-sort="firstname">
                            Full Name
                            <?php if ($sort === 'firstname'): ?>
                                <span class="sort-indicator"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                            <?php endif; ?>
                        </th>
                        <th>Status</th>
                        <th class="sortable" data-sort="timecreated">
                            Created
                            <?php if ($sort === 'timecreated'): ?>
                                <span class="sort-indicator"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                            <?php endif; ?>
                        </th>
                        <th class="sortable" data-sort="lastaccess">
                            Last Access
                            <?php if ($sort === 'lastaccess'): ?>
                                <span class="sort-indicator"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                            <?php endif; ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="user-id"><?php echo $user->id; ?></td>
                            <td class="username"><?php echo htmlspecialchars($user->username); ?></td>
                            <td class="email"><?php echo htmlspecialchars($user->email); ?></td>
                            <td class="user-name">
                                <?php 
                                $fullname = trim($user->firstname . ' ' . $user->lastname);
                                echo htmlspecialchars($fullname ?: 'N/A'); 
                                ?>
                            </td>
                            <td>
                                <?php if ($user->suspended): ?>
                                    <span class="status-badge status-suspended">Suspended</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="date-info">
                                <?php echo date('M j, Y', $user->timecreated); ?>
                            </td>
                            <td class="date-info">
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
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">●</div>
                <h3>No Users Found</h3>
                <p>
                    <?php if (!empty($search)): ?>
                        No users match your search criteria. Try adjusting your search terms.
                    <?php else: ?>
                        No users are currently registered in the system.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        </div> <!-- End usersTableSection -->
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <?php 
            // Build pagination URL with all current parameters
            $pagination_params = array();
            if (!empty($search)) $pagination_params['search'] = $search;
            if (!empty($status_filter)) $pagination_params['status'] = $status_filter;
            if ($sort !== 'id') $pagination_params['sort'] = $sort;
            if ($order !== 'ASC') $pagination_params['order'] = strtolower($order);
            ?>
            
            <?php if ($page > 0): ?>
                <?php 
                $prev_params = $pagination_params;
                $prev_params['page'] = $page - 1;
                $prev_url = '?' . http_build_query($prev_params);
                ?>
                <a href="<?php echo $prev_url; ?>" class="pagination-btn">
                    ← Previous
                </a>
            <?php else: ?>
                <span class="pagination-btn" disabled>← Previous</span>
            <?php endif; ?>

            <span class="pagination-info">
                Page <?php echo $page + 1; ?> of <?php echo $total_pages; ?>
            </span>

            <?php if ($page < $total_pages - 1): ?>
                <?php 
                $next_params = $pagination_params;
                $next_params['page'] = $page + 1;
                $next_url = '?' . http_build_query($next_params);
                ?>
                <a href="<?php echo $next_url; ?>" class="pagination-btn">
                    Next →
                </a>
            <?php else: ?>
                <span class="pagination-btn" disabled>Next →</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

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
        
        fetch(`user_details.php?action=search_suggestions&q=${encodeURIComponent(query)}`)
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Suggestions data:', data);
                if (data.success) {
                    // Debug: Log each suggestion's status
                    data.suggestions.forEach((suggestion, index) => {
                        console.log(`Suggestion ${index}:`, {
                            username: suggestion.username,
                            suspended: suggestion.suspended,
                            suspendedType: typeof suggestion.suspended
                        });
                    });
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
            suggestionsTableBody.innerHTML = '<tr><td colspan="7" class="no-suggestions">No matching users found</td></tr>';
        } else {
            suggestionsTableBody.innerHTML = suggestions.map((suggestion, index) => {
                const highlightedUsername = highlightText(suggestion.username, query);
                const highlightedEmail = highlightText(suggestion.email, query);
                const highlightedFullname = highlightText(suggestion.fullname, query);
                
                // Determine status based on suspended field
                // Note: suspended = 0 means Active, suspended = 1 means Suspended
                const status = (suggestion.suspended == 1 || suggestion.suspended === '1') ? 'Suspended' : 'Active';
                const statusClass = (suggestion.suspended == 1 || suggestion.suspended === '1') ? 'status-suspended' : 'status-active';
                
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
        // Don't automatically show - let the input handler control visibility
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
        // Don't automatically show - let the input handler control visibility
    }

    // Show error state
    function showErrorSuggestions() {
        suggestionsTableBody.innerHTML = '<tr><td colspan="7" class="no-suggestions">Error loading suggestions</td></tr>';
        // Don't automatically show - let the input handler control visibility
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
    
    // Add sortable table headers functionality
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortField = this.getAttribute('data-sort');
            const currentSort = new URLSearchParams(window.location.search).get('sort') || 'id';
            const currentOrder = new URLSearchParams(window.location.search).get('order') || 'asc';
            
            // Determine new order
            let newOrder = 'asc';
            if (sortField === currentSort && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            // Build new URL
            const url = new URL(window.location);
            url.searchParams.set('sort', sortField);
            url.searchParams.set('order', newOrder);
            
            // Navigate to new URL
            window.location.href = url.toString();
        });
    });

    // Add row click functionality (optional)
    const tableRows = document.querySelectorAll('.users-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            // You can add functionality here to view user details
            console.log('Row clicked:', this);
        });
    });

    // Add advanced search features
    function addAdvancedSearchFeatures() {
        // Add search history
        const searchHistory = JSON.parse(localStorage.getItem('userSearchHistory') || '[]');
        
        // Save search to history
        if (searchInput.value.trim()) {
            const searchTerm = searchInput.value.trim();
            if (!searchHistory.includes(searchTerm)) {
                searchHistory.unshift(searchTerm);
                searchHistory = searchHistory.slice(0, 10); // Keep only last 10 searches
                localStorage.setItem('userSearchHistory', JSON.stringify(searchHistory));
            }
        }
        
        // Add click handlers for quick search buttons (now in HTML)
        document.querySelectorAll('.quick-search-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const searchTerm = this.getAttribute('data-search');
                searchInput.value = searchTerm;
                searchInput.form.submit();
            });
        });
    }
    
    // Initialize advanced search features
    addAdvancedSearchFeatures();
    
    // Test: Show suggestions immediately if there's a search term
    if (searchInput.value.trim().length >= 2) {
        console.log('Page loaded with search term:', searchInput.value);
        showSuggestions();
        hideUsersTable();
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
            <div class="mobile-logo">User Details</div>
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
