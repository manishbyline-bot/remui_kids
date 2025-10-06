<?php
/**
 * Get Course Metrics AJAX Endpoint
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Set JSON header
header('Content-Type: application/json');

// Get course ID from request
$courseid = optional_param('courseid', 0, PARAM_INT);

if (!$courseid) {
    echo json_encode(array('error' => 'Course ID is required'));
    exit;
}

try {
    // Get course information
    $course = $DB->get_record('course', array('id' => $courseid));
    if (!$course) {
        echo json_encode(array('error' => 'Course not found'));
        exit;
    }
    
    $userid = $USER->id;
    
    // Initialize default values
    $cefr_level = 'A2';
    $hours_completed = 0;
    $events_completed = 0;
    $total_events = 0;
    $next_event = null;
    
    // Get course completion data
    $completion = new completion_info($course);
    if ($completion->is_enabled()) {
        $completion_data = $completion->get_completions($userid);
        
        // Calculate events completed
        $total_events = count($completion_data);
        $events_completed = 0;
        
        foreach ($completion_data as $completion_item) {
            if ($completion_item->is_complete()) {
                $events_completed++;
            }
        }
    }
    
    // Calculate hours completed (estimate based on course progress)
    $course_completion = $DB->get_record('course_completions', array('course' => $courseid, 'userid' => $userid));
    if ($course_completion && $course_completion->timecompleted) {
        // Course completed - estimate total hours
        $hours_completed = 120; // Default total hours
    } elseif ($course_completion && $course_completion->timestarted) {
        // Course in progress - estimate based on time elapsed
        $time_elapsed = time() - $course_completion->timestarted;
        $time_weeks = $time_elapsed / (7 * 24 * 60 * 60);
        $estimated_progress = min(95, ($time_weeks / 52) * 100); // Assume 52 week course
        $hours_completed = round((120 * $estimated_progress) / 100);
    }
    
    // Get CEFR level based on course category or progress
    $category = $DB->get_record('course_categories', array('id' => $course->category));
    if ($category) {
        if (stripos($category->name, 'advanced') !== false) {
            $cefr_level = 'C1';
        } elseif (stripos($category->name, 'intermediate') !== false) {
            $cefr_level = 'B2';
        } elseif (stripos($category->name, 'beginner') !== false) {
            $cefr_level = 'A1';
        }
    }
    
    // Determine CEFR level based on progress
    if ($events_completed > 0 && $total_events > 0) {
        $progress_percentage = ($events_completed / $total_events) * 100;
        if ($progress_percentage >= 80) {
            $cefr_level = 'C1';
        } elseif ($progress_percentage >= 60) {
            $cefr_level = 'B2';
        } elseif ($progress_percentage >= 40) {
            $cefr_level = 'B1';
        } elseif ($progress_percentage >= 20) {
            $cefr_level = 'A2';
        } else {
            $cefr_level = 'A1';
        }
    }
    
    // Get next event (mock data for now - in real implementation, get from course modules)
    if ($events_completed < $total_events) {
        $next_event = array(
            'title' => 'Next Learning Module',
            'date' => date('M d, Y', strtotime('+1 week')),
            'type' => 'eLearning'
        );
    }
    
    // Get timeline data for this specific course
    $monthly_sections = array();
    $daily_sections = array();
    
    try {
        $all_monthly_data = array();
        $all_daily_data = array();
        
        // Get course modules/activities
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        
        foreach ($sections as $section) {
            if ($section->section == 0) continue; // Skip general section
            
            // Calculate dates
            $section_week = $section->section;
            $start_date = $course->startdate ? $course->startdate : time();
            $event_date = $start_date + ($section_week * 7 * 24 * 60 * 60); // One week per section
            
            $month_key = date('Y-m', $event_date);
            $day_key = date('Y-m-d', $event_date);
            $month_name = date('F Y', $event_date);
            $day_name = 'Day ' . $section_week;
            
            // Get section name from course sections
            $section_name = 'Section ' . $section_week;
            if (!empty($section->name)) {
                $section_name = $section->name;
            }
            
            // Get activities for this section
            $section_activities = array();
            if (isset($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if ($cm->uservisible) {
                        // Get activity type and icon
                        $activity_type = ucfirst($cm->modname);
                        $type_color = 'blue';
                        $icon = 'file';
                        
                        switch ($cm->modname) {
                            case 'quiz':
                                $activity_type = 'Assessment';
                                $type_color = 'purple';
                                $icon = 'check';
                                break;
                            case 'assign':
                                $activity_type = 'Assignment';
                                $type_color = 'green';
                                $icon = 'edit';
                                break;
                            case 'forum':
                                $activity_type = 'Discussion';
                                $type_color = 'orange';
                                $icon = 'comments';
                                break;
                            case 'workshop':
                                $activity_type = 'Workshop';
                                $type_color = 'blue';
                                $icon = 'calendar';
                                break;
                            case 'lesson':
                                $activity_type = 'Lesson';
                                $type_color = 'indigo';
                                $icon = 'book';
                                break;
                            case 'label':
                                $activity_type = 'Label';
                                $type_color = 'blue';
                                $icon = 'tag';
                                break;
                            case 'resource':
                                $activity_type = 'Resource';
                                $type_color = 'blue';
                                $icon = 'file';
                                break;
                        }
                        
                        // Get completion status
                        $completed = false;
                        if ($completion->is_enabled($cm)) {
                            $completion_data = $completion->get_data($cm, false, $userid);
                            $completed = $completion_data->completionstate > 0;
                        }
                        
                        // Get activity dates and times
                        $activity_date = date('M d, Y', $event_date);
                        $activity_time = '9:00 AM - 5:00 PM';
                        $activity_duration = '8 hours';
                        
                        $section_activities[] = array(
                            'id' => $cm->id,
                            'name' => $cm->name,
                            'type' => $activity_type,
                            'type_color' => $type_color,
                            'icon' => $icon,
                            'completed' => $completed,
                            'duration' => $activity_duration,
                            'date' => $activity_date,
                            'time' => $activity_time,
                            'url' => $cm->url ? $cm->url->out(false) : '#',
                            'course_name' => $course->fullname,
                            'course_id' => $course->id
                        );
                    }
                }
            }
            
            // Group by month with course information
            if (!isset($all_monthly_data[$month_key])) {
                $all_monthly_data[$month_key] = array(
                    'month_name' => $month_name,
                    'month_key' => $month_key,
                    'sections' => array()
                );
            }
            
            // Get first activity for event card display
            $first_activity = !empty($section_activities) ? $section_activities[0] : array(
                'type' => 'Activity',
                'type_color' => 'blue',
                'icon' => 'file',
                'duration' => '2 hours'
            );
            
            $all_monthly_data[$month_key]['sections'][] = array(
                'section_number' => $section_week,
                'day_name' => $day_name,
                'section_name' => $section_name,
                'date' => date('M d, Y', $event_date),
                'activities' => $section_activities,
                'type' => $first_activity['type'],
                'type_color' => $first_activity['type_color'],
                'icon' => $first_activity['icon'],
                'completed' => !empty($section_activities) && $section_activities[0]['completed'],
                'course_name' => $course->fullname,
                'course_id' => $course->id
            );
            
            // Set total duration to 8 hours for each day
            $total_duration = 8;
            
            // Group by day with course information
            $day_key_with_course = $day_key . '_' . $course->id; // Make unique per course
            $all_daily_data[$day_key_with_course] = array(
                'day_name' => $day_name,
                'section_name' => $section_name,
                'date' => date('M d, Y', $event_date),
                'section_number' => $section_week,
                'activities' => $section_activities,
                'activity_count' => count($section_activities),
                'total_duration' => $total_duration,
                'first_activity_type' => !empty($section_activities) ? $section_activities[0]['type'] : 'Activity',
                'first_activity_icon' => !empty($section_activities) ? $section_activities[0]['icon'] : 'file',
                'first_activity_color' => !empty($section_activities) ? $section_activities[0]['type_color'] : 'blue',
                'course_name' => $course->fullname,
                'course_id' => $course->id
            );
        }
        
        // Convert to arrays for template
        foreach ($all_monthly_data as $month) {
            $monthly_sections[] = $month;
        }
        
        $day_index = 0;
        foreach ($all_daily_data as $day) {
            $day['is_even'] = ($day_index % 2 == 0);
            $daily_sections[] = $day;
            $day_index++;
        }
        
        // Sort by date
        usort($monthly_sections, function($a, $b) {
            return strcmp($a['month_key'], $b['month_key']);
        });
        
        usort($daily_sections, function($a, $b) {
            return $a['section_number'] - $b['section_number'];
        });
        
    } catch (Exception $e) {
        error_log("Error getting course timeline: " . $e->getMessage());
    }
    
    // Return JSON response
    echo json_encode(array(
        'success' => true,
        'cefr_level' => $cefr_level,
        'hours_completed' => $hours_completed,
        'total_hours' => 120,
        'events_completed' => $events_completed,
        'total_events' => $total_events,
        'has_next_event' => !empty($next_event),
        'next_event' => $next_event,
        'monthly_sections' => $monthly_sections,
        'daily_sections' => $daily_sections,
        'has_monthly_sections' => count($monthly_sections) > 0,
        'has_daily_sections' => count($daily_sections) > 0
    ));
    
} catch (Exception $e) {
    error_log("Error in get_course_metrics: " . $e->getMessage());
    echo json_encode(array('error' => 'Database error: ' . $e->getMessage()));
}