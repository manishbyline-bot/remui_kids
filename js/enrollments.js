/**
 * Enrollments Management JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Enrollments page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Enrollments page loaded');

    // Initialize AJAX search and filter functionality
    initializeEnrollmentsSearch();

    // Initialize mobile functionality
    initializeMobileFeatures();
});

/**
 * Initialize AJAX search and filter functionality for enrollments
 */
function initializeEnrollmentsSearch() {
    const searchInput = document.getElementById('searchInput');
    const courseFilter = document.getElementById('courseFilter');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody = document.getElementById('enrollmentsTableBody');
    const paginationContainer = document.getElementById('paginationContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    
    if (!searchInput) return;
    
    let filterTimeout;
    let currentPage = 0;
    
    // AJAX function to fetch enrollment data
    function fetchEnrollments(page = 0) {
        showLoading();
        
        const params = new URLSearchParams();
        params.set('action', 'get_enrollments');
        params.set('page', page);
        
        if (searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }
        if (courseFilter && courseFilter.value) {
            params.set('course', courseFilter.value);
        }
        if (statusFilter && statusFilter.value) {
            params.set('status', statusFilter.value);
        }
        
        fetch(`/Kodeit-Iomad-local/Kodeit-Iomad-local/iomad-test/theme/remui_kids/enrollments.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                
                currentPage = data.current_page;
                updateTable(data.users);
                updateSummaryCards(data);
                updatePagination(data);
                updateURL(data.filters);
            })
            .catch(error => {
                hideLoading();
                console.error('Fetch error:', error);
            });
    }
    
    // Show loading indicator
    function showLoading() {
        if (loadingIndicator) {
            loadingIndicator.style.display = 'flex';
        }
        if (tableBody) {
            tableBody.style.opacity = '0.5';
        }
    }
    
    // Hide loading indicator
    function hideLoading() {
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
        if (tableBody) {
            tableBody.style.opacity = '1';
        }
    }
    
    // Update table with new data
    function updateTable(users) {
        if (!tableBody) return;
        
        if (users.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #6c757d;">
                        No enrolled users found matching your search criteria.
                    </td>
                </tr>
            `;
            return;
        }
        
        tableBody.innerHTML = users.map(user => `
            <tr>
                <td>
                    <div class="user-info">
                        <div class="user-avatar">
                            ${user.firstname.charAt(0).toUpperCase()}${user.lastname.charAt(0).toUpperCase()}
                        </div>
                        <div class="user-details">
                            <h4>${user.firstname} ${user.lastname}</h4>
                            <p>@${user.username}</p>
                        </div>
                    </div>
                </td>
                <td>${user.email}</td>
                <td>
                    <span class="status-badge ${user.status === 'suspended' ? 'status-suspended' : 'status-active'}">
                        ${user.status === 'suspended' ? 'Suspended' : 'Active'}
                    </span>
                </td>
                <td>
                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${user.courses}
                    </div>
                </td>
                <td>
                    <span class="course-count">${user.course_count} courses</span>
                </td>
                <td>${new Date(user.timecreated * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
            </tr>
        `).join('');
    }
    
    // Update summary cards
    function updateSummaryCards(data) {
        // Update summary cards if statistics are provided
        if (data.statistics) {
            const activeCount = document.getElementById('activeCount');
            const suspendedCount = document.getElementById('suspendedCount');
            
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
        if (!paginationContainer) return;
        
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
        if (filters.course) params.set('course', filters.course);
        if (filters.status) params.set('status', filters.status);
        if (currentPage > 0) params.set('page', currentPage);
        
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.pushState({}, '', newUrl);
    }
    
    // Global function for pagination
    window.changePage = function(page) {
        currentPage = page;
        fetchEnrollments(page);
    };
    
    // Auto-search functionality
    function performAutoSearch() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            currentPage = 0; // Reset to first page on new search
            fetchEnrollments(0);
        }, 500); // 500ms delay for auto-search
    }
    
    searchInput.addEventListener('input', function() {
        // Trigger auto-search
        performAutoSearch();
    });
    
    // Add event listeners for filter changes
    if (courseFilter) {
        courseFilter.addEventListener('change', performAutoSearch);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', performAutoSearch);
    }
}

/**
 * Initialize mobile functionality for enrollments page
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
            <div class="mobile-logo">Enrollments</div>
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
