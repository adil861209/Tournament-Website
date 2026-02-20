<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$data = json_decode(file_get_contents("php://input"));
$notification_id = $data->notification_id ?? null;

if($notification_id) {
    $query = "UPDATE notifications SET is_read = 1 WHERE id = :id";
    $stmt = $conn->prepare($query);
    if($stmt->execute([':id' => $notification_id])) {
        echo json_encode(["message" => "Marked as read."]);
    } else {
        echo json_encode(["message" => "Failed to update database."]);
    }
} else {
    echo json_encode(["message" => "Missing notification ID."]);
}
?>
