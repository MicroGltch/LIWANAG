<?php
    require_once "../../../dbconfig.php";
    session_start();

    // ✅ Restrict Access to Therapists Only
    // if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    //     header("Location: ../../../loginpage.php");
    //     exit();
    // }

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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="/LIWANAG/CSS/uikit-3.22.2/css/uikit.min.css">
<script src="/LIWANAG/CSS/uikit-3.22.2/js/uikit.min.js"></script>
<script src="/LIWANAG/CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>


    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="/LIWANAG/CSS/style.css" type="text/css" >

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!-- Include Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    html, body {
    background-color: #ffffff !important;
}

</style>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h4>Manage Default Availability</h4>

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
            <div class="uk-width-1-1 uk-text-right uk-margin-top">
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Availability</button>
            </div>
           
        </form>
    </div>

    <script>
        document.getElementById("availabilityForm").addEventListener("submit", function (event) {
            event.preventDefault();
            let formData = new FormData(this);

            fetch("update_default_availability.php", {
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
