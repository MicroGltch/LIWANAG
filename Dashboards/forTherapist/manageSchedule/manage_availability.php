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
    <link rel="stylesheet" href="/LIWANAG/CSS/uikit-3.22.2/css/uikit.min.css">
    <script src="/LIWANAG/CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="/LIWANAG/CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>


    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="/LIWANAG/CSS/style.css" type="text/css" >

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
        function generateTimeOptions($start, $end, $interval = 30) {
            $startTime = strtotime($start);
            $endTime = strtotime($end);
            $options = "";

            for ($time = $startTime; $time <= $endTime; $time += $interval * 60) {
                $value = date("H:i", $time);           // 24-hour value to submit
                $label = date("g:i A", $time);         // 12-hour label to display
                $options .= "<option value=\"$value\">$label</option>";
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
                    foreach ($daysOfWeek as $day): ?>
                        <tr>
                            <td><?= $day ?></td>
                            <td>
                                <select class="uk-select" name="start_time[<?= $day ?>]">
                                    <option value="">--</option>
                                    <?php
                                    $bizStart = $globalBizHours[$day]['start'] ?? null;
                                    $bizEnd = $globalBizHours[$day]['end'] ?? null;
                                    
                                    if ($bizStart && $bizEnd) {
                                        $timeOptionsHTML = generateTimeOptions($bizStart, $bizEnd);
                                        foreach (explode("</option>", $timeOptionsHTML) as $opt) {
                                            $val = trim(strip_tags($opt));
                                            if ($val === "") continue;
                                            $selected = ($availability[$day]['start_time'] ?? '') === $val ? "selected" : "";
                                            echo "<option value=\"$val\" $selected>$val</option>";
                                        }
                                    }
                                    ?>
                                </select>

                            </td>
                            <td>
                                <select class="uk-select" name="end_time[<?= $day ?>]">
                                    <option value="">--</option>
                                    <?php
                                    $bizStart = $globalBizHours[$day]['start'] ?? null;
                                    $bizEnd = $globalBizHours[$day]['end'] ?? null;

                                    if ($bizStart && $bizEnd) {
                                        $timeOptionsHTML = generateTimeOptions($bizStart, $bizEnd);
                                        foreach (explode("</option>", $timeOptionsHTML) as $opt) {
                                            $val = trim(strip_tags($opt));
                                            if ($val === "") continue;
                                            $selected = ($availability[$day]['start_time'] ?? '') === $val ? "selected" : "";
                                            echo "<option value=\"$val\" $selected>$val</option>";
                                        }
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
