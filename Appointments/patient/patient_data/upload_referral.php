<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Unauthorized request."]);
    exit();
}

$patientID = $_POST['patient_id'] ?? null;
$referralType = $_POST['referral_type'] ?? null;
$uploadDir = "../../../uploads/doctors_referrals/";

if (!$patientID || !$referralType || empty($_FILES['referral_file']['name'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit();
}

// ✅ Validate referral type
$allowedTypes = ["official", "proof_of_booking"];
if (!in_array($referralType, $allowedTypes)) {
    echo json_encode(["status" => "error", "message" => "Invalid referral type."]);
    exit();
}

// ✅ Process File Upload
$fileExtension = pathinfo($_FILES['referral_file']['name'], PATHINFO_EXTENSION);
$fileName = time() . "_{$referralType}_" . basename($_FILES['referral_file']['name']);
$filePath = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES['referral_file']['tmp_name'], $filePath)) {
    echo json_encode(["status" => "error", "message" => "Failed to upload file."]);
    exit();
}

// ✅ Insert or Update Referral Record
$checkQuery = "SELECT referral_id FROM doctor_referrals WHERE patient_id = ?";
$stmt = $connection->prepare($checkQuery);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$result = $stmt->get_result();
$existingReferral = $result->fetch_assoc();
$stmt->close();

if ($existingReferral) {
    // ✅ Update existing referral
    $updateQuery = "UPDATE doctor_referrals 
                    SET {$referralType}_referral_file = ?, updated_at = NOW() 
                    WHERE patient_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("si", $fileName, $patientID);
} else {
    // ✅ Insert new referral
    $insertQuery = "INSERT INTO doctor_referrals (patient_id, referral_type, {$referralType}_referral_file, created_at) 
                    VALUES (?, ?, ?, NOW())";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("iss", $patientID, $referralType, $fileName);
}

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Doctor's referral uploaded successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Database update failed."]);
}

$stmt->close();
?>
