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
 * Day Pathway Page
 *
 * @package    theme_remui_kids
 * @copyright  2024 WisdmLabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// Require login
require_login();

$courseid = required_param('courseid', PARAM_INT);
$day = required_param('day', PARAM_INT);
$userid = $USER->id;

// Get course information
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Check if user is enrolled in course
if (!is_enrolled($context, $userid)) {
    throw new moodle_exception('notenrolled', 'error');
}

// Get course sections (days)
try {
    $sections = $DB->get_records('course_sections', array('course' => $courseid), 'section');
} catch (Exception $e) {
    $sections = array();
    error_log("Day Pathway Sections Error: " . $e->getMessage());
}

// Get the specific day section
$day_section = null;
$section_number = $day; // Assuming day corresponds to section number

foreach ($sections as $section) {
    if ($section->section == $section_number) {
        $day_section = $section;
        break;
    }
}

// Get activities for this day
$pathway_items = array();
$total_activities = 0;
$completed_activities = 0;

try {
    $sql = "SELECT cm.id, cm.section, cm.module, cm.instance, cm.completion, cm.completionview, cm.completionexpected,
                   m.name as modname, m.plugin
            FROM {course_modules} cm
            JOIN {modules} m ON cm.module = m.id
            WHERE cm.course = ? AND cm.section = ? AND cm.visible = 1
            ORDER BY cm.id";

    $modules = $DB->get_records_sql($sql, array($courseid, $section_number));
    
    foreach ($modules as $module) {
        $activity_name = 'Activity';
        $activity_type = 'Activity';
        $activity_description = 'Complete this activity to progress in your learning journey.';
        $activity_icon = 'fas fa-circle';
        
        // Get activity details based on module type
        switch ($module->modname) {
            case 'resource':
                $resource = $DB->get_record('resource', array('id' => $module->instance));
                $activity_name = $resource ? $resource->name : 'Reading Material';
                $activity_type = 'Resource Activity';
                $activity_description = 'Read through the provided material and complete any associated tasks.';
                $activity_icon = 'fas fa-file-alt';
                break;
            case 'quiz':
                $quiz = $DB->get_record('quiz', array('id' => $module->instance));
                $activity_name = $quiz ? $quiz->name : 'Quiz';
                $activity_type = 'Quiz Activity';
                $activity_description = 'Complete the quiz to test your understanding of the material.';
                $activity_icon = 'fas fa-question-circle';
                break;
            case 'assign':
                $assign = $DB->get_record('assign', array('id' => $module->instance));
                $activity_name = $assign ? $assign->name : 'Assignment';
                $activity_type = 'Assignment';
                $activity_description = 'Complete the assignment following the provided guidelines.';
                $activity_icon = 'fas fa-tasks';
                break;
            case 'forum':
                $forum = $DB->get_record('forum', array('id' => $module->instance));
                $activity_name = $forum ? $forum->name : 'Discussion';
                $activity_type = 'Discussion Forum';
                $activity_description = 'Participate in the discussion forum to share ideas and learn from others.';
                $activity_icon = 'fas fa-comments';
                break;
            case 'lesson':
                $lesson = $DB->get_record('lesson', array('id' => $module->instance));
                $activity_name = $lesson ? $lesson->name : 'Lesson';
                $activity_type = 'Interactive Lesson';
                $activity_description = 'Work through the interactive lesson at your own pace.';
                $activity_icon = 'fas fa-play-circle';
                break;
            case 'scorm':
                $scorm = $DB->get_record('scorm', array('id' => $module->instance));
                $activity_name = $scorm ? $scorm->name : 'SCORM Package';
                $activity_type = 'SCORM Package';
                $activity_description = 'Complete the SCORM learning package to master the content.';
                $activity_icon = 'fas fa-cube';
                break;
            default:
                $activity_name = ucfirst($module->modname) . ' Activity';
                $activity_type = ucfirst($module->modname);
                $activity_icon = 'fas fa-circle';
                break;
        }
        
        // Check completion status
        $is_completed = false;
        try {
            if (class_exists('completion_info')) {
                $completion = new completion_info($course);
                if ($completion->is_enabled()) {
                    $completion_records = $completion->get_completions($userid);
                    foreach ($completion_records as $record) {
                        if ($record->coursemoduleid == $module->id && $record->is_complete()) {
                            $is_completed = true;
                            $completed_activities++;
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Keep is_completed as false if completion check fails
        }
        
        $total_activities++;
        
        // Calculate dynamic winding road positioning
        $total_items = $total_activities; // Current total including this item
        
        $pathway_items[] = array(
            'id' => $module->id,
            'name' => $activity_name,
            'type' => $activity_type,
            'description' => $activity_description,
            'icon' => $activity_icon,
            'completed' => $is_completed,
            'order' => $total_activities,
            'position' => $total_activities,
            'road_position_x' => 0, // Will be calculated after all items are collected
            'road_position_y' => 0  // Will be calculated after all items are collected
        );
    }
    
} catch (Exception $e) {
    $pathway_items = array();
    error_log("Day Pathway Activities Error: " . $e->getMessage());
}

// If no activities found, create sample data
if (empty($pathway_items)) {
    // Create sample activities with dynamic positioning
    $sample_activities = array(
        array(
            'id' => 1,
            'name' => 'Program Handbook',
            'type' => 'Resource Activity',
            'description' => 'Review the program handbook to understand course objectives and expectations.',
            'icon' => 'fas fa-file-alt',
            'completed' => false,
            'order' => 1,
            'position' => 1,
            'road_position_x' => 0, // Will be calculated dynamically
            'road_position_y' => 0  // Will be calculated dynamically
        ),
        array(
            'id' => 2,
            'name' => 'Course Agenda',
            'type' => 'Resource Activity',
            'description' => 'Familiarize yourself with the course agenda and learning schedule.',
            'icon' => 'fas fa-calendar-alt',
            'completed' => false,
            'order' => 2,
            'position' => 2,
            'road_position_x' => 0, // Will be calculated dynamically
            'road_position_y' => 0  // Will be calculated dynamically
        ),
        array(
            'id' => 3,
            'name' => 'CEFR Self-Rating Sheet',
            'type' => 'Assignment',
            'description' => 'Complete the CEFR self-assessment to evaluate your current skill level.',
            'icon' => 'fas fa-edit',
            'completed' => false,
            'order' => 3,
            'position' => 3,
            'road_position_x' => 0, // Will be calculated dynamically
            'road_position_y' => 0  // Will be calculated dynamically
        ),
        array(
            'id' => 4,
            'name' => 'CEFR File',
            'type' => 'Resource Activity',
            'description' => 'Download and review the CEFR reference materials.',
            'icon' => 'fas fa-file-download',
            'completed' => false,
            'order' => 4,
            'position' => 4,
            'road_position_x' => 0, // Will be calculated dynamically
            'road_position_y' => 0  // Will be calculated dynamically
        ),
        array(
            'id' => 5,
            'name' => 'Assessment Quiz',
            'type' => 'Quiz',
            'description' => 'Test your understanding with a comprehensive assessment quiz.',
            'icon' => 'fas fa-question-circle',
            'completed' => false,
            'order' => 5,
            'position' => 5,
            'road_position_x' => 0, // Will be calculated dynamically
            'road_position_y' => 0  // Will be calculated dynamically
        ),
        array(
            'id' => 6,
            'name' => 'Final Review',
            'type' => 'Review Activity',
            'description' => 'Complete final review and prepare for next learning phase.',
            'icon' => 'fas fa-clipboard-check',
            'completed' => false,
            'order' => 6,
            'position' => 6,
            'road_position_x' => 0, // Will be calculated dynamically
            'road_position_y' => 0  // Will be calculated dynamically
        )
    );
    
    $pathway_items = $sample_activities;
    $total_activities = count($pathway_items);
}

// Calculate dynamic road positioning for all activities
if ($total_activities > 0) {
    for ($i = 0; $i < count($pathway_items); $i++) {
        $item = &$pathway_items[$i];
        $order = $item['order'];
        
        // Calculate X position (spread evenly across the road)
        $item['road_position_x'] = 10 + (($order - 1) / ($total_activities - 1)) * 80;
        
        // Calculate Y position (create winding effect based on total activities)
        if ($total_activities <= 3) {
            // For few activities, create a simple curve
            $y_positions = [85, 50, 25];
        } elseif ($total_activities <= 5) {
            // For medium activities, create moderate curves
            $y_positions = [85, 75, 55, 35, 25];
        } else {
            // For many activities, create a more complex winding path
            $base_y = 85;
            $end_y = 25;
            $amplitude = 20; // How much the road curves up and down
            $frequency = 2; // How many curves along the road
            
            // Create a sine wave pattern for the winding road
            $progress = ($order - 1) / ($total_activities - 1);
            $wave_offset = sin($progress * $frequency * M_PI) * $amplitude;
            $item['road_position_y'] = $base_y - ($progress * ($base_y - $end_y)) + $wave_offset;
            
            continue; // Skip the manual assignment below
        }
        
        // Use predefined positions for smaller numbers
        if (isset($y_positions[$order - 1])) {
            $item['road_position_y'] = $y_positions[$order - 1];
        }
    }
}

// Calculate day progress
$day_progress = $total_activities > 0 ? round(($completed_activities / $total_activities) * 100) : 0;

// Calculate professional stats
$completed_activities = 0;
foreach ($pathway_items as $item) {
    if ($item['completed']) {
        $completed_activities++;
    }
}
$remaining_activities = $total_activities - $completed_activities;

// Prepare template context
$templatecontext = array(
    'course_name' => $course->fullname,
    'course_shortname' => $course->shortname,
    'day_number' => $day,
    'day_title' => 'Day ' . $day . ' Learning Pathway',
    'day_subtitle' => 'Professional Learning Timeline',
    'day_progress' => $day_progress,
    'completed_activities' => $completed_activities,
    'total_activities' => $total_activities,
    'remaining_activities' => $remaining_activities,
    'pathway_items' => $pathway_items,
    'wwwroot' => $CFG->wwwroot,
    'user_name' => fullname($USER),
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/learning_pathway.php?courseid=' . $courseid
);

// Render template
$template = file_get_contents(__DIR__ . '/templates/day_pathway.mustache');
$mustache = new \core\output\mustache_engine();
echo $mustache->render($template, $templatecontext);
?>
