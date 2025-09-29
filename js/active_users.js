/**
 * Active Users JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Active Users page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Active Users page loaded');
    
    // Initialize table interactions
    initializeTableInteractions();
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize mobile responsiveness
    initializeMobileResponsiveness();
});

/**
 * Initialize table interactions
 */
function initializeTableInteractions() {
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

    // Add row click functionality
    const tableRows = document.querySelectorAll('.users-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            console.log('Row clicked:', this);
            // Add functionality to view user details
        });
    });
}

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    // Handle Enter key to submit search
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.form.submit();
        }
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
}

/**
 * Initialize mobile responsiveness
 */
function initializeMobileResponsiveness() {
    // Create mobile header if needed
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
}

/**
 * Create mobile header
 */
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

/**
 * Remove mobile header
 */
function removeMobileHeader() {
    const mobileHeader = document.querySelector('.mobile-header');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (mobileHeader) mobileHeader.remove();
    if (overlay) overlay.remove();
}

// Global functions for sidebar
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

// Export functions for global use
window.ActiveUsers = {
    initializeTableInteractions,
    initializeSearch,
    initializeMobileResponsiveness
};
