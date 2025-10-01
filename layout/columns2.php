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
 * Custom layout for all pages with admin restrictions
 *
 * @package    theme_remui_kids
 * @copyright  2024 Riyada Trainings
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include common layout elements - sets up $templatecontext
require_once(__DIR__ . '/common.php');

// Include common_end - finalizes $templatecontext
require_once(__DIR__ . '/common_end.php');

// Add custom sidebar data to template context
$templatecontext['custom_sidebar'] = true;
$templatecontext['sidebar_data'] = [
    'wwwroot' => $CFG->wwwroot,
    'sitename' => $SITE->shortname
];

// Render the parent theme's columns2 template (which includes topbar)
// Then add our custom sidebar on top
echo $OUTPUT->render_from_template('theme_remui/columns2', $templatecontext);

// Add our custom sidebar overlay
echo $OUTPUT->render_from_template('theme_remui_kids/riyada_sidebar', [
    'wwwroot' => $CFG->wwwroot,
    'sitename' => $SITE->shortname,
    'config' => ['wwwroot' => $CFG->wwwroot]
]);