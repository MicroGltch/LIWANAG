<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../../CSS/style.css" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!--SWAL-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ✅ Flatpickr Library for Multi-Date Selection -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        html,
        body {
            background-color: #ffffff !important;
        }
    </style>
</head>

<body>

    <h2>Generate System Analytics Report</h2>

    <!-- 📌 Report Generation Form -->
    <form action="generate_report.php" method="GET">
        <label class="uk-form-label" for="start_date">Select Start Date:</label>
        <input class="uk-input uk-width-1-1" type="date" name="start_date" id="start_date" required>

        <label class="uk-form-label" for="end_date">Select End Date:</label>
        <input class="uk-input uk-width-1-1" type="date" name="end_date" id="end_date" required>

        <div class="uk-text-right">
        <button class="uk-button uk-button-primary uk-margin-top" type="submit" style="border-radius: 15px;">Generate PDF Report</button>
        </div>
    </form>

    <script>
        document.getElementById("start_date").addEventListener("change", function() {
            document.getElementById("end_date").min = this.value; // Prevents selecting an end date before the start date
        });
    </script>

</body>

</html>