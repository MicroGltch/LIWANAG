<?php
require_once "../../dbconfig.php";

if (isset($_POST['email'])) {
    $email = $_POST['email'];

    try {
        $checkEmail = "SELECT * FROM users WHERE account_Email = ?";
        $stmt = $connection->prepare($checkEmail);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['exists' => true]);
        } else {
            echo json_encode(['exists' => false]);
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['error' => $e->getMessage()]); // Return error message as JSON
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]); // Catch any other errors
    }
} else {
    echo json_encode(['error' => 'Email not provided']);
}
?>