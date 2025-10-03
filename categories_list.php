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
 * Categories List Page
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

redirect_if_major_upgrade_required();

require_login();

// Check if user is admin - restrict access to admins only
$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if (!$hassiteconfig) {
    // User is not an admin, redirect to dashboard
    redirect(new moodle_url('/my/'), 'Access denied. This page is only available to administrators.', null, \core\output\notification::NOTIFY_ERROR);
}

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$context = context_system::instance();

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/categories_list.php');
$PAGE->add_body_classes(['page-categorieslist', 'fullwidth-layout']);
$PAGE->set_pagelayout('mycourses');
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

// Ensure jQuery is available for any dependencies
$PAGE->requires->jquery();

$PAGE->set_pagetype('categorieslist-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('All Categories - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

// Add custom CSS for categories list
$PAGE->requires->css('/theme/remui_kids/style/categories.css');

// Ensure jQuery is available for any dependencies
$PAGE->requires->jquery();

echo $OUTPUT->header();

if (core_userfeedback::should_display_reminder()) {
    core_userfeedback::print_reminder_block();
}

// Get all categories from database
global $DB;
$categoriesdata = new stdClass();

try {
    // Get all categories with course count
    $categories = $DB->get_records_sql("
        SELECT cc.*, 
               (SELECT COUNT(*) FROM {course} c WHERE c.category = cc.id AND c.id > 1) as course_count,
               (SELECT COUNT(*) FROM {course_categories} child WHERE child.parent = cc.id) as child_count
        FROM {course_categories} cc
        WHERE cc.visible = 1
        ORDER BY cc.name ASC
    ");
    
    $categoriesdata->categories = array_values($categories);
    $categoriesdata->totalcategories = count($categories);
    
} catch (Exception $e) {
    $categoriesdata->categories = [];
    $categoriesdata->totalcategories = 0;
}

// Add wwwroot for links and session key
$categoriesdata->wwwroot = $CFG->wwwroot;
$categoriesdata->sesskey = sesskey();

// Render categories list
echo $OUTPUT->render_from_template('theme_remui_kids/categories_list', $categoriesdata);

echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();

// Trigger event
$eventparams = array('context' => $context);
$event = \core\event\course_category_viewed::create($eventparams);
$event->trigger();
?>
