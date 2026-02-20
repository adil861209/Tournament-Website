<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

// Read raw body
$data = json_decode(file_get_contents("php://input"));

if(empty($data->report_id) || !isset($data->is_read)) {
    echo json_encode(["success" => false, "message" => "Missing report ID or read status."]);
    exit;
}

$report_id = $data->report_id;
$is_read = $data->is_read; 

try {
    $query = "UPDATE reports SET is_read = :is_read WHERE id = :report_id";
    $stmt = $conn->prepare($query);
    
    if($stmt->execute([':is_read' => $is_read, ':report_id' => $report_id])) {
        echo json_encode(["success" => true, "message" => "Report status updated."]);
    } else {
        $errorInfo = $stmt->errorInfo();
        echo json_encode(["success" => false, "message" => "Database execution failed: " . $errorInfo[2]]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Critical Database Error: " . $e->getMessage()]);
}
?>
