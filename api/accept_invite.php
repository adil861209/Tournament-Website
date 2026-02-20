<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);
$data = json_decode(file_get_contents("php://input"));

// Sanitize inputs
$invite_id = $data->invite_id ?? null;
$user_id = $data->user_id ?? null;

if(!empty($invite_id) && !empty($user_id)) {
    try {
        // 1. Get Invite Details
        $inv = $conn->prepare("SELECT team_id, status FROM invites WHERE id = ?");
        $inv->execute([$invite_id]);
        $invite = $inv->fetch();

        if(!$invite || $invite['status'] !== 'pending') { 
            echo json_encode(["message" => "Invalid or already handled invite."]); 
            exit; 
        }

        $conn->beginTransaction();

        // 2. Update User's Team in USERS table
        $updUser = $conn->prepare("UPDATE users SET team_id = ? WHERE id = ?");
        $updUser->execute([$invite['team_id'], $user_id]);

        // 3. Insert into TEAM_MEMBERS table (Crucial new step for consistency)
        $insMember = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
        $insMember->execute([$invite['team_id'], $user_id]);

        // 4. Update Invite Status
        $updInv = $conn->prepare("UPDATE invites SET status = 'accepted' WHERE id = ?");
        $updInv->execute([$invite_id]);
        
        $conn->commit();

        echo json_encode(["message" => "Joined team successfully!"]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(["message" => "Database Error during join: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["message" => "Missing required IDs."]);
}
?>
