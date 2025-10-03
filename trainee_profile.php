<?php
/**
 * Trainee Profile Page - View and edit profile information
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

// Set page context
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_url('/theme/remui_kids/trainee_profile.php');
$PAGE->set_title('My Profile - Riyada Trainings');
$PAGE->set_heading('Profile');

// Get user data
$userid = $USER->id;
$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

// Get user role
$roles = get_user_roles($context, $userid, true);
$user_role = 'trainee';
$role_name = 'Trainee';
if (!empty($roles)) {
    $role = reset($roles);
    $role_name = $role->name;
    if (stripos($role_name, 'teacher') !== false) {
        $user_role = 'teacher';
    }
}

// Get user profile fields
$profile_fields = array(
    'firstname' => $user->firstname,
    'lastname' => $user->lastname,
    'email' => $user->email,
    'department' => $user->department ?? '',
    'institution' => $user->institution ?? '',
    'phone1' => $user->phone1 ?? '',
    'phone2' => $user->phone2 ?? '',
    'username' => $user->username,
    'city' => $user->city ?? '',
    'country' => $user->country ?? '',
    'description' => $user->description ?? ''
);

// Get custom profile fields
$custom_fields = array();
$profile_field_data = $DB->get_records('user_info_data', array('userid' => $userid));
foreach ($profile_field_data as $field_data) {
    $field_info = $DB->get_record('user_info_field', array('id' => $field_data->fieldid));
    if ($field_info) {
        $custom_fields[] = array(
            'name' => $field_info->name,
            'value' => $field_data->data
        );
    }
}

// Get learning path preferences / career goals
$career_goal = 'Master Teacher Certification';
$expected_completion = 'December 2025';

// Get development focus areas (from competencies or custom fields)
$focus_areas = array(
    array('name' => 'Student Engagement Strategies', 'active' => true),
    array('name' => 'Technology Integration', 'active' => true),
    array('name' => 'Data-Driven Instruction', 'active' => true)
);

// Try to get from competencies
$competency_focus = $DB->get_records_sql("
    SELECT c.id, c.shortname
    FROM {competency_usercomp} uc
    JOIN {competency} c ON uc.competencyid = c.id
    WHERE uc.userid = ?
    ORDER BY uc.timemodified DESC
    LIMIT 5
", array($userid));

if (!empty($competency_focus)) {
    $focus_areas = array();
    foreach ($competency_focus as $comp) {
        $focus_areas[] = array(
            'name' => $comp->shortname,
            'active' => true
        );
    }
}

// Connected accounts (OAuth2 integrations)
$connected_accounts = array(
    array(
        'name' => 'Microsoft Account',
        'email' => $user->email,
        'connected' => true,
        'icon' => 'microsoft'
    ),
    array(
        'name' => 'Google Account',
        'email' => '',
        'connected' => false,
        'icon' => 'google'
    ),
    array(
        'name' => 'LinkedIn',
        'email' => '',
        'connected' => false,
        'icon' => 'linkedin'
    )
);

// Role capabilities
$capabilities = array(
    array(
        'category' => 'Learning Access',
        'icon' => 'book',
        'items' => array(
            array('text' => 'Access personalized learning paths', 'enabled' => true),
            array('text' => 'Enroll in all course types (ILT, VILT, Self-paced)', 'enabled' => true),
            array('text' => 'View and download course materials', 'enabled' => true)
        )
    ),
    array(
        'category' => 'Assessment & Certification',
        'icon' => 'check-circle',
        'items' => array(
            array('text' => 'Complete course assessments', 'enabled' => true),
            array('text' => 'Receive feedback on submissions', 'enabled' => true),
            array('text' => 'Track certification progress', 'enabled' => true)
        )
    ),
    array(
        'category' => 'Collaboration',
        'icon' => 'users',
        'items' => array(
            array('text' => 'Participate in discussion forums', 'enabled' => true),
            array('text' => 'Join peer learning groups', 'enabled' => true),
            array('text' => 'Create or lead training sessions', 'enabled' => false)
        )
    )
);

// Get user picture URL
$user_picture = $OUTPUT->user_picture($user, array('size' => 100, 'link' => false));

// Prepare template context
$templatecontext = array(
    'wwwroot' => $CFG->wwwroot,
    'user' => $user,
    'user_name' => fullname($USER),
    'user_role' => $role_name,
    'profile_fields' => $profile_fields,
    'custom_fields' => $custom_fields,
    'has_custom_fields' => count($custom_fields) > 0,
    'career_goal' => $career_goal,
    'expected_completion' => $expected_completion,
    'focus_areas' => $focus_areas,
    'has_focus_areas' => count($focus_areas) > 0,
    'connected_accounts' => $connected_accounts,
    'capabilities' => $capabilities,
    'edit_profile_url' => $CFG->wwwroot . '/user/edit.php',
    'back_url' => $CFG->wwwroot . '/theme/remui_kids/trainee_dashboard.php'
);

// Output the page
echo $OUTPUT->header();

// Include profile template
$template_file = $CFG->dirroot . '/theme/remui_kids/templates/trainee_profile.mustache';
if (file_exists($template_file)) {
    $mustache = new core\output\mustache_engine();
    $template_content = file_get_contents($template_file);
    echo $mustache->render($template_content, $templatecontext);
} else {
    echo '<div class="alert alert-warning">Profile template not found.</div>';
}

echo $OUTPUT->footer();

