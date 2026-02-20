<?php
include_once '../config.php';
$conn = getDBConnection();

$query = "
    SELECT t.*, 
           (SELECT COUNT(team_id) FROM tournament_registrations WHERE tournament_id = t.id) AS registered_teams 
    FROM tournaments t
    ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

