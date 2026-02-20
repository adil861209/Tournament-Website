<?php
include_once '../config.php';
$conn = getDBConnection();
check_admin($conn);

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->user_id)) {
    try {
        // Delete the user. 
        // Best practice is to use transactions for related deletes (e.g., from team_members, reports, etc.)
        $conn->beginTransaction();
        
        // 1. Remove from team_members (optional if FKs are set to CASCADE)
        $conn->prepare("DELETE FROM team_members WHERE user_id = :id")->execute([':id' => $data->user_id]);

        // 2. Delete the user itself. 
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $data->user_id);

        if($stmt->execute()) {
            $conn->commit();
            echo json_encode(["message" => "User deleted."]);
        } else {
            $conn->rollBack();
            echo json_encode(["message" => "Failed to delete user (user not found)."]);
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(["message" => "Failed to delete user due to database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["message" => "No ID provided."]);
}
?>
