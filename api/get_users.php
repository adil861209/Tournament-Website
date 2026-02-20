<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

try {
    // Select all user data, including the new 'status' field
    $query = "SELECT id, name, email, role, created_at, status FROM users ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error while fetching users: " . $e->getMessage(), "users" => []]);
}
?>
