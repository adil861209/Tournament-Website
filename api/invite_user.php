<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$data = json_decode(file_get_contents("php://input"));

if(empty($data->team_id) || empty($data->identifier) || empty($data->inviter_id)) {
    http_response_code(400); 
    echo json_encode(["success" => false, "message" => "Missing team ID, inviter ID, or recipient identifier."]);
    exit;
}

$team_id = $data->team_id;
$inviter_id = $data->inviter_id;
$recipient_identifier = $data->identifier;

try {
    // 1. Authorization Check: Ensure inviter is Captain/Admin (Optional, but safe)
    $query_auth = "SELECT role FROM team_members WHERE user_id = :inviter_id AND team_id = :team_id LIMIT 1";
    $stmt_auth = $conn->prepare($query_auth);
    $stmt_auth->execute([':inviter_id' => $inviter_id, ':team_id' => $team_id]);
    $inviter_role = $stmt_auth->fetchColumn();

    if ($inviter_role !== 'captain' && $inviter_role !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Permission denied: Only the captain or admin can invite."]);
        exit;
    }

    // 2. Find the User to be invited (Recipient)
    $query_find = "SELECT id, team_id FROM users WHERE email = ? OR name = ? LIMIT 1";
    $stmt_find = $conn->prepare($query_find);
    $stmt_find->execute([$recipient_identifier, $recipient_identifier]);
    $recipient = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$recipient) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User with that identifier not found."]);
        exit;
    }
    
    $recipient_id = $recipient['id'];

    // 3. CRITICAL: Check if recipient is already on a team (in the USERS or TEAM_MEMBERS table)
    $query_check_team = "SELECT COUNT(*) FROM team_members WHERE user_id = :id LIMIT 1";
    $stmt_check_team = $conn->prepare($query_check_team);
    $stmt_check_team->bindParam(':id', $recipient_id);
    $stmt_check_team->execute();
    
    if ($stmt_check_team->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(["success" => false, "message" => "This user is already a member of a team and cannot be added."]);
        exit;
    }

    // 4. Check if the user is attempting to invite themselves (optional safety check)
    if ($recipient_id == $inviter_id) {
         http_response_code(400); 
         echo json_encode(["success" => false, "message" => "Cannot invite yourself."]);
         exit;
    }
    
    $conn->beginTransaction();

    // 5. Insert the user into team_members
    $query_insert = "INSERT INTO team_members (team_id, user_id, role) VALUES (:team_id, :recipient_id, 'member')"; 
    $stmt_insert = $conn->prepare($query_insert);
    $stmt_insert->bindParam(':team_id', $team_id);
    $stmt_insert->bindParam(':recipient_id', $recipient_id);
    $stmt_insert->execute();

    // 6. Update user's team_id in users table
    $query_update_user = "UPDATE users SET team_id = :team_id WHERE id = :user_id";
    $conn->prepare($query_update_user)->execute([':team_id' => $team_id, ':user_id' => $recipient_id]);

    $conn->commit();
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "User added to the team successfully."]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    error_log("Critical Database Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Critical Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    error_log("Critical PHP Execution Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Critical PHP Execution Error: " . $e->getMessage()]);
}
?>

