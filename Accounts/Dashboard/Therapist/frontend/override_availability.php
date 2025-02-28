<?php
require_once "../../../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header("Location: ../../../loginpage.php");
    exit();
}

$therapistID = $_SESSION['account_ID'];

// ✅ Fetch existing overrides
$query = "SELECT override_id, date, status, start_time, end_time FROM therapist_overrides WHERE therapist_id = ? ORDER BY date ASC";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $therapistID);
$stmt->execute();
$result = $stmt->get_result();
$overrides = $result->fetch_all(MYSQLI_ASSOC);
$existingDates = array_column($overrides, 'date'); // Track already blocked dates

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Override Availability</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Adjust Availability for Specific Dates</h2>

        <form id="overrideForm" method="POST" class="uk-form-stacked">
            <label>Select Date:</label>
            <input class="uk-input" type="date" name="override_date" id="override_date" required>

            <label>Status:</label>
            <select class="uk-select" name="status" id="statusSelect">
                <option value="Unavailable">Unavailable</option>
                <option value="Custom">Custom Availability</option>
            </select>

            <div id="customTimes" style="display: none;">
                <label>Start Time:</label>
                <input class="uk-input" type="time" name="start_time" id="start_time">
                <label>End Time:</label>
                <input class="uk-input" type="time" name="end_time" id="end_time">
            </div>

            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Adjustment</button>
        </form>

        <h3 class="uk-margin-top">Existing Adjustments</h3>
        <table class="uk-table uk-table-divider">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overrides as $override): ?>
                    <tr>
                        <td><?= htmlspecialchars($override['date']); ?></td>
                        <td><?= htmlspecialchars($override['status']); ?></td>
                        <td><?= $override['start_time'] ?? '-' ?></td>
                        <td><?= $override['end_time'] ?? '-' ?></td>
                        <td>
                            <button class="uk-button uk-button-danger delete-btn" data-id="<?= $override['override_id']; ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <br/>
    <a href="therapist_dashboard.php">Go back to Therapist Dashboard</a>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        let dateInput = document.getElementById("override_date");

        // ✅ Set the minimum date to tomorrow
        let today = new Date();
        today.setDate(today.getDate() + 1); // Tomorrow's date
        let minDate = today.toISOString().split("T")[0];
        dateInput.setAttribute("min", minDate);

        document.getElementById("statusSelect").addEventListener("change", function () {
            document.getElementById("customTimes").style.display = this.value === "Custom" ? "block" : "none";
        });

        document.getElementById("override_date").addEventListener("change", function () {
            let selectedDate = new Date(this.value);
            let tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1); // Tomorrow

            if (selectedDate < tomorrow) {
                Swal.fire({
                    title: "Error!",
                    text: "You can only select dates starting from tomorrow.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
                this.value = ""; // Reset the date selection
            }
        });

        document.getElementById("overrideForm").addEventListener("submit", function (event) {
            event.preventDefault();
            let formData = new FormData(this);
            let selectedDate = document.getElementById("override_date").value;
            let status = document.getElementById("statusSelect").value;
            let startTime = document.getElementById("start_time").value;
            let endTime = document.getElementById("end_time").value;

            // ✅ Prevent duplicate date blocking
            let existingDates = <?= json_encode($existingDates); ?>;
            let conflictExists = existingDates.includes(selectedDate);

            if (conflictExists) {
                Swal.fire({
                    title: "Error!",
                    text: "This date is already blocked. Delete it first before changing.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
                return;
            }

            // ✅ Prevent past date selection
            let selectedDateObj = new Date(selectedDate);
            let tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1); // Tomorrow

            if (selectedDateObj < tomorrow) {
                Swal.fire({
                    title: "Error!",
                    text: "You can only block dates starting from tomorrow.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
                return;
            }

            // ✅ Validate Time Selection
            if (status === "Limited") {
                if (!startTime || !endTime) {
                    Swal.fire({
                        title: "Error!",
                        text: "Please select both start and end time.",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                    return;
                }

                let start = new Date(`2000-01-01T${startTime}`);
                let end = new Date(`2000-01-01T${endTime}`);
                let diffMinutes = (end - start) / (1000 * 60);

                if (diffMinutes < 30) {
                    Swal.fire({
                        title: "Error!",
                        text: "The minimum interval must be 30 minutes.",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                    return;
                }

                if (end <= start) {
                    Swal.fire({
                        title: "Error!",
                        text: "End time must be later than the start time.",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                    return;
                }
            }

            fetch("../backend/update_override_availability.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.fire({
                    title: data.status === "success" ? "Success!" : "Error!",
                    text: data.message,
                    icon: data.status === "success" ? "success" : "error",
                    confirmButtonText: "OK"
                }).then(() => {
                    if (data.status === "success") location.reload();
                });
            })
            .catch(error => console.error("Error:", error));
        });
    });

    document.addEventListener("DOMContentLoaded", function () {
    document.body.addEventListener("click", function (event) {
        if (event.target.classList.contains("delete-btn")) {
            let overrideId = event.target.getAttribute("data-id");

            Swal.fire({
                title: "Are you sure?",
                text: "This action cannot be undone.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("../backend/delete_override_availability.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: `override_id=${overrideId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire("Deleted!", data.message, "success").then(() => location.reload());
                        } else {
                            Swal.fire("Error!", data.message, "error");
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        Swal.fire("Error!", "An unexpected error occurred.", "error");
                    });
                }
            });
        }
    });
});

</script>

</body>
</html>
