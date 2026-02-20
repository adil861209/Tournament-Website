<?php
include_once '../config.php';
$conn = getDBConnection();

// Select all tournaments marked for the featured slider
$query = "
    SELECT 
        id, name, game_type, description, start_time, reg_end_time, prize_pool, status, banner_image
    FROM tournaments 
    WHERE featured_slider = 1
    ORDER BY start_time ASC
";

$stmt = $conn->prepare($query);
$stmt->execute();

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
