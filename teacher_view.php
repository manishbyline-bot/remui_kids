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
 * Professional Teacher View Page
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

redirect_if_major_upgrade_required();
require_login();

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Get teacher ID from URL parameter
$teacherid = required_param('id', PARAM_INT);

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/teacher_view_professional.php', array('id' => $teacherid));
$PAGE->add_body_classes(['fullwidth-layout', 'page-myteacherview']);
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');
$PAGE->set_pagelayout('mycourses');
$PAGE->set_pagetype('teacherview-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Teacher Details - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Get teacher information from database
try {
    global $DB;
    
    // Get basic teacher information
    $teacher = $DB->get_record('user', array('id' => $teacherid), 
        'id, username, firstname, lastname, email, phone1, phone2, city, country, 
         lastaccess, timecreated, lastlogin, suspended, deleted, picture');
    
    if (!$teacher) {
        throw new moodle_exception('teachernotfound', 'theme_remui_kids');
    }
    
    // Initialize empty arrays for optional data
    $role_assignments = array();
    $courses = array();
    $profile_fields = array();
    
    // Try to get teacher's role assignments (simplified)
    try {
        $role_assignments = $DB->get_records_sql(
            "SELECT ra.id, r.shortname, r.name
             FROM {role_assignments} ra
             JOIN {role} r ON ra.roleid = r.id
             WHERE ra.userid = ? AND ra.component = ''",
            array($teacherid),
            0,
            10
        );
    } catch (Exception $e) {
        $role_assignments = array();
    }
    
    // Try to get teacher's courses (simplified)
    try {
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.timecreated
             FROM {course} c
             JOIN {context} ctx ON c.id = ctx.instanceid
             JOIN {role_assignments} ra ON ctx.id = ra.contextid
             WHERE ra.userid = ? AND ctx.contextlevel = 50",
            array($teacherid),
            0,
            10
        );
    } catch (Exception $e) {
        $courses = array();
    }
    
    // Try to get basic profile information
    try {
        $profile_fields = $DB->get_records_sql(
            "SELECT uif.shortname, uif.name, uid.data
             FROM {user_info_field} uif
             LEFT JOIN {user_info_data} uid ON uif.id = uid.fieldid AND uid.userid = ?
             LIMIT 10",
            array($teacherid)
        );
    } catch (Exception $e) {
        $profile_fields = array();
    }
    
    // Prepare data for template
    $template_data = array(
        'teacher' => $teacher,
        'role_assignments' => array_values($role_assignments),
        'courses' => array_values($courses),
        'profile_fields' => array_values($profile_fields),
        'wwwroot' => $CFG->wwwroot,
        'teacherid' => $teacherid,
        'status_class' => $teacher->suspended ? 'suspended' : 'active',
        'status_text' => $teacher->suspended ? 'Suspended' : 'Active',
        'last_access' => $teacher->lastaccess ? date('M d, Y H:i', $teacher->lastaccess) : 'Never',
        'created_date' => date('M d, Y', $teacher->timecreated),
        'last_login' => $teacher->lastlogin ? date('M d, Y H:i', $teacher->lastlogin) : 'Never'
    );
    
} catch (Exception $e) {
    $template_data = array(
        'error' => $e->getMessage(),
        'wwwroot' => $CFG->wwwroot
    );
}

// Add jQuery and CSS
$PAGE->requires->jquery();
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

echo $OUTPUT->header();
?>

<style>
/* Professional Teacher View Styles */
.teacher-view-container {
    max-width: 100%;
    width: 100%;
    margin: 0;
    padding: 20px;
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8fafc;
    overflow: hidden; /* Prevent scrolling */
}

.teacher-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
}

.teacher-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    animation: float 6s ease-in-out infinite;
}

.header-top {
    position: relative;
    z-index: 3;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    width: 100%;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    text-decoration: none;
    color: white;
}

.back-icon {
    font-size: 1.2rem;
    font-weight: bold;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.teacher-info {
    display: flex;
    align-items: center;
    gap: 25px;
    position: relative;
    z-index: 2;
}

.teacher-profile-image {
    flex-shrink: 0;
}

.profile-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.3);
    object-fit: cover;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

/* Custom Scrollbar Styling */
.content-section::-webkit-scrollbar {
    width: 6px;
}

.content-section::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.content-section::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.content-section::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Remove scrollbar from Personal Information section only */
.personal-info-section {
    overflow: visible !important;
}

.personal-info-section::-webkit-scrollbar {
    display: none;
}


.teacher-details h1 {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0 0 8px 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.teacher-details .subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.teacher-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 12px;
}

.status-active {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-suspended {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}

/* Main Content Grid */
.teacher-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    height: calc(100vh - 200px); /* Fit in viewport */
    overflow: hidden;
}

.content-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    max-height: 100%;
    overflow-y: auto; /* Add scrollbar when content overflows */
    overflow-x: hidden; /* Hide horizontal scrollbar */
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-icon {
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1rem;
    font-weight: 500;
    color: #1e293b;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

/* Lists */
.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.info-list li:last-child {
    border-bottom: none;
}

.list-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: white;
    flex-shrink: 0;
}

.icon-blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.icon-green { background: linear-gradient(135deg, #10b981, #059669); }
.icon-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.icon-orange { background: linear-gradient(135deg, #f59e0b, #d97706); }

.list-content {
    flex: 1;
}

.list-title {
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 4px 0;
    font-size: 0.95rem;
}

.list-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .teacher-content {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .content-section {
        max-height: 400px; /* Set a reasonable max height for mobile */
        overflow-y: auto; /* Keep scrollbars on mobile */
        overflow-x: hidden;
    }
}

@media (max-width: 768px) {
    .teacher-view-container {
        padding: 15px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 20px;
    }
    
    .teacher-info {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .header-actions {
        justify-content: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state-text {
    font-size: 1.1rem;
    margin: 0;
}
</style>

<div class="teacher-view-container">
    <?php if (isset($template_data['error'])): ?>
        <div class="teacher-header">
            <h1>Error</h1>
            <p><?php echo htmlspecialchars($template_data['error']); ?></p>
        </div>
    <?php else: ?>
        <?php $teacher = $template_data['teacher']; ?>
        
        <!-- Teacher Header -->
        <div class="teacher-header">
            <div class="header-top">
                <div class="header-content">
                    <div class="teacher-info">
                        <?php if ($teacher->picture): ?>
                            <div class="teacher-profile-image">
                                <?php 
                                $usercontext = context_user::instance($teacher->id);
                                $profileimageurl = moodle_url::make_pluginfile_url($usercontext->id, 'user', 'icon', null, '/', 'f1');
                                echo '<img src="' . $profileimageurl . '" alt="Profile Image" class="profile-img">';
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="teacher-details">
                            <h1><?php echo htmlspecialchars($teacher->firstname . ' ' . $teacher->lastname); ?></h1>
                            <p class="subtitle"><?php echo htmlspecialchars($teacher->email); ?></p>
                            <div class="teacher-status status-<?php echo $template_data['status_class']; ?>">
                                <span class="status-dot"></span>
                                <?php echo $template_data['status_text']; ?>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/teachers.php" class="back-button">
                            <span class="back-icon">←</span>
                            Back to Teachers
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="teacher-content">
            <!-- Personal Information -->
            <div class="content-section personal-info-section">
                <h2 class="section-title">
                    <span class="section-icon">👤</span>
                    Personal Information
                </h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <div class="info-value"><?php echo htmlspecialchars($teacher->username); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <div class="info-value"><?php echo htmlspecialchars($teacher->email); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone 1</span>
                        <div class="info-value"><?php echo htmlspecialchars($teacher->phone1 ?: 'Not provided'); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone 2</span>
                        <div class="info-value"><?php echo htmlspecialchars($teacher->phone2 ?: 'Not provided'); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">City</span>
                        <div class="info-value"><?php echo htmlspecialchars($teacher->city ?: 'Not provided'); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Country</span>
                        <div class="info-value"><?php echo htmlspecialchars($teacher->country ?: 'Not provided'); ?></div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="<?php echo $CFG->wwwroot; ?>/user/edit.php?id=<?php echo $teacher->id; ?>" class="btn btn-primary">
                        ✏️ Edit Profile
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/user/profile.php?id=<?php echo $teacher->id; ?>" class="btn btn-secondary">
                        👁️ View Full Profile
                    </a>
                </div>
            </div>

            <!-- Account Information -->
            <div class="content-section">
                <h2 class="section-title">
                    <span class="section-icon">🔐</span>
                    Account Information
                </h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">User ID</span>
                        <div class="info-value"><?php echo $teacher->id; ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Created</span>
                        <div class="info-value"><?php echo $template_data['created_date']; ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Login</span>
                        <div class="info-value"><?php echo $template_data['last_login']; ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Access</span>
                        <div class="info-value"><?php echo $template_data['last_access']; ?></div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="<?php echo $CFG->wwwroot; ?>/admin/user.php?delete=<?php echo $teacher->id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this teacher?')">
                        🗑️ Delete Account
                    </a>
                    <?php if ($teacher->suspended): ?>
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/user.php?unsuspend=<?php echo $teacher->id; ?>" class="btn btn-primary">
                            ✅ Unsuspend
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/user.php?suspend=<?php echo $teacher->id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to suspend this teacher?')">
                            ⏸️ Suspend
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Roles & Permissions -->
            <div class="content-section">
                <h2 class="section-title">
                    <span class="section-icon">🎭</span>
                    Roles & Permissions
                </h2>
                
                <?php if (!empty($template_data['role_assignments'])): ?>
                    <ul class="info-list">
                        <?php foreach ($template_data['role_assignments'] as $role): ?>
                            <li>
                                <div class="list-icon icon-blue">🎭</div>
                                <div class="list-content">
                                    <div class="list-title"><?php echo htmlspecialchars($role->name); ?></div>
                                    <div class="list-subtitle">Role: <?php echo htmlspecialchars($role->shortname); ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🎭</div>
                        <p class="empty-state-text">No roles assigned</p>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="<?php echo $CFG->wwwroot; ?>/admin/roles/assign.php?contextid=1&roleid=3&user=<?php echo $teacher->id; ?>" class="btn btn-primary">
                        ➕ Assign Role
                    </a>
                </div>
            </div>

            <!-- Courses -->
            <div class="content-section">
                <h2 class="section-title">
                    <span class="section-icon">📚</span>
                    Teaching Courses
                </h2>
                
                <?php if (!empty($template_data['courses'])): ?>
                    <ul class="info-list">
                        <?php foreach ($template_data['courses'] as $course): ?>
                            <li>
                                <div class="list-icon icon-green">📚</div>
                                <div class="list-content">
                                    <div class="list-title"><?php echo htmlspecialchars($course->fullname); ?></div>
                                    <div class="list-subtitle"><?php echo htmlspecialchars($course->shortname); ?> • Created: <?php echo date('M d, Y', $course->timecreated); ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📚</div>
                        <p class="empty-state-text">No courses assigned</p>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="<?php echo $CFG->wwwroot; ?>/admin/roles/assign.php?contextid=1&roleid=3" class="btn btn-primary">
                        ➕ Assign Course
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
?>
