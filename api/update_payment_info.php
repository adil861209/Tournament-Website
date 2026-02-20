<?php
include_once '../config.php';
$conn = getDBConnection();
check_admin($conn);

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->action)) {
    echo json_encode(["success" => false, "message" => "Invalid request data."]);
    exit;
}

$action = $data->action;

try {
    if ($action === 'add' || $action === 'update') {
        if (!isset($data->account_type) || !isset($data->account_holder_name) || !isset($data->account_number)) {
            echo json_encode(["success" => false, "message" => "Missing required fields."]);
            exit;
        }

        $account_type = sanitizeInput($data->account_type);
        $account_holder_name = sanitizeInput($data->account_holder_name);
        $account_number = sanitizeInput($data->account_number);

        if ($action === 'add') {
            // Add new payment info
            $query = "INSERT INTO payment_info (account_type, account_holder_name, account_number, is_active) VALUES (?, ?, ?, 1)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$account_type, $account_holder_name, $account_number]);

            echo json_encode(["success" => true, "message" => "Payment information added successfully."]);
        } else {
            // Update existing payment info (assuming we're updating the first active one, or you can modify to update by ID)
            $query = "UPDATE payment_info SET account_type = ?, account_holder_name = ?, account_number = ?, updated_at = NOW() WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([$account_type, $account_holder_name, $account_number]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Payment information updated successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "No active payment information found to update."]);
            }
        }
    } elseif ($action === 'delete') {
        if (!isset($data->id)) {
            echo json_encode(["success" => false, "message" => "Payment info ID required for deletion."]);
            exit;
        }

        $query = "DELETE FROM payment_info WHERE id = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([$data->id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Payment information deleted successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "Payment information not found or already deleted."]);
        }
    } elseif ($action === 'list') {
        // List all payment info for admin
        $query = "SELECT * FROM payment_info ORDER BY is_active DESC, updated_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();

        $payment_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "data" => $payment_info]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action."]);
    }

} catch (PDOException $e) {
    error_log("Payment info update error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Payment info general error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error occurred."]);
}
?>
