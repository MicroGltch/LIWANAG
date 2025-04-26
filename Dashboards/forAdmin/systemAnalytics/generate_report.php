<?php
// Adjust paths as needed
require_once "../../../Accounts/signupverify/vendor/setasign/fpdf/fpdf.php";
require_once "../../../dbconfig.php";
session_start();

// --- Authentication (Example - Adapt to your needs) ---
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type'] ?? ''), ['admin', 'head therapist'])) {
    die("Access Denied.");
}

// --- Get Input Dates ---
$start_date_input = $_GET['start_date'] ?? null;
$end_date_input = $_GET['end_date'] ?? null;

// --- Validate Dates ---
if (!$start_date_input || !$end_date_input) {
    die("Error: Missing start date or end date.");
}
try {
    // Add time component to ensure full day coverage for BETWEEN queries
    $start_date = new DateTime($start_date_input . ' 00:00:00');
    $end_date = new DateTime($end_date_input . ' 23:59:59');
    if ($start_date > $end_date) {
        die("Error: Start date cannot be later than end date.");
    }
    $start_date_str = $start_date->format('Y-m-d H:i:s');
    $end_date_str = $end_date->format('Y-m-d H:i:s');
    $start_date_display = $start_date->format('Y-m-d');
    $end_date_display = $end_date->format('Y-m-d');
} catch (Exception $e) {
    die("Error: Invalid date format.");
}


global $connection; // Ensure $connection is available

// --- Data Fetching ---

// 1. Enrollment Summary (Current Snapshot - Not date range dependent)
$active_patients_total = 0;
$active_patients_by_service = ['Occupational Therapy' => 0, 'Behavioral Therapy' => 0, 'For Evaluation' => 0]; // Match patient.service_type enums
$stmt_active = $connection->prepare("
    SELECT service_type, COUNT(*) as count
    FROM patients
    WHERE status = 'enrolled'
    GROUP BY service_type
");
if ($stmt_active) {
    $stmt_active->execute();
    $result_active = $stmt_active->get_result();
    while ($row = $result_active->fetch_assoc()) {
        if (isset($active_patients_by_service[$row['service_type']])) {
             $active_patients_by_service[$row['service_type']] = $row['count'];
             $active_patients_total += $row['count'];
        } else {
            // Handle unexpected service types if necessary
            $active_patients_by_service[$row['service_type']] = $row['count']; // Store anyway
            $active_patients_total += $row['count'];
        }
    }
    $stmt_active->close();
} else { error_log("Error preparing active patient query: ".$connection->error); }


// 2. New Clients/Patients (Within Date Range)
$new_clients = 0;
$stmt_nc = $connection->prepare("SELECT COUNT(*) AS count FROM users WHERE account_Type = 'client' AND created_at BETWEEN ? AND ?");
if ($stmt_nc) {
    $stmt_nc->bind_param("ss", $start_date_str, $end_date_str);
    $stmt_nc->execute();
    $new_clients = $stmt_nc->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_nc->close();
} else { error_log("Error preparing new client query: ".$connection->error); }


$new_patients = 0;
$stmt_np = $connection->prepare("SELECT COUNT(*) AS count FROM patients WHERE created_at BETWEEN ? AND ?");
if ($stmt_np) {
    $stmt_np->bind_param("ss", $start_date_str, $end_date_str);
    $stmt_np->execute();
    $new_patients = $stmt_np->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_np->close();
} else { error_log("Error preparing new patient query: ".$connection->error); }

// 3. Non-Default Appointments Analysis (Within Date Range)
//    Focus on specific types like evaluations or manually added ones
$non_default_appt_types = ['IE-OT', 'IE-BT', 'Playgroup']; // Define types considered non-default/explicitly booked
$non_default_placeholders = implode(',', array_fill(0, count($non_default_appt_types), '?'));
$sql_params = array_merge([$start_date_str, $end_date_str], $non_default_appt_types);
$sql_types = 'ss' . str_repeat('s', count($non_default_appt_types));

$total_non_default_appts = 0;
$non_default_type_counts = [];
$non_default_status_counts = [];

// Count total and by type
$sql_nd_type = "
    SELECT session_type, COUNT(*) AS count
    FROM appointments
    WHERE created_at BETWEEN ? AND ?
    AND session_type IN ($non_default_placeholders)
    GROUP BY session_type";
$stmt_nd_type = $connection->prepare($sql_nd_type);
if ($stmt_nd_type) {
    $stmt_nd_type->bind_param($sql_types, ...$sql_params);
    $stmt_nd_type->execute();
    $res_nd_type = $stmt_nd_type->get_result();
    while ($row = $res_nd_type->fetch_assoc()) {
        $non_default_type_counts[$row['session_type']] = $row['count'];
        $total_non_default_appts += $row['count'];
    }
    $stmt_nd_type->close();
} else { error_log("Error preparing non-default type count query: ".$connection->error); }

// Count by status
$sql_nd_status = "
    SELECT status, COUNT(*) AS count
    FROM appointments
    WHERE created_at BETWEEN ? AND ?
    AND session_type IN ($non_default_placeholders)
    GROUP BY status";
$stmt_nd_status = $connection->prepare($sql_nd_status);
if ($stmt_nd_status) {
    $stmt_nd_status->bind_param($sql_types, ...$sql_params);
    $stmt_nd_status->execute();
    $res_nd_status = $stmt_nd_status->get_result();
    while ($row = $res_nd_status->fetch_assoc()) {
        $non_default_status_counts[$row['status']] = $row['count'];
    }
    $stmt_nd_status->close();
} else { error_log("Error preparing non-default status count query: ".$connection->error); }


// 4. Therapist Load Summary
$therapist_load = [];
// Get all active therapists first
$active_therapists = [];
$sql_th = "SELECT account_ID, account_FName, account_LName FROM users WHERE account_Type = 'therapist' AND account_Status = 'Active'";
$res_th = $connection->query($sql_th);
if ($res_th) {
    while ($row = $res_th->fetch_assoc()) {
        $active_therapists[$row['account_ID']] = [
            'name' => trim($row['account_FName'] . ' ' . $row['account_LName']),
            'active_patients' => 0,
            'default_hours' => 0.0,
            'non_default_appts' => 0
        ];
    }
    $res_th->free();
} else { error_log("Error fetching active therapists: ".$connection->error); }


// Calculate assigned active patients and default weekly hours
$sql_def_load = "
    SELECT
        pds.therapist_id,
        COUNT(DISTINCT pds.patient_id) as patient_count,
        SUM(TIME_TO_SEC(TIMEDIFF(pds.end_time, pds.start_time))) / 3600 as total_hours
    FROM patient_default_schedules pds
    JOIN patients p ON pds.patient_id = p.patient_id AND p.status = 'enrolled'
    WHERE pds.therapist_id IS NOT NULL
    GROUP BY pds.therapist_id";

$res_def_load = $connection->query($sql_def_load);
if ($res_def_load) {
    while($row = $res_def_load->fetch_assoc()) {
        $th_id = $row['therapist_id'];
        if (isset($active_therapists[$th_id])) {
            $active_therapists[$th_id]['active_patients'] = (int)$row['patient_count'];
            $active_therapists[$th_id]['default_hours'] = round((float)$row['total_hours'], 1);
        }
    }
    $res_def_load->free();
} else { error_log("Error fetching default schedule load: ".$connection->error); }

// Count non-default appointments handled by each therapist (within the date range)
$sql_nd_therapist = "
    SELECT therapist_id, COUNT(*) as count
    FROM appointments
    WHERE created_at BETWEEN ? AND ?
    AND session_type IN ($non_default_placeholders)
    AND therapist_id IS NOT NULL
    GROUP BY therapist_id";

$stmt_nd_therapist = $connection->prepare($sql_nd_therapist);
if ($stmt_nd_therapist) {
    $stmt_nd_therapist->bind_param($sql_types, ...$sql_params);
    $stmt_nd_therapist->execute();
    $res_nd_therapist = $stmt_nd_therapist->get_result();
    while($row = $res_nd_therapist->fetch_assoc()) {
         $th_id = $row['therapist_id'];
         if (isset($active_therapists[$th_id])) {
             $active_therapists[$th_id]['non_default_appts'] = (int)$row['count'];
         }
    }
     $stmt_nd_therapist->close();
} else { error_log("Error preparing non-default therapist count query: ".$connection->error); }

// Assign the final processed data
$therapist_load = $active_therapists;


// 5. Detailed Non-Default Appointments Table Data (Within Date Range)
$appointment_details = [];
$sql_appt_detail = "
    SELECT
        a.appointment_id,
        u.account_FName AS client_name,
        p.first_name AS patient_name,
        a.date,
        a.time,
        a.status,
        a.session_type
    FROM appointments a
    LEFT JOIN users u ON a.account_id = u.account_ID
    LEFT JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.created_at BETWEEN ? AND ?
    AND a.session_type IN ($non_default_placeholders)
    ORDER BY a.date ASC, a.time ASC";

$stmt_appt_detail = $connection->prepare($sql_appt_detail);
if ($stmt_appt_detail) {
    $stmt_appt_detail->bind_param($sql_types, ...$sql_params);
    $stmt_appt_detail->execute();
    $res_appt_detail = $stmt_appt_detail->get_result();
    while ($row = $res_appt_detail->fetch_assoc()) {
        $appointment_details[] = $row;
    }
     $stmt_appt_detail->close();
} else { error_log("Error preparing appointment detail query: ".$connection->error); }


// 6. Playgroup Sessions (Within Date Range - Assuming still relevant)
$playgroup_sessions = [];
$stmt_pg = $connection->prepare("
    SELECT pg_session_id, date, time, status, current_count, max_capacity
    FROM playgroup_sessions
    WHERE date BETWEEN ? AND ?
    ORDER BY date ASC, time ASC
");
if ($stmt_pg) {
    $stmt_pg->bind_param("ss", $start_date_display, $end_date_display); // Use date only for playgroup range
    $stmt_pg->execute();
    $result_pg = $stmt_pg->get_result();
    while ($row = $result_pg->fetch_assoc()) {
        $playgroup_sessions[] = $row;
    }
    $stmt_pg->close();
} else { error_log("Error preparing playgroup query: ".$connection->error); }


// --- Generate PDF ---
class PDF extends FPDF {
    // Page header
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, "System Analytics Report", 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        // Accessing global variables for dates - alternatively pass them in
        global $start_date_display, $end_date_display;
        $this->Cell(0, 7, "Report Period: $start_date_display to $end_date_display", 0, 1, 'C');
        $this->Ln(5);
    }
    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
    // Helper to draw section header
    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(0, 8, $title, 0, 1, 'L', false); // No border/fill for title line
        $this->Line($this->GetX(), $this->GetY(), $this->GetX()+190, $this->GetY()); // Underline
        $this->Ln(2); // Space after title
        $this->SetFont('Arial', '', 11);
    }
     // Helper for key-value pairs
     function KeyValue($key, $value) {
         $this->Cell(95, 7, $key, 0, 0, 'L');
         $this->Cell(95, 7, $value, 0, 1, 'R');
     }
}

$pdf = new PDF('P', 'mm', 'A4'); // Portrait, mm, A4
$pdf->AliasNbPages();
$pdf->AddPage();


// --- PDF Content ---

// 1. Enrollment Summary
$pdf->SectionTitle("Enrollment Summary (Current)");
$pdf->KeyValue("Total Active Patients:", $active_patients_total);
foreach($active_patients_by_service as $service => $count) {
     $pdf->KeyValue(" - Active " . ($service ?: 'Uncategorized') . ":", $count);
}
$pdf->Ln(1);
$pdf->KeyValue("New Clients Registered (Period):", $new_clients);
$pdf->KeyValue("New Patients Registered (Period):", $new_patients);
$pdf->Ln(6);

// 2. Non-Default Appointments Summary (Period)
$pdf->SectionTitle("Non-Default Appointments Booked (Period)");
$pdf->KeyValue("Total ('IE', Playgroup, etc.):", $total_non_default_appts);
$pdf->Ln(1);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(190, 6, "By Type:", 0, 1);
$pdf->SetFont('Arial','',11);
if (empty($non_default_type_counts)) { $pdf->Cell(190, 6, "  None", 0, 1); } else {
    foreach($non_default_type_counts as $type => $count) { $pdf->KeyValue(" - ".($type ?: 'Unspecified') . ":", $count); }
}
$pdf->Ln(1);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(190, 6, "By Status:", 0, 1);
$pdf->SetFont('Arial','',11);
if (empty($non_default_status_counts)) { $pdf->Cell(190, 6, "  None", 0, 1); } else {
    foreach($non_default_status_counts as $status => $count) { $pdf->KeyValue(" - ".ucfirst($status ?: 'Unknown') . ":", $count); }
}
$pdf->Ln(6);

// 3. Therapist Load Summary
$pdf->SectionTitle("Therapist Load Summary");
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(70, 7, "Therapist Name", 1, 0, 'C', true);
$pdf->Cell(40, 7, "Active Patients", 1, 0, 'C', true);
$pdf->Cell(40, 7, "Default Hrs/Wk", 1, 0, 'C', true);
$pdf->Cell(40, 7, "Non-Def Appts*", 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$fill = false;
$pdf->SetFillColor(245, 245, 245);
if (empty($therapist_load)) {
     $pdf->Cell(190, 7, "No active therapist data found.", 1, 1, 'C');
} else {
    foreach ($therapist_load as $id => $data) {
        $pdf->Cell(70, 7, $data['name'], 'LRB', 0, 'L', $fill);
        $pdf->Cell(40, 7, $data['active_patients'], 'LRB', 0, 'C', $fill);
        $pdf->Cell(40, 7, $data['default_hours'], 'LRB', 0, 'C', $fill);
        $pdf->Cell(40, 7, $data['non_default_appts'], 'LRB', 1, 'C', $fill);
        $fill = !$fill;
    }
}
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, "*Non-Default Appts (IE, Playgroup, etc.) created within the reporting period.", 0, 1);
$pdf->Ln(6);


// 4. Detailed Non-Default Appointments Table
if (!empty($appointment_details)) {
    // Check if enough space, add page if needed
    if ($pdf->GetY() > 190) { $pdf->AddPage(); }

    $pdf->SectionTitle("Detailed Non-Default Appointments (Period)");
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(15, 6, "ID", 1, 0, 'C', true);
    $pdf->Cell(40, 6, "Client", 1, 0, 'C', true);
    $pdf->Cell(40, 6, "Patient", 1, 0, 'C', true);
    $pdf->Cell(25, 6, "Date", 1, 0, 'C', true);
    $pdf->Cell(20, 6, "Time", 1, 0, 'C', true);
    $pdf->Cell(25, 6, "Type", 1, 0, 'C', true);
    $pdf->Cell(25, 6, "Status", 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $fill = false;
    $pdf->SetFillColor(245, 245, 245);
    foreach ($appointment_details as $row) {
         if ($pdf->GetY() > 270) { // Check space before drawing row
             $pdf->AddPage();
             // Optionally repeat table headers
             $pdf->SetFont('Arial', 'B', 9); $pdf->SetFillColor(230, 230, 230);
             $pdf->Cell(15, 6, "ID", 1, 0, 'C', true); $pdf->Cell(40, 6, "Client", 1, 0, 'C', true); $pdf->Cell(40, 6, "Patient", 1, 0, 'C', true); $pdf->Cell(25, 6, "Date", 1, 0, 'C', true); $pdf->Cell(20, 6, "Time", 1, 0, 'C', true); $pdf->Cell(25, 6, "Type", 1, 0, 'C', true); $pdf->Cell(25, 6, "Status", 1, 1, 'C', true);
             $pdf->SetFont('Arial', '', 9);
         }
        $pdf->Cell(15, 6, $row['appointment_id'], 1, 0, 'C', $fill);
        $pdf->Cell(40, 6, $row['client_name'] ?: 'N/A', 1, 0, 'L', $fill);
        $pdf->Cell(40, 6, $row['patient_name'] ?: 'N/A', 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, $row['date'], 1, 0, 'C', $fill);
        // Format time if exists
        $timeDisplay = $row['time'] ? date('g:i A', strtotime($row['time'])) : 'N/A';
        $pdf->Cell(20, 6, $timeDisplay, 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['session_type'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, ucfirst($row['status']), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    $pdf->Ln(6);
}


// 5. Playgroup Sessions Details (Period)
if (!empty($playgroup_sessions)) {
    if ($pdf->GetY() > 220) { $pdf->AddPage(); } // Check space

    $pdf->SectionTitle("Playgroup Session Details (Period)");
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(30, 7, "Session ID", 1, 0, 'C', true);
    $pdf->Cell(30, 7, "Date", 1, 0, 'C', true);
    $pdf->Cell(30, 7, "Time", 1, 0, 'C', true);
    $pdf->Cell(35, 7, "Status", 1, 0, 'C', true);
    $pdf->Cell(35, 7, "Participants", 1, 0, 'C', true);
    $pdf->Cell(30, 7, "Capacity", 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 10);
    $fill = false;
    $pdf->SetFillColor(245, 245, 245);
    foreach ($playgroup_sessions as $pg) {
        if ($pdf->GetY() > 270) { // Check space before drawing row
             $pdf->AddPage();
             // Optionally repeat table headers
              $pdf->SetFont('Arial', 'B', 10); $pdf->SetFillColor(230, 230, 230);
              $pdf->Cell(30, 7, "Session ID", 1, 0, 'C', true); $pdf->Cell(30, 7, "Date", 1, 0, 'C', true); $pdf->Cell(30, 7, "Time", 1, 0, 'C', true); $pdf->Cell(35, 7, "Status", 1, 0, 'C', true); $pdf->Cell(35, 7, "Participants", 1, 0, 'C', true); $pdf->Cell(30, 7, "Capacity", 1, 1, 'C', true);
              $pdf->SetFont('Arial', '', 10);
         }
        $pdf->Cell(30, 7, $pg['pg_session_id'], 1, 0, 'L', $fill);
        $pdf->Cell(30, 7, $pg['date'], 1, 0, 'C', $fill);
        $timeDisplay = $pg['time'] ? date('g:i A', strtotime($pg['time'])) : 'N/A';
        $pdf->Cell(30, 7, $timeDisplay, 1, 0, 'C', $fill);
        $pdf->Cell(35, 7, ucfirst($pg['status']), 1, 0, 'C', $fill);
        $pdf->Cell(35, 7, $pg['current_count'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 7, $pg['max_capacity'], 1, 1, 'C', $fill);
        $fill = !$fill;
    }
} else {
    $pdf->SectionTitle("Playgroup Session Details (Period)");
    $pdf->Cell(0, 7, "No Playgroup Sessions found in the specified period.", 0, 1);
}

// --- Final Output ---
$connection->close();
$pdf->Output('D', "System_Analytics_Report_{$start_date_display}_to_{$end_date_display}.pdf");
exit();
?>