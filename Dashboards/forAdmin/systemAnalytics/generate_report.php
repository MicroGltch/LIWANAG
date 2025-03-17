<?php
require_once "../../../Accounts/signupverify/vendor/setasign/fpdf/fpdf.php"; 
require_once "../../../dbconfig.php"; 

session_start();

// Check if filter_type and filter_value exist
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : null;
$filter_value = isset($_GET['filter_value']) ? $_GET['filter_value'] : null;

// Validate the input
if (!$filter_type || !$filter_value) {
    die("Error: Missing filter type or filter value.");
}

// Fix: Ensure proper reference to `appointments.created_at`
$condition = ($filter_type == 'month') 
    ? "DATE_FORMAT(CONVERT(a.created_at USING utf8mb4) COLLATE utf8mb4_unicode_ci, '%Y-%m') = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci"
    : "DATE_FORMAT(CONVERT(a.created_at USING utf8mb4) COLLATE utf8mb4_unicode_ci, '%Y') = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci";

// Fetch total appointments
$stmt = $connection->prepare("
    SELECT COUNT(*) AS total_appointments 
    FROM appointments a
    WHERE DATE_FORMAT(a.created_at, '%Y-%m') = CAST(? AS CHAR CHARACTER SET utf8mb4)
");
$stmt->bind_param("s", $filter_value);
$stmt->execute();
$result = $stmt->get_result();
$total_appointments = $result->fetch_assoc()['total_appointments'];

// Breakdown by service type
$service_types = [];
$service_result = $connection->prepare("
    SELECT session_type, COUNT(*) AS count 
    FROM appointments a
    WHERE $condition
    GROUP BY session_type
");
$service_result->bind_param("s", $filter_value);
$service_result->execute();
$service_data = $service_result->get_result();
while ($row = $service_data->fetch_assoc()) {
    $service_types[$row['session_type']] = $row['count'];
}

// Appointment status percentages
$status_counts = [];
$status_result = $connection->prepare("
    SELECT status, COUNT(*) AS count 
    FROM appointments a
    WHERE $condition
    GROUP BY status
");
$status_result->bind_param("s", $filter_value);
$status_result->execute();
$status_data = $status_result->get_result();
while ($row = $status_data->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

// Convert status counts to percentages
$status_percentages = [];
foreach ($status_counts as $status => $count) {
    $status_percentages[$status] = round(($count / $total_appointments) * 100, 2) . "%";
}

// Fetch Peak Booking Hour
$peak_result = $connection->prepare("
    SELECT HOUR(a.created_at) AS peak_hour, COUNT(*) AS total_bookings 
    FROM appointments a
    WHERE $condition
    GROUP BY peak_hour 
    ORDER BY total_bookings DESC 
    LIMIT 1
");
$peak_result->bind_param("s", $filter_value);
$peak_result->execute();
$result = $peak_result->get_result();
$peak_hour = ($result->num_rows > 0) ? $result->fetch_assoc()['peak_hour'] . ":00" : "N/A";

// Fetch Appointment Details (Using Names Instead of IDs)
$appointments = $connection->prepare("
    SELECT 
        a.appointment_id, 
        u.account_FName AS client_name, 
        p.first_name AS patient_name, 
        a.date, 
        a.time, 
        a.status 
    FROM appointments a
    JOIN users u ON a.account_id = u.account_ID
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE $condition
    ORDER BY a.date ASC
");
$appointments->bind_param("s", $filter_value);
$appointments->execute();
$appointment_result = $appointments->get_result();

// Fetch Therapist Workload (Show Therapists Even with Zero Sessions)
$therapist_workload = [];
$therapist_result = $connection->prepare("
    SELECT COALESCE(u.account_FName, 'Unassigned') AS therapist_name, COUNT(*) AS sessions_handled 
    FROM appointments a
    LEFT JOIN users u ON a.therapist_id = u.account_ID
    WHERE DATE_FORMAT(a.created_at, '%Y-%m') = CAST(? AS CHAR CHARACTER SET utf8mb4)
    GROUP BY therapist_name
    ORDER BY sessions_handled DESC
");
$therapist_result->bind_param("s", $filter_value);
$therapist_result->execute();
$therapist_data = $therapist_result->get_result();
while ($row = $therapist_data->fetch_assoc()) {
    $therapist_workload[$row['therapist_name']] = $row['sessions_handled'];
}

// New clients registered
$stmt = $connection->prepare("
    SELECT COUNT(*) AS new_clients 
    FROM users 
    WHERE account_Type = 'client' 
    AND DATE_FORMAT(users.created_at, '%Y-%m') = CAST(? AS CHAR CHARACTER SET utf8mb4)
");
$stmt->bind_param("s", $filter_value);
$stmt->execute();
$result = $stmt->get_result();
$new_clients = $result->fetch_assoc()['new_clients'];

// New patients registered
    $stmt = $connection->prepare("
        SELECT COUNT(*) AS new_patients 
        FROM patients 
        WHERE DATE_FORMAT(patients.created_at, '%Y-%m') = CAST(? AS CHAR CHARACTER SET utf8mb4)
    ");
$stmt->bind_param("s", $filter_value);
$stmt->execute();
$result = $stmt->get_result();
$new_patients = $result->fetch_assoc()['new_patients'];

// PDF Creation
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(190, 10, "System Analytics Report", 0, 1, 'C');
        //$this->Image('assets/logo.png', 10, 10, 20);
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();

// Report Duration
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Report Duration: $filter_value", 0, 1, 'C');
$pdf->Ln(8);

// Summary Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Summary", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);

$pdf->Cell(95, 8, "Total Appointments:", 0, 0);
$pdf->Cell(95, 8, $total_appointments, 0, 1, 'R');

$pdf->Cell(95, 8, "New Clients Registered:", 0, 0);
$pdf->Cell(95, 8, $new_clients, 0, 1, 'R');

$pdf->Cell(95, 8, "New Patients Registered:", 0, 0);
$pdf->Cell(95, 8, $new_patients, 0, 1, 'R');

$pdf->Cell(95, 8, "Peak Booking Hour:", 0, 0);
$pdf->Cell(95, 8, $peak_hour, 0, 1, 'R');

$pdf->Ln(10);

// Appointment Breakdown
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Breakdown by Service Type", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
foreach ($service_types as $service => $count) {
    $pdf->Cell(95, 8, ucfirst($service) . ":", 0, 0);
    $pdf->Cell(95, 8, $count, 0, 1, 'R');
}
$pdf->Ln(8);

// Therapist Workload
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Therapist Workload", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
foreach ($therapist_workload as $therapist => $sessions) {
    $pdf->Cell(95, 8, "$therapist:", 0, 0);
    $pdf->Cell(95, 8, "$sessions sessions", 0, 1, 'R');
}
$pdf->Ln(8);

// Appointment Details Table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Appointment Details", 0, 1, 'L');
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 200, 200); // Light gray background
$pdf->Cell(35, 8, "ID", 1, 0, 'C', true);
$pdf->Cell(45, 8, "Client Name", 1, 0, 'C', true);
$pdf->Cell(45, 8, "Patient Name", 1, 0, 'C', true);
$pdf->Cell(30, 8, "Date", 1, 0, 'C', true);
$pdf->Cell(35, 8, "Time", 1, 1, 'C', true);

// Table Data
$pdf->SetFont('Arial', '', 10);
$fill = false; // For alternating row colors
$pdf->SetFillColor(240, 240, 240); // Light gray row background
while ($row = $appointment_result->fetch_assoc()) {
    $pdf->Cell(35, 8, $row['appointment_id'], 1, 0, 'C', $fill);
    $pdf->Cell(45, 8, $row['client_name'], 1, 0, 'C', $fill);
    $pdf->Cell(45, 8, $row['patient_name'], 1, 0, 'C', $fill);
    $pdf->Cell(30, 8, $row['date'], 1, 0, 'C', $fill);
    $pdf->Cell(35, 8, $row['time'], 1, 1, 'C', $fill);
    $fill = !$fill; // Toggle row color
}

// Output PDF
$pdf->Output('D', "System_Analytics_Report_$filter_value.pdf");

?>
