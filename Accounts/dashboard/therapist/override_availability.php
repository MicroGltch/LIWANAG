<?php
    require_once "../../../dbconfig.php";
    session_start();

    // ✅ Restrict Access to Therapists Only
    if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
        header("Location: ../../../Accounts/loginpage.php");
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
        <h2>Override Availability for Specific Dates</h2>

        <form id="overrideForm" method="POST" class="uk-form-stacked">
            <label>Select Date:</label>
            <input class="uk-input" type="date" name="override_date" required>

            <label>Status:</label>
            <select class="uk-select" name="status" id="statusSelect">
                <option value="Unavailable">Unavailable</option>
                <option value="Custom">Custom Availability</option>
            </select>

            <div id="customTimes" style="display: none;">
                <label>Start Time:</label>
                <input class="uk-input" type="time" name="start_time">
                <label>End Time:</label>
                <input class="uk-input" type="time" name="end_time">
            </div>

            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Override</button>
        </form>

        <h3 class="uk-margin-top">Existing Overrides</h3>
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
    <a href="../dashboard.php">Go back to Therapist Dashboard</a>

    <script>
        document.getElementById("statusSelect").addEventListener("change", function () {
            document.getElementById("customTimes").style.display = this.value === "Custom" ? "block" : "none";
        });

        document.getElementById("overrideForm").addEventListener("submit", function (event) {
            event.preventDefault();
            let formData = new FormData(this);

            fetch("therapist_dashboard_backend/update_override_availability.php", {
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

        document.querySelectorAll(".delete-btn").forEach(button => {
            button.addEventListener("click", function () {
                let overrideId = this.getAttribute("data-id");

                fetch("therapist_dashboard_backend/delete_override_availability.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `override_id=${overrideId}`
                })
                .then(response => response.json())
                .then(data => {
                    Swal.fire("Success!", data.message, "success").then(() => location.reload());
                })
                .catch(error => console.error("Error:", error));
            });
        });
    </script>
</body>
</html>
