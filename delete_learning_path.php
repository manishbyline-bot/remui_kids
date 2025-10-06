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
 * Delete Learning Path AJAX Handler
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$learningpathid = required_param('learningpathid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key.']);
    die;
}

// Check capability
$context = context_system::instance();
if (!has_capability('moodle/site:config', $context)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete learning paths.']);
    die;
}

try {
    global $DB;
    
    // Check if learning paths table exists
    if (!$DB->get_manager()->table_exists('iomad_learningpath')) {
        echo json_encode(['success' => false, 'message' => 'Learning paths table does not exist.']);
        die;
    }
    
    // Check if learning path exists
    if (!$DB->record_exists('iomad_learningpath', ['id' => $learningpathid])) {
        echo json_encode(['success' => false, 'message' => 'Learning path not found.']);
        die;
    }
    
    // Delete learning path courses first
    if ($DB->get_manager()->table_exists('iomad_learningpath_course')) {
        $DB->delete_records('iomad_learningpath_course', ['learningpathid' => $learningpathid]);
    }
    
    // Delete learning path users
    if ($DB->get_manager()->table_exists('iomad_learningpath_user')) {
        $DB->delete_records('iomad_learningpath_user', ['learningpathid' => $learningpathid]);
    }
    
    // Delete the learning path
    $DB->delete_records('iomad_learningpath', ['id' => $learningpathid]);
    
    echo json_encode(['success' => true, 'message' => 'Learning path deleted successfully.']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

