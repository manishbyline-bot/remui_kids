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
 * Render trainee/teacher sidebar
 *
 * @package    theme_remui_kids
 * @copyright  2024 Riyada Trainings
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Check if user is logged in
if (!isloggedin()) {
    http_response_code(401);
    exit('Unauthorized');
}

global $USER, $CFG, $SITE;

// Prepare template data
$wwwroot = $CFG->wwwroot;
$sitename = $SITE->fullname;
$firstname = $USER->firstname;
$lastname = $USER->lastname;
$institution = !empty($USER->institution) ? $USER->institution : 'Riyada Trainings';
?>

<!-- Include sidebar CSS -->
<link rel="stylesheet" href="<?php echo $wwwroot; ?>/theme/remui_kids/style/riyada_sidebar.css">

<style>
/* Inline styles to ensure scroll works */
        .riyada-sidebar-container {
            position: fixed !important;
            left: 0 !important;
            top: 30px !important; /* Move admin sidebar up a little more */
            height: calc(100vh - 30px) !important; /* Adjust height accordingly */
    width: 280px !important;
    background: #ffffff !important;
    border-right: 1px solid #e5e7eb !important;
    z-index: 999 !important; /* Lower than navbar */
    overflow-y: auto !important; /* Allow vertical scrolling */
    overflow-x: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}

.riyada-sidebar-nav {
    flex: 1 !important;
    overflow-y: visible !important; /* Allow content to flow naturally */
    overflow-x: hidden !important;
    padding: 20px 0 !important; /* Reduced padding since container now has top padding */
    position: relative !important;
    scrollbar-width: thin !important;
    scrollbar-color: #d1d5db #f9fafb !important;
    min-height: 0 !important; /* Allow flex shrinking */
}

.riyada-sidebar-nav::-webkit-scrollbar {
    width: 8px !important;
    display: block !important;
}

.riyada-sidebar-nav::-webkit-scrollbar-track {
    background: #f9fafb !important;
    border-radius: 4px !important;
}

.riyada-sidebar-nav::-webkit-scrollbar-thumb {
    background: #d1d5db !important;
    border-radius: 4px !important;
}

.riyada-sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: #9ca3af !important;
}

/* Show sidebar for trainee and teacher users */
#riyada-sidebar {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    transform: translateX(0) !important;
    width: 280px !important;
    overflow: visible !important;
}

/* Ensure main content has proper margin when sidebar is shown - only for non-admin users */
body:not(.admin-user) .main-content,
body:not(.admin-user) #page-wrapper,
body:not(.admin-user) .container-fluid,
body:not(.admin-user) .main-content-wrapper {
    margin-left: 280px !important;
    margin-top: 40px !important; /* Add top margin to account for sidebar position */
    width: calc(100% - 280px) !important;
    max-width: calc(100% - 280px) !important;
}

/* Admin users - keep original layout with just top margin */
body.admin-user .main-content,
body.admin-user #page-wrapper,
body.admin-user .container-fluid,
body.admin-user .main-content-wrapper {
    margin-left: 0 !important;
    margin-top: 40px !important; /* Move content down to match trainee interface */
    width: 100% !important;
    max-width: 100% !important;
}

body {
    margin-left: 0 !important;
    padding-left: 0 !important;
}
</style>

<div id="riyada-sidebar" class="riyada-sidebar-container" style="position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: #ffffff; border-right: 1px solid #e5e7eb; z-index: 1000; overflow: hidden; display: flex; flex-direction: column;">
    <!-- Sidebar Header -->
    
    
    <!-- Sidebar Navigation -->
    <nav class="riyada-sidebar-nav" id="riyada-sidebar-nav" style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 20px 0; max-height: calc(100vh - 100px); position: relative;">
        
        <!-- Dashboard Section -->
        <div class="riyada-nav-section">
            <div class="riyada-nav-section-title">MY DASHBOARD</div>
            <ul class="riyada-nav-list">
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_dashboard.php" class="riyada-nav-link" data-page="dashboard">
                        <span class="riyada-nav-icon">📊</span>
                        <span class="riyada-nav-text">My Dashboard</span>
                        <span class="riyada-nav-indicator"></span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_profile.php" class="riyada-nav-link" data-page="profile">
                        <span class="riyada-nav-icon">👤</span>
                        <span class="riyada-nav-text">My Profile</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Learning Section -->
        <div class="riyada-nav-section">
            <div class="riyada-nav-section-title">MY LEARNING</div>
            <ul class="riyada-nav-list">
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/my_learning.php" class="riyada-nav-link" data-page="courses">
                        <span class="riyada-nav-icon">📖</span>
                        <span class="riyada-nav-text">My Learning</span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/my_pathway.php" class="riyada-nav-link" data-page="pathway">
                        <span class="riyada-nav-icon">🗺️</span>
                        <span class="riyada-nav-text">My Pathway</span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/badges/mybadges.php" class="riyada-nav-link" data-page="achievements">
                        <span class="riyada-nav-icon">🏆</span>
                        <span class="riyada-nav-text">Achievements</span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_assessment.php" class="riyada-nav-link" data-page="assessments">
                        <span class="riyada-nav-icon">📋</span>
                        <span class="riyada-nav-text">Assessments</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Learning Paths Section -->
        <div class="riyada-nav-section">
            <div class="riyada-nav-section-title">LEARNING PATHS</div>
            <ul class="riyada-nav-list">
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/course/index.php" class="riyada-nav-link" data-page="learning-paths">
                        <span class="riyada-nav-icon">📈</span>
                        <span class="riyada-nav-text">Learning Paths</span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_certifications.php" class="riyada-nav-link" data-page="certifications">
                        <span class="riyada-nav-icon">🎓</span>
                        <span class="riyada-nav-text">Certifications</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Competency Section -->
        <div class="riyada-nav-section">
            <div class="riyada-nav-section-title">COMPETENCY</div>
            <ul class="riyada-nav-list">
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_competency.php" class="riyada-nav-link" data-page="competency-map">
                        <span class="riyada-nav-icon">📊</span>
                        <span class="riyada-nav-text">Competency Map</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Community Section -->
        <div class="riyada-nav-section">
            <div class="riyada-nav-section-title">COMMUNITY</div>
            <ul class="riyada-nav-list">
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_peer_network.php" class="riyada-nav-link" data-page="peer-network">
                        <span class="riyada-nav-icon">👥</span>
                        <span class="riyada-nav-text">Peer Network</span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_leaderboards.php" class="riyada-nav-link" data-page="leaderboards">
                        <span class="riyada-nav-icon">🏆</span>
                        <span class="riyada-nav-text">Leaderboards</span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_forums.php" class="riyada-nav-link" data-page="forums">
                        <span class="riyada-nav-icon">💬</span>
                        <span class="riyada-nav-text">Discussion Forums</span>
                    </a>
                </li>
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/theme/remui_kids/trainee_resources.php" class="riyada-nav-link" data-page="resources">
                        <span class="riyada-nav-icon">📚</span>
                        <span class="riyada-nav-text">Resource Library</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Settings Section -->
        <div class="riyada-nav-section">
            <div class="riyada-nav-section-title">SETTINGS</div>
            <ul class="riyada-nav-list">
                <li class="riyada-nav-item">
                    <a href="<?php echo $wwwroot; ?>/user/preferences.php" class="riyada-nav-link" data-page="settings">
                        <span class="riyada-nav-icon">⚙️</span>
                        <span class="riyada-nav-text">Settings</span>
                        <span class="riyada-nav-dropdown">▼</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- User Profile Section -->
    <div class="riyada-sidebar-footer">
        <div class="riyada-user-profile">
            <div class="riyada-user-avatar">
                <span class="riyada-avatar-icon">👤</span>
            </div>
            <div class="riyada-user-info">
                <div class="riyada-user-name"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></div>
                <div class="riyada-user-school"><?php echo htmlspecialchars($institution); ?></div>
            </div>
        </div>
        <div class="riyada-logout-section">
            <button class="riyada-logout-btn" onclick="window.location.href='<?php echo $wwwroot; ?>/login/logout.php'">
                <span class="riyada-logout-icon">🚪</span>
                <span class="riyada-logout-text">Logout</span>
            </button>
        </div>
    </div>
    
    <!-- Scroll to Top Button -->
    <button class="riyada-scroll-to-top" id="scroll-to-top-btn" title="Scroll to Top">
        ↑
    </button>
    
    <!-- Floating Action Button -->
    <div class="riyada-fab">
        <button class="riyada-fab-btn" id="sidebar-toggle-btn" title="Toggle Sidebar">
            <span class="riyada-fab-icon">→</span>
        </button>
    </div>
</div>

<script>
// Initialize sidebar for trainee and teacher users
document.addEventListener('DOMContentLoaded', function() {
    // Check user role and show appropriate sidebar
    fetch('<?php echo $wwwroot; ?>/theme/remui_kids/check_admin.php')
        .then(response => response.json())
        .then(data => {
            if (data.show_sidebar && (data.istrainee || data.isteacher)) {
                // Show the sidebar for trainees and teachers
                const sidebar = document.getElementById('riyada-sidebar');
                if (sidebar) {
                    sidebar.style.display = 'block';
                    sidebar.style.visibility = 'visible';
                    sidebar.style.opacity = '1';
                    sidebar.style.transform = 'translateX(0)';
                    sidebar.style.width = '280px';
                }
                
                // Show floating action button
                const fab = document.querySelector('.riyada-fab');
                if (fab) {
                    fab.style.display = 'block';
                }
                
                // Add appropriate body class
                if (data.isteacher) {
                    document.body.classList.add('teacher-user');
                } else if (data.istrainee) {
                    document.body.classList.add('trainee-user');
                }
                
                // Initialize sidebar scroll functionality first
                initializeSidebarScroll();
                
                // Apply active page highlighting after a short delay to ensure DOM is ready
                setTimeout(() => {
                    const currentPath = window.location.pathname;
                    const currentPage = getCurrentPage(currentPath);
                    
                    console.log('Current path:', currentPath);
                    console.log('Current page:', currentPage);
                    
                    // Remove all active classes
                    document.querySelectorAll('.riyada-nav-link').forEach(link => {
                        link.classList.remove('active');
                    });
                    
                    // Add active class to current page
                    const activeLink = document.querySelector(`[data-page="${currentPage}"]`);
                    console.log('Active link found:', activeLink);
                    if (activeLink) {
                        activeLink.classList.add('active');
                        console.log('Added active class to:', activeLink);
                    } else {
                        console.log('No active link found for page:', currentPage);
                        // Debug: List all available data-page attributes
                        const allLinks = document.querySelectorAll('[data-page]');
                        console.log('Available data-page attributes:', Array.from(allLinks).map(link => link.getAttribute('data-page')));
                    }
                }, 100);
            } else {
                // Hide sidebar for admin users or if not logged in
                const sidebar = document.getElementById('riyada-sidebar');
                if (sidebar) {
                    sidebar.style.display = 'none';
                }
                
                const fab = document.querySelector('.riyada-fab');
                if (fab) {
                    fab.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.log('Could not check user role, hiding sidebar');
            const sidebar = document.getElementById('riyada-sidebar');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        });
});

function getCurrentPage(path) {
    // Trainee/Teacher pages
    if (path.includes('/trainee_dashboard.php')) {
        return 'dashboard';
    } else if (path.includes('/my/') && !path.includes('/courses.php')) {
        return 'dashboard';
    } else if (path.includes('/my_learning.php')) {
        return 'courses';
    } else if (path.includes('/my_pathway.php')) {
        return 'pathway';
    } else if (path.includes('/courses.php') || path.includes('/my/courses.php')) {
        return 'courses';
    } else if (path.includes('/course/index.php')) {
        return 'learning-paths';
    } else if (path.includes('/trainee_profile.php')) {
        return 'profile';
    } else if (path.includes('/profile.php')) {
        return 'profile';
    } else if (path.includes('/badges/mybadges.php')) {
        return 'achievements';
    } else if (path.includes('/trainee_assessment.php')) {
        return 'assessments';
    } else if (path.includes('/calendar/view.php')) {
        return 'assessments';
    } else if (path.includes('/trainee_certifications.php')) {
        return 'certifications';
    } else if (path.includes('/badges/index.php')) {
        return 'certifications';
    } else if (path.includes('/trainee_peer_network.php')) {
        return 'peer-network';
    } else if (path.includes('/trainee_leaderboards.php')) {
        return 'leaderboards';
    } else if (path.includes('/trainee_forums.php')) {
        return 'forums';
    } else if (path.includes('/forum/index.php')) {
        return 'forums';
    } else if (path.includes('/trainee_resources.php')) {
        return 'resources';
    } else if (path.includes('/user/index.php')) {
        return 'community';
    } else if (path.includes('/preferences.php')) {
        return 'settings';
    } else if (path.includes('/trainee_competency.php')) {
        return 'competency-map';
    } else if (path.includes('/tool/lp/plans.php')) {
        return 'competency-map';
    } else if (path.includes('/competency/') && path.includes('competency_map')) {
        return 'competency-map';
    }
    return null;
}

// Initialize sidebar scroll functionality
function initializeSidebarScroll() {
    const sidebarNav = document.getElementById('riyada-sidebar-nav');
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    
    if (!sidebarNav || !toggleBtn) return;
    
    // Force scroll properties
    sidebarNav.style.overflowY = 'visible';
    sidebarNav.style.overflowX = 'hidden';
    sidebarNav.style.scrollBehavior = 'smooth';
    
    // Toggle button functionality
    toggleBtn.addEventListener('click', function() {
        const sidebar = document.getElementById('riyada-sidebar');
        const isCollapsed = sidebar.style.transform === 'translateX(-100%)';
        
        if (isCollapsed) {
            // Show sidebar
            sidebar.style.transform = 'translateX(0)';
            sidebar.style.transition = 'transform 0.3s ease';
            document.body.classList.remove('sidebar-hidden');
            toggleBtn.innerHTML = '<span class="riyada-fab-icon">→</span>';
        } else {
            // Hide sidebar
            sidebar.style.transform = 'translateX(-100%)';
            sidebar.style.transition = 'transform 0.3s ease';
            document.body.classList.add('sidebar-hidden');
            toggleBtn.innerHTML = '<span class="riyada-fab-icon">←</span>';
        }
    });
    
    // Auto-scroll to active item
    const activeLink = document.querySelector('.riyada-nav-link.active');
    if (activeLink) {
        setTimeout(() => {
            activeLink.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 100);
    }
    
    // Initialize scroll to top functionality
    initializeScrollToTop();
}

// Initialize scroll to top functionality
function initializeScrollToTop() {
    const scrollToTopBtn = document.getElementById('scroll-to-top-btn');
    const sidebarNav = document.getElementById('riyada-sidebar-nav');
    
    if (!scrollToTopBtn || !sidebarNav) return;
    
    // Show/hide scroll to top button based on scroll position
    function toggleScrollToTop() {
        const { scrollTop } = sidebarNav;
        if (scrollTop > 100) {
            scrollToTopBtn.classList.add('visible');
        } else {
            scrollToTopBtn.classList.remove('visible');
        }
    }
    
    // Add scroll listener
    sidebarNav.addEventListener('scroll', toggleScrollToTop);
    
    // Scroll to top functionality
    scrollToTopBtn.addEventListener('click', function() {
        sidebarNav.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Initial check
    toggleScrollToTop();
}
</script>
