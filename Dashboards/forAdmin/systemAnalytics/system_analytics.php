<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics</title>
</head>
<body>

    <h2>Generate System Analytics Report</h2>

    <!-- 📌 Report Generation Form -->
    <form action="generate_report.php" method="GET">
        <label for="start_date">Select Start Date:</label>
        <input type="date" name="start_date" id="start_date" required>

        <label for="end_date">Select End Date:</label>
        <input type="date" name="end_date" id="end_date" required>

        <button type="submit">Generate PDF Report</button>
    </form>

    <script>
        document.getElementById("start_date").addEventListener("change", function() {
            document.getElementById("end_date").min = this.value; // Prevents selecting an end date before the start date
        });
    </script>

</body>
</html>
