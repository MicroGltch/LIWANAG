<?php
require_once "../../../dbconfig.php";
session_start();

// ✅ Check login and role
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../../Accounts/loginpage.php");
    exit();
}

$userid = $_SESSION['account_ID'];

// ✅ Get user info
$stmt = $connection->prepare("SELECT account_FName, account_LName, account_Email, account_PNum, profile_picture FROM users WHERE account_ID = ?");
$stmt->bind_param("s", $userid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $firstName = $userData['account_FName'];
    $lastName = $userData['account_LName'];
    $email = $userData['account_Email'];
    $phoneNumber = $userData['account_PNum'];
    $profilePicture = $userData['profile_picture'] ? '../uploads/client_profile_pictures/' . $userData['profile_picture'] : '../CSS/default.jpg';
} else {
    echo "No Data Found.";
}

$stmt->close();

// ✅ Load settings + blocked dates
$query = "SELECT *, DATE_FORMAT(updated_at, '%M %d, %Y %h:%i %p') AS formatted_updated_at FROM settings LIMIT 1";
$result = $connection->query($query);
$settings = $result->fetch_assoc();
$blockedDates = json_decode($settings['blocked_dates'], true);

// ✅ Load weekly business hours
$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
$bizHoursResult = $connection->query("SELECT * FROM business_hours_by_day");
$bizHours = [];
while ($row = $bizHoursResult->fetch_assoc()) {
    $bizHours[$row['day_name']] = $row;
}

// ✅ Fetch saved date overrides
$exceptionsResult = $connection->query("SELECT * FROM business_hours_exceptions ORDER BY exception_date ASC");
$exceptions = [];
while ($row = $exceptionsResult->fetch_assoc()) {
    $exceptions[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings</title>
    <link rel="stylesheet" href="../../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <link rel="stylesheet" href="../../../CSS/style.css" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="../../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        html, body { 
            background-color: #ffffff !important; 
            } 

            @media (max-width: 640px) {
                .uk-table {
                    font-size: 12px;
                    width: 100%;
                }
                .uk-table th,
                .uk-table td {
                    padding: 6px 4px;
                }

                .uk-table td:first-child {
                    font-size: 11px;
                }

                .uk-table td:nth-child(3) {
                    font-size: 11px;
                }

                .uk-button-small {
                    padding: 0 6px;
                    font-size: 11px;
                    line-height: 20px;
                    height: auto;
                }

                .uk-table-wrapper {
                    width: 100%;
                    margin-bottom: 15px;
                }

                .uk-table th {
                    font-size: 11px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
            }
    </style>
</head>

<body class="uk-container uk-margin-top">
    <h2>System Settings</h2>
    <p><strong>Last Updated:</strong> <?= $settings['formatted_updated_at'] ?? 'Never' ?></p>

    <!-- ✅ WEEKLY HOURS FORM -->
    <form id="weeklyHoursForm" method="POST" class="uk-form-stacked">
        <input type="hidden" name="form_type" value="weekly_hours">
        <h3>Weekly Business Hours</h3>

        <?php foreach ($days as $day):
            $start = $bizHours[$day]['start_time'] ?? '';
            $end   = $bizHours[$day]['end_time'] ?? '';
            $closed = ($start === null && $end === null);
        ?>
            <div class="uk-margin">
                <label class="uk-form-label"><?= $day ?>:</label>
                <div class="uk-grid-small" uk-grid>
                    <div class="uk-width-1-3">
                        <input class="uk-input" type="time" name="weekly_hours[<?= $day ?>][start]" value="<?= $start ?>">
                    </div>
                    <div class="uk-width-1-3">
                        <input class="uk-input" type="time" name="weekly_hours[<?= $day ?>][end]" value="<?= $end ?>">
                    </div>
                    <div class="uk-width-1-3">
                        <label><input class="uk-checkbox" type="checkbox" name="weekly_hours[<?= $day ?>][closed]" <?= $closed ? 'checked' : '' ?>> Closed</label>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="uk-text-right">
            <button class="uk-button uk-button-primary uk-margin-top" style="border-radius: 15px;" type="submit">Save Weekly Hours</button>
        </div>
    </form>

    <hr>

    <!-- ✅ OVERRIDE FORM -->
    <form id="overrideDateForm" method="POST" class="uk-form-stacked uk-margin-top">
        <input type="hidden" name="form_type" value="date_override">
        <h3 class="uk-text-bold">Override Specific Date</h3>

        <div class="uk-margin">
            <label>Date to Override:</label>
            <input class="uk-input" type="date" name="exception_date" required>
        </div>
        <div class="uk-margin">
            <label>Start Time:</label>
            <input class="uk-input" type="time" name="exception_start">
        </div>
        <div class="uk-margin">
            <label>End Time:</label>
            <input class="uk-input" type="time" name="exception_end">
        </div>
        <div class="uk-margin">
            <label><input class="uk-checkbox" name="exception_closed" type="checkbox"> Mark this date as closed</label>
        </div>
        <div class="uk-text-right">
            <button class="uk-button uk-button-primary" style="border-radius: 15px;" type="submit">Save Date Override</button>
        </div>
    </form>
<hr>
    <?php if (count($exceptions) > 0): ?>
        <h3 class="uk-text-bold uk-margin-top">Saved Overrides</h3>
        <table class="uk-table uk-table-divider uk-table-small">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Time Range</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exceptions as $ex): 
                    $dateFormatted = date("F j, Y", strtotime($ex['exception_date']));
                    $status = ($ex['start_time'] && $ex['end_time']) ? "Open" : "Closed";
                    $timeRange = ($status === "Open") 
                        ? date("g:i A", strtotime($ex['start_time'])) . " - " . date("g:i A", strtotime($ex['end_time']))
                        : "—";
                ?>
                    <tr data-date="<?= $ex['exception_date'] ?>">
                        <td><?= $dateFormatted ?></td>
                        <td><?= $status ?></td>
                        <td><?= $timeRange ?></td>
                        <td>
                            <button class="uk-button uk-button-danger uk-button-small delete-override-btn" style="border-radius: 15px;">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="uk-text-muted uk-margin-small-top">No saved override dates yet.</p>
    <?php endif; ?>

    <hr>
    <!-- ✅ GLOBAL SETTINGS FORM -->
    <form id="globalSettingsForm" method="POST" class="uk-form-stacked uk-margin-top">
        <input type="hidden" name="form_type" value="global_settings">
        <h3 class="uk-text-bold">Global Settings</h3>

        <div class="uk-margin">
            <label>Max Booking Days (Advance):</label>
            <input class="uk-input" type="number" name="max_days_advance" value="<?= $settings['max_days_advance']; ?>" min="1" max="60" required>
        </div>
        <div class="uk-margin">
            <label>Min Days Before Appointment:</label>
            <input class="uk-input" type="number" name="min_days_advance" value="<?= $settings['min_days_advance']; ?>" min="0" max="30" required>
        </div>
        <!-- <div class="uk-margin">
            <label>Blocked Dates:</label>
            <input class="uk-input" type="text" id="blocked_dates" name="blocked_dates" placeholder="Select dates..." required>
        </div> -->
        <div class="uk-margin">
            <label>Initial Evaluation Duration (Minutes):</label>
            <input class="uk-input" type="number" name="initial_eval_duration" value="<?= $settings['initial_eval_duration']; ?>" min="30" max="180" required>
        </div>
        <div class="uk-margin">
            <label>Playgroup Duration (Minutes):</label>
            <input class="uk-input" type="number" name="playgroup_duration" value="<?= $settings['playgroup_duration']; ?>" min="60" max="240" required>
        </div>
        <div class="uk-margin">
            <label>OT Duration (Minutes):</label>
            <input class="uk-input" type="number" name="service_ot_duration" value="<?= $settings['service_ot_duration']; ?>" min="30" max="180" required>
        </div>
        <div class="uk-margin">
            <label>BT Duration (Minutes):</label>
            <input class="uk-input" type="number" name="service_bt_duration" value="<?= $settings['service_bt_duration']; ?>" min="30" max="180" required>
        </div>

        <div class="uk-text-right">
            <button class="uk-button uk-button-primary" style="border-radius: 15px;" type="submit">Save Global Settings</button>
        </div>
    </form>

    <!-- ✅ JS for Forms and Flatpickr -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        flatpickr("#blocked_dates", {
            minDate: "today",
            altInput: true,
            mode: "multiple",
            dateFormat: "Y-m-d",
            defaultDate: <?= json_encode($blockedDates) ?>
        });

        function handleFormSubmit(formId) {
        const form = document.getElementById(formId);

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const formData = new FormData(form);
            fetch("update_timetable_settings.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                Swal.fire({
                    title: data.status === "success" ? "Success!" : "Error!",
                    html: `<b>Details:</b><br>${data.changed || data.message}`,
                    icon: data.status === "success" ? "success" : "error"
                }).then(() => {
                    if (formId === "globalSettingsForm") {
                        document.querySelector("p strong").nextSibling.textContent = " " + (data.updated_at || '');
                    }

                    // ✅ Optional: Reset only the override form
                    if (formId === "overrideDateForm" && data.status === "success") {
                        form.reset();
                    }

                    // Optional: Refresh other forms if needed
                    location.reload();
                });
            })
            .catch(err => {
                console.error("Error:", err);
                Swal.fire("Error", "Something went wrong. Check the console.", "error");
            });
        });
    }


        handleFormSubmit("weeklyHoursForm");
        handleFormSubmit("overrideDateForm");
        handleFormSubmit("globalSettingsForm");
    });

    // 🗑 Delete override handler
    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("delete-override-btn")) {
            const row = e.target.closest("tr");
            const date = row.dataset.date;

            Swal.fire({
                title: "Remove Override?",
                text: `Are you sure you want to remove the override for ${date}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, remove it",
                cancelButtonText: "Cancel"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("remove_override_date.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: "exception_date=" + encodeURIComponent(date)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === "success") {
                            row.remove();
                            Swal.fire("Removed!", data.message, "success");
                        } else {
                            Swal.fire("Error", data.message, "error");
                        }
                    })
                    .catch(() => {
                        Swal.fire("Error", "Something went wrong.", "error");
                    });
                }
            });
        }
    });

    </script>
</body>
</html>