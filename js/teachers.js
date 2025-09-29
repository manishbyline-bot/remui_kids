/**
 * Teachers Management JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Teachers page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Teachers page loaded');

    // Initialize search and filter functionality
    initializeTeachersSearch();

    // Initialize mobile functionality
    initializeMobileFeatures();
});

/**
 * Initialize search and filter functionality for teachers
 */
function initializeTeachersSearch() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const table = document.getElementById('teachersTable');
    
    if (!searchInput || !statusFilter || !table) {
        return; // Exit if elements not found
    }
    
    // Function to filter teachers
    function filterTeachers() {
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
        const actionButtons = document.querySelectorAll('.action-icon');
        actionButtons.forEach(function(button) {
            button.addEventListener('mouseenter', function() {
                if (this.classList.contains('view-icon')) {
                    this.style.color = '#52C9D9';
                    this.style.transform = 'scale(1.1)';
                } else if (this.classList.contains('edit-icon')) {
                    this.style.color = '#52C9D9';
                    this.style.transform = 'scale(1.1)';
                } else if (this.classList.contains('suspend-icon')) {
                    this.style.color = '#52C9D9';
                    this.style.transform = 'scale(1.1)';
                }
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.color = '#333';
                this.style.transform = 'scale(1)';
            });
        });
    } catch (error) {
        console.log('Error setting up button hover effects:', error);
    }
}

/**
 * Initialize mobile functionality for teachers page
 */
function initializeMobileFeatures() {
    // Mobile sidebar functionality
    if (window.innerWidth <= 768) {
        createMobileHeader();
    }
    
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
            <div class="mobile-logo">Teachers</div>
        `;
        
        document.body.insertBefore(mobileHeader, document.body.firstChild);
        
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
}

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
