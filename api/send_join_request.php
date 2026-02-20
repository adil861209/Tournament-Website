<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

// Reading the raw body for JSON data
$input_data = file_get_contents("php://input");
$data = json_decode($input_data);

// Validation check: Check both the object properties and raw input safety
if (!$data || empty($data->user_id) || empty($data->team_id)) {
    http_response_code(400); 
    // Log the input if it fails validation, to help debug
    error_log("Failed join request validation. Raw input: " . $input_data);
    echo json_encode(["success" => false, "message" => "Missing user ID or team ID in request. (Validation Failed)"]);
    exit;
}

$user_id = $data->user_id;
$team_id = $data->team_id;

try {
    // 1. Check if the user is already associated with a team in the USERS table
    $query_check_user_team_id = "SELECT team_id FROM users WHERE id = :user_id LIMIT 1";
    $stmt_check_user_team_id = $conn->prepare($query_check_user_team_id);
    $stmt_check_user_team_id->bindParam(':user_id', $user_id);
    $stmt_check_user_team_id->execute();
    $user_team_data = $stmt_check_user_team_id->fetch(PDO::FETCH_ASSOC);

    if ($user_team_data && $user_team_data['team_id'] !== NULL) {
        http_response_code(409); 
        echo json_encode(["success" => false, "message" => "Your profile is still marked as belonging to a team. Please leave it first."]);
        exit;
    }
    
    // 2. Check if the user already has a pending request for this team (from team_requests table)
    $query_check_request = "SELECT id FROM team_requests WHERE user_id = :user_id AND team_id = :team_id LIMIT 1";
    $stmt_check_request = $conn->prepare($query_check_request);
    $stmt_check_request->bindParam(':user_id', $user_id);
    $stmt_check_request->bindParam(':team_id', $team_id);
    $stmt_check_request->execute();

    if ($stmt_check_request->rowCount() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(["success" => false, "message" => "You already have a pending request for this team."]);
        exit;
    }
    
    // 3. Insert the join request into the database
    $query_insert = "INSERT INTO team_requests (team_id, user_id) VALUES (:team_id, :user_id)"; 
    $stmt_insert = $conn->prepare($query_insert);
    
    $stmt_insert->bindParam(':team_id', $team_id);
    $stmt_insert->bindParam(':user_id', $user_id);

    if ($stmt_insert->execute()) {
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Join request successfully sent. Waiting for captain approval."]);
    } else {
        $errorInfo = $stmt_insert->errorInfo();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database insertion failed: " . $errorInfo[2]]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Critical Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Critical PHP Execution Error: " . $e->getMessage()]);
}
?>
