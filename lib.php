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
 * RemUI Kids theme functions
 *
 * @package    theme_remui_kids
 * @copyright  2025 Kodeit
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_remui_kids_get_pre_scss($theme) {
    $scss = '';
    // Kids-friendly color overrides
    $scss .= '
        // Override parent theme colors with kids-friendly palette
        $primary: #FF6B35 !default;        // Bright Orange
        $secondary: #4ECDC4 !default;      // Teal
        $success: #96CEB4 !default;        // Soft Green
        $info: #45B7D1 !default;           // Sky Blue
        $warning: #FFEAA7 !default;        // Light Yellow
        $danger: #DDA0DD !default;         // Light Purple
        
        // Using default RemUI fonts (no custom typography overrides)
        
        // Rounded corners for playful look
        $border-radius: 1rem;
        $border-radius-lg: 1.5rem;
        $border-radius-sm: 0.5rem;
    ';
    return $scss;
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_remui_kids_get_extra_scss($theme) {
    $content = '';
    // Add our custom kids-friendly styles
    $content .= file_get_contents($theme->dir . '/scss/post.scss');
    return $content;
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_remui_kids_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    $scss .= file_get_contents($theme->dir . '/scss/preset/default.scss');

    if ($filename && ($filename !== 'default.scss')) {
        $presetfile = $fs->get_file($context->id, 'theme_remui_kids', 'preset', 0, '/', $filename);
        if ($presetfile) {
            $scss .= $presetfile->get_content();
        } else {
            // Safety fallback - maybe the preset is on the file system.
            $filename = $theme->dir . '/scss/preset/' . $filename;
            if (file_exists($filename)) {
                $scss .= file_get_contents($filename);
            }
        }
    }

    // Prepend variables first.
    $scss = theme_remui_kids_get_pre_scss($theme) . $scss;
    return $scss;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_remui_kids_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage')) {
        $theme = theme_config::load('remui_kids');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Get course sections data for professional card display
 *
 * @param object $course The course object
 * @return array Array of section data
 */
function theme_remui_kids_get_course_sections_data($course) {
    global $CFG, $USER;
    
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
    
    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();
    $completion = new \completion_info($course);
    
    $sections_data = [];
    
    foreach ($sections as $section) {
        if ($section->section == 0) {
            // Skip the general section (section 0) as it's usually announcements
            continue;
        }
        
        $section_data = [
            'id' => $section->id,
            'section' => $section->section,
            'name' => get_section_name($course, $section),
            'summary' => $section->summary,
            'visible' => $section->visible,
            'available' => $section->available,
            'uservisible' => $section->uservisible,
            'activities' => [],
            'progress' => 0,
            'total_activities' => 0,
            'completed_activities' => 0,
            'has_started' => false,
            'is_completed' => false
        ];
        
        // Get activities in this section
        if (isset($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if ($cm->uservisible) {
                    $section_data['total_activities']++;
                    
                    // Check completion if enabled
                    if ($completion->is_enabled($cm)) {
                        $completiondata = $completion->get_data($cm, false, $USER->id);
                        if ($completiondata->completionstate == COMPLETION_COMPLETE || 
                            $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                            $section_data['completed_activities']++;
                        }
                        
                        // Check if user has started this activity
                        if ($completiondata->timestarted > 0) {
                            $section_data['has_started'] = true;
                        }
                    }
                    
                    $section_data['activities'][] = [
                        'id' => $cm->id,
                        'name' => $cm->name,
                        'modname' => $cm->modname,
                        'url' => $cm->url,
                        'icon' => $cm->get_icon_url(),
                        'completion' => $completion->is_enabled($cm) ? $completion->get_data($cm, false, $USER->id)->completionstate : null
                    ];
                }
            }
        }
        
        // Calculate progress percentage
        if ($section_data['total_activities'] > 0) {
            $section_data['progress'] = round(($section_data['completed_activities'] / $section_data['total_activities']) * 100);
        }
        
        // Determine if section is completed
        $section_data['is_completed'] = ($section_data['progress'] == 100 && $section_data['total_activities'] > 0);
        
        // Add professional card data
        $section_data['section_image'] = theme_remui_kids_get_section_image($section->section);
        $section_data['url'] = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
        
        $sections_data[] = $section_data;
    }
    
    return $sections_data;
}

/**
 * Get default section image
 *
 * @param int $sectionnum Section number
 * @return string Image URL
 */
function theme_remui_kids_get_section_image($sectionnum) {
    global $CFG;
    
    // Default course section images - you can customize these
    $default_images = [
        1 => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=400&h=200&fit=crop&crop=center',
        2 => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=400&h=200&fit=crop&crop=center',
        3 => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=400&h=200&fit=crop&crop=center',
        4 => 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?w=400&h=200&fit=crop&crop=center',
        5 => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=400&h=200&fit=crop&crop=center',
        6 => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=400&h=200&fit=crop&crop=center',
    ];
    
    $index = (($sectionnum - 1) % 6) + 1;
    return $default_images[$index];
}

/**
 * Get activities for a specific section
 *
 * @param object $course The course object
 * @param int $sectionnum Section number
 * @return array Array of activity data
 */
function theme_remui_kids_get_section_activities($course, $sectionnum) {
    global $CFG, $USER;
    
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
    
    $modinfo = get_fast_modinfo($course);
    $section = $modinfo->get_section_info($sectionnum);
    $completion = new \completion_info($course);
    
    $activities = [];
    
    if (isset($modinfo->sections[$sectionnum])) {
        foreach ($modinfo->sections[$sectionnum] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->uservisible) {
                $activity = [
                    'id' => $cm->id,
                    'name' => $cm->name,
                    'modname' => $cm->modname,
                    'url' => $cm->url,
                    'icon' => $cm->get_icon_url(),
                    'activity_image' => theme_remui_kids_get_activity_image($cm->modname),
                    'description' => $cm->content ?? 'Complete this activity to progress in your learning.',
                    'completion' => null,
                    'is_completed' => false,
                    'has_started' => false,
                    'start_date' => $cm->availablefrom ? date('M d, Y', $cm->availablefrom) : 'Available Now',
                    'end_date' => $cm->availableuntil ? date('M d, Y', $cm->availableuntil) : 'No Deadline'
                ];
                
                // Check completion if enabled
                if ($completion->is_enabled($cm)) {
                    $completiondata = $completion->get_data($cm, false, $USER->id);
                    $activity['completion'] = $completiondata->completionstate;
                    
                    if ($completiondata->completionstate == COMPLETION_COMPLETE || 
                        $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $activity['is_completed'] = true;
                    }
                    
                    if ($completiondata->timestarted > 0) {
                        $activity['has_started'] = true;
                    }
                }
                
                $activities[] = $activity;
            }
        }
    }
    
    return [
        'section' => $section,
        'section_name' => get_section_name($course, $section),
        'section_summary' => $section->summary,
        'activities' => $activities
    ];
}

/**
 * Get default activity image based on activity type
 *
 * @param string $modname Activity module name
 * @return string Image URL
 */
function theme_remui_kids_get_activity_image($modname) {
    $activity_images = [
        'assign' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'quiz' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'page' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'scorm' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'forum' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'url' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'book' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'lesson' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'workshop' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
        'choice' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
    ];
    
    return $activity_images[$modname] ?? $activity_images['page']; // Default to page image
}

/**
 * Get course progress percentage for a user
 *
 * @param int $courseid Course ID
 * @param int $userid User ID
 * @return int Progress percentage
 */
function theme_remui_kids_get_course_progress($courseid, $userid) {
    global $DB;
    
    // Simple progress calculation based on completed activities
    $total_activities = $DB->count_records('course_modules', array('course' => $courseid, 'visible' => 1));
    
    if ($total_activities == 0) {
        return 0;
    }
    
    // Count completed activities (this is a simplified version)
    $completed = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT cm.id) 
         FROM {course_modules} cm 
         JOIN {course_modules_completion} cmc ON cm.id = cmc.coursemoduleid 
         WHERE cm.course = ? AND cmc.userid = ? AND cmc.completionstate > 0",
        array($courseid, $userid)
    );
    
    return round(($completed / $total_activities) * 100);
}

/**
 * Get count of completed courses for a user
 *
 * @param int $userid User ID
 * @return int Number of completed courses
 */
function theme_remui_kids_get_completed_courses_count($userid) {
    global $DB;
    
    return $DB->count_records_sql(
        "SELECT COUNT(DISTINCT c.id) 
         FROM {course} c 
         JOIN {course_completions} cc ON c.id = cc.course 
         WHERE cc.userid = ? AND cc.timecompleted > 0",
        array($userid)
    );
}

/**
 * Get count of in-progress courses for a user
 *
 * @param int $userid User ID
 * @return int Number of in-progress courses
 */
function theme_remui_kids_get_in_progress_courses_count($userid) {
    global $DB;
    
    return $DB->count_records_sql(
        "SELECT COUNT(DISTINCT c.id) 
         FROM {course} c 
         JOIN {course_completions} cc ON c.id = cc.course 
         WHERE cc.userid = ? AND cc.timecompleted IS NULL AND cc.timestarted > 0",
        array($userid)
    );
}

/**
 * Get count of user badges
 *
 * @param int $userid User ID
 * @return int Number of badges
 */
function theme_remui_kids_get_user_badges_count($userid) {
    global $DB;
    
    // Check if badges table exists
    if (!$DB->get_manager()->table_exists('badge_issued')) {
        return 0;
    }
    
    return $DB->count_records('badge_issued', array('userid' => $userid));
}

/**
 * Get overall completion rate across all courses
 *
 * @return float Completion rate percentage
 */
function theme_remui_kids_get_completion_rate() {
    global $DB;
    
    try {
        $total_enrollments = $DB->count_records('user_enrolments');
        if ($total_enrollments == 0) {
            return 0;
        }
        
        $completed = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.id) FROM {course_completions} cc 
             WHERE cc.timecompleted > 0"
        );
        
        return round(($completed / $total_enrollments) * 100, 1);
    } catch (Exception $e) {
        error_log("Completion rate calculation error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get completion trends over time
 *
 * @return array Completion trends data
 */
function theme_remui_kids_get_completion_trends() {
    global $DB;
    
    try {
        $trends = array();
        
        // Get completions for last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $start_time = strtotime("-$i months first day of this month");
            $end_time = strtotime("-$i months last day of this month");
            
            $completions = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {course_completions} 
                 WHERE timecompleted >= ? AND timecompleted <= ?",
                array($start_time, $end_time)
            );
            
            $trends[] = array(
                'month' => date('M Y', $start_time),
                'completions' => $completions
            );
        }
        
        return $trends;
    } catch (Exception $e) {
        error_log("Completion trends error: " . $e->getMessage());
        return array();
    }
}

/**
 * Get top performing courses
 *
 * @return array Top courses data
 */
function theme_remui_kids_get_top_courses() {
    global $DB;
    
    try {
        $top_courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, 
                    COUNT(cc.id) as completions,
                    COUNT(ue.id) as enrollments,
                    ROUND((COUNT(cc.id) / COUNT(ue.id)) * 100, 1) as completion_rate
             FROM {course} c
             LEFT JOIN {user_enrolments} ue ON c.id = ue.enrolid
             LEFT JOIN {course_completions} cc ON c.id = cc.course AND cc.timecompleted > 0
             WHERE c.visible = 1
             GROUP BY c.id, c.fullname, c.shortname
             HAVING COUNT(ue.id) > 0
             ORDER BY completion_rate DESC
             LIMIT 5",
            array()
        );
        
        return array_values($top_courses);
    } catch (Exception $e) {
        error_log("Top courses error: " . $e->getMessage());
        return array();
    }
}

/**
 * Get user engagement metrics
 *
 * @return array Engagement data
 */
function theme_remui_kids_get_engagement_metrics() {
    global $DB;
    
    try {
        $metrics = array();
        
        // Daily active users (last 7 days)
        $metrics['daily_active'] = array();
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $start_time = strtotime($date . ' 00:00:00');
            $end_time = strtotime($date . ' 23:59:59');
            
            $active_users = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT userid) FROM {log} 
                 WHERE timecreated >= ? AND timecreated <= ?",
                array($start_time, $end_time)
            );
            
            $metrics['daily_active'][] = array(
                'date' => date('M j', $start_time),
                'users' => $active_users
            );
        }
        
        // User activity by role
        $metrics['by_role'] = $DB->get_records_sql(
            "SELECT r.shortname as role, COUNT(DISTINCT u.id) as count
             FROM {user} u
             JOIN {role_assignments} ra ON u.id = ra.userid
             JOIN {role} r ON ra.roleid = r.id
             WHERE u.deleted = 0 AND u.lastaccess > (UNIX_TIMESTAMP() - (30 * 24 * 60 * 60))
             GROUP BY r.shortname
             ORDER BY count DESC",
            array()
        );
        
        return $metrics;
    } catch (Exception $e) {
        error_log("Engagement metrics error: " . $e->getMessage());
        return array('daily_active' => array(), 'by_role' => array());
    }
}

/**
 * Get system performance metrics
 *
 * @return array System metrics
 */
function theme_remui_kids_get_system_metrics() {
    global $DB, $CFG;
    
    try {
        $metrics = array();
        
        // Database size
        $db_size = $DB->get_field_sql("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = DATABASE()");
        $metrics['database_size'] = $db_size ? $db_size : 0;
        
        // Total files
        $metrics['total_files'] = $DB->count_records('files');
        
        // Storage usage
        $metrics['storage_used'] = $DB->get_field_sql(
            "SELECT ROUND(SUM(filesize) / 1024 / 1024, 1) FROM {files} WHERE filesize > 0"
        ) ?: 0;
        
        // Recent activity
        $metrics['recent_logins'] = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {user} WHERE lastaccess > (UNIX_TIMESTAMP() - (24 * 60 * 60))"
        );
        
        return $metrics;
    } catch (Exception $e) {
        error_log("System metrics error: " . $e->getMessage());
        return array(
            'database_size' => 0,
            'total_files' => 0,
            'storage_used' => 0,
            'recent_logins' => 0
        );
    }
}