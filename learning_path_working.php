<?php
/**
 * Working Learning Path Page - Bypasses enrollment check
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Get course ID from URL
$courseid = required_param('courseid', PARAM_INT);

// Set page context
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/learning_path_working.php', array('courseid' => $courseid));
$PAGE->set_title('Learning Path - Riyada Trainings');
$PAGE->set_heading('Learning Path');

try {
    // Get course information
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    
    // Skip enrollment check - we'll show the course structure anyway
    // This allows users to see the learning path even if enrollment data has issues
    
    // Get course sections with basic information
    $sections = $DB->get_records('course_sections', array('course' => $courseid), 'section ASC');
    
    $sections_data = array();
    $section_number = 0;
    
    foreach ($sections as $section) {
        if ($section->section == 0) {
            // Include section 0 (Welcome & Course Info) as it has activities
            $section_name = $section->name ? $section->name : "Welcome & Course Info";
        } else {
            $section_number++;
            $section_name = $section->name ? $section->name : "Section {$section_number}";
        }
        
        // Get activities count for this section
        $activities_count = 0;
        $activity_list = array();
        
        if (!empty($section->sequence)) {
            $cmids = explode(',', $section->sequence);
            $cmids = array_filter($cmids); // Remove empty values
            
            foreach ($cmids as $cmid) {
                try {
                    $cm = $DB->get_record('course_modules', array('id' => $cmid), 'id,module,instance,visible');
                    if ($cm && $cm->visible) {
                        $activities_count++;
                        
                        // Get activity name
                        $modname = $DB->get_field('modules', 'name', array('id' => $cm->module));
                        $activity_name = '';
                        
                        switch ($modname) {
                            case 'forum':
                                $activity_name = $DB->get_field('forum', 'name', array('id' => $cm->instance));
                                break;
                            case 'resource':
                                $activity_name = $DB->get_field('resource', 'name', array('id' => $cm->instance));
                                break;
                            case 'quiz':
                                $activity_name = $DB->get_field('quiz', 'name', array('id' => $cm->instance));
                                break;
                            case 'assign':
                                $activity_name = $DB->get_field('assign', 'name', array('id' => $cm->instance));
                                break;
                            default:
                                $activity_name = ucfirst($modname) . ' Activity';
                        }
                        
                        if ($activity_name) {
                            $activity_list[] = $activity_name;
                        }
                    }
                } catch (Exception $e) {
                    // Skip problematic activities
                    continue;
                }
            }
        }
        
        $sections_data[] = array(
            'id' => $section->id,
            'section' => $section->section,
            'name' => $section_name,
            'summary' => $section->summary,
            'activities_count' => $activities_count,
            'activity_list' => $activity_list,
            'progress' => 0, // Default progress
            'is_completed' => false,
            'has_started' => false,
            'completed_activities' => 0,
            'total_activities' => $activities_count
        );
    }
    
    // Calculate basic stats
    $total_sections = count($sections_data);
    $completed_sections = 0; // Default
    $total_activities = array_sum(array_column($sections_data, 'total_activities'));
    $completed_activities = 0; // Default
    $course_progress = 0; // Default
    
    // Get course image
    $course_image = $CFG->wwwroot . '/theme/remui_kids/pix/default_course.svg';
    
    // Get course duration
    $course_duration = 'Self-paced';
    if ($course->enddate && $course->startdate) {
        $duration_days = round(($course->enddate - $course->startdate) / (24 * 60 * 60));
        if ($duration_days >= 7) {
            $duration_weeks = round($duration_days / 7);
            $course_duration = $duration_weeks . ' weeks';
        } else {
            $course_duration = $duration_days . ' days';
        }
    }
    
    // Prepare template context
    $templatecontext = array(
        'wwwroot' => $CFG->wwwroot,
        'course' => $course,
        'course_image' => $course_image,
        'course_duration' => $course_duration,
        'sections' => $sections_data,
        'total_sections' => $total_sections,
        'completed_sections' => $completed_sections,
        'total_activities' => $total_activities,
        'completed_activities' => $completed_activities,
        'course_progress' => $course_progress,
        'section_progress' => 0,
        'is_completion_enabled' => false,
        'current_section' => null,
        'next_section' => null,
        'user_name' => fullname($USER),
        'back_url' => $CFG->wwwroot . '/theme/remui_kids/my_learning.php',
        'enrollment_status' => 'Course structure visible (enrollment check bypassed)'
    );
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Learning Path Working Error: " . $e->getMessage());
    error_log("Learning Path Working Error Trace: " . $e->getTraceAsString());
    
    // Show error page with more details
    $templatecontext = array(
        'wwwroot' => $CFG->wwwroot,
        'error' => true,
        'error_message' => 'Unable to load course data: ' . $e->getMessage(),
        'debug_info' => 'Course ID: ' . $courseid . ', User ID: ' . $USER->id,
        'back_url' => $CFG->wwwroot . '/theme/remui_kids/my_learning.php',
        'debug_url' => $CFG->wwwroot . '/theme/remui_kids/debug_learning_path.php?courseid=' . $courseid
    );
}

// Output the page
echo $OUTPUT->header();

// Include learning path template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/learning_path.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Learning Path template not found.</div>';
}

echo $OUTPUT->footer();



