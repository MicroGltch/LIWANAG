<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['form_type'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit();
}

$formType = $_POST['form_type'];

switch ($formType) {
    case 'global_settings':
        $max_days_advance = $_POST['max_days_advance'];
        $min_days_advance = $_POST['min_days_advance'];
        $initial_eval_duration = $_POST['initial_eval_duration'];
        $playgroup_duration = $_POST['playgroup_duration'];
        $service_ot_duration = $_POST['service_ot_duration'];
        $service_bt_duration = $_POST['service_bt_duration'];
    
        // Check if the settings row exists
        $check = $connection->query("SELECT COUNT(*) as count FROM settings WHERE setting_id = 1");
        $row = $check->fetch_assoc();
    
        if ($row['count'] == 0) {
            // Insert new settings row
            $query = "INSERT INTO settings (
                        setting_id,
                        max_days_advance,
                        min_days_advance,
                        initial_eval_duration,
                        playgroup_duration,
                        service_ot_duration,
                        service_bt_duration,
                        updated_at
                      ) VALUES (1, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $connection->prepare($query);
            $stmt->bind_param(
                "iiiiii",
                $max_days_advance,
                $min_days_advance,
                $initial_eval_duration,
                $playgroup_duration,
                $service_ot_duration,
                $service_bt_duration
            );
        } else {
            // Update existing row
            $query = "UPDATE settings SET 
                        max_days_advance = ?, 
                        min_days_advance = ?, 
                        initial_eval_duration = ?, 
                        playgroup_duration = ?, 
                        service_ot_duration = ?, 
                        service_bt_duration = ?, 
                        updated_at = NOW()
                      WHERE setting_id = 1";
            $stmt = $connection->prepare($query);
            $stmt->bind_param(
                "iiiiii",
                $max_days_advance,
                $min_days_advance,
                $initial_eval_duration,
                $playgroup_duration,
                $service_ot_duration,
                $service_bt_duration
            );
        }
    
        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => "Settings saved successfully.",
                "updated_at" => date("F d, Y h:i A")
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save settings. SQL Error: " . $stmt->error
            ]);
        }
        break;
    
    

    case 'weekly_hours':
        if (isset($_POST['weekly_hours'])) {
            foreach ($_POST['weekly_hours'] as $day => $info) {
                $start = isset($info['closed']) ? null : (!empty($info['start']) ? $info['start'] : null);
                $end   = isset($info['closed']) ? null : (!empty($info['end']) ? $info['end'] : null);

                if (!isset($info['closed']) && $start !== null && $end !== null && $start >= $end) {
                    echo json_encode(["status" => "error", "message" => "Invalid hours for $day. Start must be before end."]);
                    exit;
                }

                $check = $connection->prepare("SELECT 1 FROM business_hours_by_day WHERE day_name = ?");
                $check->bind_param("s", $day);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;

                if ($exists) {
                    $update = $connection->prepare("UPDATE business_hours_by_day SET start_time = ?, end_time = ? WHERE day_name = ?");
                    $update->bind_param("sss", $start, $end, $day);
                    $update->execute();
                } else {
                    $insert = $connection->prepare("INSERT INTO business_hours_by_day (day_name, start_time, end_time) VALUES (?, ?, ?)");
                    $insert->bind_param("sss", $day, $start, $end);
                    $insert->execute();
                }
            }
        }

        echo json_encode(["status" => "success", "message" => "Weekly hours saved."]);
        break;

    case 'date_override':
        if (!empty($_POST['exception_date'])) {
            $exceptionDate = $_POST['exception_date'];
            $start = isset($_POST['exception_closed']) ? null : (!empty($_POST['exception_start']) ? $_POST['exception_start'] : null);
            $end   = isset($_POST['exception_closed']) ? null : (!empty($_POST['exception_end']) ? $_POST['exception_end'] : null);

            if (!isset($_POST['exception_closed']) && $start !== null && $end !== null && $start >= $end) {
                echo json_encode(["status" => "error", "message" => "Invalid override: Start must be before end."]);
                exit;
            }

            $check = $connection->prepare("SELECT 1 FROM business_hours_exceptions WHERE exception_date = ?");
            $check->bind_param("s", $exceptionDate);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;

            if ($exists) {
                $update = $connection->prepare("UPDATE business_hours_exceptions SET start_time = ?, end_time = ? WHERE exception_date = ?");
                $update->bind_param("sss", $start, $end, $exceptionDate);
                $update->execute();
            } else {
                $insert = $connection->prepare("INSERT INTO business_hours_exceptions (exception_date, start_time, end_time) VALUES (?, ?, ?)");
                $insert->bind_param("sss", $exceptionDate, $start, $end);
                $insert->execute();
            }

            echo json_encode([
                "status" => "success",
                "message" => "Override saved.",
                "changed" => "Date <b>" . date("F j, Y", strtotime($exceptionDate)) . "</b> is now " . 
                             ($start && $end ? "open from <b>$start</b> to <b>$end</b>" : "<b>closed</b>")
            ]);
            
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Unknown form type."]);
}


?>
