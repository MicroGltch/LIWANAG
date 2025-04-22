<?php
require_once "../../dbconfig.php"; // Adjust path if needed
session_start();

// --- Authentication & Role Check ---
// Ensure ONLY head therapists (or admins if desired) can access
$allowedRoles = ['head therapist', 'admin']; // Add 'admin' if they should also have access
if (!isset($_SESSION['account_ID']) || !isset($_SESSION['account_Type']) || !in_array(strtolower($_SESSION['account_Type']), $allowedRoles)) {
    // Redirect to login or show an access denied message
    // header("Location: /login.php"); // Example redirect
    die("Access Denied. Head Therapist login required.");
    exit();
}

// $userId = (int)$_SESSION['account_ID']; // Head therapist's own ID, not usually needed for display logic

// --- Configuration ---
$apiEndpointAdmin = 'get_therapist_schedule_admin.php'; // Replace with your actual path
$pdfGenerationEnabled = false; // Set to true when you integrate a JS PDF library

$pageTitle = "Therapist Schedules";
$mainHeading = $pageTitle;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
     <style>
        /* Embed the entire CSS content from style.css here */
        body {
            font-family: sans-serif; margin: 20px; background-color: #f4f7f6; color: #333;
        }
        .container {
            max-width: 900px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            text-align: center; color: #0056b3;
        }
        .controls, .therapist-selector {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 10px; background-color: #e9ecef; border-radius: 5px;
        }
        .therapist-selector label { margin-right: 10px; font-weight: bold; }
        .therapist-selector select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px; flex-grow: 1; }

        button, #download-pdf {
            padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s ease; white-space: nowrap;
        }
        button:disabled, #download-pdf:disabled { background-color: #cccccc; cursor: not-allowed; }
        button:hover:not(:disabled), #download-pdf:hover:not(:disabled) { background-color: #0056b3; }
        #date-range {
            font-weight: bold; font-size: 1.1em; text-align: center; flex-grow: 1; margin: 0 10px;
        }
        #schedule-container {
            margin-top: 20px; border: 1px solid #ddd; border-radius: 5px; overflow: hidden; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;
        }
        .day-column {
            background-color: #fdfdfd; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 4px; display: flex; flex-direction: column;
        }
        .day-header {
            font-size: 1.2em; font-weight: bold; color: #333; margin-bottom: 15px; padding-bottom: 5px; border-bottom: 2px solid #007bff; text-align: center;
        }
        .slots-list { flex-grow: 1; }
        .slot {
            padding: 8px 5px; margin-bottom: 5px; border-radius: 3px; display: flex; justify-content: space-between; align-items: center; font-size: 0.9em; border-left-width: 5px; border-left-style: solid;
        }
        .slot .time { font-weight: bold; color: #555; flex-basis: 45%; text-align: left; }
        .slot .details { flex-basis: 50%; text-align: right; }
        .slot.free { background-color: #e9f5e9; border-left-color: #28a745; }
        .slot.free .details { color: #28a745; font-style: italic; }
        .slot.occupied { background-color: #fbe9e9; border-left-color: #dc3545; }
        .slot.occupied .details { color: #dc3545; font-weight: bold; }
        .no-slots { color: #888; text-align: center; padding: 20px; font-style: italic; }
        .loading-indicator, .error-message { text-align: center; padding: 20px; font-size: 1.1em; display: none; }
        .loading-indicator { color: #007bff; }
        .error-message { color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($mainHeading); ?></h1>

        <!-- Therapist Selector is ALWAYS present for Head Therapist -->
        <div class="therapist-selector">
            <label for="therapist-select">Select Therapist:</label>
            <select id="therapist-select" name="therapist">
                <option value="">Loading Therapists...</option>
            </select>
        </div>

        <div class="controls">
            <button id="prev-week" disabled>< Prev</button>
            <span id="date-range">Select a therapist</span>
            <button id="next-week" disabled>Next ></button>
        </div>

        <div class="loading-indicator" id="loading">Loading Schedule...</div>
        <div class="error-message" id="error"></div>

        <div id="schedule-container">
             <p class="no-slots">Please select a therapist to view their schedule.</p>
        </div>

         <div style="text-align: center; margin-top: 20px;">
             <button id="download-pdf" disabled>Download Schedule (PDF)</button>
         </div>
    </div>

    <script>
        // Embed the JavaScript specifically for the Head Therapist view
        document.addEventListener('DOMContentLoaded', () => {
            // --- Configuration ---
            const API_ENDPOINT_ADMIN = <?php echo json_encode($apiEndpointAdmin); ?>;
            const PDF_GENERATION_ENABLED = <?php echo json_encode($pdfGenerationEnabled); ?>;

            // --- DOM Elements ---
            const therapistSelect = document.getElementById('therapist-select');
            const prevWeekBtn = document.getElementById('prev-week');
            const nextWeekBtn = document.getElementById('next-week');
            const dateRangeLabel = document.getElementById('date-range');
            const scheduleContainer = document.getElementById('schedule-container');
            const loadingIndicator = document.getElementById('loading');
            const errorElement = document.getElementById('error');
            const downloadBtn = document.getElementById('download-pdf');

            // --- State ---
            let currentStartDate = getMonday(new Date());
            let selectedTherapistId = null;
            let therapists = [];
            let currentScheduleData = null;

            // --- Initialization ---
            function init() {
                addEventListeners();
                fetchTherapistList(); // Start by fetching list
                enableControls(false); // Controls disabled until selection
            }

            // --- Event Listeners ---
            function addEventListeners() {
                therapistSelect.addEventListener('change', handleTherapistChange);
                prevWeekBtn.addEventListener('click', handlePrevWeek);
                nextWeekBtn.addEventListener('click', handleNextWeek);
                downloadBtn.addEventListener('click', handleDownload);
            }

            // --- Event Handlers ---
            function handleTherapistChange() {
                selectedTherapistId = therapistSelect.value;
                if (selectedTherapistId) {
                    enableControls(true);
                    fetchScheduleData();
                } else {
                    clearScheduleView("Select a therapist");
                    enableControls(false);
                }
            }
            function handlePrevWeek() {
                if (!selectedTherapistId) return;
                currentStartDate.setDate(currentStartDate.getDate() - 7);
                fetchScheduleData();
            }
            function handleNextWeek() {
                if (!selectedTherapistId) return;
                currentStartDate.setDate(currentStartDate.getDate() + 7);
                fetchScheduleData();
            }
            function handleDownload() {
                if (!selectedTherapistId || !currentScheduleData || currentScheduleData.length === 0) {
                    alert("Select a therapist and ensure schedule data is loaded.");
                    return;
                }

                // Get current start date for the URL parameter
                const startDateStr = formatDateForAPI(currentStartDate);
                const therapistIdToDownload = selectedTherapistId; // Use the selected ID

                // Construct the URL for the PDF generation script
                // *** ADJUST THE PATH TO generate_schedule_pdf.php ***
                const url = `generate_schedule_pdf.php?therapist_id=${therapistIdToDownload}&start_date=${startDateStr}`;

                // Open the URL in a new tab/window
                window.open(url, '_blank');
            }

            // --- Data Fetching ---
            async function fetchTherapistList() {
                showLoading(true, "Loading Therapists..."); showError(null); enableControls(false);
                const url = `${API_ENDPOINT_ADMIN}?action=get_list`;
                try {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    const data = await response.json();
                    if (data.status === 'success' && data.therapists) {
                        therapists = data.therapists; populateTherapistDropdown(data.therapists);
                    } else { throw new Error(data.message || 'Failed to load therapist list.'); }
                } catch (error) {
                    console.error('Fetch Therapists Error:', error); showError(`Failed to load therapists: ${error.message}`);
                    if (therapistSelect) therapistSelect.innerHTML = '<option value="">Error loading</option>';
                } finally { showLoading(false); enableControls(!!selectedTherapistId); }
            }
            async function fetchScheduleData() {
                if (!selectedTherapistId) { clearScheduleView("Select a therapist"); return; }
                showLoading(true, "Loading Schedule..."); showError(null); downloadBtn.disabled = true; currentScheduleData = null;
                const endDate = new Date(currentStartDate); endDate.setDate(endDate.getDate() + 6);
                const startDateStr = formatDateForAPI(currentStartDate); const endDateStr = formatDateForAPI(endDate);
                const url = `${API_ENDPOINT_ADMIN}?action=get_schedule&therapist_id=${selectedTherapistId}&start_date=${startDateStr}&end_date=${endDateStr}`;
                try {
                    const response = await fetch(url);
                    if (!response.ok) { let errorMsg = `Error: ${response.status} ${response.statusText}`; try { const errorData = await response.json(); errorMsg = errorData.message || errorMsg; } catch(e){} throw new Error(errorMsg); }
                    const data = await response.json();
                    if (data.status === 'success') {
                        currentScheduleData = data.schedule;
                        renderSchedule(data.schedule || []);
                        // Enable download button if data exists
                        downloadBtn.disabled = !currentScheduleData || currentScheduleData.length === 0;
                    } else { throw new Error(data.message || 'Failed to load schedule data.'); }
                } catch (error) {
                    console.error('Fetch Schedule Error:', error); showError(`Failed to load schedule: ${error.message}`); renderSchedule([]);
                } finally { showLoading(false); }
            }

            // --- UI Rendering & Updates ---
             function populateTherapistDropdown(therapistList) {
                 therapistSelect.innerHTML = '<option value="">-- Select a Therapist --</option>';
                 if (!therapistList || therapistList.length === 0) { therapistSelect.innerHTML = '<option value="">No therapists found</option>'; return; }
                 therapistList.sort((a, b) => { const nameA = `${a.lastName} ${a.firstName}`.toLowerCase(); const nameB = `${b.lastName} ${b.firstName}`.toLowerCase(); if (nameA < nameB) return -1; if (nameA > nameB) return 1; return 0; });
                 therapistList.forEach(therapist => { const option = document.createElement('option'); option.value = therapist.id; option.textContent = `${therapist.lastName}, ${therapist.firstName}`; therapistSelect.appendChild(option); });
             }
             function renderSchedule(scheduleDays) { // Identical renderSchedule function
                scheduleContainer.innerHTML = ''; updateDateRangeLabel();
                if (!scheduleDays || scheduleDays.length === 0) {
                     if (selectedTherapistId) { scheduleContainer.innerHTML = '<p class="no-slots">No schedule data available for this period.</p>'; }
                     else { scheduleContainer.innerHTML = '<p class="no-slots">Please select a therapist to view their schedule.</p>'; }
                    return;
                }
                scheduleDays.forEach(day => {
                    const dayColumn = document.createElement('div'); dayColumn.classList.add('day-column');
                    const dayHeader = document.createElement('h3'); dayHeader.classList.add('day-header'); dayHeader.textContent = formatDateForDisplay(new Date(day.date + 'T00:00:00')); dayColumn.appendChild(dayHeader);
                    const slotsList = document.createElement('div'); slotsList.classList.add('slots-list');
                    if (!day.slots || day.slots.length === 0) { const noSlots = document.createElement('p'); noSlots.classList.add('no-slots'); noSlots.textContent = 'No scheduled slots / Unavailable'; slotsList.appendChild(noSlots); }
                    else { day.slots.forEach(slot => { const slotElement = document.createElement('div'); slotElement.classList.add('slot', slot.status); const timeSpan = document.createElement('span'); timeSpan.classList.add('time'); timeSpan.textContent = `${formatTime(slot.startTime)} - ${formatTime(slot.endTime)}`; const detailsSpan = document.createElement('span'); detailsSpan.classList.add('details'); detailsSpan.textContent = (slot.status === 'free') ? 'Free' : (slot.patientName || 'Occupied'); slotElement.appendChild(timeSpan); slotElement.appendChild(detailsSpan); slotsList.appendChild(slotElement); }); }
                    dayColumn.appendChild(slotsList); scheduleContainer.appendChild(dayColumn);
                });
            }
            function clearScheduleView(message = "") { scheduleContainer.innerHTML = `<p class="no-slots">${message}</p>`; dateRangeLabel.textContent = message; showError(null); currentScheduleData = null; downloadBtn.disabled = true; }
            function updateDateRangeLabel() { if (!selectedTherapistId) { dateRangeLabel.textContent = "Select a therapist"; return; } const endDate = new Date(currentStartDate); endDate.setDate(endDate.getDate() + 6); dateRangeLabel.textContent = `${formatDateForDisplay(currentStartDate, false)} - ${formatDateForDisplay(endDate, false)}`; }
            function showLoading(isLoading, message = "Loading Schedule...") { loadingIndicator.textContent = message; loadingIndicator.style.display = isLoading ? 'block' : 'none'; scheduleContainer.style.display = isLoading ? 'none' : 'grid'; if (isLoading) { errorElement.style.display = 'none'; } }
            function showError(message) { if (message) { errorElement.textContent = message; errorElement.style.display = 'block'; scheduleContainer.style.display = 'none'; } else { errorElement.style.display = 'none'; } }
            function enableControls(enabled) { prevWeekBtn.disabled = !enabled; nextWeekBtn.disabled = !enabled; /* Download handled separately */ }

            // --- Utility Functions --- (Identical utility functions)
             function getMonday(d) { d = new Date(d); const day = d.getDay(); const diff = d.getDate() - day + (day === 0 ? -6 : 1); return new Date(d.setDate(diff)); }
             function formatDateForAPI(date) { const d = new Date(date); const year = d.getFullYear(); const month = String(d.getMonth() + 1).padStart(2, '0'); const day = String(d.getDate()).padStart(2, '0'); return `${year}-${month}-${day}`; }
             function formatDateForDisplay(date, includeDayName = true) { const options = { month: 'short', day: 'numeric' }; if (includeDayName) { options.weekday = 'long'; } return date.toLocaleDateString(undefined, options); }
             function formatTime(timeString) { if (!timeString) return ''; try { const [hours, minutes] = timeString.split(':'); const hour = parseInt(hours, 10); const ampm = hour >= 12 ? 'PM' : 'AM'; const displayHour = hour % 12 === 0 ? 12 : hour % 12; return `${displayHour}:${minutes} ${ampm}`; } catch (e) { console.warn("Could not format time:", timeString); return timeString; } }

            // --- Start ---
            init();
        });
    </script>
</body>
</html>