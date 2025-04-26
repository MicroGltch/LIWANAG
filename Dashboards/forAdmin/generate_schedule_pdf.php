<?php
// --- Essential Includes ---
// Adjust these paths based on your actual directory structure
require_once "../../dbconfig.php";
require_once "schedule_functions.php"; // Assumes schedule_functions.php is in the same directory
require_once "../../Accounts/signupverify/vendor/setasign/fpdf/fpdf.php"; // Adjust path to your FPDF library


session_start();

// --- Authentication & Role Check ---
if (!isset($_SESSION['account_ID']) || !isset($_SESSION['account_Type'])) {
    http_response_code(403);
    die("Access Denied: Not logged in.");
}

$loggedInUserId = (int)$_SESSION['account_ID'];
$loggedInUserRole = strtolower($_SESSION['account_Type']);

// --- Get Request Parameters ---
$requestedTherapistId = filter_input(INPUT_GET, 'therapist_id', FILTER_VALIDATE_INT);
$startDateStr = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING); // Basic sanitize

// --- Validate Parameters ---
if (!$requestedTherapistId || $requestedTherapistId <= 0) {
    http_response_code(400);
    die("Bad Request: Missing or invalid therapist_id.");
}

if (!$startDateStr || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateStr)) {
     http_response_code(400);
     die("Bad Request: Missing or invalid start_date (YYYY-MM-DD).");
}

// --- Authorization Check ---
if ($loggedInUserRole === 'therapist') {
    // Therapists can only download their own schedule
    if ($requestedTherapistId !== $loggedInUserId) {
        http_response_code(403);
        die("Access Denied: Therapists can only download their own schedule.");
    }
} elseif ($loggedInUserRole !== 'head therapist' && $loggedInUserRole !== 'admin') {
    // Only allow head therapists or admins (adjust if needed)
    http_response_code(403);
    die("Access Denied: Insufficient permissions.");
}
// Head therapists/admins can download any therapist's schedule (implicit pass)

// --- Calculate Date Range ---
try {
    $startDate = new DateTime($startDateStr);
    $endDate = clone $startDate;
    $endDate->modify('+6 days'); // Full week
} catch (Exception $e) {
    http_response_code(400);
    die("Bad Request: Error processing date.");
}

// --- Fetch Therapist Name (for filename/title) ---
global $connection; // Ensure $connection is available
$therapistName = "Therapist " . $requestedTherapistId; // Default
$sql_name = "SELECT account_FName, account_LName FROM users WHERE account_ID = ?";
if ($stmt_name = $connection->prepare($sql_name)) {
    $stmt_name->bind_param("i", $requestedTherapistId);
    if ($stmt_name->execute()) {
        $res_name = $stmt_name->get_result();
        if ($row_name = $res_name->fetch_assoc()) {
            $therapistName = trim($row_name['account_FName'] . ' ' . $row_name['account_LName']);
        }
        $res_name->free();
    }
    $stmt_name->close();
}

// --- Fetch Schedule Data using shared function ---
$scheduleData = getTherapistScheduleData($connection, $requestedTherapistId, $startDate, $endDate);

if ($scheduleData === null) {
    http_response_code(500);
    // You could generate a PDF saying "Error loading data" or just die
    die("Error: Could not retrieve schedule data to generate PDF.");
}

// --- Generate PDF using FPDF ---

class PDF extends FPDF {
    private $therapistName = '';
    private $dateRangeStr = '';

    function setDocumentHeader($name, $dateRange) {
        $this->therapistName = $name;
        $this->dateRangeStr = $dateRange;
    }

    // Page header
    function Header() {
        // Logo or Title
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, $this->therapistName . ' - Weekly Schedule', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, $this->dateRangeStr, 0, 1, 'C');
        // Line break
        $this->Ln(5);
    }

    // Page footer
    function Footer() {
        $this->SetY(-15); // Position at 1.5 cm from bottom
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C'); // Page number
    }

    // Schedule Table
    function ScheduleTable($scheduleData) {
        $colWidthTime = 45; // Width for time column
        $colWidthStatus = 140; // Width for status/patient column (Page width approx 190 usable)
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(230, 230, 230); // Light grey for headers/borders

        foreach ($scheduleData as $day) {
            // Check for page break before starting a new day
             if ($this->GetY() > 250) { // Adjust threshold as needed
                $this->AddPage();
             }

            // Day Header
            $dayDate = new DateTime($day['date']);
            $dayStr = $dayDate->format('l, F j, Y'); // e.g., Monday, July 14, 2025
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(220, 220, 220);
            $this->Cell(0, 8, $dayStr, 1, 1, 'C', true); // Day header with border and fill
            $this->SetFont('Arial', '', 10);
             $this->Ln(1); // Space after day header

            if (empty($day['slots'])) {
                 $this->SetFont('Arial', 'I', 10);
                 $this->Cell(0, 7, 'No scheduled slots / Unavailable', 'LRB', 1, 'C'); // Border around the message
                 $this->SetFont('Arial', '', 10);
                  $this->Ln(2); // Space after unavailable day
            } else {
                 $isFirstSlot = true;
                foreach ($day['slots'] as $slot) {
                    // Page break check within day's slots
                    if ($this->GetY() > 265) {
                        $this->Cell($colWidthTime, 6, '', 'LR', 0); // Draw bottom border for previous cells if needed
                        $this->Cell($colWidthStatus, 6, '', 'LR', 1);
                        $this->AddPage();
                         // Optional: Repeat day header?
                         //$this->SetFont('Arial','B',12);
                         //$this->Cell(0, 8, $dayStr . " (cont.)", 1, 1, 'C', true);
                         //$this->SetFont('Arial','',10);
                         //$this->Ln(1);
                         $isFirstSlot = true; // Treat as first slot on new page
                    }

                    // Format Time (e.g., 9:00 AM - 10:00 AM)
                    $timeFormatted = formatTimeFPDF($slot['startTime']) . ' - ' . formatTimeFPDF($slot['endTime']);

                    // Determine Status/Patient and Styling
                    $statusText = '';
                    $isFree = false;
                    if ($slot['status'] === 'free') {
                        $statusText = 'Free';
                        $this->SetTextColor(0, 100, 0); // Dark Green
                        $this->SetFont('Arial', 'I', 10);
                        $isFree = true;
                    } else { // occupied
                        $statusText = $slot['patientName'] ?: 'Occupied';
                        $this->SetTextColor(194, 8, 8); // Dark Red
                        $this->SetFont('Arial', 'B', 10);
                        $isFree = false;
                    }

                     // Draw Cell Borders (Top border only for the first slot)
                     $borderTime = $isFirstSlot ? 'LTR' : 'LR';
                     $borderStatus = $isFirstSlot ? 'LTR' : 'LR';
                     if (count($day['slots']) == 1 || $slot === end($day['slots'])) { // Last slot needs bottom border
                           $borderTime .= 'B';
                           $borderStatus .= 'B';
                     }


                    // Draw Cells
                    $this->Cell($colWidthTime, 7, $timeFormatted, $borderTime, 0, 'L');
                    $this->Cell($colWidthStatus, 7, $statusText, $borderStatus, 1, 'L');

                    // Reset font and color
                    $this->SetFont('Arial', '', 10);
                    $this->SetTextColor(0, 0, 0);
                    $isFirstSlot = false; // No longer the first slot
                }
                 $this->Ln(2); // Add space after the last slot of the day
            }
        }
    }
}

// Helper function to format time for FPDF (can't use JS functions here)
function formatTimeFPDF($timeString) {
    if (empty($timeString)) return '';
    try {
        $time = new DateTime('1970-01-01 ' . $timeString); // Use a dummy date
        return $time->format('g:i A'); // e.g., 9:00 AM
    } catch (Exception $e) {
        return $timeString; // Return original if fails
    }
}


// --- Create and Output PDF ---
$pdf = new PDF();
$pdf->AliasNbPages(); // Enable page number alias {nb}

// Set header info before adding the first page
$dateRangeDisplay = $startDate->format('M j, Y') . ' - ' . $endDate->format('M j, Y');
$pdf->setDocumentHeader($therapistName, $dateRangeDisplay);

$pdf->AddPage();
$pdf->ScheduleTable($scheduleData); // Pass the fetched schedule data

// Clean therapist name for filename
$safeTherapistName = preg_replace('/[^a-zA-Z0-9_ -]/', '', $therapistName);
$safeTherapistName = str_replace(' ', '_', $safeTherapistName);
$filename = $safeTherapistName . '_Schedule_' . $startDate->format('Y-m-d') . '.pdf';

$connection->close(); // Close DB connection before outputting

$pdf->Output('D', $filename); // 'D' forces download
exit(); // Stop script execution after sending PDF

?>