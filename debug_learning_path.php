<?php
/**
 * Debug Learning Path - Test database queries and course data
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

echo "<h1>Debug Learning Path - Course ID: $courseid</h1>";

echo "<h2>1. User Information</h2>";
echo "User ID: " . $USER->id . "<br>";
echo "User Name: " . fullname($USER) . "<br>";

echo "<h2>2. Database Connection Test</h2>";
try {
    $test_query = $DB->get_records_sql("SELECT 1 as test");
    echo "✅ Database connection: OK<br>";
} catch (Exception $e) {
    echo "❌ Database connection: ERROR - " . $e->getMessage() . "<br>";
}

echo "<h2>3. Course Information</h2>";
try {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    echo "✅ Course found: " . $course->fullname . "<br>";
    echo "Course shortname: " . $course->shortname . "<br>";
    echo "Course visible: " . ($course->visible ? 'Yes' : 'No') . "<br>";
} catch (Exception $e) {
    echo "❌ Course not found: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>4. User Enrollment Check</h2>";
try {
    $enrolment = $DB->get_record_sql("
        SELECT e.*, ue.timeenrolled, ue.timestarted, ue.timecompleted
        FROM {enrol} e
        JOIN {user_enrolments} ue ON e.id = ue.enrolid
        WHERE e.courseid = ? AND ue.userid = ? AND e.status = ?
    ", array($courseid, $USER->id, 0));
    
    if ($enrolment) {
        echo "✅ User is enrolled in course<br>";
        echo "Enrollment method: " . $enrolment->enrol . "<br>";
        echo "Time enrolled: " . date('Y-m-d H:i:s', $enrolment->timeenrolled) . "<br>";
    } else {
        echo "❌ User is NOT enrolled in course<br>";
    }
} catch (Exception $e) {
    echo "❌ Enrollment check error: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Course Sections</h2>";
try {
    $sections = $DB->get_records('course_sections', array('course' => $courseid), 'section ASC');
    echo "✅ Found " . count($sections) . " course sections<br>";
    
    foreach ($sections as $section) {
        echo "Section {$section->section}: " . ($section->name ?: "Unnamed") . " (Activities: " . (strlen($section->sequence) ? count(explode(',', $section->sequence)) : 0) . ")<br>";
    }
} catch (Exception $e) {
    echo "❌ Sections error: " . $e->getMessage() . "<br>";
}

echo "<h2>6. Context Check</h2>";
try {
    $context = context_course::instance($courseid);
    echo "✅ Course context created successfully<br>";
    echo "Context ID: " . $context->id . "<br>";
} catch (Exception $e) {
    echo "❌ Context error: " . $e->getMessage() . "<br>";
}

echo "<h2>7. Template File Check</h2>";
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/learning_path.mustache';
if (file_exists($template_file)) {
    echo "✅ Learning path template exists<br>";
} else {
    echo "❌ Learning path template missing<br>";
}

echo "<h2>8. CSS File Check</h2>";
$css_file = $CFG->dirroot . '/theme/remui_kids/style/learning_path.css';
if (file_exists($css_file)) {
    echo "✅ Learning path CSS exists<br>";
} else {
    echo "❌ Learning path CSS missing<br>";
}

echo "<h2>9. Test Simple Learning Path</h2>";
try {
    // Test the simple learning path logic
    $sections = $DB->get_records('course_sections', array('course' => $courseid), 'section ASC');
    
    $sections_data = array();
    $section_number = 0;
    
    foreach ($sections as $section) {
        if ($section->section == 0) {
            continue; // Skip general section
        }
        
        $section_number++;
        
        // Get section name
        $section_name = $section->name ? $section->name : "Section {$section_number}";
        
        // Get activities count for this section
        $activities_count = 0;
        if (!empty($section->sequence)) {
            $cmids = explode(',', $section->sequence);
            $activities_count = count(array_filter($cmids));
        }
        
        $sections_data[] = array(
            'id' => $section->id,
            'section' => $section->section,
            'name' => $section_name,
            'summary' => $section->summary,
            'activities_count' => $activities_count,
            'progress' => 0,
            'is_completed' => false,
            'has_started' => false,
            'completed_activities' => 0,
            'total_activities' => $activities_count
        );
    }
    
    echo "✅ Simple learning path data created successfully<br>";
    echo "Sections processed: " . count($sections_data) . "<br>";
    
    foreach ($sections_data as $section) {
        echo "- {$section['name']} (ID: {$section['id']}, Activities: {$section['total_activities']})<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Simple learning path error: " . $e->getMessage() . "<br>";
}

echo "<h2>10. All Tests Complete</h2>";
echo "<a href='learning_path_simple.php?courseid=$courseid'>Try Learning Path Again</a><br>";
echo "<a href='my_learning.php'>Back to My Learning</a>";




