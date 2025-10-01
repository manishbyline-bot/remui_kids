<?php
/**
 * Trainee Competency Page - Display competencies and progress
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_competency.php');
$PAGE->set_title('My Competencies - Riyada Trainings');
$PAGE->set_heading('My Competencies');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Check if competency framework exists
$has_competency_enabled = get_config('core_competency', 'enabled');

// Get user competencies
$user_competencies = array();
$total_competencies = 0;
$proficient_competencies = 0;
$in_progress_competencies = 0;
$not_started_competencies = 0;
$average_rating = 0;
$total_rating = 0;
$rated_count = 0;

// Get competencies by framework
$frameworks = array();
$competencies_by_framework = array();

// Check if competency tables exist
try {
    if ($DB->get_manager()->table_exists('competency_framework')) {
        $frameworks = $DB->get_records('competency_framework', array('visible' => 1), 'sortorder ASC');
    }
} catch (Exception $e) {
    // Competency tables don't exist
    error_log('Competency framework table error: ' . $e->getMessage());
}

foreach ($frameworks as $framework) {
    $framework_competencies = array();
    
    try {
        // Get all competencies in this framework
        $competencies = $DB->get_records_sql("
            SELECT c.*
            FROM {competency} c
            WHERE c.competencyframeworkid = ?
            ORDER BY c.sortorder ASC
        ", array($framework->id));
        
        foreach ($competencies as $competency) {
            // Get user competency data
            $user_comp = $DB->get_record('competency_usercomp', array(
                'userid' => $userid,
                'competencyid' => $competency->id
            ));
            
            $is_proficient = false;
        $proficiency_value = 0;
        $grade = null;
        $rating_name = 'Not Rated';
        $progress_percentage = 0;
        
        if ($user_comp) {
            $proficiency_value = $user_comp->proficiency ?? 0;
            $grade = $user_comp->grade ?? null;
            
            // Get scale value for display
            if ($competency->scaleid && $grade !== null) {
                $scale = $DB->get_record('scale', array('id' => $competency->scaleid));
                if ($scale) {
                    $scale_items = explode(',', $scale->scale);
                    if (isset($scale_items[$grade - 1])) {
                        $rating_name = trim($scale_items[$grade - 1]);
                    }
                    
                    // Calculate progress percentage
                    $max_scale = count($scale_items);
                    if ($max_scale > 0) {
                        $progress_percentage = round(($grade / $max_scale) * 100);
                    }
                }
                
                $total_rating += $grade;
                $rated_count++;
            }
            
            // Check if proficient
            if ($user_comp->proficiency == 1) {
                $is_proficient = true;
                $proficient_competencies++;
            } elseif ($grade > 0) {
                $in_progress_competencies++;
            } else {
                $not_started_competencies++;
            }
        } else {
            $not_started_competencies++;
        }
        
        $total_competencies++;
        
        // Get related courses
        $related_courses = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.fullname, c.shortname
            FROM {competency_coursecomp} cc
            JOIN {course} c ON cc.courseid = c.id
            WHERE cc.competencyid = ?
            ORDER BY c.fullname ASC
            LIMIT 3
        ", array($competency->id));
        
        $courses_list = array();
        foreach ($related_courses as $course) {
            $courses_list[] = array(
                'id' => $course->id,
                'name' => $course->fullname
            );
        }
        
        $framework_competencies[] = array(
            'id' => $competency->id,
            'name' => $competency->shortname,
            'description' => strip_tags($competency->description),
            'is_proficient' => $is_proficient,
            'rating_name' => $rating_name,
            'rating_value' => $grade ?? 0,
            'progress_percentage' => $progress_percentage,
            'has_rating' => $grade !== null,
            'is_in_progress' => !$is_proficient && $grade > 0,
            'is_not_started' => $grade === null || $grade == 0,
            'related_courses' => $courses_list,
            'has_courses' => count($courses_list) > 0
        );
        }
        
        if (count($framework_competencies) > 0) {
            $competencies_by_framework[] = array(
                'framework_name' => $framework->shortname,
                'framework_description' => strip_tags($framework->description),
                'competencies' => $framework_competencies
            );
        }
    } catch (Exception $e) {
        error_log('Error processing framework ' . $framework->shortname . ': ' . $e->getMessage());
        continue;
    }
}

// Calculate average rating
if ($rated_count > 0) {
    $average_rating = round($total_rating / $rated_count, 1);
}

// Calculate overall proficiency percentage
$proficiency_percentage = $total_competencies > 0 ? 
    round(($proficient_competencies / $total_competencies) * 100) : 0;

// Prepare radar chart data (for visualization)
$chart_data = array();
$chart_labels = array();

// Get top competencies for radar chart (limit to 8 for visibility)
try {
    if ($DB->get_manager()->table_exists('competency_usercomp')) {
        $sql = "SELECT c.id, c.shortname, uc.grade, s.scale
                FROM {competency_usercomp} uc
                JOIN {competency} c ON uc.competencyid = c.id
                LEFT JOIN {scale} s ON c.scaleid = s.id
                WHERE uc.userid = ? AND uc.grade IS NOT NULL
                ORDER BY uc.timemodified DESC
                LIMIT 8";

        $top_competencies = $DB->get_records_sql($sql, array($userid));

        foreach ($top_competencies as $comp) {
            $chart_labels[] = substr($comp->shortname, 0, 20); // Truncate for display
            
            if ($comp->scale) {
                $scale_items = explode(',', $comp->scale);
                $max_value = count($scale_items);
                $normalized_value = $max_value > 0 ? round(($comp->grade / $max_value) * 100) : 0;
                $chart_data[] = $normalized_value;
            } else {
                $chart_data[] = $comp->grade * 20; // Scale to 100
            }
        }
    }
} catch (Exception $e) {
    error_log('Error fetching chart data: ' . $e->getMessage());
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'total_competencies' => $total_competencies,
    'proficient_competencies' => $proficient_competencies,
    'in_progress_competencies' => $in_progress_competencies,
    'not_started_competencies' => $not_started_competencies,
    'average_rating' => $average_rating,
    'proficiency_percentage' => $proficiency_percentage,
    'has_competencies' => $total_competencies > 0,
    'competencies_by_framework' => $competencies_by_framework,
    'chart_labels' => json_encode($chart_labels),
    'chart_data' => json_encode($chart_data),
    'has_chart_data' => count($chart_data) > 0,
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include competency template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_competency.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Competency template not found.</div>';
}

echo $OUTPUT->footer();

