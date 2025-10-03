<?php
/**
 * Trainee Certifications Page - Display and download earned certificates
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/badges/lib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Set page context
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_certifications.php');
$PAGE->set_title('My Certifications - Riyada Trainings');
$PAGE->set_heading('My Certifications');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid));

// Get all earned badges/certificates
$sql = "SELECT b.id, b.name, b.description, b.issuercontact, b.timecreated,
               bi.uniquehash, bi.dateissued, bi.dateexpire,
               c.id as courseid, c.fullname as coursename
        FROM {badge} b
        JOIN {badge_issued} bi ON b.id = bi.badgeid
        LEFT JOIN {course} c ON b.courseid = c.id
        WHERE bi.userid = ? AND b.status = ?
        ORDER BY bi.dateissued DESC";

$badges = $DB->get_records_sql($sql, array($userid, BADGE_STATUS_ACTIVE));

$certificates_data = array();
$total_certificates = 0;
$certificates_this_year = 0;
$expired_certificates = 0;
$active_certificates = 0;
$current_year = date('Y');

foreach ($badges as $badge) {
    $badge_obj = new badge($badge->id);
    
    // Get badge image
    $badge_image = $CFG->wwwroot . '/theme/remui_kids/pix/default_certificate.png';
    $fs = get_file_storage();
    $badge_context = context_system::instance();
    
    // Try to get the badge image
    $imagefile = $badge_obj->get_image();
    if ($imagefile) {
        $badge_image = moodle_url::make_pluginfile_url(
            $imagefile->get_contextid(),
            $imagefile->get_component(),
            $imagefile->get_filearea(),
            $imagefile->get_itemid(),
            $imagefile->get_filepath(),
            $imagefile->get_filename()
        )->out();
    }
    
    // Check if expired
    $is_expired = false;
    $expiry_status = 'Never Expires';
    if ($badge->dateexpire > 0) {
        if ($badge->dateexpire < time()) {
            $is_expired = true;
            $expired_certificates++;
            $expiry_status = 'Expired on ' . userdate($badge->dateexpire, '%d %b %Y');
        } else {
            $expiry_status = 'Expires on ' . userdate($badge->dateexpire, '%d %b %Y');
            $active_certificates++;
        }
    } else {
        $active_certificates++;
    }
    
    // Count certificates this year
    if (date('Y', $badge->dateissued) == $current_year) {
        $certificates_this_year++;
    }
    
    $total_certificates++;
    
    // Get competencies associated with this badge (if any)
    $competencies = array();
    $badge_competencies = $DB->get_records_sql("
        SELECT c.id, c.shortname, c.description
        FROM {competency} c
        JOIN {badge_competency} bc ON c.id = bc.competencyid
        WHERE bc.badgeid = ?
    ", array($badge->id));
    
    foreach ($badge_competencies as $comp) {
        $competencies[] = array(
            'name' => $comp->shortname,
            'description' => strip_tags($comp->description)
        );
    }
    
    // Badge verification URL
    $verification_url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
    
    // Download URL
    $download_url = new moodle_url('/theme/remui_kids/download_certificate.php', array(
        'badgeid' => $badge->id,
        'userid' => $userid
    ));
    
    $certificates_data[] = array(
        'id' => $badge->id,
        'name' => $badge->name,
        'description' => strip_tags($badge->description),
        'badge_image' => $badge_image,
        'issued_date' => userdate($badge->dateissued, '%d %B %Y'),
        'issued_timestamp' => $badge->dateissued,
        'expiry_status' => $expiry_status,
        'is_expired' => $is_expired,
        'is_active' => !$is_expired,
        'course_name' => $badge->coursename ?? 'Site-wide Badge',
        'course_id' => $badge->courseid,
        'has_course' => !empty($badge->courseid),
        'competencies' => $competencies,
        'has_competencies' => count($competencies) > 0,
        'issuer' => $badge->issuercontact ?? 'Riyada Trainings',
        'verification_url' => $verification_url->out(),
        'download_url' => $download_url->out(),
        'certificate_id' => strtoupper(substr($badge->uniquehash, 0, 8))
    );
}

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'user_fullname' => fullname($USER),
    'total_certificates' => $total_certificates,
    'certificates_this_year' => $certificates_this_year,
    'active_certificates' => $active_certificates,
    'expired_certificates' => $expired_certificates,
    'certificates' => $certificates_data,
    'has_certificates' => count($certificates_data) > 0,
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include certifications template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_certifications.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Certifications template not found.</div>';
}

echo $OUTPUT->footer();


