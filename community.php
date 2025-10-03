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
 * Community Page
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

redirect_if_major_upgrade_required();

require_login();

$context = context_system::instance();

// Set up the page
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/community.php');
$PAGE->add_body_classes(['page-community', 'fullwidth-layout']);
$PAGE->set_pagelayout('mycourses');
$PAGE->requires->css('/theme/remui_kids/style/fullwidth.css');

$PAGE->set_pagetype('community-index');
$PAGE->blocks->add_region('content');
$PAGE->set_title('Community - Riyada Trainings');
$PAGE->set_heading(''); // Empty heading - using custom header instead

// Force the add block out of the default area.
$PAGE->theme->addblockposition = BLOCK_ADDBLOCK_POSITION_CUSTOM;

echo $OUTPUT->header();

if (core_userfeedback::should_display_reminder()) {
    core_userfeedback::print_reminder_block();
}

// Get community data
global $DB;
$communitydata = new stdClass();

try {
    // Get total users count
    $communitydata->totalusers = $DB->count_records('user', ['deleted' => 0]);
    
    // Get active users (last 30 days)
    $thirtydaysago = time() - (30 * 24 * 60 * 60);
    $communitydata->activeusers = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {user} WHERE deleted = 0 AND lastaccess > ?", 
        [$thirtydaysago]
    );
    
    // Get total courses
    $communitydata->totalcourses = $DB->count_records('course', ['id' => ['>', 1]]);
    
    // Get total enrollments
    $communitydata->totalenrollments = $DB->count_records('user_enrolments');
    
} catch (Exception $e) {
    $communitydata->totalusers = 0;
    $communitydata->activeusers = 0;
    $communitydata->totalcourses = 0;
    $communitydata->totalenrollments = 0;
}

// Add wwwroot for links
$communitydata->wwwroot = $CFG->wwwroot;

// Render community page
echo $OUTPUT->render_from_template('theme_remui_kids/community', $communitydata);

echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();

// Trigger event
$eventparams = array('context' => $context);
$event = \core\event\course_viewed::create($eventparams);
$event->trigger();
?>
