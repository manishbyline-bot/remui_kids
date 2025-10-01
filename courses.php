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
 * My Courses.
 *
 * - each user can currently have their own page (cloned from system and then customised)
 * - only the user can see their own dashboard
 * - users can add any blocks they want
 *
 * @package    core
 * @subpackage my
 * @copyright  2021 Mathew May <mathew.solutions>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

redirect_if_major_upgrade_required();

require_login();

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Get the My Moodle page info.  Should always return something unless the database is broken.
if (!$currentpage = my_get_page(null, MY_PAGE_PUBLIC, MY_PAGE_COURSES)) {
    throw new Exception('mymoodlesetup');
}

// Start setting up the page.
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/courses.php');
$PAGE->add_body_classes(['page-mycourses', 'fullwidth-layout']);
$PAGE->set_pagelayout('mycourses');
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

$PAGE->set_pagetype('my-index');
$PAGE->blocks->add_region('content');
$PAGE->set_subpage($currentpage->id);
$PAGE->set_title(get_string('mycourses'));
$PAGE->set_heading(get_string('mycourses'));

// No blocks can be edited on this page (including by managers/admins) because:
// - Course overview is a fixed item on the page and cannot be moved/removed.
// - We do not want new blocks on the page.
// - Only global blocks (if any) should be visible on the site panel, and cannot be moved int othe centre pane.
$PAGE->force_lock_all_blocks();

// Force the add block out of the default area.
$PAGE->theme->addblockposition  = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Add course management if the user has the capabilities for it.
$coursecat = core_course_category::user_top();
$coursemanagemenu = [];
// Only display the action menu if the user has courses (otherwise, the buttons will be displayed in the zero state).
if (count(enrol_get_all_users_courses($USER->id, true)) > 0) {
    if ($coursecat && ($category = core_course_category::get_nearest_editable_subcategory($coursecat, ['create']))) {
        // The user has the capability to create course.
        $coursemanagemenu['newcourseurl'] = new moodle_url('/course/edit.php', ['category' => $category->id]);
    }
    if ($coursecat && ($category = core_course_category::get_nearest_editable_subcategory($coursecat, ['manage']))) {
        // The user has the capability to manage the course category.
        $coursemanagemenu['manageurl'] = new moodle_url('/course/management.php', ['categoryid' => $category->id]);
    }
    if ($coursecat) {
        $category = core_course_category::get_nearest_editable_subcategory($coursecat, ['moodle/course:request']);
        if ($category && $category->can_request_course()) {
            $coursemanagemenu['courserequesturl'] = new moodle_url('/course/request.php', ['categoryid' => $category->id]);
        }
    }
}
if (!empty($coursemanagemenu)) {
    // Render the course management menu.
    $PAGE->add_header_action($OUTPUT->render_from_template('my/dropdown', $coursemanagemenu));
}

echo $OUTPUT->header();

// Add courses JavaScript
$PAGE->requires->js('/theme/remui_kids/js/courses.js');

if (core_userfeedback::should_display_reminder()) {
    core_userfeedback::print_reminder_block();
}

// Add dashboard cards data
global $DB;
$dashboarddata = new stdClass();

// Get total courses count using COUNT(fullname) query (matching the database image)
$dashboarddata->totalcourses = $DB->count_records('course');

// Get categories count - using the same logic as COUNT(*) FROM mdl_course_categories
$dashboarddata->categories = $DB->count_records('course_categories');

// Get total enrollments count
$dashboarddata->totalenrollments = $DB->count_records('user_enrolments');

// Get learning paths count from iomad_learningpath table if it exists
try {
    if ($DB->get_manager()->table_exists('iomad_learningpath')) {
        $dashboarddata->learningpaths = $DB->count_records('iomad_learningpath');
    } else {
        // Fallback: count from local_iomad_learningpath if the table exists
        if ($DB->get_manager()->table_exists('local_iomad_learningpath')) {
            $dashboarddata->learningpaths = $DB->count_records('local_iomad_learningpath');
        } else {
            // If no learning path table exists, set to 0
            $dashboarddata->learningpaths = 0;
        }
    }
} catch (Exception $e) {
    // If there's any error, set to 0
    $dashboarddata->learningpaths = 0;
}

// Add wwwroot for links
$dashboarddata->wwwroot = $CFG->wwwroot;

// Render dashboard cards
echo $OUTPUT->render_from_template('my/dashboard_cards', $dashboarddata);

echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();

// Trigger dashboard has been viewed event.
$eventparams = array('context' => $context);
$event = \core\event\mycourses_viewed::create($eventparams);
$event->trigger();
