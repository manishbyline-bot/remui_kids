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
 * Check current user role and permissions
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
    echo json_encode(['user_role' => 'guest', 'show_sidebar' => false]);
    exit;
}

global $USER;

// Check user role
$isadmin = has_capability('moodle/site:config', context_system::instance());
$isteacher = has_capability('moodle/course:manageactivities', context_system::instance()) && !$isadmin;
$istrainee = !$isadmin && !$isteacher;

// Determine user role
$user_role = 'trainee'; // default
if ($isadmin) {
    $user_role = 'admin';
} elseif ($isteacher) {
    $user_role = 'teacher';
}

// Return JSON response with role information
echo json_encode([
    'user_role' => $user_role,
    'isadmin' => $isadmin,
    'isteacher' => $isteacher,
    'istrainee' => $istrainee,
    'show_sidebar' => true, // Show sidebar for all logged in users
    'show_user_menu' => true // Show user menu for all logged in users
]);
