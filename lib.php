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
    global $CFG, $USER, $DB;
    
    try {
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
        
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $completion = new \completion_info($course);
    } catch (Exception $e) {
        error_log("Course sections data error: " . $e->getMessage());
        return array();
    }
    
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
                    try {
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
                    } catch (Exception $e) {
                        error_log("Completion data error for activity {$cm->id}: " . $e->getMessage());
                    }
                    
                    try {
                        $section_data['activities'][] = [
                            'id' => $cm->id,
                            'name' => $cm->name,
                            'modname' => $cm->modname,
                            'url' => $cm->url,
                            'icon' => $cm->get_icon_url(),
                            'completion' => $completion->is_enabled($cm) ? $completion->get_data($cm, false, $USER->id)->completionstate : null
                        ];
                    } catch (Exception $e) {
                        error_log("Activity data error for {$cm->id}: " . $e->getMessage());
                        // Add basic activity data without completion info
                        $section_data['activities'][] = [
                            'id' => $cm->id,
                            'name' => $cm->name,
                            'modname' => $cm->modname,
                            'url' => $cm->url,
                            'icon' => $cm->get_icon_url(),
                            'completion' => null
                        ];
                    }
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