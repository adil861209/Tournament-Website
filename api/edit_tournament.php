<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

if(!empty($_POST['id'])) {
    
    $id = $_POST['id'];
    
    // Determine if featured_slider is set (it comes as 'true' or 'false' string from frontend)
    $featured_slider = (isset($_POST['featured_slider']) && $_POST['featured_slider'] === 'true') ? 1 : 0; 
    
    // --- 1. Update Core Details (Using Prepared Statement) ---
    $sql = "UPDATE tournaments SET
            name = :name,
            game_type = :game_type,
            description = :description,
            start_time = :start_time,
            reg_end_time = :reg_end_time,
            rules = :rules,
            entry_fee = :entry_fee,
            max_teams = :max_teams,
            prize_pool = :prize_pool,
            min_players = :min_players,
            featured_slider = :featured_slider
            WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $params = [
        ':name' => $_POST['name'],
        ':game_type' => $_POST['game_type'],
        ':description' => $_POST['description'],
        ':start_time' => $_POST['start_time'],
        ':reg_end_time' => $_POST['reg_end_time'],
        ':rules' => $_POST['rules'],
        ':entry_fee' => $_POST['entry_fee'],
        ':max_teams' => $_POST['max_teams'],
        ':prize_pool' => $_POST['prize_pool'],
        ':min_players' => $_POST['min_players'],
        ':featured_slider' => $featured_slider,
        ':id' => $id
    ];
    
    try {
        $stmt->execute($params);

        // --- 2. Update Image ONLY if a new one is uploaded ---
        if(isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] == 0) {
            $bannerName = "tourney_" . time() . "_" . $_FILES['banner_image']['name'];
            $upload_dir = __DIR__ . "/uploads/" . $bannerName;
            
            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $upload_dir)) {
                $updImg = $conn->prepare("UPDATE tournaments SET banner_image=? WHERE id=?");
                $updImg->execute([$bannerName, $id]);
            } else {
                 error_log("Failed to move uploaded file in edit_tournament.php");
            }
        }

        echo json_encode(["message" => "Tournament Updated"]);
    } catch (PDOException $e) {
        error_log("DB Error in edit_tournament.php: " . $e->getMessage());
        echo json_encode(["message" => "Database Update Failed: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["message" => "Missing tournament ID for update."]);
}
?>

