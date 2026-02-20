<?php
include_once '../config.php';
$conn = getDBConnection();

$tournament_id = $_GET['tournament_id'] ?? null;

if (!$tournament_id) {
    // Return empty array for no ID
    echo json_encode([]);
    exit;
}

// Fetch all matches for the tournament, including team names
$query = "
    SELECT 
        m.id, 
        m.tournament_id, 
        m.round_number, 
        m.match_number,
        m.team_a_id, 
        m.team_b_id,
        m.score_a, 
        m.score_b,
        m.winner_id,
        COALESCE(ta.team_name, 'TBD') as team_a_name,
        COALESCE(tb.team_name, 'TBD') as team_b_name
    FROM matches m
    LEFT JOIN teams ta ON m.team_a_id = ta.id
    LEFT JOIN teams tb ON m.team_b_id = tb.id
    WHERE m.tournament_id = :tid
    ORDER BY m.round_number ASC, m.match_number ASC
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':tid', $tournament_id);
$stmt->execute();

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
