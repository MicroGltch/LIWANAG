<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict to Therapist Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// Initialize arrays
$existingSchedules = [];
$existingMakeupSchedules = [];
$patientInfo = null;
$therapistAvailability = [];
$patientScheduleConflicts = []; // Conflicts from DB
$availableDays = []; // Days with ANY slots (after DB conflict check)

$therapistID = $_SESSION['account_ID'];

// --- Fetch Therapist's Default Availability ---
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
    error_log("Error fetching therapist availability for ID: $therapistID - " . $connection->error);
}

// --- Fetch Eligible Patients ---
$query = "SELECT DISTINCT p.patient_id, p.first_name, p.last_name, p.service_type, p.status
          FROM appointments a JOIN patients p ON a.patient_id = p.patient_id
          WHERE a.therapist_id = ? AND a.status = 'Completed' AND p.status IN ('enrolled', 'pending') -- Allow managing pending too? Adjusted status check
          ORDER BY p.last_name, p.first_name";
$stmt = $connection->prepare($query);
if (!$stmt) die("Prepare failed (fetch patients): " . $connection->error);
$stmt->bind_param("i", $therapistID);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$patient_id = $_GET['patient_id'] ?? null;

// --- Fetch Patient Data and Conflicts if patient_id is valid ---
if ($patient_id) {
    $canManagePatient = false;
    foreach ($patients as $p) {
        if ($p['patient_id'] == $patient_id) {
            $canManagePatient = true;
            break;
        }
    }

    if (!$canManagePatient) {
        $_SESSION['error_message'] = "You do not have permission to manage this patient's schedule.";
        $patient_id = null;
    } else {
        // Fetch patient info
        $patientInfoQuery = "SELECT * FROM patients WHERE patient_id = ?";
        $stmtPI = $connection->prepare($patientInfoQuery);
        if (!$stmtPI) die("Prepare failed (fetch patient info): " . $connection->error);
        $stmtPI->bind_param("i", $patient_id);
        $stmtPI->execute();
        $patientInfo = $stmtPI->get_result()->fetch_assoc();
        $stmtPI->close();

        // Fetch existing default schedules
        $scheduleQuery = "SELECT * FROM patient_default_schedules WHERE patient_id = ?";
        $stmtDS = $connection->prepare($scheduleQuery);
        if (!$stmtDS) die("Prepare failed (fetch default sched): " . $connection->error);
        $stmtDS->bind_param("i", $patient_id);
        $stmtDS->execute();
        $existingSchedules = $stmtDS->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDS->close();

        // Fetch existing makeup schedules
        $makeupScheduleQuery = "SELECT * FROM patient_makeup_schedules WHERE patient_id = ?";
        $stmtMS = $connection->prepare($makeupScheduleQuery);
        if (!$stmtMS) die("Prepare failed (fetch makeup sched): " . $connection->error);
        $stmtMS->bind_param("i", $patient_id);
        $stmtMS->execute();
        $existingMakeupSchedules = $stmtMS->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMS->close();

        // --- Fetch Schedule Conflicts (for this therapist, excluding current patient) ---
        $conflictQuery = "SELECT day_of_week, start_time
                          FROM patient_default_schedules
                          WHERE therapist_id = ? AND patient_id != ?";
        $stmtConflict = $connection->prepare($conflictQuery);
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
            error_log("Fetched Conflicts: " . json_encode($patientScheduleConflicts));
        } else {
            error_log("Error preparing conflict query: " . $connection->error);
        }
    }
}

$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

// --- Helper Function to generate time slots, checking conflicts ---
function generateTimeSlotsInternal($startTimeStr, $endTimeStr, $conflicts = [], $intervalMinutes = 60)
{
    $slots = [];
    $start = strtotime($startTimeStr);
    $end = strtotime($endTimeStr);
    $sessionDurationSeconds = 60 * 60; // TODO: Make dynamic

    if ($start === false || $end === false) return [];

    $current = $start;
    while ($current < $end) {
        $slotEnd = $current + $sessionDurationSeconds;
        if ($slotEnd <= $end) {
            $slotStartTimeFormatted = date('H:i', $current);
            if (!isset($conflicts[$slotStartTimeFormatted])) { // Check conflict
                $slots[] = ['start' => $slotStartTimeFormatted, 'end' => date('H:i', $slotEnd)];
            }
        }
        $current += $intervalMinutes * 60;
    }
    return $slots;
}

// --- Pre-calculate which days have ANY available, non-conflicting slots ---
foreach ($days as $day) {
    $dayAvail = $therapistAvailability[$day] ?? [];
    $dayConf = $patientScheduleConflicts[$day] ?? [];
    $hasSlots = false;
    if (!empty($dayAvail)) {
        foreach ($dayAvail as $block) {
            // Use the internal function to see if *any* non-conflicting slots are generated
            if (!empty(generateTimeSlotsInternal($block['start'], $block['end'], $dayConf))) {
                $hasSlots = true;
                break;
            }
        }
    }
    $availableDays[$day] = $hasSlots;
}
// --- End Pre-calculation ---


// --- Helper Function to generate time options HTML ---
function generateTimeOptions($dayAvailability, $selectedStartTime = null, $selectedEndTime = null, $conflicts = [])
{
    $optionsStart = '';
    $optionsEnd = '';
    $allSlots = [];
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
        $optionsEnd = '<option value="">N/A</option>'; // Add end time placeholder for consistency
    } else {
        $optionsStart = '<option value="">Select Start Time</option>'; // Add placeholder
        foreach ($allSlots as $slot) {
            $isSelected = ($slot['start'] == $selectedStartTimeHHMM);
            $startSelectedAttr = $isSelected ? ' selected' : '';
            $optionsStart .= "<option value=\"{$slot['start']}\"{$startSelectedAttr}>{$slot['start']}</option>";
            $distinctEndTimes[$slot['end']] = true;
            if ($isSelected) $foundSelectedStart = true;
        }
    }

    if ($selectedStartTimeHHMM && !$foundSelectedStart && isset($conflicts[$selectedStartTimeHHMM])) {
        $optionsStart = "<option value=\"\" disabled selected>Saved: {$selectedStartTimeHHMM} (Conflict)</option>" . $optionsStart;
    } elseif ($selectedStartTimeHHMM && !$foundSelectedStart && !empty($dayAvailability)) { // Only show N/A if day has availability
        $optionsStart = "<option value=\"\" disabled selected>Saved: {$selectedStartTimeHHMM} (N/A)</option>" . $optionsStart;
    }

    $optionsEnd = '<option value="">Select End Time</option>';
    if (!empty($distinctEndTimes)) {
        ksort($distinctEndTimes);
        foreach (array_keys($distinctEndTimes) as $endTime) {
            $endSelectedAttr = ($endTime == $selectedEndTimeHHMM) ? ' selected' : '';
            $optionsEnd .= "<option value=\"{$endTime}\"{$endSelectedAttr}>{$endTime}</option>";
        }
    }
    if ($selectedEndTimeHHMM && !isset($distinctEndTimes[$selectedEndTimeHHMM]) && !empty($dayAvailability)) {
        $optionsEnd = "<option value=\"\" disabled selected>Saved: {$selectedEndTimeHHMM} (N/A)</option>" . $optionsEnd;
    } elseif (empty($distinctEndTimes) && empty($allSlots) && !empty($dayAvailability)) {
        $optionsEnd = '<option value="">N/A</option>'; // Match start time when no slots
    }

    return ['start' => $optionsStart, 'end' => $optionsEnd];
}
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
</head>

<body>
    <div class="uk-container uk-margin-top">
        <h2 class="uk-heading-line"><span>Update Patient Details</span></h2>

        <!-- Error Message Display -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="uk-alert-danger" uk-alert><a class="uk-alert-close" uk-close></a>
                <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
            </div>
        <?php unset($_SESSION['error_message']);
        endif; ?>

        <form id="update-patient-form" action="../app_process/save_patient_details.php" method="POST" class="uk-form-stacked">

            <!-- Patient Selection -->
            <div class="uk-margin">
                <label class="uk-form-label">Select Patient</label>
                <div class="uk-form-controls">
                    <select name="patient_id" id="patient_id" class="uk-select" onchange="if(this.value) window.location.href='?patient_id='+this.value">
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?> <option value="<?= htmlspecialchars($patient['patient_id']) ?>" <?= ($patient['patient_id'] == $patient_id) ? 'selected' : '' ?>><?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']) ?> (<?= htmlspecialchars($patient['service_type']) ?>)</option> <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($patient_id && $patientInfo): ?>
                <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">

                <!-- Service Type & Status -->
                <div class="uk-grid-small uk-child-width-expand@s" uk-grid>
                    <!-- Service Type and Status Dropdowns -->
                    <div> <label class="uk-form-label">Service Type</label>
                        <div class="uk-form-controls"><select name="service_type" class="uk-select" required><?php if ($patientInfo['service_type'] == 'For Evaluation'): ?><option value="For Evaluation" selected>For Evaluation</option><?php endif; ?><option value="Occupational Therapy" <?= $patientInfo['service_type'] == 'Occupational Therapy' ? 'selected' : '' ?>>Occupational Therapy</option>
                                <option value="Behavioral Therapy" <?= $patientInfo['service_type'] == 'Behavioral Therapy' ? 'selected' : '' ?>>Behavioral Therapy</option>
                            </select></div>
                    </div>
                    <div> <label class="uk-form-label">Status</label>
                        <div class="uk-form-controls"><select name="status" class="uk-select" required><?php if (($patientInfo['status'] ?? 'pending') == 'pending'): ?><option value="pending" selected>Pending</option><?php endif; ?><option value="enrolled" <?= ($patientInfo['status'] ?? '') == 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                                <option value="declined_enrollment" <?= ($patientInfo['status'] ?? '') == 'declined_enrollment' ? 'selected' : '' ?>>Declined Enrollement</option>
                                <option value="completed" <?= ($patientInfo['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed Program</option>
                                <option value="cancelled" <?= ($patientInfo['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select></div>
                    </div>
                </div>

                <!-- Default Schedules -->
                <h3>Default Schedules</h3>
                <div id="default-schedules" class="uk-margin">
                    <!-- Existing Schedules Loop -->
                    <?php if (!empty($existingSchedules)): ?>
                        <?php foreach ($existingSchedules as $index => $schedule): ?>
                            <?php
                            $day = $schedule['day_of_week'];
                            $dayAvailability = $therapistAvailability[$day] ?? [];
                            $dayConflicts = $patientScheduleConflicts[$day] ?? [];
                            $timeOptions = generateTimeOptions($dayAvailability, $schedule['start_time'], $schedule['end_time'], $dayConflicts);
                            ?>
                            <div class="uk-margin uk-card uk-card-default uk-card-body default-schedule-item">
                                <input type="hidden" name="default_schedule_id[<?= $index ?>]" value="<?= htmlspecialchars($schedule['id']) ?>">
                                <div class="uk-grid-small" uk-grid>
                                    <div class="uk-width-1-3@s">
                                        <label class="uk-form-label">Day:</label>
                                        <select name="default_day[<?= $index ?>]" class="uk-select uk-margin-small-bottom default-day-select" required>
                                            <?php foreach ($days as $d):
                                                $isDisabled = !$availableDays[$d];
                                                // Allow the currently selected day even if it theoretically has no slots now
                                                // (e.g., if availability changed after it was saved)
                                                if ($day == $d) $isDisabled = false;
                                            ?>
                                                <option value="<?= $d ?>" <?= ($day == $d) ? 'selected' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>>
                                                    <?= $d ?> <?= $isDisabled ? '(No Slots)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="uk-width-expand">
                                        <label class="uk-form-label">Start Time:</label>
                                        <select name="default_start_time[<?= $index ?>]" class="uk-select default-start-time-select" required data-saved-start="<?= htmlspecialchars($schedule['start_time']) ?>">
                                            <?= $timeOptions['start'] ?>
                                        </select>
                                    </div>
                                    <div class="uk-width-expand">
                                        <label class="uk-form-label">End Time:</label>
                                        <select name="default_end_time[<?= $index ?>]" class="uk-select default-end-time-select" required data-saved-end="<?= htmlspecialchars($schedule['end_time']) ?>">
                                            <?= $timeOptions['end'] ?>
                                        </select>
                                    </div>
                                    <div class="uk-width-auto@s uk-text-right uk-margin-small-top"> <label class="uk-form-label"> </label> <button type="button" class="uk-button uk-button-danger remove-schedule-btn" title="Remove Schedule"><span uk-icon="trash"></span></button> </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Template for new schedules -->
                    <template id="default-schedule-template">
                        <div class="uk-margin uk-card uk-card-default uk-card-body default-schedule-item">
                            <div class="uk-grid-small" uk-grid>
                                <div class="uk-width-1-3@s">
                                    <label class="uk-form-label">Day:</label>
                                    <select name="default_day[]" class="uk-select uk-margin-small-bottom default-day-select" required>
                                        <option value="">-- Select Day --</option>
                                        <?php foreach ($days as $d):
                                            $isDisabled = !$availableDays[$d];
                                        ?> <option value="<?= $d ?>" <?= $isDisabled ? 'disabled' : '' ?>><?= $d ?> <?= $isDisabled ? '(No Slots)' : '' ?></option> <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="uk-width-expand"> <label class="uk-form-label">Start Time:</label> <select name="default_start_time[]" class="uk-select default-start-time-select" required>
                                        <option value="">Select Day First</option>
                                    </select> </div>
                                <div class="uk-width-expand"> <label class="uk-form-label">End Time:</label> <select name="default_end_time[]" class="uk-select default-end-time-select" required>
                                        <option value="">Select Day First</option>
                                    </select> </div>
                                <div class="uk-width-auto@s uk-text-right uk-margin-small-top"> <label class="uk-form-label"> </label> <button type="button" class="uk-button uk-button-danger remove-schedule-btn" title="Remove Schedule"><span uk-icon="trash"></span></button> </div>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" onclick="addDefaultSchedule()">
                    <span uk-icon="plus"></span> Add Default Schedule
                </button>

                <!-- Makeup Schedules -->
                <h3>Makeup Schedules</h3>
                <div id="makeup-schedules" class="uk-margin">
                    <!-- Makeup schedule rendering -->
                    <?php if (!empty($existingMakeupSchedules)): ?>
                        <?php foreach ($existingMakeupSchedules as $index => $makeup): ?>
                            <div class="uk-margin uk-card uk-card-default uk-card-body makeup-schedule-item">
                                <input type="hidden" name="makeup_schedule_id[<?= $index ?>]" value="<?= htmlspecialchars($makeup['id']) ?>">
                                <div class="uk-grid-small" uk-grid>
                                    <div class="uk-width-1-3@s"> <label class="uk-form-label">Date:</label> <input type="date" name="makeup_date[<?= $index ?>]" class="uk-input makeup-date-input" value="<?= htmlspecialchars($makeup['date']) ?>" required> </div>
                                    <div class="uk-width-expand"> <label class="uk-form-label">Start Time:</label> <select name="makeup_start_time[<?= $index ?>]" class="uk-select makeup-start-time-select" required data-saved-start="<?= htmlspecialchars($makeup['start_time']) ?>">
                                            <option value="">Loading...</option>
                                        </select> </div>
                                    <div class="uk-width-expand"> <label class="uk-form-label">End Time:</label> <select name="makeup_end_time[<?= $index ?>]" class="uk-select makeup-end-time-select" required data-saved-end="<?= htmlspecialchars($makeup['end_time']) ?>">
                                            <option value="">Loading...</option>
                                        </select> </div>
                                    <div class="uk-width-1-1"> <label class="uk-form-label">Notes:</label> <input type="text" name="makeup_notes[<?= $index ?>]" class="uk-input" value="<?= htmlspecialchars($makeup['notes'] ?? '') ?>" placeholder="Optional notes"> </div>
                                    <div class="uk-width-1-1 uk-text-right"> <button type="button" class="uk-button uk-button-danger uk-margin-small-top remove-schedule-btn" title="Remove Makeup Schedule"><span uk-icon="trash"></span></button> </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Makeup Template -->
                    <template id="makeup-schedule-template">
                        <div class="uk-margin uk-card uk-card-default uk-card-body makeup-schedule-item">
                            <div class="uk-grid-small" uk-grid>
                                <div class="uk-width-1-3@s"> <label class="uk-form-label">Date:</label> <input type="date" name="makeup_date[]" class="uk-input makeup-date-input" required> </div>
                                <div class="uk-width-expand"> <label class="uk-form-label">Start Time:</label> <select name="makeup_start_time[]" class="uk-select makeup-start-time-select" required>
                                        <option value="">Select Date First</option>
                                    </select> </div>
                                <div class="uk-width-expand"> <label class="uk-form-label">End Time:</label> <select name="makeup_end_time[]" class="uk-select makeup-end-time-select" required>
                                        <option value="">Select Date First</option>
                                    </select> </div>
                                <div class="uk-width-1-1"> <label class="uk-form-label">Notes:</label> <input type="text" name="makeup_notes[]" class="uk-input" placeholder="Optional notes"> </div>
                                <div class="uk-width-1-1 uk-text-right"> <button type="button" class="uk-button uk-button-danger uk-margin-small-top remove-schedule-btn" title="Remove Makeup Schedule"><span uk-icon="trash"></span></button> </div>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" onclick="addMakeupSchedule()">
                    <span uk-icon="plus"></span> Add Makeup Schedule
                </button>
                <br><br>

                <button type="submit" class="uk-button uk-button-secondary uk-width-1-1">Save All Changes</button>
            <?php endif; ?>
        </form>
    </div>

    <script>
        const therapistAvailability = <?php echo json_encode($therapistAvailability); ?>;
        const patientConflicts = <?php echo json_encode($patientScheduleConflicts); ?>; // DB Conflicts
        const daysOfWeek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

        // --- JS: generateTimeSlotsJS (No changes needed from previous version) ---
        function generateTimeSlotsJS(startTimeStr, endTimeStr, conflicts = {}, intervalMinutes = 60) {
            const slots = [];
            if (!startTimeStr || !endTimeStr) return slots;
            const baseDate = '1970-01-01';
            const start = new Date(`${baseDate}T${startTimeStr}`);
            const end = new Date(`${baseDate}T${endTimeStr}`);
            if (isNaN(start) || isNaN(end) || start >= end) return slots;
            const startTotalMinutes = start.getHours() * 60 + start.getMinutes();
            const endTotalMinutes = end.getHours() * 60 + end.getMinutes();
            const sessionDurationMinutes = 60; // TODO: Dynamic
            let currentTotalMinutes = startTotalMinutes;
            while (currentTotalMinutes < endTotalMinutes) {
                const slotEndTotalMinutes = currentTotalMinutes + sessionDurationMinutes;
                if (slotEndTotalMinutes <= endTotalMinutes) {
                    const currentH = String(Math.floor(currentTotalMinutes / 60)).padStart(2, '0');
                    const currentM = String(currentTotalMinutes % 60).padStart(2, '0');
                    const slotStartFormatted = `${currentH}:${currentM}`;
                    if (!conflicts[slotStartFormatted]) { // Check conflict
                        const slotEndH = String(Math.floor(slotEndTotalMinutes / 60)).padStart(2, '0');
                        const slotEndM = String(slotEndTotalMinutes % 60).padStart(2, '0');
                        slots.push({
                            start: slotStartFormatted,
                            end: `${slotEndH}:${slotEndM}`
                        });
                    }
                }
                currentTotalMinutes += intervalMinutes;
            }
            return slots;
        }

        // --- JS: updateDefaultTimeSelects (REVISED - No Sibling Trigger INSIDE) ---
        function updateDefaultTimeSelects(scheduleItem) {
            if (!scheduleItem) return;
            const daySelect = scheduleItem.querySelector('.default-day-select');
            const startSelect = scheduleItem.querySelector('.default-start-time-select');
            const endSelect = scheduleItem.querySelector('.default-end-time-select');
            if (!daySelect || !startSelect || !endSelect) return;

            const selectedDay = daySelect.value;
            const savedStartTimeRaw = startSelect.dataset.savedStart || '';
            const savedEndTimeRaw = endSelect.dataset.savedEnd || '';
            const savedStartTime = savedStartTimeRaw ? savedStartTimeRaw.substring(0, 5) : '';
            const savedEndTime = savedEndTimeRaw ? savedEndTimeRaw.substring(0, 5) : '';

            // Store the currently selected start time *before* repopulating
            const previouslySelectedStartTime = startSelect.value;

            startSelect.innerHTML = '<option value="">Loading...</option>';
            endSelect.innerHTML = '<option value="">Loading...</option>';

            if (!selectedDay) {
                startSelect.innerHTML = '<option value="">Select Day First</option>';
                endSelect.innerHTML = '<option value="">Select Day First</option>';
                return;
            }
            if (daySelect.options[daySelect.selectedIndex]?.disabled) {
                startSelect.innerHTML = '<option value="">Day Unavailable</option>';
                endSelect.innerHTML = '<option value="">Day Unavailable</option>';
                // daySelect.value = ""; // Keep day selected to show it's disabled
                return;
            }

            const dayAvailability = therapistAvailability[selectedDay] || [];
            const dayDbConflicts = patientConflicts[selectedDay] || {};
            let allValidSlots = [];
            let foundSavedStart = false;
            let savedStartConflictsDb = savedStartTime && dayDbConflicts[savedStartTime]; // DB conflict for saved time

            // --- Check for conflicts selected in OTHER rows on the current form ---
            const localConflicts = {};
            document.querySelectorAll('.default-schedule-item').forEach(otherItem => {
                if (otherItem !== scheduleItem) {
                    const otherDaySelect = otherItem.querySelector('.default-day-select');
                    const otherStartSelect = otherItem.querySelector('.default-start-time-select');
                    if (otherDaySelect?.value === selectedDay && otherStartSelect?.value) {
                        localConflicts[otherStartSelect.value] = true;
                    }
                }
            });
            // --- End Local Conflict Check ---

            if (dayAvailability.length > 0) {
                dayAvailability.forEach(block => {
                    // Generate slots, filtering BOTH DB and Local conflicts
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

            if (allValidSlots.length === 0 && Object.keys(localConflicts).length === 0) { // No slots AT ALL (not just local conflicts)
                if (dayAvailability.length > 0) {
                    startOptions = '<option value="">No Conflict-Free Slots</option>';
                } else {
                    startOptions = '<option value="">Not Available</option>';
                }
                endOptions = '<option value="">N/A</option>';
            } else {
                startOptions = '<option value="">Select Start Time</option>';
                // Add slots that are not locally conflicted
                allValidSlots.forEach(slot => {
                    startOptions += `<option value="${slot.start}">${slot.start}</option>`; // Already filtered by generateTimeSlotsJS
                    distinctEndTimes[slot.end] = true;
                    if (slot.start === savedStartTime) foundSavedStart = true; // Check if original saved is still valid
                });

                // Add locally conflicted slots back as disabled options for clarity
                Object.keys(localConflicts).forEach(conflictTime => {
                    // Check if this conflict time was part of the therapist's general availability for the day
                    // (Avoids adding disabled options for times the therapist isn't even available)
                    let wasGenerallyAvailable = false;
                    if (dayAvailability.length > 0) {
                        const conflictSlots = generateTimeSlotsJS(dayAvailability[0].start, dayAvailability[0].end, dayDbConflicts); // Check against DB conflicts only
                        if (conflictSlots.some(s => s.start === conflictTime)) {
                            wasGenerallyAvailable = true;
                        }
                        // Note: This assumes single availability block per day for simplicity here
                    }

                    if (wasGenerallyAvailable) {
                        startOptions += `<option value="${conflictTime}" disabled>${conflictTime} (Selected Above/Below)</option>`;
                    }
                });

                // Re-sort options to put disabled ones logically (optional, might be complex)
                // For simplicity, disabled options will appear at the end now.

                Object.keys(distinctEndTimes).sort().forEach(endTime => {
                    endOptions += `<option value="${endTime}">${endTime}</option>`;
                });
            }

            // Handle displaying the original SAVED time correctly if needed
            if (savedStartConflictsDb && !foundSavedStart) {
                startOptions = `<option value="" disabled selected>Saved: ${savedStartTime} (DB Conflict)</option>` + startOptions;
            } else if (savedStartTime && !foundSavedStart && dayAvailability.length > 0 && !localConflicts[savedStartTime]) {
                startOptions = `<option value="" disabled selected>Saved: ${savedStartTime} (N/A)</option>` + startOptions;
            }

            startSelect.innerHTML = startOptions;
            endSelect.innerHTML = endOptions;

            // --- Re-select Logic ---
            // Try to reselect the value that was selected *just before* this function ran
            if (previouslySelectedStartTime && startSelect.querySelector(`option[value="${previouslySelectedStartTime}"]:not([disabled])`)) {
                startSelect.value = previouslySelectedStartTime;
            }
            // Otherwise, try to select the original saved value if it's valid and wasn't the previously selected one
            else if (foundSavedStart && previouslySelectedStartTime !== savedStartTime) {
                startSelect.value = savedStartTime;
            }
            // If the previously selected time is now locally conflicted, reset
            else if (previouslySelectedStartTime && localConflicts[previouslySelectedStartTime]) {
                startSelect.value = ""; // Reset if the previous selection caused the conflict display
            }
            // If nothing else works, try setting back to original saved value if found
            else if (foundSavedStart) {
                startSelect.value = savedStartTime;
            }

            // Re-select End time (simpler logic usually ok)
            if (savedEndTime && distinctEndTimes[savedEndTime]) {
                endSelect.value = savedEndTime;
            } else if (savedEndTime && endSelect.value === "") {
                if (!endSelect.querySelector('option[disabled][selected]')) endSelect.insertAdjacentHTML('afterbegin', `<option value="" disabled selected>Saved: ${savedEndTime} (N/A)</option>`);
            } else if (!savedEndTime && endSelect.value === "") {
                endSelect.value = "";
            }

            // --- REMOVED Sibling Trigger Block ---
        }

        // --- JS: updateMakeupTimeSelects (No changes needed) ---
        function updateMakeupTimeSelects(scheduleItem) {
            /* ... as before ... */
            const dateInput = scheduleItem.querySelector('.makeup-date-input');
            const startSelect = scheduleItem.querySelector('.makeup-start-time-select');
            const endSelect = scheduleItem.querySelector('.makeup-end-time-select');

            if (!dateInput || !startSelect || !endSelect) {
                console.error("Missing select elements in makeup item", scheduleItem);
                return;
            }

            const selectedDate = dateInput.value;
            const savedStartTime = startSelect.dataset.savedStart || '';
            const savedEndTime = endSelect.dataset.savedEnd || '';
            const savedStartTimeHHMM = savedStartTime.substring(0, 5);
            const savedEndTimeHHMM = savedEndTime.substring(0, 5);

            startSelect.innerHTML = '<option value="">Loading...</option>';
            endSelect.innerHTML = '<option value="">Loading...</option>';

            if (!selectedDate) {
                startSelect.innerHTML = '<option value="">Select Date First</option>';
                endSelect.innerHTML = '<option value="">Select Date First</option>';
                return;
            }
            console.log(`Updating makeup times for date: ${selectedDate}, Item:`, scheduleItem);

            $.ajax({
                url: '../app_process/get_therapist_slots.php',
                method: 'GET',
                data: {
                    therapist_id: <?php echo $therapistID; ?>,
                    date: selectedDate
                },
                dataType: 'json',
                success: function(response) {
                    console.log(`AJAX response for ${selectedDate}:`, response);
                    let startOptions = '<option value="">Select Start Time</option>';
                    let endOptions = '<option value="">Select End Time</option>';
                    let foundSavedStart = false;
                    let foundSavedEnd = false;

                    if (response.status === 'success' && response.slots.length > 0) {
                        const distinctEndTimes = {};
                        response.slots.forEach(slot => {
                            const isSelectedStart = (slot.start === savedStartTimeHHMM);
                            startOptions += `<option value="${slot.start}">${slot.start}</option>`;
                            distinctEndTimes[slot.end] = true;
                            if (isSelectedStart) foundSavedStart = true;
                        });
                        Object.keys(distinctEndTimes).sort().forEach(endTime => {
                            endOptions += `<option value="${endTime}">${endTime}</option>`;
                            if (endTime === savedEndTimeHHMM) foundSavedEnd = true;
                        });
                    } else {
                        startOptions = '<option value="">No Slots Available</option>';
                        endOptions = '<option value="">N/A</option>';
                        console.log(`No slots found or error for date ${selectedDate}`);
                    }

                    startSelect.innerHTML = startOptions;
                    endSelect.innerHTML = endOptions;

                    if (foundSavedStart) {
                        startSelect.value = savedStartTimeHHMM;
                    } else if (savedStartTime) {
                        console.warn(`Makeup start time ${savedStartTime} unavailable for ${selectedDate}.`);
                        startSelect.insertAdjacentHTML('afterbegin', `<option value="" disabled selected>Saved: ${savedStartTimeHHMM} (N/A)</option>`);
                    }

                    if (foundSavedEnd) {
                        endSelect.value = savedEndTimeHHMM;
                    } else if (savedEndTime) {
                        console.warn(`Makeup end time ${savedEndTime} unavailable for ${selectedDate}.`);
                        if (!endSelect.querySelector('option[disabled][selected]')) endSelect.insertAdjacentHTML('afterbegin', `<option value="" disabled selected>Saved: ${savedEndTimeHHMM} (N/A)</option>`);
                    }
                    console.log(`Finished updating makeup selects for ${selectedDate}`);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    startSelect.innerHTML = '<option value="">Error loading slots</option>';
                    endSelect.innerHTML = '<option value="">Error loading slots</option>';
                    console.error(`Error fetching makeup slots for ${selectedDate}: ${textStatus}`, errorThrown, jqXHR);
                    Swal.fire('Error', `Could not fetch time slots for ${selectedDate}.`, 'error');
                }
            });
        }

        // --- JS: addDefaultSchedule (No changes needed) ---
        function addDefaultSchedule() {
            /* ... as before ... */
            const template = document.getElementById("default-schedule-template").content.cloneNode(true);
            const newItemElement = template.querySelector('.default-schedule-item'); // Get element before appending template
            const container = document.getElementById("default-schedules");

            if (!newItemElement) {
                console.error("Template structure error");
                return;
            }

            const existingItems = container.querySelectorAll('.default-schedule-item');
            const nextIndex = existingItems.length;

            newItemElement.querySelectorAll('[name^="default_day"]').forEach(el => el.name = `default_day[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="default_start_time"]').forEach(el => el.name = `default_start_time[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="default_end_time"]').forEach(el => el.name = `default_end_time[${nextIndex}]`);

            container.appendChild(template); // Append the whole template content

            // Get the actual added element from the DOM
            const addedItemElement = container.querySelector('.default-schedule-item:last-child');
            if (addedItemElement) {
                // updateDefaultTimeSelects(addedItemElement); // Don't call yet, day not selected
                attachScheduleEventListeners(addedItemElement); // Attach listeners
            } else {
                console.error("Failed to find the newly added default schedule item element.");
            }
        }

        // --- JS: addMakeupSchedule (No changes needed) ---
        function addMakeupSchedule() {
            /* ... as before ... */
            const template = document.getElementById("makeup-schedule-template").content.cloneNode(true);
            const newItemElement = template.querySelector('.makeup-schedule-item'); // Get element before appending template
            const container = document.getElementById("makeup-schedules");

            if (!newItemElement) {
                console.error("Makeup template structure error");
                return;
            }

            const existingItems = container.querySelectorAll('.makeup-schedule-item');
            const nextIndex = existingItems.length;
            // Set names...
            newItemElement.querySelectorAll('[name^="makeup_date"]').forEach(el => el.name = `makeup_date[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="makeup_start_time"]').forEach(el => el.name = `makeup_start_time[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="makeup_end_time"]').forEach(el => el.name = `makeup_end_time[${nextIndex}]`);
            newItemElement.querySelectorAll('[name^="makeup_notes"]').forEach(el => el.name = `makeup_notes[${nextIndex}]`);
            container.appendChild(template); // Append the whole template content

            // Get the actual added element from the DOM
            const addedItemElement = container.querySelector('.makeup-schedule-item:last-child');
            if (addedItemElement) {
                attachScheduleEventListeners(addedItemElement);
            } else {
                console.error("Failed find added makeup item.");
            }
        }


        // --- JS: attachScheduleEventListeners (REVISED - Added Start Time Listener, Refined Remove) ---
        function attachScheduleEventListeners(scheduleItem) {
            if (!scheduleItem) {
                console.warn("attachScheduleEventListeners called with null item");
                return;
            }
            console.log("Attaching listeners to:", scheduleItem);

            // Day change listener
            scheduleItem.querySelector('.default-day-select')?.addEventListener('change', function() {
                console.log("Default day changed for:", scheduleItem);
                updateDefaultTimeSelects(scheduleItem); // Update self first
                // Update siblings on the *newly* selected day
                const newlySelectedDay = this.value;
                if (newlySelectedDay) {
                    updateSiblingDefaultSchedules(scheduleItem, newlySelectedDay);
                }
                // Also update siblings on the *previously* selected day if needed (more complex state tracking needed, maybe skip for now)
            });

            // Start Time change listener
            scheduleItem.querySelector('.default-start-time-select')?.addEventListener('change', function(event) {
                console.log("Default start time changed for:", scheduleItem);
                // Validate against other rows on the form AND update siblings
                handleStartTimeChange(event.target);
            });

            // Date change listener
            scheduleItem.querySelector('.makeup-date-input')?.addEventListener('change', function() {
                console.log("Makeup date changed for:", scheduleItem);
                updateMakeupTimeSelects(scheduleItem);
            });

            // Remove button listener
            scheduleItem.querySelector('.remove-schedule-btn')?.addEventListener('click', function() {
                console.log("Remove clicked for:", scheduleItem);
                const dayToRemove = scheduleItem.querySelector('.default-day-select')?.value; // Get day before removing
                const isDefault = !!dayToRemove;

                UIkit.modal.confirm('Are you sure you want to remove this schedule entry?').then(() => {
                    scheduleItem.remove();
                    // If a default item was removed, update remaining siblings on the same day
                    if (isDefault && dayToRemove) {
                        console.log(`Updating siblings after removing item on ${dayToRemove}`);
                        updateSiblingDefaultSchedules(null, dayToRemove); // Pass null for current item, just update others
                    }
                }, () => {});
            });

            // --- Initial Population Trigger ---
            setTimeout(() => {
                const daySelect = scheduleItem.querySelector('.default-day-select');
                if (daySelect && daySelect.value && !daySelect.options[daySelect.selectedIndex]?.disabled) {
                    console.log("Initial trigger: updateDefaultTimeSelects for", scheduleItem);
                    updateDefaultTimeSelects(scheduleItem);
                }
                const dateInput = scheduleItem.querySelector('.makeup-date-input');
                if (dateInput && dateInput.value) {
                    console.log("Initial trigger: updateMakeupTimeSelects for", scheduleItem);
                    updateMakeupTimeSelects(scheduleItem);
                }
            }, 150); // Slightly increased delay for safety
        }

        // --- NEW JS: Function to handle start time change and update siblings ---
        function handleStartTimeChange(currentStartSelect) {
            const scheduleItem = currentStartSelect.closest('.default-schedule-item');
            if (!scheduleItem) return;

            const daySelect = scheduleItem.querySelector('.default-day-select');
            const selectedDay = daySelect?.value;
            const selectedStartTime = currentStartSelect.value;

            if (!selectedDay) return; // Day must be selected

            // 1. Check for immediate conflict with others
            let conflictFound = false;
            document.querySelectorAll('.default-schedule-item').forEach(otherItem => {
                if (otherItem !== scheduleItem) {
                    const otherDaySelect = otherItem.querySelector('.default-day-select');
                    const otherStartSelect = otherItem.querySelector('.default-start-time-select');
                    if (otherDaySelect?.value === selectedDay && otherStartSelect?.value === selectedStartTime && selectedStartTime !== "") { // Check only if time is selected
                        conflictFound = true;
                    }
                }
            });

            if (conflictFound) {
                console.warn(`Local Conflict Detected on Change: ${selectedDay} ${selectedStartTime}`);
                Swal.fire({
                    icon: 'warning',
                    title: 'Schedule Conflict',
                    text: `The time slot ${selectedStartTime} on ${selectedDay} is already selected in another row.`,
                    timer: 3000,
                    showConfirmButton: false
                });
                currentStartSelect.value = ""; // Reset the selection
                const endSelect = scheduleItem.querySelector('.default-end-time-select');
                if (endSelect) endSelect.value = "";
                // Update siblings AFTER resetting this one
                updateSiblingDefaultSchedules(scheduleItem, selectedDay);
            } else {
                // No immediate conflict found, just update siblings
                updateSiblingDefaultSchedules(scheduleItem, selectedDay);
            }
        }

        // --- NEW JS: Helper function to update sibling rows ---
        function updateSiblingDefaultSchedules(currentItem, dayToUpdate) {
            console.log(`Updating siblings for day: ${dayToUpdate}, triggered by:`, currentItem);
            document.querySelectorAll('.default-schedule-item').forEach(otherItem => {
                if (otherItem !== currentItem) { // Don't update the item that triggered the change
                    const otherDaySelect = otherItem.querySelector('.default-day-select');
                    // Update only if the sibling is ALSO on the affected day
                    if (otherDaySelect && otherDaySelect.value === dayToUpdate) {
                        console.log(`Updating sibling:`, otherItem);
                        updateDefaultTimeSelects(otherItem);
                    }
                }
            });
        }


        // --- JS: DOMContentLoaded ---
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOMContentLoaded event");
            document.querySelectorAll('.default-schedule-item, .makeup-schedule-item').forEach(item => {
                attachScheduleEventListeners(item);
            });

            // Session messages...
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

            // Form Validation
            const form = document.getElementById('update-patient-form');
            if (form) {
                form.addEventListener('submit', function(event) {
                    /* Submit validation logic as before */
                    let isValid = true;
                    const errorMessages = [];
                    const formSelections = {}; // Temp object for submit validation

                    // Validate start < end
                    document.querySelectorAll('.default-schedule-item, .makeup-schedule-item').forEach(item => {
                        const startSelect = item.querySelector('.default-start-time-select, .makeup-start-time-select');
                        const endSelect = item.querySelector('.default-end-time-select, .makeup-end-time-select');
                        const dayOrDate = item.querySelector('.default-day-select, .makeup-date-input');
                        if (startSelect?.value && endSelect?.value && startSelect.value >= endSelect.value) {
                            isValid = false;
                            const identifier = dayOrDate?.value || 'Unknown Row';
                            errorMessages.push(`Schedule on ${identifier}: Start time (${startSelect.value}) must be before end time (${endSelect.value}).`);
                        }
                    });

                    // Validate local default schedule conflicts on submit
                    document.querySelectorAll('.default-schedule-item').forEach(item => {
                        const daySelect = item.querySelector('.default-day-select');
                        const startSelect = item.querySelector('.default-start-time-select');
                        const day = daySelect?.value;
                        const start = startSelect?.value;
                        if (day && start) {
                            const key = `${day}-${start}`;
                            if (formSelections[key]) {
                                isValid = false;
                                errorMessages.push(`Duplicate schedule selection: ${day} at ${start}. Please ensure each time slot is selected only once.`);
                            }
                            formSelections[key] = true;
                        } else if (day && !start && !daySelect.options[daySelect.selectedIndex]?.disabled) {
                            isValid = false;
                            errorMessages.push(`Missing Start Time for selected day: ${day}.`);
                        }
                    });

                    if (!isValid) {
                        event.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Schedule Entries',
                            html: errorMessages.join('<br>')
                        });
                    }

                });
            } else {
                console.error("Form #update-patient-form not found!");
            }
        });
    </script>

</body>

</html>