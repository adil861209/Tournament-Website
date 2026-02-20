<?php
include_once '../config.php';
$conn = getDBConnection();
check_admin($conn);

// Check for required fields
if (empty($_POST['name']) || empty($_POST['game_type']) || empty($_POST['start_time']) || empty($_POST['reg_end_time'])) {
    echo json_encode(["message" => "Missing required tournament fields."]);
    exit;
}

$name = $_POST['name'];
$game_type = $_POST['game_type'];
$description = $_POST['description'] ?? '';
$start_time = $_POST['start_time'];
$reg_end_time = $_POST['reg_end_time'];
$rules = $_POST['rules'] ?? '';
$entry_fee = $_POST['entry_fee'] ?? 0;
$max_teams = $_POST['max_teams'] ?? 16;
$prize_pool = $_POST['prize_pool'] ?? 0;
$min_players = $_POST['min_players'] ?? 5;
// NEW: Check for the featured slider checkbox (it sends 'true'/'false' string)
$featured_slider = (isset($_POST['featured_slider']) && $_POST['featured_slider'] === 'true') ? 1 : 0; 

$banner_filename = null;

// --- Handle File Upload ---
if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] == 0) {
    $fileName = $_FILES['banner_image']['name'];
    $tempPath = $_FILES['banner_image']['tmp_name'];
    
    // Define the absolute path to the uploads folder
    $upload_dir = __DIR__ . "/uploads/"; 
    $uniqueName = "banner_" . time() . "_" . $fileName;
    $target_file = $upload_dir . $uniqueName;

    if (move_uploaded_file($tempPath, $target_file)) {
        $banner_filename = $uniqueName;
    } else {
        // Continue but log error
        error_log("Failed to move uploaded banner file.");
    }
}

// --- Insert into Database ---
$query = "INSERT INTO tournaments (
    name, game_type, description, start_time, reg_end_time, rules, 
    entry_fee, max_teams, prize_pool, min_players, banner_image, featured_slider, status
) VALUES (
    :name, :game_type, :description, :start_time, :reg_end_time, :rules, 
    :entry_fee, :max_teams, :prize_pool, :min_players, :banner_image, :featured_slider, 'open'
)";

$stmt = $conn->prepare($query);

try {
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':game_type', $game_type);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':start_time', $start_time);
    $stmt->bindParam(':reg_end_time', $reg_end_time);
    $stmt->bindParam(':rules', $rules);
    $stmt->bindParam(':entry_fee', $entry_fee);
    $stmt->bindParam(':max_teams', $max_teams);
    $stmt->bindParam(':prize_pool', $prize_pool);
    $stmt->bindParam(':min_players', $min_players);
    $stmt->bindParam(':banner_image', $banner_filename);
    // NEW BINDING
    $stmt->bindParam(':featured_slider', $featured_slider); 

    if ($stmt->execute()) {
        echo json_encode(["message" => "Tournament created successfully.", "id" => $conn->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Database error during tournament creation."]);
    }
} catch (PDOException $e) {
    echo json_encode(["message" => "Database Error: " . $e->getMessage()]);
}
?>
