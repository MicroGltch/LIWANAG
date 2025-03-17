<?php
session_start();
?>

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
        <label for="filter_type">Select Duration:</label>
        <select name="filter_type" id="filter_type" required>
            <option value="month">Monthly Report</option>
            <option value="year">Yearly Report [NOT IMPLEMENTED YET]</option>
        </select>
        
        <label for="filter_value">Select Month/Year:</label>
        <input type="month" name="filter_value" id="filter_value" required>
        
        <button type="submit">Generate PDF Report</button>
    </form>

</body>
</html>
