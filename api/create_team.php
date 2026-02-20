<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

// For simplicity and matching the existing frontend, we use $_POST
if(!empty($_POST['user_id']) && !empty($_POST['team_name']) && !empty($_POST['game_type'])) {

    $user_id = $_POST['user_id'];
    // Strict sanitization for user-provided strings
    $team_name = htmlspecialchars(strip_tags($_POST['team_name']));
    $game_type = htmlspecialchars(strip_tags($_POST['game_type']));
    $members = $_POST['members'] ?? 'Captain details not recorded'; 

    $conn->beginTransaction();

    try {
        // 1. Check if the user already has a team (in the dedicated mapping table)
        $checkMember = $conn->prepare("SELECT COUNT(*) FROM team_members WHERE user_id = ?");
        $checkMember->execute([$user_id]);
        if ($checkMember->fetchColumn() > 0) {
            $conn->rollBack();
            echo json_encode(["message" => "User already belongs to a team. Leave your current team first."]);
            exit();
        }
        
        // 2. INSERT into TEAMS
        $query = "INSERT INTO teams (user_id, team_name, game_type, members, status) 
                  VALUES (:user_id, :team_name, :game_type, :members, 'pending')";
        
        $stmt = $conn->prepare($query);

        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':team_name', $team_name);
        $stmt->bindParam(':game_type', $game_type);
        $stmt->bindParam(':members', $members);

        if($stmt->execute()) {
            $team_id = $conn->lastInsertId();

            // 3. Update Captain's team_id in USERS table
            $updateUser = $conn->prepare("UPDATE users SET team_id = ? WHERE id = ?");
            $updateUser->execute([$team_id, $user_id]);

            // 4. CRITICAL: Add Captain to TEAM_MEMBERS table
            $insertMember = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'captain')");
            $insertMember->execute([$team_id, $user_id]);

            $conn->commit();
            echo json_encode(["message" => "Team created successfully!", "team_id" => $team_id]);
        } else {
            $conn->rollBack();
            $errorInfo = $stmt->errorInfo();
            error_log("SQL Error in create_team.php: " . $errorInfo[2]);
            echo json_encode(["message" => "Database execution error. Check SQL/columns."]);
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Critical DB Error in create_team.php: " . $e->getMessage());
        echo json_encode(["message" => "Critical Database Error: " . $e->getMessage()]);
    }

} else {
    echo json_encode(["message" => "Incomplete data sent to server (Missing user_id, team_name, or game_type)."]);
}
?>
