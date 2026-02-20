<?php
include_once '../config.php';
$conn = getDBConnection();

header("Content-Type: application/json; charset=UTF-8");

// Get team ID from URL
$team_id = $_GET['id'] ?? null;

if (!$team_id || !is_numeric($team_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Valid team ID required"]);
    exit;
}

// Check if user is authenticated
$user = check_auth($conn);

try {
    // Get team details first
    $query = "SELECT t.*, 
                     (SELECT COUNT(*) as member_count 
                      FROM team_members tm 
                      WHERE tm.team_id = t.id) as member_count
               FROM teams t
               WHERE t.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$team) {
        http_response_code(404);
        echo json_encode(["error" => "Team not found"]);
        exit;
    }
    
    // Get team members separately
    $members_query = "SELECT tm.user_id, u.name, u.avatar, tm.role, u.whatsapp_number
                     FROM team_members tm
                     JOIN users u ON tm.user_id = u.id
                     WHERE tm.team_id = ?
                     ORDER BY tm.role DESC, u.name ASC";
    
    $members_stmt = $conn->prepare($members_query);
    $members_stmt->execute([$team_id]);
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if user is team member or admin
    $is_member = false;
    foreach ($members as $member) {
        if ($member['user_id'] == $user['id']) {
            $is_member = true;
            break;
        }
    }
    
    // Add members to team array
    $team['members'] = $members;
    
    echo json_encode([
        "success" => true,
        "team" => $team,
        "is_member" => $is_member,
        "can_manage" => $is_member || $user['role'] === 'admin'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>

