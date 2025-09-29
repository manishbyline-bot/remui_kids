/**
 * Schools Management JavaScript
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/modal_factory', 'core/modal_events'], 
function($, ajax, notification, ModalFactory, ModalEvents) {
    
    return {
        init: function() {
            this.initEventListeners();
            this.initAnimations();
            this.initTooltips();
            this.loadSchoolData();
        },

        initEventListeners: function() {
            var self = this;
            
            // Management card clicks
            $('.management-card').on('click', function() {
                self.handleManagementCardClick($(this));
            });

            // School action buttons
            $('.school-actions').on('click', 'button', function(e) {
                e.stopPropagation();
                var action = $(this).data('action');
                var schoolId = $(this).data('school-id');
                self.handleSchoolAction(action, schoolId);
            });

            // Search functionality
            $('#school-search').on('input', function() {
                self.filterSchools($(this).val());
            });

            // Filter dropdown
            $('#school-filter').on('change', function() {
                self.filterSchoolsByStatus($(this).val());
            });

            // Refresh button
            $('#refresh-schools').on('click', function() {
                self.refreshSchoolData();
            });

            // Export functionality
            $('#export-schools').on('click', function() {
                self.exportSchoolsData();
            });
        },

        initAnimations: function() {
            // Animate summary cards on load
            $('.summary-card').each(function(index) {
                $(this).css('animation-delay', (index * 0.1) + 's');
                $(this).addClass('fade-in-up');
            });

            // Animate management cards
            $('.management-card').each(function(index) {
                $(this).css('animation-delay', (index * 0.1 + 0.4) + 's');
                $(this).addClass('fade-in-up');
            });

            // Counter animation for numbers
            this.animateCounters();
        },

        initTooltips: function() {
            // Initialize Bootstrap tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Custom tooltips for management cards
            $('.management-card').each(function() {
                $(this).attr('data-toggle', 'tooltip');
                $(this).attr('title', 'Click to ' + $(this).find('.card-title').text().toLowerCase());
            });
        },

        animateCounters: function() {
            $('.card-number').each(function() {
                var $this = $(this);
                var countTo = parseInt($this.text());
                
                $({ countNum: 0 }).animate({
                    countNum: countTo
                }, {
                    duration: 2000,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.floor(this.countNum));
                    },
                    complete: function() {
                        $this.text(this.countNum);
                    }
                });
            });
        },

        handleManagementCardClick: function($card) {
            var action = $card.find('.card-title').text().toLowerCase().replace(/\s+/g, '');
            
            switch(action) {
                case 'createschool':
                    this.showCreateSchoolModal();
                    break;
                case 'editschool':
                    this.showEditSchoolModal();
                    break;
                case 'advancedschoolsettings':
                    this.showAdvancedSettingsModal();
                    break;
                case 'manageschools':
                    this.showManageSchoolsModal();
                    break;
                case 'managedepartments':
                    this.showManageDepartmentsModal();
                    break;
                case 'optionalprofiles':
                    this.showOptionalProfilesModal();
                    break;
                case 'restrictcapabilities':
                    this.showRestrictCapabilitiesModal();
                    break;
                case 'importschools':
                    this.showImportSchoolsModal();
                    break;
                default:
                    notification.addNotification({
                        message: 'Functionality coming soon!',
                        type: 'info'
                    });
            }
        },

        handleSchoolAction: function(action, schoolId) {
            switch(action) {
                case 'view':
                    this.viewSchool(schoolId);
                    break;
                case 'edit':
                    this.editSchool(schoolId);
                    break;
                case 'toggle':
                    this.toggleSchoolStatus(schoolId);
                    break;
                case 'delete':
                    this.deleteSchool(schoolId);
                    break;
            }
        },

        showCreateSchoolModal: function() {
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: 'Create New School',
                body: this.getCreateSchoolForm(),
                large: true
            }).then(function(modal) {
                modal.show();
                
                modal.getRoot().on(ModalEvents.save, function() {
                    this.createSchool(modal);
                }.bind(this));
                
                modal.getRoot().on(ModalEvents.cancel, function() {
                    modal.destroy();
                });
            }.bind(this));
        },

        showEditSchoolModal: function() {
            notification.addNotification({
                message: 'Edit School functionality will be implemented here',
                type: 'info'
            });
        },

        showAdvancedSettingsModal: function() {
            notification.addNotification({
                message: 'Advanced Settings functionality will be implemented here',
                type: 'info'
            });
        },

        showManageSchoolsModal: function() {
            notification.addNotification({
                message: 'Manage Schools functionality will be implemented here',
                type: 'info'
            });
        },

        showManageDepartmentsModal: function() {
            notification.addNotification({
                message: 'Manage Departments functionality will be implemented here',
                type: 'info'
            });
        },

        showOptionalProfilesModal: function() {
            notification.addNotification({
                message: 'Optional Profiles functionality will be implemented here',
                type: 'info'
            });
        },

        showRestrictCapabilitiesModal: function() {
            notification.addNotification({
                message: 'Restrict Capabilities functionality will be implemented here',
                type: 'info'
            });
        },

        showImportSchoolsModal: function() {
            notification.addNotification({
                message: 'Import Schools functionality will be implemented here',
                type: 'info'
            });
        },

        getCreateSchoolForm: function() {
            return `
                <form id="create-school-form">
                    <div class="form-group">
                        <label for="school-name">School Name *</label>
                        <input type="text" class="form-control" id="school-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="school-description">Description</label>
                        <textarea class="form-control" id="school-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="school-location">Location</label>
                                <input type="text" class="form-control" id="school-location" name="location">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="school-type">School Type</label>
                                <select class="form-control" id="school-type" name="type">
                                    <option value="public">Public School</option>
                                    <option value="private">Private School</option>
                                    <option value="charter">Charter School</option>
                                    <option value="international">International School</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="auto-enroll" name="auto_enroll">
                            <label class="form-check-label" for="auto-enroll">
                                Auto-enroll students
                            </label>
                        </div>
                    </div>
                </form>
            `;
        },

        createSchool: function(modal) {
            var formData = $('#create-school-form').serialize();
            
            ajax.call([{
                methodname: 'theme_remui_kids_create_school',
                args: formData
            }])[0].then(function(response) {
                if (response.success) {
                    notification.addNotification({
                        message: 'School created successfully!',
                        type: 'success'
                    });
                    modal.destroy();
                    this.refreshSchoolData();
                } else {
                    notification.addNotification({
                        message: response.message || 'Failed to create school',
                        type: 'error'
                    });
                }
            }.bind(this)).catch(function(error) {
                notification.addNotification({
                    message: 'Error creating school: ' + error.message,
                    type: 'error'
                });
            });
        },

        viewSchool: function(schoolId) {
            window.location.href = M.cfg.wwwroot + '/theme/remui_kids/school_view.php?id=' + schoolId;
        },

        editSchool: function(schoolId) {
            window.location.href = M.cfg.wwwroot + '/theme/remui_kids/school_edit.php?id=' + schoolId;
        },

        toggleSchoolStatus: function(schoolId) {
            ModalFactory.create({
                type: ModalFactory.types.CONFIRM,
                title: 'Confirm Action',
                body: 'Are you sure you want to change the status of this school?'
            }).then(function(modal) {
                modal.show();
                
                modal.getRoot().on(ModalEvents.yes, function() {
                    this.performToggleStatus(schoolId);
                    modal.destroy();
                }.bind(this));
            }.bind(this));
        },

        performToggleStatus: function(schoolId) {
            ajax.call([{
                methodname: 'theme_remui_kids_toggle_school_status',
                args: { schoolid: schoolId }
            }])[0].then(function(response) {
                if (response.success) {
                    notification.addNotification({
                        message: 'School status updated successfully!',
                        type: 'success'
                    });
                    this.refreshSchoolData();
                } else {
                    notification.addNotification({
                        message: response.message || 'Failed to update school status',
                        type: 'error'
                    });
                }
            }.bind(this));
        },

        deleteSchool: function(schoolId) {
            ModalFactory.create({
                type: ModalFactory.types.CONFIRM,
                title: 'Delete School',
                body: 'Are you sure you want to delete this school? This action cannot be undone.'
            }).then(function(modal) {
                modal.show();
                
                modal.getRoot().on(ModalEvents.yes, function() {
                    this.performDeleteSchool(schoolId);
                    modal.destroy();
                }.bind(this));
            }.bind(this));
        },

        performDeleteSchool: function(schoolId) {
            ajax.call([{
                methodname: 'theme_remui_kids_delete_school',
                args: { schoolid: schoolId }
            }])[0].then(function(response) {
                if (response.success) {
                    notification.addNotification({
                        message: 'School deleted successfully!',
                        type: 'success'
                    });
                    this.refreshSchoolData();
                } else {
                    notification.addNotification({
                        message: response.message || 'Failed to delete school',
                        type: 'error'
                    });
                }
            }.bind(this));
        },

        filterSchools: function(searchTerm) {
            $('.schools-list tbody tr').each(function() {
                var schoolName = $(this).find('.school-name').text().toLowerCase();
                var schoolDescription = $(this).find('.school-description').text().toLowerCase();
                var search = searchTerm.toLowerCase();
                
                if (schoolName.includes(search) || schoolDescription.includes(search)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        filterSchoolsByStatus: function(status) {
            $('.schools-list tbody tr').each(function() {
                var schoolStatus = $(this).find('.badge').text().toLowerCase();
                
                if (status === 'all' || schoolStatus.includes(status)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        loadSchoolData: function() {
            // This would typically load additional school data via AJAX
            console.log('Loading school data...');
        },

        refreshSchoolData: function() {
            window.location.reload();
        },

        exportSchoolsData: function() {
            notification.addNotification({
                message: 'Export functionality will be implemented here',
                type: 'info'
            });
        }
    };
});

