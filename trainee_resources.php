<?php
/**
 * Trainee Resource Library Page - Browse and download course materials
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_resources.php');
$PAGE->set_title('Resource Library - Riyada Trainings');
$PAGE->set_heading('Resource Library');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get filter parameters
$file_type_filter = optional_param('type', 'all', PARAM_ALPHA);
$course_filter = optional_param('course', 0, PARAM_INT);
$search_query = optional_param('search', '', PARAM_TEXT);

// Get all enrolled courses
$enrolled_courses = enrol_get_users_courses($userid, true, 'id, fullname, shortname');

// Get file storage
$fs = get_file_storage();

// Collect resources
$resources_data = array();
$total_resources = 0;
$documents_count = 0;
$videos_count = 0;
$audio_count = 0;
$images_count = 0;
$archives_count = 0;

// Define file type mappings
$document_types = array('pdf', 'doc', 'docx', 'txt', 'odt', 'rtf', 'ppt', 'pptx', 'xls', 'xlsx');
$video_types = array('mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm');
$audio_types = array('mp3', 'wav', 'ogg', 'wma', 'm4a');
$image_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp');
$archive_types = array('zip', 'rar', '7z', 'tar', 'gz');

foreach ($enrolled_courses as $course) {
    // Skip if course filter is set and doesn't match
    if ($course_filter > 0 && $course->id != $course_filter) {
        continue;
    }
    
    $context = context_course::instance($course->id);
    
    // Get files from course summary (overview)
    $files = $fs->get_area_files($context->id, 'course', 'summary', 0, 'filename', false);
    
    // Get files from resource modules
    $resources = $DB->get_records('resource', array('course' => $course->id));
    
    foreach ($resources as $resource) {
        $cm = get_coursemodule_from_instance('resource', $resource->id);
        if (!$cm || !$cm->visible) {
            continue;
        }
        
        $module_context = context_module::instance($cm->id);
        $resource_files = $fs->get_area_files($module_context->id, 'mod_resource', 'content', 0, 'filename', false);
        
        foreach ($resource_files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            
            $filename = $file->get_filename();
            $filesize = $file->get_filesize();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Apply search filter
            if (!empty($search_query) && stripos($filename, $search_query) === false) {
                continue;
            }
            
            // Determine file type and category
            $file_category = 'other';
            $file_icon = 'file';
            $file_color = 'gray';
            
            if (in_array($extension, $document_types)) {
                $file_category = 'document';
                $file_icon = 'file-pdf';
                $file_color = 'red';
                $documents_count++;
            } elseif (in_array($extension, $video_types)) {
                $file_category = 'video';
                $file_icon = 'file-video';
                $file_color = 'purple';
                $videos_count++;
            } elseif (in_array($extension, $audio_types)) {
                $file_category = 'audio';
                $file_icon = 'file-audio';
                $file_color = 'green';
                $audio_count++;
            } elseif (in_array($extension, $image_types)) {
                $file_category = 'image';
                $file_icon = 'file-image';
                $file_color = 'blue';
                $images_count++;
            } elseif (in_array($extension, $archive_types)) {
                $file_category = 'archive';
                $file_icon = 'file-archive';
                $file_color = 'yellow';
                $archives_count++;
            }
            
            // Apply file type filter
            if ($file_type_filter !== 'all' && $file_category !== $file_type_filter) {
                continue;
            }
            
            // Format file size
            $size_formatted = display_size($filesize);
            
            // Get download URL
            $download_url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                true
            )->out(false);
            
            $total_resources++;
            
            $resources_data[] = array(
                'filename' => $filename,
                'filesize' => $size_formatted,
                'extension' => strtoupper($extension),
                'course_name' => $course->fullname,
                'course_shortname' => $course->shortname,
                'course_id' => $course->id,
                'file_category' => $file_category,
                'file_icon' => $file_icon,
                'file_color' => $file_color,
                'download_url' => $download_url,
                'uploaded_date' => userdate($file->get_timemodified(), '%d %b %Y'),
                'is_document' => ($file_category === 'document'),
                'is_video' => ($file_category === 'video'),
                'is_audio' => ($file_category === 'audio'),
                'is_image' => ($file_category === 'image'),
                'is_archive' => ($file_category === 'archive')
            );
        }
    }
    
    // Also get files from folder modules
    $folders = $DB->get_records('folder', array('course' => $course->id));
    foreach ($folders as $folder) {
        $cm = get_coursemodule_from_instance('folder', $folder->id);
        if (!$cm || !$cm->visible) {
            continue;
        }
        
        $module_context = context_module::instance($cm->id);
        $folder_files = $fs->get_area_files($module_context->id, 'mod_folder', 'content', 0, 'filename', false);
        
        foreach ($folder_files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            
            $filename = $file->get_filename();
            $filesize = $file->get_filesize();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Apply search filter
            if (!empty($search_query) && stripos($filename, $search_query) === false) {
                continue;
            }
            
            // Determine file type and category
            $file_category = 'other';
            $file_icon = 'file';
            $file_color = 'gray';
            
            if (in_array($extension, $document_types)) {
                $file_category = 'document';
                $file_icon = 'file-pdf';
                $file_color = 'red';
                $documents_count++;
            } elseif (in_array($extension, $video_types)) {
                $file_category = 'video';
                $file_icon = 'file-video';
                $file_color = 'purple';
                $videos_count++;
            } elseif (in_array($extension, $audio_types)) {
                $file_category = 'audio';
                $file_icon = 'file-audio';
                $file_color = 'green';
                $audio_count++;
            } elseif (in_array($extension, $image_types)) {
                $file_category = 'image';
                $file_icon = 'file-image';
                $file_color = 'blue';
                $images_count++;
            } elseif (in_array($extension, $archive_types)) {
                $file_category = 'archive';
                $file_icon = 'file-archive';
                $file_color = 'yellow';
                $archives_count++;
            }
            
            // Apply file type filter
            if ($file_type_filter !== 'all' && $file_category !== $file_type_filter) {
                continue;
            }
            
            // Format file size
            $size_formatted = display_size($filesize);
            
            // Get download URL
            $download_url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                true
            )->out(false);
            
            $total_resources++;
            
            $resources_data[] = array(
                'filename' => $filename,
                'filesize' => $size_formatted,
                'extension' => strtoupper($extension),
                'course_name' => $course->fullname,
                'course_shortname' => $course->shortname,
                'course_id' => $course->id,
                'file_category' => $file_category,
                'file_icon' => $file_icon,
                'file_color' => $file_color,
                'download_url' => $download_url,
                'uploaded_date' => userdate($file->get_timemodified(), '%d %b %Y'),
                'is_document' => ($file_category === 'document'),
                'is_video' => ($file_category === 'video'),
                'is_audio' => ($file_category === 'audio'),
                'is_image' => ($file_category === 'image'),
                'is_archive' => ($file_category === 'archive')
            );
        }
    }
}

// Prepare courses list for filter
$courses_list = array();
foreach ($enrolled_courses as $course) {
    $courses_list[] = array(
        'id' => $course->id,
        'name' => $course->fullname,
        'is_selected' => ($course->id == $course_filter)
    );
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'total_resources' => $total_resources,
    'documents_count' => $documents_count,
    'videos_count' => $videos_count,
    'audio_count' => $audio_count,
    'images_count' => $images_count,
    'archives_count' => $archives_count,
    'resources' => $resources_data,
    'has_resources' => count($resources_data) > 0,
    'courses' => $courses_list,
    'has_courses' => count($courses_list) > 0,
    'search_query' => $search_query,
    'is_all_type' => ($file_type_filter === 'all'),
    'is_document_type' => ($file_type_filter === 'document'),
    'is_video_type' => ($file_type_filter === 'video'),
    'is_audio_type' => ($file_type_filter === 'audio'),
    'is_image_type' => ($file_type_filter === 'image'),
    'is_archive_type' => ($file_type_filter === 'archive'),
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include resources template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_resources.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Resource Library template not found.</div>';
}

echo $OUTPUT->footer();

