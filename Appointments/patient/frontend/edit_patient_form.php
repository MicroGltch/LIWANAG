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

            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Changes</button>
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
                        existingProfilePicInput.value = data.patient.profile_picture; // Store current picture filename

                        if (data.patient.profile_picture) {
                            profilePicPreview.src = "../../uploads/profile_pictures/" + data.patient.profile_picture;
                            profilePicPreview.style.display = "block";
                        } else {
                            profilePicPreview.style.display = "none";
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
    </script>
</body>
</html>
