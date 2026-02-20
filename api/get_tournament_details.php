<?php
include_once '../config.php';
$conn = getDBConnection();

header("Content-Type: application/json; charset=UTF-8");

// Get tournament ID from URL
$tournament_id = $_GET['id'] ?? null;

if (!$tournament_id || !is_numeric($tournament_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Valid tournament ID required"]);
    exit;
}

try {
    // Get tournament details with team information
    $query = "SELECT t.*, 
                     (SELECT COUNT(*) as registered_teams 
                      FROM tournament_registrations tr 
                      WHERE tr.tournament_id = t.id) as registered_teams,
                     (SELECT COUNT(*) as total_matches 
                      FROM matches m 
                      WHERE m.tournament_id = t.id) as total_matches
              FROM tournaments t 
              WHERE t.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$tournament_id]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tournament) {
        http_response_code(404);
        echo json_encode(["error" => "Tournament not found"]);
        exit;
    }
    
    // Get registered teams
    $teams_query = "SELECT t.id, t.team_name, t.game_type, t.status
               FROM teams t
               LEFT JOIN tournament_registrations tr ON t.id = tr.team_id
               WHERE tr.tournament_id = ?";

    $teams_stmt = $conn->prepare($teams_query);
    $teams_stmt->execute([$tournament_id]);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get members for each team
    foreach ($teams as &$team) {
        $members_query = "SELECT tm.user_id, u.name, u.avatar, tm.role, u.whatsapp_number
                         FROM team_members tm
                         JOIN users u ON tm.user_id = u.id
                         WHERE tm.team_id = ?
                         ORDER BY tm.role DESC, u.name ASC";

        $members_stmt = $conn->prepare($members_query);
        $members_stmt->execute([$team['id']]);
        $team['members'] = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        "success" => true,
        "tournament" => $tournament,
        "teams" => $teams
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>

