<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$rawBody = file_get_contents("php://input");
$data = json_decode($rawBody);

if (empty($data->target_user_id) || empty($data->team_id) || empty($data->requester_id)) {
    http_response_code(400); 
    echo json_encode(["success" => false, "message" => "Missing IDs (target, team, or requester)."]);
    exit;
}

$target_user_id = $data->target_user_id;
$team_id = $data->team_id;
$requester_id = $data->requester_id; // The captain/admin initiating the kick

try {
    // 1. Authorization Check: Ensure the requester is the CAPTAIN/ADMIN 
    $query_auth = "SELECT role FROM team_members WHERE user_id = :requester_id AND team_id = :team_id LIMIT 1";
    $stmt_auth = $conn->prepare($query_auth);
    $stmt_auth->bindParam(':requester_id', $requester_id);
    $stmt_auth->bindParam(':team_id', $team_id);
    $stmt_auth->execute();
    $requester_role = $stmt_auth->fetchColumn();

    if ($requester_role !== 'captain' && $requester_role !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Permission denied: Only the captain or admin can kick members."]);
        exit;
    }
    
    // 2. Safety: Prevent captain from kicking themselves (must use 'leave team')
    $query_target_role = "SELECT role FROM team_members WHERE user_id = :target_user_id AND team_id = :team_id LIMIT 1";
    $stmt_target_role = $conn->prepare($query_target_role);
    $stmt_target_role->execute([':target_user_id' => $target_user_id, ':team_id' => $team_id]);
    $target_role = $stmt_target_role->fetchColumn();

    if ($target_role === 'captain') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "A captain cannot be kicked; they must use the 'Leave Team' function."]);
        exit;
    }
    
    $conn->beginTransaction();

    // 3. Delete the user's membership record
    $query_delete = "DELETE FROM team_members WHERE user_id = :target_user_id AND team_id = :team_id";
    $stmt_delete = $conn->prepare($query_delete);
    $stmt_delete->bindParam(':target_user_id', $target_user_id);
    $stmt_delete->bindParam(':team_id', $team_id);
    
    if ($stmt_delete->execute()) {
        
        // 4. Update the kicked user's record in the users table
        $query_clear_user = "UPDATE users SET team_id = NULL WHERE id = :target_user_id";
        $stmt_clear_user = $conn->prepare($query_clear_user);
        $stmt_clear_user->bindParam(':target_user_id', $target_user_id);
        $stmt_clear_user->execute();

        $conn->commit();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Member successfully kicked."]);
    } else {
        $conn->rollBack();
        $errorInfo = $stmt_delete->errorInfo();
        http_response_code(500);
        error_log("Database error during kick: " . $errorInfo[2]); // Log detailed error
        echo json_encode(["success" => false, "message" => "Database error during kick: Failed to delete membership record."]);
    }

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    // CRITICAL: Return the specific database error to the client for debugging
    error_log("Critical Database Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Critical Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Critical PHP Execution Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Critical PHP Execution Error: " . $e->getMessage()]);
}
?>
