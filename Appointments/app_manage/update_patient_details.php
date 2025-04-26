<?php
require_once "../../dbconfig.php";
session_start();

// Permission Check...
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") { /* ... exit ... */
}

// Initialize arrays...
$existingSchedules = [];
$existingMakeupSchedules = [];
$patientInfo = null;
$therapistAvailability = [];
$patientScheduleConflicts = [];
$availableDays = [];
$therapistID = $_SESSION['account_ID'];
$today = date('Y-m-d');

// Fetch Therapist Availability... (Code as before)
$availQuery = "SELECT day, start_time, end_time FROM therapist_default_availability WHERE therapist_id = ?";
$stmtAvail = $connection->prepare($availQuery);
if ($stmtAvail) {
    $stmtAvail->bind_param("i", $therapistID);
    $stmtAvail->execute();
    $resultAvail = $stmtAvail->get_result();
    while ($row = $resultAvail->fetch_assoc()) {
        $day = $row['day'];
        if (!isset($therapistAvailability[$day])) $therapistAvailability[$day] = [];
        $therapistAvailability[$day][] = ['start' => $row['start_time'], 'end' => $row['end_time']];
    }
    $stmtAvail->close();
} else {
    error_log("Error fetching therapist availability: " . $connection->error);
}

// Fetch Eligible Patients... (Code as before)
$query = "SELECT DISTINCT p.patient_id, p.first_name, p.last_name, p.service_type, p.status FROM appointments a JOIN patients p ON a.patient_id = p.patient_id WHERE a.therapist_id = ? AND a.status = 'Completed' AND p.status IN ('enrolled', 'pending') ORDER BY p.last_name, p.first_name";
$stmt = $connection->prepare($query);
if (!$stmt) die("Prepare failed (patients)");
$stmt->bind_param("i", $therapistID);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$patient_id = $_GET['patient_id'] ?? null;

// Fetch Patient Data and Conflicts if patient_id is valid... (Code as before)
if ($patient_id) {
    $canManagePatient = false;
    foreach ($patients as $p) {
        if ($p['patient_id'] == $patient_id) {
            $canManagePatient = true;
            break;
        }
    }
    if (!$canManagePatient) {
        $_SESSION['error_message'] = "Permission denied.";
        $patient_id = null;
    } else {
        // Fetch patient info, existing schedules, makeup schedules... (Code as before)
        $stmtPI = $connection->prepare("SELECT * FROM patients WHERE patient_id = ?");
        if ($stmtPI) {
            $stmtPI->bind_param("i", $patient_id);
            $stmtPI->execute();
            $patientInfo = $stmtPI->get_result()->fetch_assoc();
            $stmtPI->close();
        }
        $stmtDS = $connection->prepare("SELECT * FROM patient_default_schedules WHERE patient_id = ?");
        if ($stmtDS) {
            $stmtDS->bind_param("i", $patient_id);
            $stmtDS->execute();
            $existingSchedules = $stmtDS->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtDS->close();
        }
        $stmtMS = $connection->prepare("SELECT * FROM patient_makeup_schedules WHERE patient_id = ?");
        if ($stmtMS) {
            $stmtMS->bind_param("i", $patient_id);
            $stmtMS->execute();
            $existingMakeupSchedules = $stmtMS->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtMS->close();
        }

        // Fetch Conflicts... (Code as before)
        $stmtConflict = $connection->prepare("SELECT day_of_week, start_time FROM patient_default_schedules WHERE therapist_id = ? AND patient_id != ?");
        if ($stmtConflict) {
            $stmtConflict->bind_param("ii", $therapistID, $patient_id);
            $stmtConflict->execute();
            $resultConflict = $stmtConflict->get_result();
            while ($row = $resultConflict->fetch_assoc()) {
                $day = $row['day_of_week'];
                $startTime = date('H:i', strtotime($row['start_time']));
                if (!isset($patientScheduleConflicts[$day])) $patientScheduleConflicts[$day] = [];
                $patientScheduleConflicts[$day][$startTime] = true;
            }
            $stmtConflict->close();
        }
    }
}

$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

// --- OPTIMIZED Helper Function: Check if ANY slot exists ---
function hasAnyNonConflictingSlot($startTimeStr, $endTimeStr, $conflicts = [], $intervalMinutes = 60)
{
    $start = strtotime($startTimeStr);
    $end = strtotime($endTimeStr);
    $sessionDurationSeconds = 60 * 60;
    if ($start === false || $end === false) return false;
    $current = $start;
    while ($current < $end) {
        $slotEnd = $current + $sessionDurationSeconds;
        if ($slotEnd <= $end) {
            $slotStartTimeFormatted = date('H:i', $current);
            if (!isset($conflicts[$slotStartTimeFormatted])) {
                return true;
            }
        }
        $current += $intervalMinutes * 60;
    }
    return false;
}

// --- OPTIMIZED Pre-calculation for availableDays ---
foreach ($days as $day) {
    $dayAvail = $therapistAvailability[$day] ?? [];
    $dayConf = $patientScheduleConflicts[$day] ?? [];
    $hasSlots = false;
    if (!empty($dayAvail)) {
        foreach ($dayAvail as $block) {
            if (hasAnyNonConflictingSlot($block['start'], $block['end'], $dayConf)) {
                $hasSlots = true;
                break;
            }
        }
    }
    $availableDays[$day] = $hasSlots;
}
error_log("Pre-calculated Available Days: " . json_encode($availableDays));

// --- Helper Function: generateTimeSlotsInternal ---
function generateTimeSlotsInternal($startTimeStr, $endTimeStr, $conflicts = [], $intervalMinutes = 60)
{
    $slots = [];
    $start = strtotime($startTimeStr);
    $end = strtotime($endTimeStr);
    $sessionDurationSeconds = 60 * 60;
    if ($start === false || $end === false) return [];
    $current = $start;
    while ($current < $end) {
        $slotEnd = $current + $sessionDurationSeconds;
        if ($slotEnd <= $end) {
            $slotStartTimeFormatted = date('H:i', $current);
            if (!isset($conflicts[$slotStartTimeFormatted])) {
                $slots[] = ['start' => $slotStartTimeFormatted, 'end' => date('H:i', $slotEnd)];
            }
        }
        $current += $intervalMinutes * 60;
    }
    return $slots;
}

// --- Helper Function: generateTimeOptions HTML ---
// --- *** REVISED generateTimeOptions Function (Formats Display Time) *** ---
function formatTimeForDisplay($timeHHMMSS)
{
    if (empty($timeHHMMSS)) return '';
    $timestamp = strtotime($timeHHMMSS);
    return $timestamp ? date('g:i A', $timestamp) : ''; // Format as 1:00 PM
}

function generateTimeOptions($dayAvailability, $selectedStartTime = null, $selectedEndTime = null, $conflicts = [])
{
    $optionsStart = '';
    $optionsEnd = '';
    $allSlots = [];
    // Use HH:MM for comparison logic
    $selectedStartTimeHHMM = $selectedStartTime ? date('H:i', strtotime($selectedStartTime)) : null;
    $selectedEndTimeHHMM = $selectedEndTime ? date('H:i', strtotime($selectedEndTime)) : null;
    $foundSelectedStart = false;

    if (!empty($dayAvailability)) {
        foreach ($dayAvailability as $availBlock) {
            $allSlots = array_merge($allSlots, generateTimeSlotsInternal($availBlock['start'], $availBlock['end'], $conflicts));
        }
    }
    usort($allSlots, function ($a, $b) {
        return strcmp($a['start'], $b['start']);
    });

    $distinctEndTimes = [];
    if (empty($allSlots)) {
        if (!empty($dayAvailability)) {
            $optionsStart = '<option value="">No Conflict-Free Slots</option>';
        } else {
            $optionsStart = '<option value="">Not Available</option>';
        }
        $optionsEnd = '<option value="">N/A</option>';
    } else {
        $optionsStart = '<option value="">Select Start Time</option>';
        foreach ($allSlots as $slot) {
            $isSelected = ($slot['start'] == $selectedStartTimeHHMM);
            $startSelectedAttr = $isSelected ? ' selected' : '';
            $displayText = formatTimeForDisplay($slot['start']); // Format for display
            $optionsStart .= "<option value=\"{$slot['start']}\"{$startSelectedAttr}>{$displayText}</option>"; // Use HH:MM value, AM/PM text
            $distinctEndTimes[$slot['end']] = true;
            if ($isSelected) $foundSelectedStart = true;
        }
    }

    // Handle saved time display (if it conflicts or is N/A) - still use HH:MM for internal logic
    if ($selectedStartTimeHHMM && !$foundSelectedStart && isset($conflicts[$selectedStartTimeHHMM])) {
        $displayText = formatTimeForDisplay($selectedStartTimeHHMM);
        $optionsStart = "<option value=\"\" disabled selected>Saved: {$displayText} (Conflict)</option>" . $optionsStart;
    } elseif ($selectedStartTimeHHMM && !$foundSelectedStart && !empty($dayAvailability)) {
        $displayText = formatTimeForDisplay($selectedStartTimeHHMM);
        $optionsStart = "<option value=\"\" disabled selected>Saved: {$displayText} (N/A)</option>" . $optionsStart;
    }

    // Generate end time options
    $optionsEnd = '<option value="">Select End Time</option>';
    if (!empty($distinctEndTimes)) {
        ksort($distinctEndTimes);
        foreach (array_keys($distinctEndTimes) as $endTimeHHMM) { // Key is HH:MM
            $endSelectedAttr = ($endTimeHHMM == $selectedEndTimeHHMM) ? ' selected' : '';
            $displayText = formatTimeForDisplay($endTimeHHMM); // Format for display
            $optionsEnd .= "<option value=\"{$endTimeHHMM}\"{$endSelectedAttr}>{$displayText}</option>"; // Use HH:MM value, AM/PM text
        }
    }
    if ($selectedEndTimeHHMM && !isset($distinctEndTimes[$selectedEndTimeHHMM]) && !empty($dayAvailability)) {
        $displayText = formatTimeForDisplay($selectedEndTimeHHMM);
        $optionsEnd = "<option value=\"\" disabled selected>Saved: {$displayText} (N/A)</option>" . $optionsEnd;
    } elseif (empty($distinctEndTimes) && empty($allSlots) && !empty($dayAvailability)) {
        $optionsEnd = '<option value="">N/A</option>';
    }

    return ['start' => $optionsStart, 'end' => $optionsEnd];
}
// --- *** END REVISED generateTimeOptions Function *** ---
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Update Patient Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.7.3/dist/css/uikit.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.7.3/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.7.3/dist/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-container.swal2-backdrop-show {
            background: rgba(0, 0, 0, 0.6) !important;
        }
    </style>
</head>

<body>
    <div class="uk-container uk-margin-top">
        <h2 class="uk-heading-line"><span>Update Patient Details</span></h2>

        <!-- Error Message Display -->
        <?php if (isset($_SESSION['error_message'])): ?> <div class="uk-alert-danger" uk-alert><a class="uk-alert-close" uk-close></a>
                <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
            </div> <?php unset($_SESSION['error_message']);
                endif; ?>

        <form id="update-patient-form" action="../app_process/save_patient_details.php" method="POST" class="uk-form-stacked">
            <!-- Patient Selection -->
            <div class="uk-margin"> <label class="uk-form-label">Select Patient</label>
                <div class="uk-form-controls"><select name="patient_id" id="patient_id" class="uk-select" onchange="if(this.value) window.location.href='?patient_id='+this.value">
                        <option value="">-- Select Patient --</option> <?php foreach ($patients as $patient): ?> <option value="<?= htmlspecialchars($patient['patient_id']) ?>" <?= ($patient['patient_id'] == $patient_id) ? 'selected' : '' ?>><?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']) ?> (<?= htmlspecialchars($patient['service_type']) ?>)</option> <?php endforeach; ?>
                    </select></div>
            </div>

            <?php if ($patient_id && $patientInfo): ?>
                <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">
                <!-- Service Type & Status -->
                <div class="uk-grid-small uk-child-width-expand@s" uk-grid>
                    <div> <label class="uk-form-label">Service Type</label>
                        <div class="uk-form-controls"><select name="service_type" class="uk-select" required><?php if ($patientInfo['service_type'] == 'For Evaluation'): ?><option value="For Evaluation" selected>For Evaluation</option><?php endif; ?><option value="Occupational Therapy" <?= $patientInfo['service_type'] == 'Occupational Therapy' ? 'selected' : '' ?>>Occupational Therapy</option>
                                <option value="Behavioral Therapy" <?= $patientInfo['service_type'] == 'Behavioral Therapy' ? 'selected' : '' ?>>Behavioral Therapy</option>
                            </select></div>
                    </div>
                    <div> <label class="uk-form-label">Status</label>
                        <div class="uk-form-controls"><select name="status" class="uk-select" required><?php if (($patientInfo['status'] ?? 'pending') == 'pending'): ?><option value="pending" selected>Pending</option><?php endif; ?><option value="enrolled" <?= ($patientInfo['status'] ?? '') == 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                                <option value="declined_enrollment" <?= ($patientInfo['status'] ?? '') == 'declined_enrollment' ? 'selected' : '' ?>>Declined  Enrollment</option>
                                <option value="completed" <?= ($patientInfo['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed Program</option>
                                <option value="cancelled" <?= ($patientInfo['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select></div>
                    </div>
                </div>

                <!-- Default Schedules -->
                <h3>Default Schedules</h3>
                <div id="default-schedules" class="uk-margin">
                    <?php if (!empty($existingSchedules)): foreach ($existingSchedules as $index => $schedule): ?> <?php $day = $schedule['day_of_week'];
                                                                                                                    $dayAvailability = $therapistAvailability[$day] ?? [];
                                                                                                                    $dayConflicts = $patientScheduleConflicts[$day] ?? [];
                                                                                                                    $timeOptions = generateTimeOptions($dayAvailability, $schedule['start_time'], $schedule['end_time'], $dayConflicts); ?> <div class="uk-margin uk-card uk-card-default uk-card-body default-schedule-item"> <input type="hidden" name="default_schedule_id[<?= $index ?>]" value="<?= htmlspecialchars($schedule['id']) ?>">
                                <div class="uk-grid-small" uk-grid>
                                    <div class="uk-width-1-3@s"> <label class="uk-form-label">Day:</label> <select name="default_day[<?= $index ?>]" class="uk-select uk-margin-small-bottom default-day-select" required> <?php foreach ($days as $d): $isDisabled = !$availableDays[$d];
                                                                                                                                                                                                                                if ($day == $d) $isDisabled = false; ?> <option value="<?= $d ?>" <?= ($day == $d) ? 'selected' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>><?= $d ?> <?= $isDisabled ? '(No Slots)' : '' ?></option> <?php endforeach; ?> </select></div>
                                    <div class="uk-width-expand"> <label class="uk-form-label">Start Time:</label> <select name="default_start_time[<?= $index ?>]" class="uk-select default-start-time-select" required data-saved-start="<?= htmlspecialchars($schedule['start_time']) ?>"><?= $timeOptions['start'] ?></select></div>
                                    <div class="uk-width-expand"> <label class="uk-form-label">End Time:</label> <select name="default_end_time[<?= $index ?>]" class="uk-select default-end-time-select" required data-saved-end="<?= htmlspecialchars($schedule['end_time']) ?>"><?= $timeOptions['end'] ?></select></div>
                                    <div class="uk-width-auto@s uk-text-right uk-margin-small-top"> <label class="uk-form-label"> </label> <button type="button" class="uk-button uk-button-danger remove-schedule-btn" title="Remove"><span uk-icon="trash"></span></button> </div>
                                </div>
                            </div> <?php endforeach;
                            endif; ?>
                    <template id="default-schedule-template">
                        <div class="uk-margin uk-card uk-card-default uk-card-body default-schedule-item">
                            <div class="uk-grid-small" uk-grid>
                                <div class="uk-width-1-3@s"> <label class="uk-form-label">Day:</label> <select name="default_day[]" class="uk-select uk-margin-small-bottom default-day-select" required>
                                        <option value="">-- Select Day --</option> <?php foreach ($days as $d): $isDisabled = !$availableDays[$d]; ?> <option value="<?= $d ?>" <?= $isDisabled ? 'disabled' : '' ?>><?= $d ?> <?= $isDisabled ? '(No Slots)' : '' ?></option> <?php endforeach; ?>
                                    </select> </div>
                                <div class="uk-width-expand"> <label class="uk-form-label">Start Time:</label> <select name="default_start_time[]" class="uk-select default-start-time-select" required>
                                        <option value="">Select Day First</option>
                                    </select> </div>
                                <div class="uk-width-expand"> <label class="uk-form-label">End Time:</label> <select name="default_end_time[]" class="uk-select default-end-time-select" required>
                                        <option value="">Select Day First</option>
                                    </select> </div>
                                <div class="uk-width-auto@s uk-text-right uk-margin-small-top"> <label class="uk-form-label"> </label> <button type="button" class="uk-button uk-button-danger remove-schedule-btn" title="Remove"><span uk-icon="trash"></span></button> </div>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" onclick="addDefaultSchedule()"><span uk-icon="plus"></span> Add Default</button>

                <!-- Makeup Schedules -->
                <h3>Makeup Schedules</h3>
                <div id="makeup-schedules" class="uk-margin">
                    <?php if (!empty($existingMakeupSchedules)): foreach ($existingMakeupSchedules as $index => $makeup): ?> <div class="uk-margin uk-card uk-card-default uk-card-body makeup-schedule-item"> <input type="hidden" name="makeup_schedule_id[<?= $index ?>]" value="<?= htmlspecialchars($makeup['id']) ?>">
                                <div class="uk-grid-small" uk-grid>
                                    <!-- *** Add min attribute to date input *** -->
                                    <div class="uk-width-1-3@s"> <label class="uk-form-label">Date:</label> <input type="date" name="makeup_date[<?= $index ?>]" class="uk-input makeup-date-input" value="<?= htmlspecialchars($makeup['date']) ?>" required min="<?= $today ?>"> </div>
                                    <!-- *** End add min attribute *** -->
                                    <div class="uk-width-expand"> <label class="uk-form-label">Start Time:</label> <select name="makeup_start_time[<?= $index ?>]" class="uk-select makeup-start-time-select" required data-saved-start="<?= htmlspecialchars($makeup['start_time']) ?>">
                                            <option value="">Loading...</option>
                                        </select> </div>
                                    <div class="uk-width-expand"> <label class="uk-form-label">End Time:</label> <select name="makeup_end_time[<?= $index ?>]" class="uk-select makeup-end-time-select" required data-saved-end="<?= htmlspecialchars($makeup['end_time']) ?>">
                                            <option value="">Loading...</option>
                                        </select> </div>
                                    <div class="uk-width-1-1"> <label class="uk-form-label">Notes:</label> <input type="text" name="makeup_notes[<?= $index ?>]" class="uk-input" value="<?= htmlspecialchars($makeup['notes'] ?? '') ?>" placeholder="Optional"> </div>
                                    <div class="uk-width-1-1 uk-text-right"> <button type="button" class="uk-button uk-button-danger uk-margin-small-top remove-schedule-btn" title="Remove"><span uk-icon="trash"></span></button> </div>
                                </div>
                            </div> <?php endforeach;
                            endif; ?>
                    <template id="makeup-schedule-template">
                        <div class="uk-margin uk-card uk-card-default uk-card-body makeup-schedule-item">
                            <div class="uk-grid-small" uk-grid>
                                <!-- *** Add min attribute to date input *** -->
                                <div class="uk-width-1-3@s"> <label class="uk-form-label">Date:</label> <input type="date" name="makeup_date[]" class="uk-input makeup-date-input" required min="<?= $today ?>"> </div>
                                <!-- *** End add min attribute *** -->
                                <div class="uk-width-expand"> <label class="uk-form-label">Start Time:</label> <select name="makeup_start_time[]" class="uk-select makeup-start-time-select" required>
                                        <option value="">Select Date First</option>
                                    </select> </div>
                                <div class="uk-width-expand"> <label class="uk-form-label">End Time:</label> <select name="makeup_end_time[]" class="uk-select makeup-end-time-select" required>
                                        <option value="">Select Date First</option>
                                    </select> </div>
                                <div class="uk-width-1-1"> <label class="uk-form-label">Notes:</label> <input type="text" name="makeup_notes[]" class="uk-input" placeholder="Optional"> </div>
                                <div class="uk-width-1-1 uk-text-right"> <button type="button" class="uk-button uk-button-danger uk-margin-small-top remove-schedule-btn" title="Remove"><span uk-icon="trash"></span></button> </div>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" onclick="addMakeupSchedule()"><span uk-icon="plus"></span> Add Makeup</button>
                <br><br>
                <button type="submit" class="uk-button uk-button-secondary uk-width-1-1">Save All Changes</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- JavaScript Section -->
    <script>
        const therapistAvailability = <?php echo json_encode($therapistAvailability); ?>;
        const patientConflicts = <?php echo json_encode($patientScheduleConflicts); ?>;
        const daysOfWeek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
        let isUpdatingSiblings = false;

        // --- JS Helper Function: Format Time to AM/PM ---
        function formatTimeAmPm(timeHHMM) {
            if (!timeHHMM) return '';
            try {
                const [hours, minutes] = timeHHMM.split(':');
                const hourInt = parseInt(hours, 10);
                const minStr = String(minutes).padStart(2, '0');
                const ampm = hourInt >= 12 ? 'PM' : 'AM';
                const hour12 = hourInt % 12 || 12; // Convert 0 to 12 for 12 AM/PM
                return `${hour12}:${minStr} ${ampm}`;
            } catch (e) {
                console.error("Error formatting time:", timeHHMM, e);
                return timeHHMM; // Return original on error
            }
        }

        // --- JS Helper Functions ---
        function generateTimeSlotsJS(startTimeStr, endTimeStr, conflicts = {}, intervalMinutes = 60) {
            /* ... As in previous correct version ... */
            const slots = [];
            if (!startTimeStr || !endTimeStr) return slots;
            const baseDate = '1970-01-01';
            const start = new Date(`${baseDate}T${startTimeStr}`);
            const end = new Date(`${baseDate}T${endTimeStr}`);
            if (isNaN(start) || isNaN(end) || start >= end) return slots;
            const startTotalMinutes = start.getHours() * 60 + start.getMinutes();
            const endTotalMinutes = end.getHours() * 60 + end.getMinutes();
            const sessionDurationMinutes = 60;
            let currentTotalMinutes = startTotalMinutes;
            while (currentTotalMinutes < endTotalMinutes) {
                const slotEndTotalMinutes = currentTotalMinutes + sessionDurationMinutes;
                if (slotEndTotalMinutes <= endTotalMinutes) {
                    const cH = String(Math.floor(currentTotalMinutes / 60)).padStart(2, '0');
                    const cM = String(currentTotalMinutes % 60).padStart(2, '0');
                    const slotStartFmt = `${cH}:${cM}`;
                    if (!conflicts[slotStartFmt]) {
                        const slotEndH = String(Math.floor(slotEndTotalMinutes / 60)).padStart(2, '0');
                        const slotEndM = String(slotEndTotalMinutes % 60).padStart(2, '0');
                        slots.push({
                            start: slotStartFmt,
                            end: `${slotEndH}:${slotEndM}`
                        });
                    }
                }
                currentTotalMinutes += intervalMinutes;
            }
            return slots;
        }

        function isTimeGenerallyAvailable(day, timeToCheck, dbConflicts) {
            /* ... As in previous correct version ... */
            const dayAvail = therapistAvailability[day] || [];
            if (dayAvail.length === 0) return false;
            let isAvailable = false;
            dayAvail.forEach(block => {
                const slots = generateTimeSlotsJS(block.start, block.end, dbConflicts);
                if (slots.some(s => s.start === timeToCheck)) {
                    isAvailable = true;
                }
            });
            return isAvailable;
        }

        function getLocalConflicts(selectedDay, currentItem) {
            /* ... As in previous correct version ... */
            const conflicts = {};
            document.querySelectorAll('.default-schedule-item').forEach(item => {
                if (item !== currentItem) {
                    const daySelect = item.querySelector('.default-day-select');
                    const startSelect = item.querySelector('.default-start-time-select');
                    if (daySelect?.value === selectedDay && startSelect?.value) {
                        conflicts[startSelect.value] = true;
                    }
                }
            });
            return conflicts;
        }

        // --- JS: updateDefaultTimeSelects (REVISED - Use AM/PM Display) ---
        function updateDefaultTimeSelects(scheduleItem) {
            // ... (Keep validation and setup logic as before) ...
            if (!scheduleItem || isUpdatingSiblings) return;
            const daySelect = scheduleItem.querySelector('.default-day-select');
            const startSelect = scheduleItem.querySelector('.default-start-time-select');
            const endSelect = scheduleItem.querySelector('.default-end-time-select');
            if (!daySelect || !startSelect || !endSelect) return;
            const selectedDay = daySelect.value;
            const savedStartTimeRaw = startSelect.dataset.savedStart || '';
            const savedEndTimeRaw = endSelect.dataset.savedEnd || '';
            const savedStartTime = savedStartTimeRaw ? savedStartTimeRaw.substring(0, 5) : '';
            const savedEndTime = savedEndTimeRaw ? savedEndTimeRaw.substring(0, 5) : '';
            const previouslySelectedStartTime = startSelect.value;
            startSelect.innerHTML = '<option value="">Loading...</option>';
            endSelect.innerHTML = '<option value="">Loading...</option>';
            if (!selectedDay) {
                /*...*/
                return;
            }
            if (daySelect.options[daySelect.selectedIndex]?.disabled) {
                /*...*/
                return;
            }
            const dayAvailability = therapistAvailability[selectedDay] || [];
            const dayDbConflicts = patientConflicts[selectedDay] || {};
            let allValidSlots = [];
            let foundSavedStart = false;
            let savedStartConflictsDb = savedStartTime && dayDbConflicts[savedStartTime];
            const localConflicts = getLocalConflicts(selectedDay, scheduleItem);
            if (dayAvailability.length > 0) {
                dayAvailability.forEach(block => {
                    allValidSlots = allValidSlots.concat(generateTimeSlotsJS(block.start, block.end, {
                        ...dayDbConflicts,
                        ...localConflicts
                    }));
                });
            }
            allValidSlots.sort((a, b) => a.start.localeCompare(b.start));
            let startOptions = '';
            let endOptions = '<option value="">Select End Time</option>';
            const distinctEndTimes = {};
            if (allValidSlots.length === 0 && Object.keys(localConflicts).length === 0) {
                /* Set No Slots/Not Available */
                if (dayAvailability.length > 0) {
                    startOptions = '<option value="">No Conflict-Free Slots</option>';
                } else {
                    startOptions = '<option value="">Not Available</option>';
                }
                endOptions = '<option value="">N/A</option>';
            } else {
                startOptions = '<option value="">Select Start Time</option>';
                allValidSlots.forEach(slot => {
                    const displayText = formatTimeAmPm(slot.start); // *** Format Display ***
                    startOptions += `<option value="${slot.start}">${displayText}</option>`; // Value HH:MM, Text AM/PM
                    distinctEndTimes[slot.end] = true;
                    if (slot.start === savedStartTime) foundSavedStart = true;
                });
                Object.keys(localConflicts).forEach(conflictTime => {
                    if (isTimeGenerallyAvailable(selectedDay, conflictTime, dayDbConflicts)) {
                        const displayText = formatTimeAmPm(conflictTime);
                        startOptions += `<option value="${conflictTime}" disabled>${displayText} (Selected)</option>`;
                    }
                });
                Object.keys(distinctEndTimes).sort().forEach(endTime => {
                    const displayText = formatTimeAmPm(endTime);
                    endOptions += `<option value="${endTime}">${displayText}</option>`;
                }); // *** Format Display ***
            }
            // Display saved conflicting/NA times using AM/PM
            if (savedStartConflictsDb && !foundSavedStart) {
                const displayText = formatTimeAmPm(savedStartTime);
                startOptions = `<option value="" disabled selected>Saved: ${displayText} (Conflict)</option>` + startOptions;
            } else if (savedStartTime && !foundSavedStart && dayAvailability.length > 0 && !localConflicts[savedStartTime]) {
                const displayText = formatTimeAmPm(savedStartTime);
                startOptions = `<option value="" disabled selected>Saved: ${displayText} (N/A)</option>` + startOptions;
            }

            startSelect.innerHTML = startOptions;
            endSelect.innerHTML = endOptions;
            // Re-select Logic (uses HH:MM value) - No Change Needed Here
            if (startSelect.querySelector(`option[value="${previouslySelectedStartTime}"]:not([disabled])`)) {
                startSelect.value = previouslySelectedStartTime;
            } else if (foundSavedStart) {
                startSelect.value = savedStartTime;
            } else {
                startSelect.value = "";
            }
            if (savedEndTime && distinctEndTimes[savedEndTime]) {
                endSelect.value = savedEndTime;
            } else if (savedEndTime && startSelect.value === "") {
                if (!endSelect.querySelector('option[disabled][selected]')) {
                    const displayText = formatTimeAmPm(savedEndTime);
                    endSelect.insertAdjacentHTML('afterbegin', `<option value="" disabled selected>Saved: ${displayText} (N/A)</option>`);
                }
                endSelect.value = "";
            } else if (endSelect.value === "") {
                endSelect.value = "";
            }
        }

        // --- JS: updateMakeupTimeSelects (REVISED - Use AM/PM Display, Reset on Date Change) ---
        function updateMakeupTimeSelects(scheduleItem) {
            const dateInput = scheduleItem.querySelector('.makeup-date-input');
            const startSelect = scheduleItem.querySelector('.makeup-start-time-select');
            const endSelect = scheduleItem.querySelector('.makeup-end-time-select');
            if (!dateInput || !startSelect || !endSelect) return;

            const selectedDate = dateInput.value;

            // --- *** Read saved values FIRST *** ---
            const savedStartTime = startSelect.dataset.savedStart || '';
            const savedEndTime = endSelect.dataset.savedEnd || '';
            const savedStartTimeHHMM = savedStartTime.substring(0, 5);
            const savedEndTimeHHMM = savedEndTime.substring(0, 5);
            // --- *** End Read saved values FIRST *** ---

            startSelect.innerHTML = '<option value="">Loading...</option>';
            endSelect.innerHTML = '<option value="">Loading...</option>';

            if (!selectedDate) {
                startSelect.innerHTML = '<option value="">Select Date First</option>';
                endSelect.innerHTML = '<option value="">Select Date First</option>';
                return;
            }

            // --- Reset saved data attributes ONLY if date has actually changed from a previously loaded one ---
            if(startSelect.dataset.dateLoaded && startSelect.dataset.dateLoaded !== selectedDate){
                console.log(`Date changed from ${startSelect.dataset.dateLoaded} to ${selectedDate}. Clearing saved time attributes.`);
                delete startSelect.dataset.savedStart; // Clear only if date *changed*
                delete endSelect.dataset.savedEnd;
                // *** We still use the values read *before* this block for the current run ***
            }
            startSelect.dataset.dateLoaded = selectedDate; // Mark the date we just loaded/reloaded for
            // --- End Reset Logic ---

            console.log(`Updating makeup times for date: ${selectedDate}. Saved Start: ${savedStartTimeHHMM}, Saved End: ${savedEndTimeHHMM}`); // Log values being used

            $.ajax({
                url: '../app_process/get_therapist_slots.php', method: 'GET', data: { therapist_id: <?php echo $therapistID; ?>, date: selectedDate }, dataType: 'json',
                success: function(response) {
                    console.log(`AJAX response for ${selectedDate}:`, response);
                    let startOptions = '<option value="">Select Start Time</option>';
                    let endOptions = '<option value="">Select End Time</option>';
                    let foundSavedStart = false;
                    let foundSavedEnd = false;

                    if (response.status === 'success' && response.slots.length > 0) {
                        const distinctEndTimes = {};
                        response.slots.forEach(slot => {
                            const isSelectedStart = (slot.start === savedStartTimeHHMM); // Compare with value read *before* reset block
                            const displayText = formatTimeAmPm(slot.start);
                            startOptions += `<option value="${slot.start}">${displayText}</option>`;
                            distinctEndTimes[slot.end] = true;
                            if (isSelectedStart) foundSavedStart = true;
                        });
                        Object.keys(distinctEndTimes).sort().forEach(endTime => {
                            const displayText = formatTimeAmPm(endTime);
                            endOptions += `<option value="${endTime}">${displayText}</option>`;
                            if (endTime === savedEndTimeHHMM) foundSavedEnd = true; // Compare with value read *before* reset block
                        });
                    } else {
                        startOptions = '<option value="">No Slots Available</option>'; endOptions = '<option value="">N/A</option>';
                    }

                    startSelect.innerHTML = startOptions; endSelect.innerHTML = endOptions;

                    // Re-select Logic - using values read *before* the potential reset
                    console.log(`Attempting re-select: FoundStart=${foundSavedStart}, StartVal=${savedStartTimeHHMM} | FoundEnd=${foundSavedEnd}, EndVal=${savedEndTimeHHMM}`);
                    if (foundSavedStart) {
                        startSelect.value = savedStartTimeHHMM; // Use HH:MM format
                    } else {
                        startSelect.value = ""; // Reset if not found (don't show N/A for date changes)
                        if(savedStartTime) console.warn(`Saved start time ${savedStartTime} not found for date ${selectedDate}`);
                    }

                    if (foundSavedEnd) {
                        endSelect.value = savedEndTimeHHMM; // Use HH:MM format
                    } else {
                        endSelect.value = ""; // Reset if not found
                        if(savedEndTime) console.warn(`Saved end time ${savedEndTime} not found for date ${selectedDate}`);
                    }
                    console.log(`Finished updating makeup selects for ${selectedDate}. Final values: Start=${startSelect.value}, End=${endSelect.value}`);
                },
                error: function() {
                    /* ... Error Handling ... */
                    startSelect.innerHTML = '<option value="">Error</option>';
                    endSelect.innerHTML = '<option value="">Error</option>';
                    Swal.fire('Error', `Could not fetch slots for ${selectedDate}.`, 'error');
                }
            });
        }

        // Adds a new Default Schedule row
        function addDefaultSchedule() {
            /* ... As in previous correct version ... */
            const template = document.getElementById("default-schedule-template").content.cloneNode(true);
            const newItemElement = template.querySelector('.default-schedule-item');
            const container = document.getElementById("default-schedules");
            if (!newItemElement) return;
            const nextIndex = container.querySelectorAll('.default-schedule-item').length;
            newItemElement.querySelectorAll('[name^="default_day"]').forEach(el => el.name = `default_day[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="default_start_time"]').forEach(el => el.name = `default_start_time[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="default_end_time"]').forEach(el => el.name = `default_end_time[${nextIndex}]`);
            container.appendChild(template);
            const addedItemElement = container.querySelector('.default-schedule-item:last-child');
            if (addedItemElement) attachScheduleEventListeners(addedItemElement);
        }
        // Adds a new Makeup Schedule row
        function addMakeupSchedule() {
            /* ... As in previous correct version ... */
            const template = document.getElementById("makeup-schedule-template").content.cloneNode(true);
            const newItemElement = template.querySelector('.makeup-schedule-item');
            const container = document.getElementById("makeup-schedules");
            if (!newItemElement) return;
            const nextIndex = container.querySelectorAll('.makeup-schedule-item').length;
            newItemElement.querySelectorAll('[name^="makeup_date"]').forEach(el => el.name = `makeup_date[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="makeup_start_time"]').forEach(el => el.name = `makeup_start_time[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="makeup_end_time"]').forEach(el => el.name = `makeup_end_time[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="makeup_notes"]').forEach(el => el.name = `makeup_notes[${nextIndex}]`);
            container.appendChild(template);
            const addedItemElement = container.querySelector('.makeup-schedule-item:last-child');
            if (addedItemElement) attachScheduleEventListeners(addedItemElement);
        }

        // Updates all sibling default schedule rows on a specific day
        function updateAllSiblingDefaultSchedules(currentItem, dayToUpdate) {
            if (isUpdatingSiblings || !dayToUpdate) return;
            isUpdatingSiblings = true;
            console.log(`Updating ALL siblings for day: ${dayToUpdate}`);
            document.querySelectorAll('.default-schedule-item').forEach(otherItem => {
                if (otherItem !== currentItem) {
                    const otherDaySelect = otherItem.querySelector('.default-day-select');
                    if (otherDaySelect && otherDaySelect.value === dayToUpdate) {
                        console.log(`Updating sibling:`, otherItem);
                        updateDefaultTimeSelects(otherItem);
                    }
                }
            });
            setTimeout(() => {
                isUpdatingSiblings = false;
            }, 100); // Release flag after delay
        }

        // Handles Start Time change for local conflicts and sibling updates
        function handleStartTimeChange(currentStartSelect) {
            const scheduleItem = currentStartSelect.closest('.default-schedule-item');
            if (!scheduleItem) return;
            const daySelect = scheduleItem.querySelector('.default-day-select');
            const selectedDay = daySelect?.value;
            const selectedStartTime = currentStartSelect.value;
            if (!selectedDay) return;
            let conflictFound = false;
            if (selectedStartTime !== "") {
                const localConflicts = getLocalConflicts(selectedDay, scheduleItem);
                if (localConflicts[selectedStartTime]) {
                    conflictFound = true;
                }
            }
            if (conflictFound) {
                console.warn(`Local Conflict: ${selectedDay} ${selectedStartTime}`);
                Swal.fire({
                    icon: 'warning',
                    title: 'Conflict',
                    text: `Slot ${selectedStartTime} on ${selectedDay} is selected below/above.`,
                    timer: 2500,
                    showConfirmButton: false
                });
                currentStartSelect.value = "";
                const endSelect = scheduleItem.querySelector('.default-end-time-select');
                if (endSelect) endSelect.value = "";
            }
            updateAllSiblingDefaultSchedules(scheduleItem, selectedDay); // Update siblings regardless
        }

        // Attaches event listeners to a schedule row
        function attachScheduleEventListeners(scheduleItem) {
            if (!scheduleItem) return;
            console.log("Attaching listeners to:", scheduleItem);
            // Day change
            scheduleItem.querySelector('.default-day-select')?.addEventListener('change', function() {
                if (isUpdatingSiblings) return;
                updateDefaultTimeSelects(scheduleItem);
                updateAllSiblingDefaultSchedules(scheduleItem, this.value);
            });
            // Start Time change
            scheduleItem.querySelector('.default-start-time-select')?.addEventListener('change', function(event) {
                if (isUpdatingSiblings) return;
                handleStartTimeChange(event.target);
            });
            // Date change
            scheduleItem.querySelector('.makeup-date-input')?.addEventListener('change', function() {
                updateMakeupTimeSelects(scheduleItem);
            });
            // Remove button
            scheduleItem.querySelector('.remove-schedule-btn')?.addEventListener('click', function() {
                if (isUpdatingSiblings) return;
                const dayToRemove = scheduleItem.querySelector('.default-day-select')?.value;
                const isDefault = !!dayToRemove;
                UIkit.modal.confirm('Remove schedule?').then(() => {
                    scheduleItem.remove();
                    if (isDefault && dayToRemove) {
                        updateAllSiblingDefaultSchedules(null, dayToRemove);
                    }
                }, () => {});
            });
            // Initial Population Trigger
            setTimeout(() => {
                const daySelect = scheduleItem.querySelector('.default-day-select');
                if (daySelect?.value && !daySelect.options[daySelect.selectedIndex]?.disabled) {
                    updateDefaultTimeSelects(scheduleItem);
                }
                const dateInput = scheduleItem.querySelector('.makeup-date-input');
                if (dateInput?.value) {
                    updateMakeupTimeSelects(scheduleItem);
                }
            }, 250);
        }

        // --- JS: DOMContentLoaded ---
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOMContentLoaded event");
            document.querySelectorAll('.default-schedule-item, .makeup-schedule-item').forEach(item => {
                attachScheduleEventListeners(item);
            });

            // Session message display
            <?php if (isset($_SESSION['success_message'])): ?> Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo addslashes($_SESSION['success_message']); ?>'
                });
            <?php unset($_SESSION['success_message']);
            endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?> Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?php echo addslashes($_SESSION['error_message']); ?>'
                });
            <?php unset($_SESSION['error_message']);
            endif; ?>

            // --- REVISED Form Submit Listener ---
            const form = document.getElementById('update-patient-form');
            const saveButton = form ? form.querySelector('button[type="submit"]') : null;

            if (form && saveButton) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault(); // *** ALWAYS prevent default initially ***
                    console.log("Form submit intercepted.");

                    let isValid = true;
                    const errorMessages = [];
                    const formSelections = {};

                    // --- Run client-side validations ---
                    // 1. Start < End
                    document.querySelectorAll('.default-schedule-item, .makeup-schedule-item').forEach(item => {
                        const startSelect = item.querySelector('.default-start-time-select, .makeup-start-time-select');
                        const endSelect = item.querySelector('.default-end-time-select, .makeup-end-time-select');
                        const dayOrDate = item.querySelector('.default-day-select, .makeup-date-input');
                        if (startSelect?.value && endSelect?.value && startSelect.value >= endSelect.value) {
                            isValid = false;
                            const identifier = dayOrDate?.value || 'Row';
                            errorMessages.push(`On ${identifier}: Start time must be before end time.`);
                        }
                    });
                    // 2. Local Default Schedule Duplicates
                    document.querySelectorAll('.default-schedule-item').forEach(item => {
                        const daySelect = item.querySelector('.default-day-select');
                        const startSelect = item.querySelector('.default-start-time-select');
                        const day = daySelect?.value;
                        const start = startSelect?.value;
                        if (day && start) {
                            const key = `${day}-${start}`;
                            if (formSelections[key]) {
                                isValid = false;
                                errorMessages.push(`Duplicate: ${day} at ${start}.`);
                            }
                            formSelections[key] = true;
                        } else if (day && !start && !daySelect.options[daySelect.selectedIndex]?.disabled) {
                            isValid = false;
                            errorMessages.push(`Missing Start Time for ${day}.`);
                        }
                    });
                    // --- End Validations ---

                    if (!isValid) {
                        console.log("Form validation failed.");
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Schedule',
                            html: errorMessages.join('<br>')
                        });
                        return; // Stop processing if invalid
                    }

                    // --- Validation Passed: Show Loading Overlay ---
                    console.log("Form validation passed. Showing loading overlay.");
                    saveButton.disabled = true; // Disable button

                    Swal.fire({
                        title: 'Processing...',
                        text: 'Saving details and sending notifications...',
                        // icon: 'info', // Optional icon
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading(); // Show spinner
                        }
                    });

                    // --- Submit the form programmatically after a short delay ---
                    // This delay allows Swal to render before the browser might block on submit
                    setTimeout(() => {
                        console.log("Submitting form programmatically now.");
                        form.submit(); // Submit the actual form
                    }, 150); // 150ms delay, adjust if needed
                });
            } else {
                console.error("Form or Save Button not found!");
            }
        });
    </script>

</body>

</html>