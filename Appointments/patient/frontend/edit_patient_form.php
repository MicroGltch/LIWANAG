<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// ✅ Fetch All Patients Belonging to the Logged-in Client
$patientsQuery = "SELECT patient_id, first_name, last_name FROM patients WHERE account_id = ?";
$stmt = $connection->prepare($patientsQuery);
$stmt->bind_param("i", $_SESSION['account_ID']);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Edit Patient Details</h2>

        <!-- 🔹 Patient Selection Dropdown -->
        <label>Select Patient:</label>
        <select class="uk-select" id="patientDropdown">
            <option value="" disabled selected>Select a Patient</option>
            <?php foreach ($patients as $patient): ?>
                <option value="<?= $patient['patient_id']; ?>">
                    <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- 🔹 Patient Details Form (Initially Hidden) -->
        <form id="editPatientForm" action="../backend/update_patient_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked" style="display: none;">
            <input type="hidden" name="patient_id" id="patient_id">
            <input type="hidden" name="existing_profile_picture" id="existing_profile_picture"> <!-- Store existing picture -->

            <label>First Name:</label>
            <input class="uk-input" type="text" name="first_name" id="first_name" required>

            <label>Last Name:</label>
            <input class="uk-input" type="text" name="last_name" id="last_name" required>

            <label>Age:</label>
            <input class="uk-input" type="number" name="age" id="age" required>

            <label>Gender:</label>
            <select class="uk-select" name="gender" id="gender">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>

            <label>Profile Picture:</label>
            <input class="uk-input" type="file" name="profile_picture" id="profile_picture_input">
            
            <div class="uk-margin">
                <img id="profile_picture_preview" src="" class="uk-border-rounded uk-margin-top" style="width: 100px; height: 100px; display: none;">
            </div>

            <div class="uk-margin">
                <label>Official Referral:</label>
                <a id="official_referral_link" href="#" class="uk-button uk-button-link" target="_blank" style="display: none;">View File</a>
            </div>

            <div class="uk-margin">
                <label>Proof of Booking:</label>
                <a id="proof_of_booking_link" href="#" class="uk-button uk-button-link" target="_blank" style="display: none;">View File</a>
            </div>
            

            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Profile Changes</button>

            <hr>
            <h4>Upload Doctor's Referral</h4>

            <!-- Select Referral Type -->
            <label>Referral Type:</label>
            <select class="uk-select" name="referral_type" id="referral_type_select" required>
                <option value="" disabled selected>Select Referral Type</option>
                <option value="official">Official Referral</option>
                <option value="proof_of_booking">Proof of Booking</option>
            </select>

            <!-- Upload Referral File -->
            <label>Upload File:</label>
            <input class="uk-input" type="file" name="referral_file" id="referral_file_input" required>

            <!-- Submit Button -->
            <button class="uk-button uk-button-primary uk-margin-top" type="button" id="uploadReferralBtn">
                Upload Referral
            </button>


        </form>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        let patientDropdown = document.getElementById("patientDropdown");
        let editForm = document.getElementById("editPatientForm");
        let patientIDInput = document.getElementById("patient_id");
        let firstNameInput = document.getElementById("first_name");
        let lastNameInput = document.getElementById("last_name");
        let ageInput = document.getElementById("age");
        let genderInput = document.getElementById("gender");
        let profilePicPreview = document.getElementById("profile_picture_preview");
        let profilePicInput = document.getElementById("profile_picture_input");
        let existingProfilePicInput = document.getElementById("existing_profile_picture");

        patientDropdown.addEventListener("change", function () {
            let patientID = this.value;
            if (!patientID) {
                editForm.style.display = "none";
                return;
            }

            fetch("../backend/fetch_patient_details.php?patient_id=" + patientID)
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    patientIDInput.value = data.patient.patient_id;
                    firstNameInput.value = data.patient.first_name;
                    lastNameInput.value = data.patient.last_name;
                    ageInput.value = data.patient.age;
                    genderInput.value = data.patient.gender;
                    existingProfilePicInput.value = data.patient.profile_picture;

                    if (data.patient.profile_picture) {
                        profilePicPreview.src = "../../../uploads/profile_pictures/" + data.patient.profile_picture;
                        profilePicPreview.style.display = "block";
                    } else {
                        profilePicPreview.style.display = "none";
                    }

                    // ✅ Display latest referral details
                    if (data.latest_referrals.official) {
                        document.getElementById("official_referral_link").href = "../../../uploads/doctors_referrals/" + data.latest_referrals.official.official_referral_file;
                        document.getElementById("official_referral_link").style.display = "block";
                    } else {
                        document.getElementById("official_referral_link").style.display = "none";
                    }

                    if (data.latest_referrals.proof_of_booking) {
                        document.getElementById("proof_of_booking_link").href = "../../../uploads/doctors_referrals/" + data.latest_referrals.proof_of_booking.proof_of_booking_referral_file;
                        document.getElementById("proof_of_booking_link").style.display = "block";
                    } else {
                        document.getElementById("proof_of_booking_link").style.display = "none";
                    }

                    editForm.style.display = "block";
                } else {
                    editForm.style.display = "none";
                    Swal.fire("Error", "Patient details could not be loaded.", "error");
                }
            })
            .catch(error => console.error("Error fetching patient details:", error));


        });

        // ✅ Show preview when selecting a new profile picture
        profilePicInput.addEventListener("change", function () {
            let file = this.files[0];
            if (file) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    profilePicPreview.src = e.target.result;
                    profilePicPreview.style.display = "block";
                };
                reader.readAsDataURL(file);
            }
        });
    });


    document.getElementById("uploadReferralBtn").addEventListener("click", function () {
        let patientID = document.getElementById("patientDropdown").value;
        let referralType = document.getElementById("referral_type_select").value;
        let referralFile = document.getElementById("referral_file_input").files[0];

        if (!patientID || !referralType || !referralFile) {
            Swal.fire("Error", "Please select a patient, referral type, and upload a file.", "error");
            return;
        }

        let formData = new FormData();
        formData.append("patient_id", patientID);
        formData.append("referral_type", referralType);
        formData.append("referral_file", referralFile);

        fetch("../backend/upload_referral.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                Swal.fire("Success!", data.message, "success").then(() => {
                    location.reload(); // Reload page to update referral display
                });
            } else {
                Swal.fire("Error!", data.message, "error");
            }
        })
        .catch(error => console.error("Error:", error));
    });
    </script>
</body>
</html>
