<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

$data = json_decode(file_get_contents("php://input"));
if(!empty($data->id)) {
    try {
        $stmt = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
        $stmt->execute([$data->id]);
        echo json_encode(["message" => "Tournament Deleted"]);
    } catch (PDOException $e) {
        // Log or handle foreign key constraints if DELETE CASCADE is not set
        echo json_encode(["message" => "Failed to delete tournament due to database constraints: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["message" => "Missing ID"]);
}
