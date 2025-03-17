<?php
require_once "../../../Accounts/signupverify/vendor/setasign/fpdf/fpdf.php"; 
require_once "../../../dbconfig.php"; 

session_start();

// Check if start_date and end_date exist
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Validate the input
if (!$start_date || !$end_date) {
    die("Error: Missing start date or end date.");
}

// Ensure the date range is valid
if ($start_date > $end_date) {
    die("Error: Start date cannot be later than end date.");
}

// Set the condition for the date range
$condition = "a.created_at BETWEEN ? AND ?";

// Fetch total appointments
$stmt = $connection->prepare("
    SELECT COUNT(*) AS total_appointments 
    FROM appointments a
    WHERE $condition
");
$stmt->bind_param("ss", $start_date, $end_date);
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
$service_result->bind_param("ss", $start_date, $end_date);
$service_result->execute();
$service_data = $service_result->get_result();
while ($row = $service_data->fetch_assoc()) {
    $service_types[$row['session_type']] = $row['count'];
}

// **Appointment Status Breakdown**
$status_counts = [];
$status_result = $connection->prepare("
    SELECT status, COUNT(*) AS count 
    FROM appointments a
    WHERE $condition
    GROUP BY status
");
$status_result->bind_param("ss", $start_date, $end_date);
$status_result->execute();
$status_data = $status_result->get_result();
while ($row = $status_data->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
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
$peak_result->bind_param("ss", $start_date, $end_date);
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
$appointments->bind_param("ss", $start_date, $end_date);
$appointments->execute();
$appointment_result = $appointments->get_result();

// Fetch Therapist Workload
$therapist_workload = [];
$therapist_result = $connection->prepare("
    SELECT COALESCE(u.account_FName, 'Unassigned') AS therapist_name, COUNT(*) AS sessions_handled 
    FROM appointments a
    LEFT JOIN users u ON a.therapist_id = u.account_ID
    WHERE $condition
    GROUP BY therapist_name
    ORDER BY sessions_handled DESC
");
$therapist_result->bind_param("ss", $start_date, $end_date);
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
    AND created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$new_clients = $result->fetch_assoc()['new_clients'];

// New patients registered
$stmt = $connection->prepare("
    SELECT COUNT(*) AS new_patients 
    FROM patients 
    WHERE created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$new_patients = $result->fetch_assoc()['new_patients'];

// PDF Creation
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(190, 10, "System Analytics Report", 0, 1, 'C');
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
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 10, "Report Duration: $start_date to $end_date", 0, 1, 'C');
$pdf->Ln(5);

// Summary
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

$pdf->Ln(8);

// Appointment Status Breakdown
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Appointment Status Breakdown", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
foreach ($status_counts as $status => $count) {
    $pdf->Cell(95, 8, ucfirst($status) . ":", 0, 0);
    $pdf->Cell(95, 8, $count, 0, 1, 'R');
}
$pdf->Ln(8);

// Breakdown by Service Type
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
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(20, 8, "ID", 1, 0, 'C', true);
$pdf->Cell(40, 8, "Client Name", 1, 0, 'C', true);
$pdf->Cell(40, 8, "Patient Name", 1, 0, 'C', true);
$pdf->Cell(30, 8, "Date", 1, 0, 'C', true);
$pdf->Cell(25, 8, "Time", 1, 0, 'C', true);
$pdf->Cell(35, 8, "Status", 1, 1, 'C', true);

// Table Data
$pdf->SetFont('Arial', '', 10);
$fill = false;
$pdf->SetFillColor(240, 240, 240);
while ($row = $appointment_result->fetch_assoc()) {
    $pdf->Cell(20, 8, $row['appointment_id'], 1, 0, 'C', $fill);
    $pdf->Cell(40, 8, $row['client_name'], 1, 0, 'C', $fill);
    $pdf->Cell(40, 8, $row['patient_name'], 1, 0, 'C', $fill);
    $pdf->Cell(30, 8, $row['date'], 1, 0, 'C', $fill);
    $pdf->Cell(25, 8, $row['time'], 1, 0, 'C', $fill);
    $pdf->Cell(35, 8, ucfirst($row['status']), 1, 1, 'C', $fill);
    $fill = !$fill;
}

// Output PDF
$pdf->Output('D', "System_Analytics_Report_{$start_date}_to_{$end_date}.pdf");

?>
