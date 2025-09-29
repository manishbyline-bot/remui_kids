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
 * Language strings for theme_remui_kids
 *
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'RemUI Kids';
$string['configtitle'] = 'RemUI Kids';
$string['choosereadme'] = 'RemUI Kids theme for Riyada Trainings';

// Teacher Management
$string['teachernotfound'] = 'Teacher not found';
$string['emailalreadytaken'] = 'Email address is already taken by another user';
$string['cannotsuspendself'] = 'You cannot suspend your own account';

// Teacher View Page
$string['teacherdetails'] = 'Teacher Details';
$string['teacherinformation'] = 'Teacher Information';
$string['roleassignments'] = 'Role Assignments';
$string['additionalinformation'] = 'Additional Information';
$string['assignedcourses'] = 'Assigned Courses';
$string['nocoursesassigned'] = 'No courses assigned to this teacher';
$string['noroleassignments'] = 'No role assignments found';
$string['noadditionalinfo'] = 'No additional information available';

// Teacher Edit Page
$string['editteacher'] = 'Edit Teacher';
$string['basicinformation'] = 'Basic Information';
$string['contactinformation'] = 'Contact Information';
$string['professionalinformation'] = 'Professional Information';
$string['teacherinfoupdated'] = 'Teacher information updated successfully!';

// Teacher Suspend Page
$string['teacherstatusmanagement'] = 'Teacher Status Management';
$string['currentstatus'] = 'Current Status';
$string['statusmanagement'] = 'Status Management';
$string['recentactivity'] = 'Recent Activity (Last 30 Days)';
$string['teachersuspended'] = 'Teacher has been suspended successfully!';
$string['teacheractivated'] = 'Teacher has been activated successfully!';
$string['suspensionwarning'] = 'Teacher is currently suspended. They cannot access the system or their courses.';
$string['activationnotice'] = 'Teacher is currently active. They have full access to the system.';

// Actions
$string['viewteacher'] = 'View Teacher';
$string['editteacher'] = 'Edit Teacher';
$string['managestatus'] = 'Manage Status';
$string['backtoteachers'] = 'Back to Teachers';
$string['savechanges'] = 'Save Changes';
$string['cancel'] = 'Cancel';
$string['activate'] = 'Activate';
$string['suspend'] = 'Suspend';

// Form Fields
$string['firstname'] = 'First Name';
$string['lastname'] = 'Last Name';
$string['emailaddress'] = 'Email Address';
$string['username'] = 'Username';
$string['primaryphone'] = 'Primary Phone';
$string['secondaryphone'] = 'Secondary Phone';
$string['city'] = 'City';
$string['country'] = 'Country';
$string['department'] = 'Department';
$string['specialization'] = 'Specialization';
$string['reason'] = 'Reason (Optional)';
$string['selectaction'] = 'Select Action';

// Status
$string['active'] = 'Active';
$string['suspended'] = 'Suspended';
$string['lastaccess'] = 'Last Access';
$string['created'] = 'Created';
$string['lastlogin'] = 'Last Login';
$string['never'] = 'Never';

// Warnings and Info
$string['activationwill'] = 'Activation will:';
$string['suspensionwill'] = 'Suspension will:';
$string['restoreaccess'] = 'Restore full system access';
$string['allowcourseaccess'] = 'Allow access to assigned courses';
$string['enablelogin'] = 'Enable login capabilities';
$string['blocksystemaccess'] = 'Block system access';
$string['preventcourseaccess'] = 'Prevent course access';
$string['disablelogin'] = 'Disable login capabilities';
$string['preservedata'] = 'Preserve all data and assignments';

// Schools Management
$string['schoolsmanagement'] = 'Schools Management';
$string['createschool'] = 'Create School';
$string['editschool'] = 'Edit School';
$string['manageschools'] = 'Manage Schools';
$string['schoolnameempty'] = 'School name cannot be empty';
$string['schoolnameexists'] = 'A school with this name already exists';
$string['schoolcreationfailed'] = 'Failed to create school';
$string['schoolcreatedsuccessfully'] = 'School created successfully';
$string['schoolinfoupdated'] = 'School information updated successfully';
$string['totalschools'] = 'Total Schools';
$string['activeschools'] = 'Active Schools';
$string['suspendedschools'] = 'Suspended Schools';
$string['averageschools'] = 'Average Schools';
$string['schoolmanagement'] = 'School Management';
$string['schoolconfiguration'] = 'School Configuration';
$string['departmentmanagement'] = 'Department Management';
$string['profilemanagement'] = 'Profile Management';
$string['accesscontrol'] = 'Access Control';
$string['dataimport'] = 'Data Import';
$string['advancedschoolsettings'] = 'Advanced School Settings';
$string['managedepartments'] = 'Manage Departments';
$string['optionalprofiles'] = 'Optional Profiles';
$string['restrictcapabilities'] = 'Restrict Capabilities';
$string['importschools'] = 'Import Schools';
$string['schoolname'] = 'School Name';
$string['schoolcode'] = 'School Code';
$string['schooldescription'] = 'Description';
$string['schoollocation'] = 'Location';
$string['schooltype'] = 'School Type';
$string['schoolphone'] = 'Phone Number';
$string['schoolemail'] = 'Email Address';
$string['schoolwebsite'] = 'Website';
$string['publicschool'] = 'Public School';
$string['privateschool'] = 'Private School';
$string['charterschool'] = 'Charter School';
$string['internationalschool'] = 'International School';
$string['vocationalschool'] = 'Vocational School';
$string['specialeducation'] = 'Special Education';
$string['autoenrollstudents'] = 'Auto-enroll students';
$string['enablenotifications'] = 'Enable notifications';
$string['createnewschool'] = 'Create New School';
$string['schoolinformation'] = 'School Information';
$string['basicinformation'] = 'Basic Information';
$string['locationinformation'] = 'Location Information';
$string['contactinformation'] = 'Contact Information';
$string['additionalsettings'] = 'Additional Settings';
$string['needshelp'] = 'Need Help?';
$string['schoolwillbeavailable'] = 'School will be immediately available';
$string['cancreatedepartments'] = 'Can create departments within the school';
$string['canassignteachers'] = 'Can assign teachers and students';
$string['customfieldscanbeadded'] = 'Custom fields can be added later';
$string['settingscanbemodified'] = 'Settings can be modified anytime';
$string['canbehiddenordeleted'] = 'Can be hidden or deleted if needed';