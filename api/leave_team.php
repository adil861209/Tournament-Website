<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$data = json_decode(file_get_contents("php://input"));

if (empty($data->user_id) || empty($data->team_id)) {
    http_response_code(400); 
    echo json_encode(["success" => false, "message" => "Missing user ID or team ID."]);
    exit;
}

$user_id = $data->user_id;
$team_id = $data->team_id;

try {
    $conn->beginTransaction();
    
    // 1. Delete the user's membership record from the team_members table
    $query_delete = "DELETE FROM team_members WHERE user_id = :user_id AND team_id = :team_id";
    $stmt_delete = $conn->prepare($query_delete);
    $stmt_delete->bindParam(':user_id', $user_id);
    $stmt_delete->bindParam(':team_id', $team_id);
    
    if ($stmt_delete->execute()) {
        
        // 2. CRITICAL: Clear the team_id from the user's record in the users table.
        $query_clear_user = "UPDATE users SET team_id = NULL WHERE id = :user_id";
        $stmt_clear_user = $conn->prepare($query_clear_user);
        $stmt_clear_user->bindParam(':user_id', $user_id);
        $stmt_clear_user->execute();
        
        // 3. Check if the team is now empty (or needs captain change)
        $query_count = "SELECT COUNT(*) FROM team_members WHERE team_id = :team_id";
        $stmt_count = $conn->prepare($query_count);
        $stmt_count->bindParam(':team_id', $team_id);
        $stmt_count->execute();
        $member_count = $stmt_count->fetchColumn();
        
        $message = "You have successfully left the team.";

        if ($member_count == 0) {
            // 4. Team is empty, delete the team record from the 'teams' table
            $query_disband = "DELETE FROM teams WHERE id = :team_id";
            $stmt_disband = $conn->prepare($query_disband);
            $stmt_disband->bindParam(':team_id', $team_id);
            $stmt_disband->execute();
            $message = "You have successfully left the team and it has been disbanded.";
        } else {
            // Check if the user leaving was the captain. If so, promote oldest member.
            $query_captain = "SELECT COUNT(*) FROM team_members WHERE team_id = :team_id AND role = 'captain'";
            $stmt_captain = $conn->prepare($query_captain);
            $stmt_captain->bindParam(':team_id', $team_id);
            $stmt_captain->execute();

            if ($stmt_captain->fetchColumn() == 0) {
                 // Promote the longest-standing member to captain
                 $query_promote = "UPDATE team_members SET role = 'captain' WHERE team_id = :team_id ORDER BY created_at ASC LIMIT 1";
                 $conn->prepare($query_promote)->execute([':team_id' => $team_id]);
                 $message .= " A new captain was automatically assigned.";
            }
        }
        
        $conn->commit();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => $message]);
        
    } else {
        $conn->rollBack();
        $errorInfo = $stmt_delete->errorInfo();
        http_response_code(500);
        error_log("Leave Team DB Error: " . $errorInfo[2]);
        echo json_encode(["success" => false, "message" => "Could not remove membership record."]);
    }

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    error_log("Critical Database Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Critical Database Error: " . $e->getMessage()]);
}
?>
