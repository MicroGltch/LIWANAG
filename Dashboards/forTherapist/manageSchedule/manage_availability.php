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

// Fetch per-day business hours
$globalBizHours = [];
$bizQuery = $connection->query("SELECT day_name, start_time, end_time FROM business_hours_by_day");
while ($row = $bizQuery->fetch_assoc()) {
    $globalBizHours[$row['day_name']] = [
        'start' => $row['start_time'],
        'end' => $row['end_time']
    ];
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
    <link rel="stylesheet" href="../../../CSS/uikit-3.22.2/css/uikit.min.css">
    <script src="../../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>


    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../../CSS/style.css" type="text/css" >

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!--SWAL-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



<style>
    html, body {
    background-color: #ffffff !important;
}

</style>
</head>
<body>
    <div class="uk-container uk-margin-top">
    <h4 class="uk-card-title uk-text-bold">Manage Default Availability</h4>

    <?php
function generateTimeOptions($start, $end, $selectedValue = null, $interval = 30) {
    $startTime = strtotime($start);
    $endTime = strtotime($end);
    $options = "";

    // Ensure valid times - add error handling if needed
    if ($startTime === false || $endTime === false || $startTime > $endTime) {
        return ""; // Return empty if times are invalid
    }

    for ($time = $startTime; $time <= $endTime; $time += $interval * 60) {
        $value = date("H:i", $time);           // 24-hour value (e.g., "09:00")
        $label = date("g:i A", $time);         // 12-hour label (e.g., "9:00 AM")

        // Check if this option's value matches the saved value
        $selectedAttr = ($selectedValue !== null && $value === $selectedValue) ? " selected" : "";

        $options .= "<option value=\"$value\"{$selectedAttr}>$label</option>";
    }

    return $options;
}
?>

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
    foreach ($daysOfWeek as $day):
        // Get the saved times for this day
        $savedStartTimeRaw = $availability[$day]['start_time'] ?? null;
        $savedEndTimeRaw = $availability[$day]['end_time'] ?? null;

        // Format to HH:MM for comparison, only if the value exists
        $savedStartTime = $savedStartTimeRaw ? substr($savedStartTimeRaw, 0, 5) : null; // Extract first 5 chars (HH:MM)
        $savedEndTime = $savedEndTimeRaw ? substr($savedEndTimeRaw, 0, 5) : null;     // Extract first 5 chars (HH:MM)

        // Alternative using date() and strtotime() - slightly more robust if format isn't guaranteed
        // $savedStartTime = $savedStartTimeRaw ? date('H:i', strtotime($savedStartTimeRaw)) : null;
        // $savedEndTime = $savedEndTimeRaw ? date('H:i', strtotime($savedEndTimeRaw)) : null;

        // Get business hours for this day
        $bizStart = $globalBizHours[$day]['start'] ?? null;
        $bizEnd = $globalBizHours[$day]['end'] ?? null;
            ?>
        <tr>
            <td><?= htmlspecialchars($day) // Good practice to escape output ?></td>
            <td>
                <select class="uk-select" name="start_time[<?= htmlspecialchars($day) ?>]">
                    <option value="">--</option> <?php // Add empty value for the default option ?>
                    <?php
                    // Only generate options if business hours are set for the day
                    if ($bizStart && $bizEnd) {
                        // Pass the saved start time to the function
                        echo generateTimeOptions($bizStart, $bizEnd, $savedStartTime);
                    }
                    ?>
                </select>
            </td>
            <td>
                <select class="uk-select" name="end_time[<?= htmlspecialchars($day) ?>]">
                    <option value="">--</option> <?php // Add empty value for the default option ?>
                    <?php
                    // Only generate options if business hours are set for the day
                    if ($bizStart && $bizEnd) {
                        // Pass the saved end time to the function
                        echo generateTimeOptions($bizStart, $bizEnd, $savedEndTime);
                    }
                    ?>
                </select>
            </td>
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

    // Clear previous error messages
    document.querySelectorAll(".error-message").forEach(el => el.remove());

    let formData = new FormData(this);

    fetch("update_default_availability.php", {
        method: "POST",
        body: formData
    })
    .then(async response => {
        let data;
        try {
            data = await response.json();
        } catch (err) {
            throw new Error("Invalid JSON response from server.");
        }

        if (data.status === "success") {
            Swal.fire({
                title: "Success!",
                text: data.message,
                icon: "success",
                confirmButtonText: "OK"
            });
        } else if (data.status === "field_error") {
            // Show per-field errors
            for (const [day, message] of Object.entries(data.field_errors)) {
                const row = [...document.querySelectorAll("tbody tr")]
                    .find(tr => tr.querySelector("td")?.innerText.trim() === day);

                if (row) {
                    const td = document.createElement("td");
                    td.colSpan = 3;
                    td.classList.add("error-message");
                    td.style.color = "red";
                    td.style.fontSize = "0.875rem";
                    td.textContent = message;

                    const existingError = row.nextElementSibling;
                    if (!existingError?.classList.contains("error-message")) {
                        row.after(td);
                    }
                }
            }

            Swal.fire({
                title: "Invalid Input",
                text: "Some days were not saved due to input errors.",
                icon: "error",
                confirmButtonText: "Review"
            });
        } else {
            Swal.fire({
                title: "Error!",
                text: data.message || "Something went wrong.",
                icon: "error",
                confirmButtonText: "OK"
            });
        }
    })
    .catch(error => {
        console.error("Error caught:", error);
        Swal.fire({
            title: "Error!",
            text: "Something went wrong. Please try again later.",
            icon: "error",
            confirmButtonText: "OK"
        });
    });
});
</script>

</body>
</html>
