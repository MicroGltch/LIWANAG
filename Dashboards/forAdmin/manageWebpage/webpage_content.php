<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    header("Location: ../../../Accounts/loginpage.php");
    exit();
}

// ✅ Ensure only Admin can access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin"])) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: ../../../Accounts/loginpage.php");
    exit();
}

// Fetch existing content from the database
$stmt = $connection->prepare("SELECT section_name, content FROM webpage_content");
$stmt->execute();
$result = $stmt->get_result();

$content = [];
while ($row = $result->fetch_assoc()) {
    $content[$row['section_name']] = $row['content'];
}

$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $section => $newContent) {
        // Check if the section exists in the database
        $checkStmt = $connection->prepare("SELECT id FROM webpage_content WHERE section_name = ?");
        $checkStmt->bind_param("s", $section);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // Update existing record
            $updateStmt = $connection->prepare("UPDATE webpage_content SET content = ? WHERE section_name = ?");
            $updateStmt->bind_param("ss", $newContent, $section);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert new record
            $insertStmt = $connection->prepare("INSERT INTO webpage_content (section_name, content) VALUES (?, ?)");
            $insertStmt->bind_param("ss", $section, $newContent);
            $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();
    }

    // Refresh content from the database after update
    $stmt = $connection->prepare("SELECT section_name, content FROM webpage_content");
    $stmt->execute();
    $result = $stmt->get_result();

    $content = [];
    while ($row = $result->fetch_assoc()) {
        $content[$row['section_name']] = $row['content'];
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Webpage Content</title>

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
    <h2 class="uk-text-bold">Edit Webpage Contents</h2>
    
    <form id="webpageForm" method="POST" class="uk-form-stacked">

    <div class="uk-margin">
        <label class="uk-form-label">Email:</label>
        <div class="uk-form-controls">
            <textarea id="email" name="email" class="uk-input"  disabled><?php echo htmlspecialchars($content['email'] ?? ''); ?></textarea>
        </div>
    </div>

    <div class="uk-margin">
        <label class="uk-form-label">Mobile Number:</label>
        <div class="uk-form-controls">
            <textarea id="mobile" name="mobile" class="uk-input"  disabled><?php echo htmlspecialchars($content['mobile'] ?? ''); ?></textarea>
        </div>
    </div>

    <div class="uk-margin">
        <label class="uk-form-label">Address:</label>
        <div class="uk-form-controls">
            <textarea id="address" name="address" class="uk-textarea" rows="4" style="resize: vertical;" disabled><?php echo htmlspecialchars($content['address'] ?? ''); ?></textarea>
        </div>
    </div>


        <div class="uk-margin">
            <label class="uk-form-label">About Us:</label>
            <div class="uk-form-controls">
            <textarea id="about_us" name="about_us" class="uk-input" rows="12" cols="50" style="resize: vertical; min-height: 250px;" disabled><?php echo htmlspecialchars($content['about_us'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Terms & Conditions:</label>
            <div class="uk-form-controls">
            <textarea id="terms" name="terms" class="uk-input" rows="12" cols="50" style="resize: vertical; min-height: 250px;" disabled><?php echo htmlspecialchars($content['terms'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">FAQs:</label>
            <div class="uk-form-controls">
                <textarea id="faqs" name="faqs" class="uk-input" rows="12" cols="50" style="resize: vertical; min-height: 250px;" disabled><?php echo htmlspecialchars_decode($content['faqs'] ?? '', ENT_QUOTES); ?></textarea>
            </div>
        </div>

        <div class="uk-text-right">
            <button type="button" id="editButton" class="uk-button uk-button-default" style="border-radius: 15px; background-color: #1a202c; color:white;">Edit</button>
            <button type="button" id="cancelButton" class="uk-button uk-button-default" style="display: none; border-radius: 15px;">Cancel</button>
            <button type="submit" id="saveButton" class="uk-button uk-button-primary" style="display: none; border-radius: 15px;">Save Contents</button>
        </div>

    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButton = document.getElementById('editButton');
            const cancelButton = document.getElementById('cancelButton');
            const saveButton = document.getElementById('saveButton');
            const textareas = document.querySelectorAll('textarea');
            const form = document.getElementById('webpageForm'); // Get the form element

            let originalValues = {};

            editButton.addEventListener('click', function() {
                textareas.forEach(textarea => {
                    textarea.disabled = false;
                    originalValues[textarea.id] = textarea.value; // Store original values
                });
                editButton.style.display = 'none';
                cancelButton.style.display = 'inline-block';
                saveButton.style.display = 'inline-block';
            });

            cancelButton.addEventListener('click', function() {
                textareas.forEach(textarea => {
                    textarea.disabled = true;
                    textarea.value = originalValues[textarea.id]; // Restore original values
                });
                editButton.style.display = 'inline-block';
                cancelButton.style.display = 'none';
                saveButton.style.display = 'none';
            });

            saveButton.addEventListener('click', function(event) {
                event.preventDefault(); // Prevent default form submission

                Swal.fire({
                    title: 'Confirm Save',
                    text: 'Are you sure you want to save the changes?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit(); // Submit the form if confirmed
                    }
                });
            });
        });
    </script>
</body>


</html>