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

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Set up the page exactly like schools.php
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/teachers.php');
$PAGE->add_body_classes(['fullwidth-layout', 'page-myteachers']);
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');
$PAGE->set_pagelayout('mycourses');

$PAGE->set_pagetype('teachers-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Teachers Management - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Include full width CSS - MUST be before header output
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

$PAGE->requires->js('/theme/remui_kids/js/teachers.js', true);

// Ensure jQuery is available for any dependencies
$PAGE->requires->jquery();

echo $OUTPUT->header();
?>

<style>
.teachers-container {
    max-width: calc(100% - 40px) !important;
    width: 100% !important;
    margin: 0 auto !important;
    padding: 0 !important;
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.teachers-card {
    background: white;
    padding: 32px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.teachers-header {
    margin-bottom: 32px;
    text-align: center;
}

.teachers-title {
    color: #2c3e50;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.teachers-subtitle {
    color: #6c757d;
    font-size: 1.1rem;
    font-weight: 400;
    margin: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 15px;
    border: 1px solid #e9ecef;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.stat-card.total::before { background: linear-gradient(135deg, #52C9D9 0%, #4a9fd1 100%); }
.stat-card.active::before { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
.stat-card.suspended::before { background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%); }

.stat-title {
    margin: 0 0 12px 0;
    color: #495057;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-number.total { 
    background: linear-gradient(135deg, #52C9D9 0%, #4a9fd1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-number.active { 
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-number.suspended { 
    background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.search-section {
    background: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.search-controls {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 300px;
    padding: 14px 20px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f8f9fa;
    font-family: inherit;
}

.search-input:focus {
    outline: none;
    border-color: #52C9D9;
    background: white;
    box-shadow: 0 0 0 4px rgba(82, 201, 217, 0.1);
}

.status-filter {
    padding: 14px 20px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 15px;
    background: #f8f9fa;
    font-family: inherit;
    transition: all 0.3s ease;
}

.status-filter:focus {
    outline: none;
    border-color: #52C9D9;
    background: white;
    box-shadow: 0 0 0 4px rgba(82, 201, 217, 0.1);
}

.teachers-table-container {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    min-height: 200px;
}

.table-header {
    background: #f8f9fa;
    color: #495057;
    padding: 20px 24px;
    font-weight: 600;
    font-size: 1.2rem;
    letter-spacing: 0.5px;
    border: 1px solid #e9ecef;
}

.teachers-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.teachers-table th {
    padding: 16px 20px;
    text-align: left;
    background: #f8f9fa;
    color: #495057;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e9ecef;
}

.teachers-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
}

.teacher-row:hover {
    background: #f8f9fa;
    transition: background 0.2s ease;
}

.teacher-info {
    display: flex;
    align-items: center;
}

.teacher-details h4 {
    margin: 0 0 4px 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 15px;
}

.teacher-details p {
    margin: 0;
    color: #6c757d;
    font-size: 13px;
    font-weight: 500;
}

.contact-info {
    font-size: 14px;
}

.contact-email {
    color: #2c3e50;
    font-weight: 500;
    margin-bottom: 4px;
}

.contact-phone {
    color: #6c757d;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.institution-info {
    font-size: 14px;
}

.institution-name {
    color: #2c3e50;
    font-weight: 500;
    margin-bottom: 4px;
}

.institution-dept {
    color: #6c757d;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.last-access {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
}

.status-badge {
    padding: 6px 12px;
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

.action-icons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
}

.action-icon {
    width: 32px;
    height: 32px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 20px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #333;
    padding: 6px;
}

.view-icon:hover,
.edit-icon:hover,
.suspend-icon:hover {
    color: #52C9D9;
    transform: scale(1.1);
}

.back-button {
    background: #6c757d;
    color: white;
    padding: 14px 28px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    border: 1px solid #5a6268;
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
    display: inline-block;
    margin-top: 24px;
}

.back-button:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
    color: white;
    text-decoration: none;
}

@media (max-width: 768px) {
    .teachers-container {
        padding: 0 15px;
    }
    
    .teachers-card {
        padding: 20px;
    }
    
    .teachers-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .search-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input {
        min-width: auto;
    }
    
    .teachers-table {
        font-size: 12px;
    }
    
    .teachers-table th,
    .teachers-table td {
        padding: 12px 8px;
    }
    
    .action-icons {
        gap: 4px;
    }
    
    .action-icon {
        width: 28px;
        height: 28px;
        font-size: 18px;
    }
}

/* Teacher Profile Modal Styles */
.teacher-profile-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.teacher-profile-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.teacher-profile-content {
    position: relative;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    max-width: 350px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.teacher-profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 15px 15px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.teacher-profile-header h2 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
}

.teacher-profile-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.teacher-profile-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.teacher-profile-body {
    padding: 20px;
}

.teacher-profile-image {
    text-align: center;
    margin-bottom: 20px;
}

.teacher-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
}

.teacher-avatar i {
    font-size: 2rem;
    color: white;
}

.teacher-profile-info {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.profile-section h3 {
    color: #2c3e50;
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 10px 0;
    padding-bottom: 6px;
    border-bottom: 2px solid #e9ecef;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.info-item {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
}

.info-item label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    min-width: 70px;
    flex-shrink: 0;
}

.info-item span {
    color: #2c3e50;
    font-size: 0.85rem;
    font-weight: 500;
    flex: 1;
}

.teacher-profile-footer {
    padding: 12px 20px;
    background: #f8f9fa;
    border-radius: 0 0 15px 15px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
}

/* Responsive Design for Modal */
@media (max-width: 768px) {
    .teacher-profile-modal {
        padding: 10px;
    }
    
    .teacher-profile-content {
        max-width: 100%;
        border-radius: 12px;
    }
    
    .teacher-profile-header {
        padding: 12px 15px;
        border-radius: 12px 12px 0 0;
    }
    
    .teacher-profile-header h2 {
        font-size: 1.1rem;
    }
    
    .teacher-profile-body {
        padding: 15px;
    }
    
    .teacher-avatar {
        width: 70px;
        height: 70px;
    }
    
    .teacher-avatar i {
        font-size: 1.8rem;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
    }
    
    .info-item label {
        min-width: auto;
        font-size: 0.7rem;
    }
    
    .info-item span {
        font-size: 0.8rem;
    }
    
    .teacher-profile-footer {
        padding: 10px 15px;
        flex-direction: column;
        border-radius: 0 0 12px 12px;
    }
    
    .btn {
        width: 100%;
        padding: 10px;
        font-size: 0.75rem;
    }
}
</style>

<div class="teachers-container">
    <div class="teachers-card">
        <div class="teachers-header">
            <h1 class="teachers-title">Teachers Management</h1>
            <p class="teachers-subtitle">Manage teachers and their account status</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card total">
                <h3 class="stat-title">Total Teachers</h3>
                <div class="stat-number total">
                    <?php
                    try {
                        // Use role ID 3 directly as shown in the database query
                        $teacher_role_id = 3;
                        
                        $total_teachers = $DB->count_records_sql("
                            SELECT COUNT(DISTINCT u.id) 
                            FROM {user} u
                            JOIN {role_assignments} ra ON u.id = ra.userid
                            WHERE ra.roleid = ?
                        ", array($teacher_role_id));
                        echo number_format($total_teachers);
                    } catch (Exception $e) {
                        echo "0";
                    }
                    ?>
                </div>
            </div>
            
            <div class="stat-card active">
                <h3 class="stat-title">Active Teachers</h3>
                <div class="stat-number active">
                    <?php
                    try {
                        // Use role ID 3 directly as shown in the database query
                        $teacher_role_id = 3;
                        
                        $active_teachers = $DB->count_records_sql("
                            SELECT COUNT(DISTINCT u.id) 
                            FROM {user} u
                            JOIN {role_assignments} ra ON u.id = ra.userid
                            WHERE ra.roleid = ? AND u.suspended = 0 AND u.deleted = 0
                        ", array($teacher_role_id));
                        echo number_format($active_teachers);
                    } catch (Exception $e) {
                        echo "0";
                    }
                    ?>
                </div>
            </div>
            
            <div class="stat-card suspended">
                <h3 class="stat-title">Suspended Teachers</h3>
                <div class="stat-number suspended">
                    <?php
                    try {
                        // Use role ID 3 directly as shown in the database query
                        $teacher_role_id = 3;
                        
                        $suspended_teachers = $DB->count_records_sql("
                            SELECT COUNT(DISTINCT u.id) 
                            FROM {user} u
                            JOIN {role_assignments} ra ON u.id = ra.userid
                            WHERE ra.roleid = ? AND (u.suspended = 1 OR u.deleted = 1)
                        ", array($teacher_role_id));
                        echo number_format($suspended_teachers);
                    } catch (Exception $e) {
                        echo "0";
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="search-section">
            <div class="search-controls">
                <input type="text" id="searchInput" class="search-input" placeholder="Search teachers by name, email, or username...">
                <select id="statusFilter" class="status-filter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>

        <div class="teachers-table-container">
            <div class="table-header">
                Teachers List
            </div>
            <div style="padding: 0;">
                <table id="teachersTable" class="teachers-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Email</th>
                            <th>Last Access</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Use role ID 3 directly as shown in the database query
                            $teacher_role_id = 3;
                            
                            $teachers = $DB->get_records_sql("
                                SELECT u.id, u.username, u.email, u.firstname, u.lastname, u.suspended, u.deleted, 
                                       u.timecreated, u.lastaccess, u.lastlogin, u.institution, u.department, 
                                       u.phone1, u.address, u.city, u.country
                                FROM {user} u
                                JOIN {role_assignments} ra ON u.id = ra.userid
                                WHERE ra.roleid = ?
                                ORDER BY u.firstname ASC, u.lastname ASC
                                LIMIT 20
                            ", array($teacher_role_id));
                            
                            if (empty($teachers)) {
                                echo '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #6c757d;">No teachers found with role ID 3. Total users in database: ' . $DB->count_records('user') . '<br>Please check if role assignments exist.</td></tr>';
                            } else {
                                foreach ($teachers as $teacher) {
                                    $status = ($teacher->suspended == 0 && $teacher->deleted == 0) ? 'Active' : 'Suspended';
                                    $status_color = ($teacher->suspended == 0 && $teacher->deleted == 0) ? '#28a745' : '#dc3545';
                                    $last_access = $teacher->lastaccess ? date('M d, Y', $teacher->lastaccess) : 'Never';
                                    $phone = $teacher->phone1 ? $teacher->phone1 : 'N/A';
                                    $institution = $teacher->institution ? $teacher->institution : 'N/A';
                                    $department = $teacher->department ? $teacher->department : 'N/A';
                                    
                                    echo '<tr class="teacher-row" data-status="' . strtolower($status) . '" data-teacher-id="' . $teacher->id . '" onclick="showTeacherProfile(' . $teacher->id . ', \'' . htmlspecialchars($teacher->firstname . ' ' . $teacher->lastname) . '\', \'' . htmlspecialchars($teacher->email) . '\', \'' . htmlspecialchars($teacher->username) . '\', \'' . $phone . '\', \'' . htmlspecialchars($institution) . '\', \'' . htmlspecialchars($department) . '\', \'' . $last_access . '\', \'' . $status . '\')" style="cursor: pointer;">';
                                    
                                    // Teacher Info
                                    echo '<td>';
                                    echo '<div class="teacher-details">';
                                    echo '<h4>' . htmlspecialchars($teacher->firstname . ' ' . $teacher->lastname) . '</h4>';
                                    echo '<p>@' . htmlspecialchars($teacher->username) . '</p>';
                                    echo '</div>';
                                    echo '</td>';
                                    
                                    // Email Info
                                    echo '<td>';
                                    echo '<div class="contact-email">' . htmlspecialchars($teacher->email) . '</div>';
                                    echo '</td>';
                                    
                                    // Last Access
                                    echo '<td>';
                                    echo '<div class="last-access">' . $last_access . '</div>';
                                    echo '</td>';
                                    
                                    // Status
                                    echo '<td>';
                                    echo '<span class="status-badge ' . ($status === 'Active' ? 'status-active' : 'status-suspended') . '">' . $status . '</span>';
                                    echo '</td>';
                                    
                                    // Actions
                                    echo '<td>';
                                    echo '<div class="action-icons" onclick="event.stopPropagation();">';
                                    echo '<a href="' . $CFG->wwwroot . '/theme/remui_kids/teacher_view.php?id=' . $teacher->id . '" class="action-icon view-icon" title="View Details">👁</a>';
                                    echo '<a href="teacher_edit.php?id=' . $teacher->id . '" class="action-icon edit-icon" title="Edit Teacher">✏</a>';
                                    echo '<a href="teacher_suspend.php?id=' . $teacher->id . '" class="action-icon suspend-icon" title="Manage Status">⏸</a>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            }
                        } catch (Exception $e) {
                            echo '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc3545;">Error loading teachers: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="../../my/" class="back-button">← Back to Dashboard</a>
        </div>
    </div>
</div>

<!-- Teacher Profile Modal -->
<div id="teacherProfileModal" class="teacher-profile-modal" style="display: none;">
    <div class="teacher-profile-overlay" onclick="closeTeacherProfile()"></div>
    <div class="teacher-profile-content">
        <div class="teacher-profile-header">
            <h2 id="teacherProfileName">Teacher Profile</h2>
            <button class="teacher-profile-close" onclick="closeTeacherProfile()">&times;</button>
        </div>
        <div class="teacher-profile-body">
            <div class="teacher-profile-image">
                <div class="teacher-avatar">
                    <i class="fa fa-user"></i>
                </div>
            </div>
            <div class="teacher-profile-info">
                <div class="profile-section">
                    <h3>Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name:</label>
                            <span id="profileFullName">-</span>
                        </div>
                        <div class="info-item">
                            <label>Username:</label>
                            <span id="profileUsername">-</span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span id="profileEmail">-</span>
                        </div>
                        <div class="info-item">
                            <label>Phone:</label>
                            <span id="profilePhone">-</span>
                        </div>
                    </div>
                </div>
                <div class="profile-section">
                    <h3>Institution Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Institution:</label>
                            <span id="profileInstitution">-</span>
                        </div>
                        <div class="info-item">
                            <label>Department:</label>
                            <span id="profileDepartment">-</span>
                        </div>
                        <div class="info-item">
                            <label>Last Access:</label>
                            <span id="profileLastAccess">-</span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span id="profileStatus" class="status-badge">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="teacher-profile-footer">
            <button class="btn btn-primary" onclick="editTeacher()">Edit Teacher</button>
            <button class="btn btn-secondary" onclick="closeTeacherProfile()">Close</button>
        </div>
    </div>
</div>

<script>
// Ensure DOM is loaded before running scripts
document.addEventListener('DOMContentLoaded', function() {
    // Function to filter teachers
    function filterTeachers() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const table = document.getElementById('teachersTable');
        
        if (!searchInput || !statusFilter || !table) {
            return; // Exit if elements not found
        }
        
        const rows = table.getElementsByClassName('teacher-row');
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();
        
        let visibleCount = 0;
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const teacherName = row.cells[0].textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();
            const status = row.getAttribute('data-status');
            
            const matchesSearch = teacherName.includes(searchTerm) || email.includes(searchTerm);
            const matchesStatus = !statusValue || status === statusValue;
            
            if (matchesSearch && matchesStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        
        // Update search input border color
        if (searchTerm.length > 0) {
            searchInput.style.borderColor = '#52C9D9';
        } else {
            searchInput.style.borderColor = '#e9ecef';
        }
    }

    // Add event listeners with error handling
    try {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        
        if (searchInput) {
            searchInput.addEventListener('keyup', filterTeachers);
            searchInput.addEventListener('focus', function() {
                this.style.borderColor = '#52C9D9';
                this.style.boxShadow = '0 0 0 3px rgba(82, 201, 217, 0.1)';
            });
            searchInput.addEventListener('blur', function() {
                this.style.borderColor = '#e9ecef';
                this.style.boxShadow = 'none';
            });
        }
        
        if (statusFilter) {
            statusFilter.addEventListener('change', filterTeachers);
            statusFilter.addEventListener('focus', function() {
                this.style.borderColor = '#52C9D9';
                this.style.boxShadow = '0 0 0 3px rgba(82, 201, 217, 0.1)';
            });
            statusFilter.addEventListener('blur', function() {
                this.style.borderColor = '#e9ecef';
                this.style.boxShadow = 'none';
            });
        }
    } catch (error) {
        console.log('Error setting up event listeners:', error);
    }
    
    // Add hover effects for action buttons
    try {
        const actionButtons = document.querySelectorAll('.action-btn');
        actionButtons.forEach(function(button) {
            button.addEventListener('mouseenter', function() {
                if (this.classList.contains('view-btn')) {
                    this.style.background = '#0056b3';
                } else if (this.classList.contains('edit-btn')) {
                    this.style.background = '#545b62';
                } else if (this.classList.contains('suspend-btn')) {
                    this.style.background = '#c82333';
                }
            });
            
            button.addEventListener('mouseleave', function() {
                if (this.classList.contains('view-btn')) {
                    this.style.background = '#007bff';
                } else if (this.classList.contains('edit-btn')) {
                    this.style.background = '#6c757d';
                } else if (this.classList.contains('suspend-btn')) {
                    this.style.background = '#dc3545';
                }
            });
        });
    } catch (error) {
        console.log('Error setting up button hover effects:', error);
    }
});

// Teacher Profile Modal Functions
let currentTeacherId = null;

function showTeacherProfile(id, fullName, email, username, phone, institution, department, lastAccess, status) {
    currentTeacherId = id;
    
    // Update modal content
    document.getElementById('teacherProfileName').textContent = fullName + ' - Profile';
    document.getElementById('profileFullName').textContent = fullName;
    document.getElementById('profileUsername').textContent = '@' + username;
    document.getElementById('profileEmail').textContent = email;
    document.getElementById('profilePhone').textContent = phone;
    document.getElementById('profileInstitution').textContent = institution;
    document.getElementById('profileDepartment').textContent = department;
    document.getElementById('profileLastAccess').textContent = lastAccess;
    
    // Update status badge
    const statusElement = document.getElementById('profileStatus');
    statusElement.textContent = status;
    statusElement.className = 'status-badge ' + (status === 'Active' ? 'status-active' : 'status-suspended');
    
    // Show modal
    document.getElementById('teacherProfileModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeTeacherProfile() {
    document.getElementById('teacherProfileModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentTeacherId = null;
}

function editTeacher() {
    if (currentTeacherId) {
        window.location.href = 'teacher_edit.php?id=' + currentTeacherId;
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTeacherProfile();
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>
