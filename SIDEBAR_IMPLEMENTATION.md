# Riyada Sidebar Implementation for Trainees and Teachers

## Overview
This implementation provides role-based sidebar navigation for the Riyada Training platform. The sidebar now shows different content based on user roles: Admin, Teacher, and Trainee.

## Files Created/Modified

### 1. Updated Files

#### `check_admin.php`
- **Purpose**: Determines user role and permissions
- **Changes**: 
  - Now identifies admin, teacher, and trainee roles
  - Returns role-specific information via JSON API
  - Shows sidebar for all logged-in users (not just admins)

#### `riyada_sidebar.mustache`
- **Purpose**: Main sidebar template with role-based logic
- **Changes**:
  - Updated JavaScript to load different sidebars based on user role
  - Admin users see the original admin sidebar
  - Non-admin users get the trainee/teacher sidebar

#### `riyada_sidebar.css`
- **Purpose**: Styling for all sidebar variants
- **Changes**:
  - Added styles for user profile section
  - Added logout button styling
  - Added role-specific border colors (red for admin, blue for teacher, green for trainee)

### 2. New Files

#### `riyada_sidebar_trainee_teacher.mustache`
- **Purpose**: Sidebar template specifically for trainees and teachers
- **Features**:
  - Dashboard section with "My Dashboard" and "My Profile"
  - Learning section with "My Learning", "Achievements", and "Assessments"
  - Learning Paths section with "Learning Paths" and "Certifications"
  - Competency section with "Competency Map" (highlighted as active)
  - Community section
  - Settings section
  - User profile footer with avatar, name, school, and logout button

#### `render_trainee_sidebar.php`
- **Purpose**: PHP endpoint to render the trainee/teacher sidebar
- **Features**:
  - Checks user authentication
  - Prepares template data with user information
  - Renders the sidebar template

## User Role Detection

The system detects user roles using Moodle's capability system:

- **Admin**: Users with `moodle/site:config` capability
- **Teacher**: Users with `moodle/course:manageactivities` capability (but not admin)
- **Trainee**: All other logged-in users

## Sidebar Features

### For Trainees and Teachers:
- **My Dashboard**: Personal dashboard and profile access
- **My Learning**: Course access, achievements, and assessments
- **Learning Paths**: Course catalog and certifications
- **Competency Map**: Visual competency tracking (highlighted as active)
- **Community**: Access to user communities
- **Settings**: User preferences and settings
- **User Profile**: Shows user name, school, and logout button

### Visual Design:
- Matches the design from the provided image
- Clean, modern interface with proper spacing
- Role-specific color coding (border colors)
- Responsive design with proper scrolling
- User profile section at the bottom with avatar and logout

## How It Works

1. **Page Load**: The main sidebar template loads
2. **Role Check**: JavaScript calls `check_admin.php` to determine user role
3. **Sidebar Selection**: Based on role:
   - Admin users see the original admin sidebar
   - Trainees and teachers get the new trainee/teacher sidebar
4. **Dynamic Loading**: For non-admin users, the system loads the trainee/teacher sidebar via AJAX
5. **Styling**: CSS applies role-specific styling and ensures proper layout

## Security

- All endpoints check for proper authentication
- Role detection uses Moodle's built-in capability system
- Sidebar content is filtered based on user permissions
- No sensitive admin functions are exposed to trainees/teachers

## Testing

To test the implementation:

1. **As Admin**: Should see the original admin sidebar with all admin functions
2. **As Teacher**: Should see the trainee/teacher sidebar with learning-focused navigation
3. **As Trainee**: Should see the trainee/teacher sidebar with student-focused navigation
4. **As Guest**: Should not see any sidebar

The sidebar will automatically adjust based on the user's role and show appropriate navigation options.
