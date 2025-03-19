<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin"])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

$stmt = null; // Initialize $stmt

// ✅ Get selected role (default to "Clients")
$selected_role = isset($_GET['role']) ? $_GET['role'] : 'client';

if ($selected_role == "Patients") {
    // ✅ Fetch Patients Data (Including Client Name)
    $stmt = $connection->prepare(" SELECT p.patient_id, p.account_id, 
        p.first_name AS patient_fname, p.last_name AS patient_lname, 
        p.bday, p.gender, p.profile_picture, 
        u.account_FName AS client_fname, u.account_LName AS client_lname
        FROM patients p
        INNER JOIN users u ON p.account_id = u.account_ID;
    ");
} else if ($selected_role == "client" || $selected_role == "therapist") {
    $stmt = $connection->prepare("SELECT account_id, account_FName, account_LName, account_Email, account_PNum, account_Address, profile_picture FROM users WHERE account_Type = ? AND account_status = 'active'");
    $stmt->bind_param("s", $selected_role);
}


if ($stmt) { 
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    echo "Error: Invalid role selected.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Test</title>

    <!-- UIkit & Styles -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css">
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <link rel="stylesheet" href="../../CSS/style.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>
    <!-- Navbar -->
    <nav class="uk-navbar-container logged-in">
        <div class="uk-container">
            <div uk-navbar>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li>
                            <a href="#" class="uk-navbar-item">
                                <img class="profile-image" src="../../CSS/default.jpg" alt="Profile Image" uk-img>
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;">
                            <?php echo $_SESSION['username']; ?>
                        </li>
                        <li><a href="../../Accounts/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <hr class="solid">

    <!-- Main Content -->
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-height-viewport">
        <!-- Sidebar -->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
            <ul class="uk-nav uk-nav-default">
                <li><a href="../admindashboard.php">Dashboard</a></li>
                <li><a href="manageWebpage/timetable_settings.php">Manage Timetable Settings</a></li>
                <li><a href="../../Appointments/app_manage/view_all_appointments.php">View All Appointments</a></li>
                <li><a href="add_therapist.php">Manage Therapists (Adding Only)</a></li>
                <li class="uk-active"><a href="users.php">Users (Accounts)</a></li>
            </ul>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <h1 class="uk-text-bold">Manage Users</h1>

            <!-- Navigation Links -->
            <div class="uk-margin">
                <ul class="uk-subnav uk-subnav-pill">
                    <li class="<?= $selected_role == 'clients' ? 'uk-active' : '' ?>">
                        <a href="?role=client">Clients</a>
                    </li>
                    <li class="<?= $selected_role == 'Patients' ? 'uk-active' : '' ?>">
                        <a href="?role=Patients">Patients</a>
                    </li>
                    <li class="<?= $selected_role == 'therapists' ? 'uk-active' : '' ?>">
                        <a href="?role=therapist">Therapists</a>
                    </li>
                </ul>
            </div>

            <!-- User Table -->
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <table class="uk-table uk-table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <?php if ($selected_role == "Patients") : ?>
                                <th>Birthday</th>
                                <th>Gender</th>
                                <th>Client (Parent/Guardian)</th>
                            <?php else : ?>
                                <th>Email</th>
                                <th>Phone Number</th>
                            <?php endif; ?>
                            <th>Profile Picture</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            $selected_role == "Patients" 
                                                ? $user['patient_fname'] . ' ' . $user['patient_lname']
                                                : $user['account_FName'] . ' ' . $user['account_LName']
                                        ); ?>
                                    </td>
                                    <?php if ($selected_role == "Patients") : ?>
                                        <td><?= htmlspecialchars($user['bday']); ?></td>
                                        <td><?= htmlspecialchars($user['gender']); ?></td>
                                        <td><?= htmlspecialchars($user['client_fname'] . ' ' . $user['client_lname']); ?></td>
                                    <?php else : ?>
                                        <td><?= htmlspecialchars($user['account_Email']); ?></td>
                                        <td><?= htmlspecialchars($user['account_PNum']); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <img src="<?= $user['profile_picture'] ? '../uploads/profile_pictures/' . htmlspecialchars($user['profile_picture']) : '../CSS/default.jpg'; ?>" 
                                            width="40" height="40" class="uk-border-circle">
                                    </td>
                                    <?php if ($selected_role != "Patients") : ?>
                                        <td>
                                            <button class="uk-button uk-button-danger archive-user" data-account-id="<?= $user['account_id']; ?>">Archive</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>
</body>

<script>
    // Archive User
    document.querySelectorAll('.archive-user').forEach(button => {
        button.addEventListener('click', function() {
            const accountId = this.dataset.accountId;

            Swal.fire({
                title: 'Are you sure?',
                text: "This user will be archived and unable to access their Account!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../../Accounts/manageaccount/archive_account.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'account_id=' + accountId
                    }).then(response => {
                        if (response.ok) {
                            Swal.fire(
                                'Archived!',
                                'The account has been archived.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                'Failed to archive the account.',
                                'error'
                            );
                        }
                    });
                }
            });
        });
    });
</script>

</html>
