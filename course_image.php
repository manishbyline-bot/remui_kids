<?php
/**
 * Course Image Handler - Serve course images
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Get course ID
$courseid = required_param('courseid', PARAM_INT);

// Get file storage
$fs = get_file_storage();

try {
    $context = context_course::instance($courseid);
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'timemodified DESC', false);
    
    if (!empty($files)) {
        $file = reset($files);
        
        // Send the file
        send_stored_file($file, 86400, 0, false);
        exit;
    }
} catch (Exception $e) {
    // If error or no file, redirect to default
}

// If no file found, redirect to default image
header('Location: ' . $CFG->wwwroot . '/theme/remui_kids/pix/default_course.svg');
exit;

