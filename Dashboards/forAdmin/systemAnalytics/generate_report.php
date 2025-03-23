<?php
require_once "../../../Accounts/signupverify/vendor/setasign/fpdf/fpdf.php"; 
require_once "../../../dbconfig.php"; 
session_start();

// Get input dates
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Validate
if (!$start_date || !$end_date) {
    die("Error: Missing start date or end date.");
}
if ($start_date > $end_date) {
    die("Error: Start date cannot be later than end date.");
}

$appointment_condition = "a.created_at BETWEEN ? AND ?";
$user_condition = "u.created_at BETWEEN ? AND ?";
$patient_condition = "p.created_at BETWEEN ? AND ?";
$pg_condition = "pg.date BETWEEN ? AND ?";

// Fetch total appointments
$stmt = $connection->prepare("
    SELECT COUNT(*) AS total_appointments 
    FROM appointments a
    WHERE $appointment_condition
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_appointments = $stmt->get_result()->fetch_assoc()['total_appointments'];

// Breakdown by service type
$service_types = [];
$stmt = $connection->prepare("
    SELECT a.session_type, COUNT(*) AS count 
    FROM appointments a
    WHERE $appointment_condition
    GROUP BY a.session_type
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $service_types[$row['session_type']] = $row['count'];
}

// Appointment status breakdown
$status_counts = [];
$stmt = $connection->prepare("
    SELECT a.status, COUNT(*) AS count 
    FROM appointments a
    WHERE $appointment_condition
    GROUP BY a.status
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

// Peak booking hour
$stmt = $connection->prepare("
    SELECT HOUR(a.created_at) AS peak_hour, COUNT(*) AS total_bookings 
    FROM appointments a
    WHERE $appointment_condition
    GROUP BY peak_hour 
    ORDER BY total_bookings DESC 
    LIMIT 1
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$peak_hour = ($result->num_rows > 0) ? $result->fetch_assoc()['peak_hour'] . ":00" : "N/A";

// Appointment details
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
    WHERE $appointment_condition
    ORDER BY a.date ASC
");
$appointments->bind_param("ss", $start_date, $end_date);
$appointments->execute();
$appointment_result = $appointments->get_result();

// Therapist workload
$therapist_workload = [];
$stmt = $connection->prepare("
    SELECT COALESCE(u.account_FName, 'Unassigned') AS therapist_name, COUNT(*) AS sessions_handled 
    FROM appointments a
    LEFT JOIN users u ON a.therapist_id = u.account_ID
    WHERE $appointment_condition
    GROUP BY therapist_name
    ORDER BY sessions_handled DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $therapist_workload[$row['therapist_name']] = $row['sessions_handled'];
}

// New clients
$stmt = $connection->prepare("
    SELECT COUNT(*) AS new_clients 
    FROM users u
    WHERE u.account_Type = 'client' 
    AND u.created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$new_clients = $stmt->get_result()->fetch_assoc()['new_clients'];

// New patients
$stmt = $connection->prepare("
    SELECT COUNT(*) AS new_patients 
    FROM patients p
    WHERE p.created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$new_patients = $stmt->get_result()->fetch_assoc()['new_patients'];

// Playgroup sessions
$playgroup_sessions = [];
$stmt = $connection->prepare("
    SELECT pg_session_id, date, time, status, current_count, max_capacity
    FROM playgroup_sessions pg
    WHERE $pg_condition
    ORDER BY date ASC, time ASC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $playgroup_sessions[] = $row;
}

// Generate PDF
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

// Status breakdown
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Appointment Status Breakdown", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
foreach ($status_counts as $status => $count) {
    $pdf->Cell(95, 8, ucfirst($status) . ":", 0, 0);
    $pdf->Cell(95, 8, $count, 0, 1, 'R');
}
$pdf->Ln(8);

// Service type breakdown
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Breakdown by Service Type", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
foreach ($service_types as $service => $count) {
    $pdf->Cell(95, 8, ucfirst($service) . ":", 0, 0);
    $pdf->Cell(95, 8, $count, 0, 1, 'R');
}
$pdf->Ln(8);

// Therapist workload
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Therapist Workload", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
foreach ($therapist_workload as $therapist => $sessions) {
    $pdf->Cell(95, 8, $therapist . ":", 0, 0);
    $pdf->Cell(95, 8, "$sessions sessions", 0, 1, 'R');
}
$pdf->Ln(8);

// Appointment table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Appointment Details", 0, 1, 'L');
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(20, 8, "ID", 1, 0, 'C', true);
$pdf->Cell(40, 8, "Client Name", 1, 0, 'C', true);
$pdf->Cell(40, 8, "Patient Name", 1, 0, 'C', true);
$pdf->Cell(30, 8, "Date", 1, 0, 'C', true);
$pdf->Cell(25, 8, "Time", 1, 0, 'C', true);
$pdf->Cell(35, 8, "Status", 1, 1, 'C', true);

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
$pdf->Ln(8);

// Playgroup sessions
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, "Playgroup Session Details", 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
if (!empty($playgroup_sessions)) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 8, "Session ID", 1, 0, 'C');
    $pdf->Cell(30, 8, "Date", 1, 0, 'C');
    $pdf->Cell(30, 8, "Time", 1, 0, 'C');
    $pdf->Cell(30, 8, "Status", 1, 0, 'C');
    $pdf->Cell(35, 8, "Participants", 1, 0, 'C');
    $pdf->Cell(35, 8, "Capacity", 1, 1, 'C');

    $pdf->SetFont('Arial', '', 10);
    foreach ($playgroup_sessions as $pg) {
        $pdf->Cell(30, 8, $pg['pg_session_id'], 1, 0, 'C');
        $pdf->Cell(30, 8, $pg['date'], 1, 0, 'C');
        $pdf->Cell(30, 8, $pg['time'], 1, 0, 'C');
        $pdf->Cell(30, 8, ucfirst($pg['status']), 1, 0, 'C');
        $pdf->Cell(35, 8, $pg['current_count'], 1, 0, 'C');
        $pdf->Cell(35, 8, $pg['max_capacity'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(190, 8, "No Playgroup Sessions found.", 0, 1, 'L');
}

$pdf->Output('D', "System_Analytics_Report_{$start_date}_to_{$end_date}.pdf");

?>