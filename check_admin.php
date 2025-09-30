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
 * Check if current user is admin
 *
 * @package    theme_remui_kids
 * @copyright  2024 Riyada Trainings
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isloggedin()) {
    echo json_encode(['isadmin' => false]);
    exit;
}

// Check if user has admin capabilities
$isadmin = has_capability('moodle/site:config', context_system::instance());

// Return JSON response with admin status and user menu visibility
echo json_encode([
    'isadmin' => $isadmin,
    'show_user_menu' => $isadmin, // Only show user menu for admins
    'show_sidebar' => $isadmin    // Only show sidebar for admins
]);
