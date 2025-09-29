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
 * Schools Management Layout
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $PAGE, $USER;

// Include parent theme's common layout setup
require_once($CFG->dirroot . '/theme/remui/layout/common.php');

// Set up the page context for schools management
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/theme/remui_kids/schools.php');

// Add custom CSS for schools management
$PAGE->requires->css('/theme/remui_kids/style/schools.css');

// Set template context variables
$templatecontext['page_type'] = 'schools_management';
$templatecontext['user_fullname'] = fullname($USER);
$templatecontext['is_admin'] = is_siteadmin();
$templatecontext['current_time'] = date('M d, Y H:i');

// Add breadcrumb navigation
$templatecontext['breadcrumbs'] = array(
    array(
        'name' => 'Dashboard',
        'url' => new moodle_url('/my/')
    ),
    array(
        'name' => 'Schools Management',
        'url' => new moodle_url('/theme/remui_kids/schools.php'),
        'active' => true
    )
);

// Add navigation menu items for schools management
$templatecontext['schools_nav_items'] = array(
    array(
        'name' => 'Overview',
        'url' => new moodle_url('/theme/remui_kids/schools.php'),
        'icon' => 'fas fa-tachometer-alt',
        'active' => true
    ),
    array(
        'name' => 'Create School',
        'url' => new moodle_url('/theme/remui_kids/school_create.php'),
        'icon' => 'fas fa-plus'
    ),
    array(
        'name' => 'Manage Schools',
        'url' => new moodle_url('/theme/remui_kids/school_manage.php'),
        'icon' => 'fas fa-cogs'
    ),
    array(
        'name' => 'Reports',
        'url' => new moodle_url('/theme/remui_kids/school_reports.php'),
        'icon' => 'fas fa-chart-bar'
    )
);

// Get schools statistics for sidebar
try {
    global $DB;
    
    $schools_stats = new stdClass();
    $schools_stats->total_schools = $DB->count_records('course_categories', array('parent' => 0));
    $schools_stats->active_schools = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT c.category) 
         FROM {course} c 
         JOIN {course_categories} cc ON c.category = cc.id 
         WHERE cc.parent = 0 AND c.visible = 1"
    );
    $schools_stats->total_courses = $DB->count_records('course', array('visible' => 1));
    $schools_stats->total_students = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid) 
         FROM {user_enrolments} ue 
         JOIN {enrol} e ON ue.enrolid = e.id 
         JOIN {user} u ON ue.userid = u.id 
         WHERE e.enrol = 'manual' AND u.deleted = 0"
    );
    
    $templatecontext['schools_stats'] = $schools_stats;
    
} catch (Exception $e) {
    $templatecontext['schools_stats'] = new stdClass();
    $templatecontext['schools_stats']->total_schools = 0;
    $templatecontext['schools_stats']->active_schools = 0;
    $templatecontext['schools_stats']->total_courses = 0;
    $templatecontext['schools_stats']->total_students = 0;
}

// Add quick actions for schools management
$templatecontext['quick_actions'] = array(
    array(
        'name' => 'Create New School',
        'url' => new moodle_url('/theme/remui_kids/school_create.php'),
        'icon' => 'fas fa-plus',
        'color' => 'success'
    ),
    array(
        'name' => 'Import Schools',
        'url' => new moodle_url('/theme/remui_kids/school_import.php'),
        'icon' => 'fas fa-upload',
        'color' => 'info'
    ),
    array(
        'name' => 'School Reports',
        'url' => new moodle_url('/theme/remui_kids/school_reports.php'),
        'icon' => 'fas fa-chart-bar',
        'color' => 'warning'
    ),
    array(
        'name' => 'Settings',
        'url' => new moodle_url('/admin/settings.php?section=themesettingremui_kids'),
        'icon' => 'fas fa-cog',
        'color' => 'secondary'
    )
);

// Include parent theme's common end
require_once($CFG->dirroot . '/theme/remui/layout/common_end.php');

// Render the schools template
echo $OUTPUT->render_from_template('theme_remui_kids/schools', $templatecontext);
?>
