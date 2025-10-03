<?php
/**
 * My Pathway Page - Enhanced Learning Dashboard
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/my_pathway.php');
$PAGE->set_title('My Pathway - Riyada Trainings');
$PAGE->set_heading('My Pathway');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get enrolled courses with detailed information
$enrolled_courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname, c.summary, c.startdate, c.enddate,
           c.timecreated, c.timemodified, c.category, c.format,
           cc.timeenrolled, cc.timestarted, cc.timecompleted,
           CASE WHEN cc.timecompleted IS NOT NULL THEN 'completed'
                WHEN cc.timestarted IS NOT NULL THEN 'in_progress'
                ELSE 'not_started' END as status,
           cat.name as categoryname
    FROM {enrol} e
    JOIN {user_enrolments} ue ON e.id = ue.enrolid
    JOIN {course} c ON e.courseid = c.id
    LEFT JOIN {course_completions} cc ON c.id = cc.course AND cc.userid = ?
    LEFT JOIN {course_categories} cat ON c.category = cat.id
    WHERE ue.userid = ? AND e.status = ? AND c.visible = ?
    ORDER BY cc.timeenrolled DESC, c.fullname ASC
", array($userid, $userid, 0, 1));

// Set current pathway (default to first enrolled course)
$current_pathway = 'No Courses Enrolled'; // Default pathway name
if (!empty($enrolled_courses)) {
    $first_course = reset($enrolled_courses);
    $current_pathway = $first_course->fullname;
}

// Calculate pathway statistics
$total_courses = count($enrolled_courses);
$completed_courses = count(array_filter($enrolled_courses, function($course) {
    return $course->status === 'completed';
}));
$in_progress_courses = count(array_filter($enrolled_courses, function($course) {
    return $course->status === 'in_progress';
}));

// Calculate overall progress
$total_progress = 0;
foreach ($enrolled_courses as $course) {
    if ($course->timecompleted) {
        $total_progress += 100;
    } elseif ($course->timestarted) {
        $time_elapsed = time() - $course->timestarted;
        $time_weeks = $time_elapsed / (7 * 24 * 60 * 60);
        $course_progress = min(95, round(($time_weeks / 52) * 100));
        $total_progress += $course_progress;
    }
}
$pathway_progress = $total_courses > 0 ? round($total_progress / $total_courses) : 0;

// Calculate real course metrics based on selected course
$selected_course = null;
if (!empty($enrolled_courses)) {
    $selected_course = reset($enrolled_courses); // Get first course as default
}

// Initialize default values
$cefr_level = 'A2';
$hours_completed = 0;
$events_completed = 0;
$total_events = 0;
$next_event = null;

if ($selected_course) {
    // Get course completion data
    try {
        $completion = new completion_info($selected_course);
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
    } catch (Exception $e) {
        error_log("Error getting completion data: " . $e->getMessage());
    }
    
    // Calculate hours completed (estimate based on course progress)
    if ($selected_course->timecompleted) {
        // Course completed - estimate total hours
        $hours_completed = 120; // Default total hours
    } elseif ($selected_course->timestarted) {
        // Course in progress - estimate based on time elapsed
        $time_elapsed = time() - $selected_course->timestarted;
        $time_weeks = $time_elapsed / (7 * 24 * 60 * 60);
        $estimated_progress = min(95, ($time_weeks / 52) * 100); // Assume 52 week course
        $hours_completed = round((120 * $estimated_progress) / 100);
    }
    
    // Get CEFR level based on course category or progress
    if ($selected_course->categoryname) {
        if (stripos($selected_course->categoryname, 'advanced') !== false) {
            $cefr_level = 'C1';
        } elseif (stripos($selected_course->categoryname, 'intermediate') !== false) {
            $cefr_level = 'B2';
        } elseif (stripos($selected_course->categoryname, 'beginner') !== false) {
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
}

// Get recent achievements
$recent_achievements = array(
    array(
        'name' => 'Grammar Fundamentals Completed',
        'date' => '2 days ago',
        'points' => 50
    )
);

// Get upcoming events (mock data)
$upcoming_events = array();

// Get real pathway events/timeline from selected course
$pathway_events = array();
$monthly_sections = array();
$daily_sections = array();

if ($selected_course) {
    try {
        // Get course modules/activities
        $modinfo = get_fast_modinfo($selected_course);
        $sections = $modinfo->get_section_info_all();
        
        $monthly_data = array();
        $daily_data = array();
        
        foreach ($sections as $section) {
            if ($section->section == 0) continue; // Skip general section
            
            // Calculate dates
            $section_week = $section->section;
            $start_date = $selected_course->startdate ? $selected_course->startdate : time();
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
                        $activity_date = '';
                        $activity_time = '';
                        $activity_duration = '8 hours'; // Full day duration (9 AM - 5 PM)
                        
                        // Try to get actual dates from course modules
                        if (isset($cm->availability)) {
                            // Parse availability for dates if available
                            $activity_date = date('M d, Y', $event_date);
                        } else {
                            $activity_date = date('M d, Y', $event_date);
                        }
                        
                        // Calculate time based on section and activity order
                        $activity_hour = 9; // Start at 9 AM
                        $activity_time = sprintf('%02d:00 - %02d:00', $activity_hour, $activity_hour + 8); // 9 AM - 5 PM
                        
                        // Use full day duration for all activities
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
                            'url' => $cm->url ? $cm->url->out(false) : '#'
                        );
                    }
                }
            }
            
            // Group by month
            if (!isset($monthly_data[$month_key])) {
                $monthly_data[$month_key] = array(
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
            
            $monthly_data[$month_key]['sections'][] = array(
                'section_number' => $section_week,
                'day_name' => $day_name,
                'section_name' => $section_name,
                'date' => date('M d, Y', $event_date),
                'activities' => $section_activities,
                'type' => $first_activity['type'],
                'type_color' => $first_activity['type_color'],
                'icon' => $first_activity['icon'],
                'completed' => !empty($section_activities) && $section_activities[0]['completed']
            );
            
            // Set total duration to 8 hours for each day
            $total_duration = 8;
            
            // Group by day
            $daily_data[$day_key] = array(
                'day_name' => $day_name,
                'section_name' => $section_name,
                'date' => date('M d, Y', $event_date),
                'section_number' => $section_week,
                'activities' => $section_activities,
                'activity_count' => count($section_activities),
                'total_duration' => $total_duration,
                'first_activity_type' => !empty($section_activities) ? $section_activities[0]['type'] : 'Activity',
                'first_activity_icon' => !empty($section_activities) ? $section_activities[0]['icon'] : 'file',
                'first_activity_color' => !empty($section_activities) ? $section_activities[0]['type_color'] : 'blue'
            );
        }
        
        // Convert to arrays for template
        foreach ($monthly_data as $month) {
            $monthly_sections[] = $month;
        }
        
        $day_index = 0;
        foreach ($daily_data as $day) {
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
        error_log("Error getting pathway events: " . $e->getMessage());
    }
}

// Prepare enrolled courses for dropdown
$pathway_options = array();
foreach ($enrolled_courses as $course) {
    $pathway_options[] = array(
        'id' => $course->id,
        'name' => $course->fullname,
        'status' => $course->status,
        'selected' => ($course->fullname === $current_pathway)
    );
}

// Prepare template data
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'current_pathway' => $current_pathway,
    'pathway_progress' => $pathway_progress,
    'cefr_level' => $cefr_level,
    'hours_completed' => $hours_completed,
    'total_hours' => 120,
    'events_completed' => $events_completed,
    'total_events' => $total_events,
    'next_event' => $next_event,
    'has_next_event' => !empty($next_event),
    'recent_achievements' => $recent_achievements,
    'upcoming_events' => $upcoming_events,
    'pathway_events' => $pathway_events,
    'monthly_sections' => $monthly_sections,
    'daily_sections' => $daily_sections,
    'pathway_options' => $pathway_options,
    'has_events' => count($pathway_events) > 0,
    'has_monthly_sections' => count($monthly_sections) > 0,
    'has_daily_sections' => count($daily_sections) > 0,
    'has_achievements' => count($recent_achievements) > 0,
    'has_upcoming' => count($upcoming_events) > 0,
    'has_pathways' => count($pathway_options) > 0
);

// Output the page
echo $OUTPUT->header();

// Include pathway template directly
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/my_pathway.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">My Pathway template not found.</div>';
}

echo $OUTPUT->footer();
