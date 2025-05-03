<?php
// fetch_patient_details.php

// Use require_once for critical files like dbconfig
require_once "../../../dbconfig.php"; // Adjust path if necessary

// Start session if not already started (good practice)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set header *before* any output
header('Content-Type: application/json');

// Initialize response structure
$response = ["status" => "error", "message" => "An unexpected error occurred."];

// Check session and GET parameter
if (!isset($_SESSION['account_ID']) || !isset($_GET['patient_id'])) {
    $response["message"] = "Unauthorized request.";
    echo json_encode($response);
    exit();
}

// Sanitize/Validate input
$patientID = filter_var($_GET['patient_id'], FILTER_VALIDATE_INT);
$accountID = $_SESSION['account_ID']; // Assuming account_ID is an integer

if (!$patientID) {
    $response["message"] = "Invalid Patient ID.";
    echo json_encode($response);
    exit();
}


try {
    // Fetch Main Patient Details
    // Added service_type based on your query
    $query = "SELECT patient_id, first_name, last_name, service_type,
                    CASE
                        WHEN bday = '0000-00-00' THEN NULL
                        ELSE bday
                    END AS bday,
                    gender, profile_picture
                    FROM patients WHERE patient_id = ? AND account_id = ?";
    $stmt = $connection->prepare($query);
    if (!$stmt) {
         throw new Exception("Prepare failed (patient): " . $connection->error);
    }
    $stmt->bind_param("ii", $patientID, $accountID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $response["message"] = "Patient not found or access denied.";
        echo json_encode($response);
        $stmt->close();
        $connection->close(); // Close connection before exiting
        exit();
    }

    $patient = $result->fetch_assoc();
    $stmt->close();

    // Format birthday (already handled well in your code)
    if ($patient['bday']) {
        // Check if it's a valid date string before formatting
        if (strtotime($patient['bday']) !== false) {
             $patient['bday'] = date('Y-m-d', strtotime($patient['bday']));
        } else {
            $patient['bday'] = null; // Set to null if parsing fails
        }
    }

    // Fetch Latest Official Referral
    $latestOfficialReferral = null; // Initialize
    $officialReferralQuery = "SELECT referral_id, official_referral_file, created_at
                                        FROM doctor_referrals
                                        WHERE patient_id = ? AND official_referral_file IS NOT NULL
                                        ORDER BY created_at DESC LIMIT 1";
    $stmtRefOff = $connection->prepare($officialReferralQuery);
     if (!$stmtRefOff) {
         throw new Exception("Prepare failed (ref_off): " . $connection->error);
    }
    $stmtRefOff->bind_param("i", $patientID);
    $stmtRefOff->execute();
    $resultRefOff = $stmtRefOff->get_result();
    if ($row = $resultRefOff->fetch_assoc()) {
        $latestOfficialReferral = $row;
    }
    $stmtRefOff->close();


    // Fetch Latest Proof of Booking
    $latestProofReferral = null; // Initialize
    $proofReferralQuery = "SELECT referral_id, proof_of_booking_referral_file, created_at
                                       FROM doctor_referrals
                                       WHERE patient_id = ? AND proof_of_booking_referral_file IS NOT NULL
                                       ORDER BY created_at DESC LIMIT 1";
    $stmtRefPob = $connection->prepare($proofReferralQuery);
     if (!$stmtRefPob) {
         throw new Exception("Prepare failed (ref_pob): " . $connection->error);
    }
    $stmtRefPob->bind_param("i", $patientID);
    $stmtRefPob->execute();
    $resultRefPob = $stmtRefPob->get_result();
    if ($row = $resultRefPob->fetch_assoc()) {
        $latestProofReferral = $row;
    }
    $stmtRefPob->close();


    // *** NEW: Fetch Default Schedule ***
    $defaultScheduleData = []; // Initialize
    $scheduleQuery = "SELECT day_of_week, start_time, end_time
                        FROM patient_default_schedules
                        WHERE patient_id = ?
                        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time";
    $stmtSchedule = $connection->prepare($scheduleQuery);
    if (!$stmtSchedule) {
         throw new Exception("Prepare failed (schedule): " . $connection->error);
    }
    $stmtSchedule->bind_param("i", $patientID);
    $stmtSchedule->execute();
    $resultSchedule = $stmtSchedule->get_result();

    while ($rowSchedule = $resultSchedule->fetch_assoc()) {
        // Optionally format time here
        if ($rowSchedule['start_time'] && strtotime($rowSchedule['start_time']) !== false) {
             $rowSchedule['start_time_formatted'] = date("g:i A", strtotime($rowSchedule['start_time']));
        } else {
             $rowSchedule['start_time_formatted'] = 'N/A'; // Or handle invalid time
        }
        if ($rowSchedule['end_time'] && strtotime($rowSchedule['end_time']) !== false) {
            $rowSchedule['end_time_formatted'] = date("g:i A", strtotime($rowSchedule['end_time']));
        } else {
             $rowSchedule['end_time_formatted'] = 'N/A';
        }
        $defaultScheduleData[] = $rowSchedule;
    }
    $stmtSchedule->close();
    // *** END NEW ***


    // Prepare final successful response
    $response = [
        "status" => "success",
        "patient" => $patient,
        "latest_referrals" => [
            "official" => $latestOfficialReferral, // Will be null if none found
            "proof_of_booking" => $latestProofReferral // Will be null if none found
        ],
        "default_schedule" => $defaultScheduleData // *** Include schedule data ***
    ];

} catch (Exception $e) {
    // Log error ideally
    // error_log("Fetch Patient Details Error: " . $e->getMessage());
    $response["message"] = "Database error occurred. " . $e->getMessage(); // More detailed error for debugging if needed
} finally {
    // Ensure connection is closed
    if (isset($connection) && $connection instanceof mysqli && $connection->thread_id) {
        $connection->close();
    }
}

// Output the final JSON response
echo json_encode($response);
?>