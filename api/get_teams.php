<?php
include_once '../config.php';
$conn = getDBConnection();

try {
    // CRITICAL FIX: Only select teams that have at least one member.
    // We join the teams table with the team_members table and group by team ID.
    $query = "
        SELECT 
            t.id, 
            t.team_name, 
            t.game_type, 
            COUNT(tm.user_id) AS member_count
        FROM 
            teams t
        JOIN 
            team_members tm ON t.id = tm.team_id
        GROUP BY 
            t.id, t.team_name, t.game_type
        HAVING 
            member_count > 0  -- Ensure only teams with members are listed
        ORDER BY 
            t.team_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    // Send the list of non-empty teams back
    echo json_encode($teams);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server Error: " . $e->getMessage()]);
}
