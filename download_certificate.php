<?php
/**
 * Download Certificate Handler
 * 
 * @package   theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/badges/lib.php');
require_once($CFG->libdir . '/pdflib.php');

// Check if user is logged in
require_login();

global $USER, $DB, $CFG;

// Get parameters
$badgeid = required_param('badgeid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

// Verify that the badge belongs to this user
$badge_issued = $DB->get_record('badge_issued', array(
    'badgeid' => $badgeid,
    'userid' => $userid
), '*', MUST_EXIST);

// Verify the current user is accessing their own certificate or is admin
if ($USER->id != $userid && !is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'download this certificate');
}

// Get badge details
$badge = $DB->get_record('badge', array('id' => $badgeid), '*', MUST_EXIST);
$badge_obj = new badge($badgeid);

// Get user details
$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

// Get course name if applicable
$course_name = 'Site-wide Achievement';
if ($badge->courseid) {
    $course = $DB->get_record('course', array('id' => $badge->courseid));
    if ($course) {
        $course_name = $course->fullname;
    }
}

// Create PDF
$pdf = new pdf();

// Set document properties
$pdf->SetCreator('Riyada Trainings');
$pdf->SetAuthor('Riyada Trainings');
$pdf->SetTitle('Certificate - ' . $badge->name);
$pdf->SetSubject('Certificate of Achievement');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage('L', 'A4'); // Landscape orientation

// Set background color
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 297, 210, 'F');

// Add decorative border
$pdf->SetLineStyle(array('width' => 2, 'color' => array(102, 126, 234)));
$pdf->Rect(10, 10, 277, 190);
$pdf->SetLineStyle(array('width' => 1, 'color' => array(102, 126, 234)));
$pdf->Rect(12, 12, 273, 186);

// Add logo/header
$pdf->SetFont('helvetica', 'B', 28);
$pdf->SetTextColor(102, 126, 234);
$pdf->SetXY(20, 25);
$pdf->Cell(257, 15, 'CERTIFICATE OF ACHIEVEMENT', 0, 1, 'C');

// Add decorative line
$pdf->SetLineStyle(array('width' => 0.5, 'color' => array(102, 126, 234)));
$pdf->Line(70, 43, 227, 43);

// Organization name
$pdf->SetFont('helvetica', '', 12);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY(20, 48);
$pdf->Cell(257, 8, 'Riyada Trainings', 0, 1, 'C');

// "This certifies that" text
$pdf->SetFont('helvetica', '', 14);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(20, 65);
$pdf->Cell(257, 8, 'This certifies that', 0, 1, 'C');

// User name
$pdf->SetFont('helvetica', 'B', 24);
$pdf->SetTextColor(102, 126, 234);
$pdf->SetXY(20, 78);
$pdf->Cell(257, 12, fullname($user), 0, 1, 'C');

// Underline user name
$pdf->SetLineStyle(array('width' => 0.5, 'color' => array(102, 126, 234)));
$name_width = $pdf->GetStringWidth(fullname($user));
$pdf->Line((297 - $name_width) / 2, 91, (297 + $name_width) / 2, 91);

// "has successfully completed" text
$pdf->SetFont('helvetica', '', 14);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(20, 100);
$pdf->Cell(257, 8, 'has successfully earned the', 0, 1, 'C');

// Badge name
$pdf->SetFont('helvetica', 'B', 18);
$pdf->SetTextColor(118, 75, 162);
$pdf->SetXY(20, 112);
$pdf->MultiCell(257, 10, $badge->name, 0, 'C');

// Course name (if applicable)
if ($badge->courseid) {
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(20, 128);
    $pdf->Cell(257, 6, 'in the course: ' . $course_name, 0, 1, 'C');
}

// Date issued
$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(20, 145);
$pdf->Cell(257, 6, 'Date Issued: ' . userdate($badge_issued->dateissued, '%d %B %Y'), 0, 1, 'C');

// Expiry date (if applicable)
if ($badge_issued->dateexpire > 0) {
    $pdf->SetXY(20, 152);
    $pdf->Cell(257, 6, 'Valid Until: ' . userdate($badge_issued->dateexpire, '%d %B %Y'), 0, 1, 'C');
}

// Certificate ID
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(120, 120, 120);
$pdf->SetXY(20, 165);
$cert_id = 'Certificate ID: ' . strtoupper(substr($badge_issued->uniquehash, 0, 12));
$pdf->Cell(257, 5, $cert_id, 0, 1, 'C');

// Signature section
$pdf->SetLineStyle(array('width' => 0.5, 'color' => array(60, 60, 60)));
$pdf->Line(50, 185, 110, 185);
$pdf->Line(187, 185, 247, 185);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(50, 187);
$pdf->Cell(60, 5, 'Authorized Signature', 0, 0, 'C');
$pdf->SetXY(187, 187);
$pdf->Cell(60, 5, 'Date', 0, 0, 'C');

// Footer
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(150, 150, 150);
$pdf->SetXY(20, 198);
$pdf->Cell(257, 4, 'Verify this certificate at: ' . $CFG->wwwroot . '/badges/badge.php?hash=' . $badge_issued->uniquehash, 0, 1, 'C');

// Generate filename
$filename = clean_filename($badge->name . ' - ' . fullname($user) . '.pdf');

// Output PDF
$pdf->Output($filename, 'D'); // 'D' forces download
exit;

