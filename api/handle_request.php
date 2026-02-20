<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$rawBody = file_get_contents("php://input");
$data = json_decode($rawBody);

if (empty($data->request_id) || empty($data->team_id) || empty($data->action) || empty($data->requester_id)) {
    http_response_code(400); 
    echo json_encode(["success" => false, "message" => "Missing required data (request_id, team_id, action, or requester_id)."]);
    exit;
}

$request_id = $data->request_id;
$team_id = $data->team_id;
$action = $data->action;
$requester_id = $data->requester_id;

try {
    // 1. Authorization Check: Ensure the requester is the CAPTAIN or ADMIN
    $query_auth = "SELECT role FROM team_members WHERE user_id = :requester_id AND team_id = :team_id LIMIT 1";
    $stmt_auth = $conn->prepare($query_auth);
    $stmt_auth->bindParam(':requester_id', $requester_id);
    $stmt_auth->bindParam(':team_id', $team_id);
    $stmt_auth->execute();
    $requester_role = $stmt_auth->fetchColumn();

    if ($requester_role !== 'captain' && $requester_role !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Permission denied: Only the captain or admin can manage requests."]);
        exit;
    }

    // 2. Fetch the user_id associated with the request
    $query_user = "SELECT user_id FROM team_requests WHERE id = :request_id AND team_id = :team_id LIMIT 1";
    $stmt_user = $conn->prepare($query_user);
    $stmt_user->bindParam(':request_id', $request_id);
    $stmt_user->bindParam(':team_id', $team_id);
    $stmt_user->execute();
    $target_user_id = $stmt_user->fetchColumn();

    if (!$target_user_id) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Request not found or invalid team ID."]);
        exit;
    }

    $conn->beginTransaction();
    $message = "";

    if ($action === 'accept') {
        
        // Check if user is already a member of ANY team (safety against race conditions)
        $query_check_membership = "SELECT COUNT(*) FROM team_members WHERE user_id = :user_id";
        $stmt_check_membership = $conn->prepare($query_check_membership);
        $stmt_check_membership->execute([':user_id' => $target_user_id]);
        if ($stmt_check_membership->fetchColumn() > 0) {
            // Already a member, reject the action but delete the request
            $query_delete_request = "DELETE FROM team_requests WHERE id = :request_id";
            $conn->prepare($query_delete_request)->execute([':request_id' => $request_id]);
            $conn->commit();
            http_response_code(409);
            echo json_encode(["success" => false, "message" => "User is already a member of a team. Request cleared."]);
            exit;
        }

        // A. Insert user into team_members
        $query_insert = "INSERT INTO team_members (team_id, user_id, role) VALUES (:team_id, :user_id, 'member')";
        $stmt_insert = $conn->prepare($query_insert);
        $stmt_insert->bindParam(':team_id', $team_id);
        $stmt_insert->bindParam(':user_id', $target_user_id);
        $stmt_insert->execute();

        // B. Update user's team_id in users table
        $query_update_user = "UPDATE users SET team_id = :team_id WHERE id = :user_id";
        $stmt_update_user = $conn->prepare($query_update_user);
        $stmt_update_user->bindParam(':team_id', $team_id);
        $stmt_update_user->bindParam(':user_id', $target_user_id);
        $stmt_update_user->execute();
        
        // C. Delete the request
        $query_delete_request = "DELETE FROM team_requests WHERE id = :request_id";
        $stmt_delete_request = $conn->prepare($query_delete_request);
        $stmt_delete_request->bindParam(':request_id', $request_id);
        $stmt_delete_request->execute();

        $message = "Request accepted. User is now a member.";

    } elseif ($action === 'reject') {
        
        // A. Delete the request
        $query_delete_request = "DELETE FROM team_requests WHERE id = :request_id";
        $stmt_delete_request = $conn->prepare($query_delete_request);
        $stmt_delete_request->bindParam(':request_id', $request_id);
        $stmt_delete_request->execute();
        
        $message = "Request successfully declined.";
    
    } else {
        throw new Exception("Invalid action specified.");
    }

    $conn->commit();
    http_response_code(200);
    echo json_encode(["success" => true, "message" => $message]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    error_log("DB Transaction Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database Transaction Error: " . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    error_log("Server Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
}
?>
