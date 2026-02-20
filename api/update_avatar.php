<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

if(isset($_FILES['avatar']) && isset($_POST['user_id'])) {
    
    $user_id = $_POST['user_id'];
    
    // File Details and Validation
    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(500);
        echo json_encode(["message" => "File upload failed with error code: " . $file['error']]);
        exit;
    }

    $fileName = $file['name'];
    $tempPath = $file['tmp_name'];
    
    // Define the uploads path (using a timestamp and user ID for uniqueness)
    $upload_dir = __DIR__ . "/uploads/";
    $uniqueName = "avatar_" . $user_id . "_" . time() . "_" . basename($fileName);
    $uploadPath = $upload_dir . $uniqueName;
    
    // Ensure the uploads directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Get old avatar filename to potentially delete it
    $old_avatar_stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    $old_avatar_stmt->execute([$user_id]);
    $old_avatar = $old_avatar_stmt->fetchColumn();

    if(move_uploaded_file($tempPath, $uploadPath)) {
        
        try {
            // Update Database with new avatar filename
            $query = "UPDATE users SET avatar = :avatar WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':avatar', $uniqueName);
            $stmt->bindParam(':id', $user_id);
    
            if($stmt->execute()) {
                
                // CRITICAL: Delete old avatar if it's not the default
                if ($old_avatar && $old_avatar !== 'default_player.png' && file_exists($upload_dir . $old_avatar)) {
                    unlink($upload_dir . $old_avatar);
                }
                
                http_response_code(200);
                echo json_encode(["message" => "Avatar updated.", "avatar" => $uniqueName]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Database update failed."]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Database error: " . $e->getMessage()]);
        }

    } else {
        http_response_code(500);
        echo json_encode(["message" => "File move failed. Check folder permissions (uploads/)." . " Target: " . $uploadPath]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "No file or user ID provided."]);
}
?>
