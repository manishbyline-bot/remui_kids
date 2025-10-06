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
 * Delete Category AJAX Handler
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has admin capabilities
require_login();
$hassiteconfig = has_capability('moodle/site:config', context_system::instance());

if (!$hassiteconfig) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get parameters
$categoryid = required_param('categoryid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

// Verify session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

try {
    // Get category
    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    
    // Check if it's the top level category (cannot delete)
    if ($categoryid == 1) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete the top level category']);
        exit;
    }
    
    // Check if category has courses
    $coursecount = $DB->count_records('course', ['category' => $categoryid]);
    if ($coursecount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category with courses. Please move or delete courses first.']);
        exit;
    }
    
    // Check if category has subcategories
    $subcategorycount = $DB->count_records('course_categories', ['parent' => $categoryid]);
    if ($subcategorycount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category with subcategories. Please move or delete subcategories first.']);
        exit;
    }
    
    // Check if user has permission to delete this category
    $context = context_coursecat::instance($categoryid);
    if (!has_capability('moodle/category:manage', $context)) {
        echo json_encode(['success' => false, 'message' => 'No permission to delete this category']);
        exit;
    }
    
    // Delete the category
    $result = coursecat::get($categoryid)->delete_full(false);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete category']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

