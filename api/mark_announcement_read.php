<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$data = json_decode(file_get_contents("php://input"));

if(empty($data->user_id) || empty($data->announcement_id)) {
    echo json_encode(["success" => false, "message" => "Missing user or announcement ID."]);
    exit;
}

$user_id = $data->user_id;
$announcement_id = $data->announcement_id;

try {
    // INSERT OR UPDATE: Tries to insert the status; if the unique key (user_id, announcement_id) conflicts, it UPDATES the existing row.
    $query = "
        INSERT INTO user_announcement_status (user_id, announcement_id, is_read) 
        VALUES (:user_id, :announcement_id, 1)
        ON DUPLICATE KEY UPDATE is_read = 1
    ";
    $stmt = $conn->prepare($query);
    
    if($stmt->execute([':user_id' => $user_id, ':announcement_id' => $announcement_id])) {
        echo json_encode(["success" => true, "message" => "Announcement marked as read."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update status."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
