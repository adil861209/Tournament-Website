<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

// Check for team_id parameter
if (empty($_GET['team_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "Missing team ID."]);
    exit;
}

$team_id = $_GET['team_id'];
$response = [];

try {
    // --- 1. Fetch Team Members ---
    // Includes user details (name, avatar) and their role in the team
    $query_members = "
        SELECT 
            u.id, 
            u.name, 
            u.avatar, 
            tm.role
        FROM 
            team_members tm
        JOIN 
            users u ON tm.user_id = u.id
        WHERE 
            tm.team_id = :team_id
        ORDER BY 
            tm.role DESC, u.name ASC
    ";
    $stmt_members = $conn->prepare($query_members);
    $stmt_members->bindParam(':team_id', $team_id);
    $stmt_members->execute();
    $response['members'] = $stmt_members->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. Fetch Pending Join Requests ---
    // Joins team_requests with users to get the name of the requester
    $query_requests = "
        SELECT 
            tr.id AS request_id, 
            tr.user_id,
            u.name
        FROM 
            team_requests tr
        JOIN 
            users u ON tr.user_id = u.id
        WHERE 
            tr.team_id = :team_id AND tr.status = 'pending'
        ORDER BY 
            tr.requested_at ASC
    ";
    $stmt_requests = $conn->prepare($query_requests);
    $stmt_requests->bindParam(':team_id', $team_id);
    $stmt_requests->execute();
    $response['requests'] = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);
    
    // --- 3. Fetch Team Details (Optional, but useful) ---
    $query_team = "SELECT id, team_name, game_type, status FROM teams WHERE id = :team_id LIMIT 1";
    $stmt_team = $conn->prepare($query_team);
    $stmt_team->bindParam(':team_id', $team_id);
    $stmt_team->execute();
    $response['team'] = $stmt_team->fetch(PDO::FETCH_ASSOC);


    http_response_code(200);
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database Error: " . $e->getMessage(), "requests" => [], "members" => []]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server Error: " . $e->getMessage(), "requests" => [], "members" => []]);
}
?>
