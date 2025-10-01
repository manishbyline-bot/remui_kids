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

// Include common layout elements - sets up $templatecontext with all navbar, user menu, etc.
require_once(__DIR__ . '/common.php');

// Include common_end - finalizes $templatecontext with body attributes, blocks, etc.
require_once(__DIR__ . '/common_end.php');

// Add custom sidebar data to template context
$templatecontext['config'] = [
    'wwwroot' => $CFG->wwwroot
];
$templatecontext['sitename'] = $SITE->shortname;

// Render our custom columns2 template (which extends parent and adds Riyada sidebar)
echo $OUTPUT->render_from_template('theme_remui_kids/columns2', $templatecontext);