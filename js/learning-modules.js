/**
 * Learning Modules JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Learning Modules functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Learning Modules page loaded');

    // Initialize Learning Modules functionality
    initializeLearningModules();
    
    // Initialize Section Activities functionality if present
    if (document.querySelector('.professional-section-activities')) {
        initializeSectionActivities();
    }
});

/**
 * Initialize Learning Modules functionality
 */
function initializeLearningModules() {
    // Initialize view system
    initializeViewSystem();
    
    // Initialize interactive elements
    initializeInteractiveElements();
    
    // Initialize filter system
    initializeFilterSystem();
}

/**
 * Initialize view toggle system
 */
function initializeViewSystem() {
    // Check for saved view preference
    const savedView = localStorage.getItem('modulesView');
    const defaultView = savedView || 'grid';
    
    // Initialize with saved or default view
    changeView(defaultView);
    
    // Add click animations to all buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Add ripple effect
            const ripple = document.createElement('span');
            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.background = 'rgba(59, 130, 246, 0.3)';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'ripple 0.6s linear';
            ripple.style.left = '50%';
            ripple.style.top = '50%';
            ripple.style.width = '20px';
            ripple.style.height = '20px';
            ripple.style.marginLeft = '-10px';
            ripple.style.marginTop = '-10px';
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

/**
 * Change view type (grid, list, timeline)
 */
function changeView(viewType) {
    // Remove active class from all buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active class to clicked button
    const targetBtn = document.querySelector(`[data-view="${viewType}"]`);
    if (targetBtn) {
        targetBtn.classList.add('active');
    }
    
    // Get modules grid element
    const modulesGrid = document.getElementById('modules-grid');
    
    if (modulesGrid) {
        // Remove existing view classes
        modulesGrid.classList.remove('grid-view', 'list-view', 'timeline-view');
        
        // Apply new view class
        modulesGrid.classList.add(`${viewType}-view`);
        
        // Store current view in localStorage for persistence
        localStorage.setItem('modulesView', viewType);
        
        console.log(`View changed to: ${viewType}`);
        
        // Add visual feedback
        if (targetBtn) {
            targetBtn.style.transform = 'scale(0.95)';
            setTimeout(() => {
                targetBtn.style.transform = 'scale(1)';
            }, 150);
        }
    }
}

/**
 * Initialize interactive elements
 */
function initializeInteractiveElements() {
    // Handle action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Add loading state
            this.classList.add('loading');
            const span = this.querySelector('span');
            const originalText = span ? span.textContent : '';
            
            if (span) {
                span.textContent = 'Loading...';
            }
            
            // Remove loading state after navigation
            setTimeout(() => {
                this.classList.remove('loading');
                if (span) {
                    span.textContent = originalText;
                }
            }, 2000);
        });
    });
    
    // Handle favorite buttons
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            this.classList.toggle('active');
            
            if (this.classList.contains('active')) {
                this.style.color = '#f59e0b';
                this.style.background = 'white';
            } else {
                this.style.color = '#f59e0b';
                this.style.background = 'rgba(255, 255, 255, 0.9)';
            }
        });
    });
    
    // Handle play button
    document.querySelectorAll('.play-button').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const card = this.closest('.learning-module-card');
            const actionBtn = card ? card.querySelector('.action-btn') : null;
            if (actionBtn) {
                actionBtn.click();
            }
        });
    });
    
    // Handle card hover effects
    document.querySelectorAll('.learning-module-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('hovered');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hovered');
        });
    });
}

/**
 * Initialize filter system
 */
function initializeFilterSystem() {
    const filterTrigger = document.querySelector('.filter-btn');
    const filterMenu = document.querySelector('.filter-menu');
    
    if (filterTrigger && filterMenu) {
        filterTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            filterMenu.classList.toggle('active');
        });
        
        // Handle filter options
        document.querySelectorAll('.filter-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                document.querySelectorAll('.filter-option').forEach(o => o.classList.remove('active'));
                
                // Add active class to clicked option
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                filterModuleCards(filter);
                
                // Update filter button text
                const filterText = filterTrigger.querySelector('span');
                if (filterText) {
                    filterText.textContent = this.textContent;
                }
                
                // Close menu
                filterMenu.classList.remove('active');
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!filterTrigger.contains(e.target) && !filterMenu.contains(e.target)) {
                filterMenu.classList.remove('active');
            }
        });
    }
    
    // Handle sort button
    const sortBtn = document.querySelector('.sort-btn');
    if (sortBtn) {
        sortBtn.addEventListener('click', function() {
            // Toggle sort order
            const isAscending = this.classList.contains('ascending');
            this.classList.toggle('ascending');
            
            // Update sort text
            const sortText = this.querySelector('span');
            if (sortText) {
                sortText.textContent = isAscending ? 'By Progress' : 'By Progress';
            }
            
            // Sort cards
            sortModuleCards(isAscending ? 'desc' : 'asc');
        });
    }
}

/**
 * Filter module cards based on status
 */
function filterModuleCards(filter) {
    const cards = document.querySelectorAll('.learning-module-card');
    
    cards.forEach(card => {
        const status = card.getAttribute('data-status');
        
        if (filter === 'all' || status === filter) {
            card.style.display = 'block';
            card.classList.add('filtered-in');
            card.classList.remove('filtered-out');
        } else {
            card.style.display = 'none';
            card.classList.add('filtered-out');
            card.classList.remove('filtered-in');
        }
    });
    
    console.log('Filtered modules by:', filter);
}

/**
 * Sort module cards by progress
 */
function sortModuleCards(order = 'asc') {
    const grid = document.getElementById('modules-grid');
    const cards = Array.from(grid.querySelectorAll('.learning-module-card'));
    
    cards.sort((a, b) => {
        const progressA = parseInt(a.querySelector('.progress-percentage')?.textContent || '0');
        const progressB = parseInt(b.querySelector('.progress-percentage')?.textContent || '0');
        
        return order === 'asc' ? progressA - progressB : progressB - progressA;
    });
    
    // Re-append sorted cards
    cards.forEach(card => {
        grid.appendChild(card);
    });
    
    console.log('Sorted modules by progress:', order);
}

/**
 * Initialize Section Activities functionality
 */
function initializeSectionActivities() {
    console.log('Initializing Section Activities...');
    
    // Auto-expand current section activities
    const currentSection = document.querySelector('.section-navigation-item.current');
    if (currentSection) {
        const activitiesList = currentSection.querySelector('.section-activities-list');
        const expandBtn = currentSection.querySelector('.section-expand-btn');
        
        if (activitiesList && expandBtn) {
            activitiesList.classList.add('expanded');
            expandBtn.classList.add('rotated');
            currentSection.classList.add('section-expanded');
            console.log('Auto-expanded current section activities');
        }
    }
    
    // Debug: Log all section elements found
    const allSections = document.querySelectorAll('.section-navigation-item');
    console.log('Found sections:', allSections.length);
    
    allSections.forEach((section, index) => {
        const sectionNum = section.querySelector('.section-number')?.textContent;
        const activitiesList = section.querySelector('.section-activities-list');
        const expandBtn = section.querySelector('.section-expand-btn');
        console.log(`Section ${index + 1}:`, {
            sectionNum,
            hasActivitiesList: !!activitiesList,
            hasExpandBtn: !!expandBtn,
            activitiesListId: activitiesList?.id
        });
    });
    
    // Add click handlers for activity links
    document.querySelectorAll('.activity-link').forEach(link => {
        link.addEventListener('click', function(e) {
            console.log('Activity link clicked:', this.href);
            
            // Add loading state
            this.classList.add('loading');
            
            // Add ripple effect
            const ripple = document.createElement('span');
            ripple.classList.add('ripple-effect');
            this.appendChild(ripple);
            
            // Remove ripple after animation
            setTimeout(() => {
                ripple.remove();
            }, 600);
            
            // Remove loading state after navigation
            setTimeout(() => {
                this.classList.remove('loading');
            }, 2000);
        });
    });
    
    // Add hover effects for navigation items
    document.querySelectorAll('.activity-navigation-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.classList.add('hovered');
        });
        
        item.addEventListener('mouseleave', function() {
            this.classList.remove('hovered');
        });
    });
    
    // Add click handlers for section headers (alternative to button)
    document.querySelectorAll('.section-header').forEach(header => {
        header.addEventListener('click', function(e) {
            // Don't trigger if clicking on the expand button
            if (!e.target.closest('.section-expand-btn')) {
                const sectionItem = this.closest('.section-navigation-item');
                const sectionNum = sectionItem.querySelector('.section-number').textContent;
                console.log('Section header clicked, section number:', sectionNum);
                toggleSection(parseInt(sectionNum));
            }
        });
    });
    
    // Add click handlers for expand buttons
    document.querySelectorAll('.section-expand-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const sectionItem = this.closest('.section-navigation-item');
            const sectionNum = sectionItem.querySelector('.section-number').textContent;
            console.log('Expand button clicked, section number:', sectionNum);
            toggleSection(parseInt(sectionNum));
        });
    });
    
    // Add keyboard support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
    
    console.log('Section Activities initialized successfully!');
}

// Global functions for section activities
function toggleSection(sectionNum) {
    console.log('toggleSection called with:', sectionNum);
    
    const activitiesList = document.getElementById(`section-${sectionNum}-activities`);
    const expandBtn = document.querySelector(`button[onclick="toggleSection(${sectionNum})"]`);
    const sectionItem = activitiesList ? activitiesList.closest('.section-navigation-item') : null;
    
    console.log('Found elements:', { activitiesList, expandBtn, sectionItem });
    
    if (activitiesList && expandBtn) {
        const isExpanded = activitiesList.classList.contains('expanded');
        console.log('Current state - isExpanded:', isExpanded);
        
        if (isExpanded) {
            // Collapse
            activitiesList.classList.remove('expanded');
            expandBtn.classList.remove('rotated');
            if (sectionItem) {
                sectionItem.classList.remove('section-expanded');
            }
            console.log('Section collapsed');
        } else {
            // Expand
            activitiesList.classList.add('expanded');
            expandBtn.classList.add('rotated');
            if (sectionItem) {
                sectionItem.classList.add('section-expanded');
            }
            console.log('Section expanded');
        }
    } else {
        console.error('Could not find required elements for section:', sectionNum);
    }
}

function goBackToCourse() {
    // Navigate back to the main course page
    const courseUrl = document.querySelector('.breadcrumb-item').href;
    window.location.href = courseUrl;
}

function collapseSidebar() {
    const sidebar = document.querySelector('.course-navigation-sidebar');
    
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
        console.log('Sidebar toggled');
    }
}

function changeActivitiesView(viewType) {
    // Remove active class from all buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active class to clicked button
    document.querySelector(`[data-view="${viewType}"].view-btn`).classList.add('active');
    
    // Get activities table container
    const activitiesContainer = document.querySelector('.activities-table-container');
    
    if (activitiesContainer) {
        // Remove existing view classes
        activitiesContainer.classList.remove('grid-view', 'list-view');
        
        // Apply new view class
        activitiesContainer.classList.add(`${viewType}-view`);
        
        // Store current view in localStorage for persistence
        localStorage.setItem('activitiesView', viewType);
        
        console.log(`Activities view changed to: ${viewType}`);
        
        // Add visual feedback
        const button = document.querySelector(`[data-view="${viewType}"].view-btn`);
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
            button.style.transform = 'scale(1)';
        }, 150);
    }
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .action-btn.loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    .favorite-btn.active {
        color: #f59e0b !important;
        background: white !important;
    }
    
    .learning-module-card.hovered {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .learning-module-card.filtered-out {
        opacity: 0;
        transform: scale(0.8);
    }
    
    .learning-module-card.filtered-in {
        opacity: 1;
        transform: scale(1);
    }
    
    .filter-menu {
        position: absolute;
        top: 100%;
        left: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        min-width: 150px;
        display: none;
    }
    
    .filter-menu.active {
        display: block;
    }
    
    .filter-option {
        padding: 10px 15px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    
    .filter-option:hover {
        background: #f8fafc;
    }
    
    .filter-option.active {
        background: #3b82f6;
        color: white;
    }
    
    .sort-btn.ascending::after {
        content: ' ↑';
    }
    
    .sort-btn:not(.ascending)::after {
        content: ' ↓';
    }
    
    /* Section Activities specific styles */
    .activity-navigation-item.hovered {
        background: #f8fafc;
        border-radius: 6px;
    }
    
    .activity-link.loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    .ripple-effect {
        position: absolute;
        border-radius: 50%;
        background: rgba(59, 130, 246, 0.3);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    }
    
    .course-navigation-sidebar.collapsed {
        width: 60px;
    }
    
    .course-navigation-sidebar.collapsed .sidebar-controls,
    .course-navigation-sidebar.collapsed .course-sections-navigation {
        display: none;
    }
    
    .course-navigation-sidebar.collapsed .sidebar-title {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        font-size: 0.7rem;
    }
`;
document.head.appendChild(style);
