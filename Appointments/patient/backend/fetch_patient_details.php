<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !isset($_GET['patient_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized request."]);
    exit();
}

$patientID = $_GET['patient_id'];
$accountID = $_SESSION['account_ID'];

// ✅ Fetch patient details
$query = "SELECT patient_id, first_name, last_name, age, gender, profile_picture FROM patients WHERE patient_id = ? AND account_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ii", $patientID, $accountID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(["status" => "error", "message" => "Patient not found."]);
    exit();
}

$patient = $result->fetch_assoc();
$stmt->close();

// ✅ Fetch latest official referral
$officialReferralQuery = "SELECT referral_id, official_referral_file, created_at 
                          FROM doctor_referrals 
                          WHERE patient_id = ? AND official_referral_file IS NOT NULL 
                          ORDER BY created_at DESC LIMIT 1";
$stmt = $connection->prepare($officialReferralQuery);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$result = $stmt->get_result();
$latestOfficialReferral = $result->fetch_assoc();
$stmt->close();

// ✅ Fetch latest proof of booking
$proofReferralQuery = "SELECT referral_id, proof_of_booking_referral_file, created_at 
                       FROM doctor_referrals 
                       WHERE patient_id = ? AND proof_of_booking_referral_file IS NOT NULL 
                       ORDER BY created_at DESC LIMIT 1";
$stmt = $connection->prepare($proofReferralQuery);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$result = $stmt->get_result();
$latestProofReferral = $result->fetch_assoc();
$stmt->close();

// ✅ Return patient details + latest referrals
echo json_encode([
    "status" => "success",
    "patient" => $patient,
    "latest_referrals" => [
        "official" => $latestOfficialReferral ?: null,
        "proof_of_booking" => $latestProofReferral ?: null
    ]
]);
