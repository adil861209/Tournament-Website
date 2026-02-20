<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

// Reading the raw body for JSON data
$data = json_decode(file_get_contents("php://input"));

if(empty($data->user_id) || empty($data->action)) {
    echo json_encode(["success" => false, "message" => "Missing user ID or action."]);
    exit;
}

$user_id = $data->user_id;
$action = $data->action;
$new_value = $data->new_value ?? null; 

try {
    if ($action === 'status') {
        // Handle Ban/Unban/Warn
        $query = "UPDATE users SET status = :new_value WHERE id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':new_value', $new_value);
    } elseif ($action === 'role') {
        // Handle Role Change
        $query = "UPDATE users SET role = :new_value WHERE id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':new_value', $new_value);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action specified."]);
        exit;
    }

    $stmt->bindParam(':user_id', $user_id);

    if($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "User $action updated to $new_value."]);
    } else {
        $errorInfo = $stmt->errorInfo();
        echo json_encode(["success" => false, "message" => "Database execution failed: " . $errorInfo[2]]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Critical Database Error: " . $e->getMessage()]);
}
?>
