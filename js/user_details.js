/**
 * User Details JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// User Details page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('User Details page loaded');
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize table interactions
    initializeTableInteractions();
    
    // Initialize mobile responsiveness
    initializeMobileResponsiveness();
    
    // Initialize advanced search features
    initializeAdvancedSearch();
});

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsSection = document.getElementById('suggestionsSection');
    const suggestionsTableBody = document.getElementById('suggestionsTableBody');
    const suggestionsCount = document.getElementById('suggestionsCount');
    const usersTableSection = document.getElementById('usersTableSection');
    let currentSuggestions = [];
    let searchTimeout;

    if (!searchInput) return;

    // Auto-focus search input
    if (!searchInput.value) {
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
    }

    // Show error state
    function showErrorSuggestions() {
        suggestionsTableBody.innerHTML = '<tr><td colspan="7" class="no-suggestions">Error loading suggestions</td></tr>';
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
}

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
        <div class="mobile-logo">User Details</div>
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

/**
 * Initialize advanced search features
 */
function initializeAdvancedSearch() {
    // Add search history
    const searchHistory = JSON.parse(localStorage.getItem('userSearchHistory') || '[]');
    
    // Save search to history
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim()) {
        const searchTerm = searchInput.value.trim();
        if (!searchHistory.includes(searchTerm)) {
            searchHistory.unshift(searchTerm);
            searchHistory = searchHistory.slice(0, 10); // Keep only last 10 searches
            localStorage.setItem('userSearchHistory', JSON.stringify(searchHistory));
        }
    }
    
    // Add click handlers for quick search buttons
    document.querySelectorAll('.quick-search-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const searchTerm = this.getAttribute('data-search');
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.value = searchTerm;
                searchInput.form.submit();
            }
        });
    });
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
window.UserDetails = {
    initializeSearch,
    initializeTableInteractions,
    initializeMobileResponsiveness,
    initializeAdvancedSearch
};
