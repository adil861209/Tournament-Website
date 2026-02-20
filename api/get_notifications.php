<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$user_id = $_GET['user_id'] ?? null;

if($user_id) {
    // Fetch user notifications, ordered newest first, limited to 20
    $query = "SELECT id, message, type, is_read, created_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo json_encode([]);
}
