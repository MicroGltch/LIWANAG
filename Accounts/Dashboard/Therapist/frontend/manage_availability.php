<?php
    require_once "../../../../dbconfig.php";
    session_start();

    // ✅ Restrict Access to Therapists Only
    if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
        header("Location: ../../../loginpage.php");
        exit();
    }

    $therapistID = $_SESSION['account_ID'];

    // ✅ Fetch current default availability
    $query = "SELECT day, start_time, end_time FROM therapist_default_availability WHERE therapist_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $therapistID);
    $stmt->execute();
    $result = $stmt->get_result();
    $availability = [];

    while ($row = $result->fetch_assoc()) {
        $availability[$row['day']] = $row;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Manage Default Availability</h2>

        <form id="availabilityForm" method="POST" class="uk-form-stacked">
            <table class="uk-table uk-table-divider">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
                    foreach ($daysOfWeek as $day): ?>
                        <tr>
                            <td><?= $day ?></td>
                            <td><input class="uk-input" type="time" name="start_time[<?= $day ?>]" value="<?= $availability[$day]['start_time'] ?? '' ?>"></td>
                            <td><input class="uk-input" type="time" name="end_time[<?= $day ?>]" value="<?= $availability[$day]['end_time'] ?? '' ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Availability</button>
        </form>
    </div>


    <br/>
    <a href="therapist_dashboard.php">Go back to Therapist Dashboard</a>

    <script>
        document.getElementById("availabilityForm").addEventListener("submit", function (event) {
            event.preventDefault();
            let formData = new FormData(this);

            fetch("../backend/update_default_availability.php", {
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
                });
            })
            .catch(error => console.error("Error:", error));
        });
    </script>
</body>
</html>
