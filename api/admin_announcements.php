<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

$method = $_SERVER['REQUEST_METHOD'];
$response = ["success" => false, "message" => "Invalid operation."];

try {
    switch ($method) {
        case 'GET':
            $query = "SELECT id, title, message, created_at, type, expires_at FROM announcements ORDER BY created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'POST':
            $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
            // Handle both application/json and text/plain (CORS workaround)
            $rawBody = file_get_contents("php://input");
            $data = json_decode($rawBody);

            if ($data === null) {
                $response["message"] = "Invalid JSON payload.";
                break;
            }
            
            $action = $data->action ?? 'create';

            if ($action === 'create') {
                if (!empty($data->title) && !empty($data->message) && !empty($data->type)) {
                    $expires_at = !empty($data->expires_at) ? date('Y-m-d H:i:s', strtotime($data->expires_at)) : NULL;

                    $query = "INSERT INTO announcements (title, message, type, expires_at) VALUES (:title, :message, :type, :expires_at)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([':title' => $data->title, ':message' => $data->message, ':type' => $data->type, ':expires_at' => $expires_at]);
                    $response = ["success" => true, "message" => "Announcement created successfully."];
                } else {
                    $response["message"] = "Missing required fields for creation.";
                }
            } 
            
            else if ($action === 'update') {
                if (!empty($data->id) && !empty($data->title) && !empty($data->message) && !empty($data->type)) {
                    $expires_at = !empty($data->expires_at) ? date('Y-m-d H:i:s', strtotime($data->expires_at)) : NULL;

                    $query = "UPDATE announcements SET title = :title, message = :message, type = :type, expires_at = :expires_at WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    
                    $stmt->bindParam(':id', $data->id);
                    $stmt->bindParam(':title', $data->title);
                    $stmt->bindParam(':message', $data->message);
                    $stmt->bindParam(':type', $data->type);
                    $stmt->bindParam(':expires_at', $expires_at);

                    if ($stmt->execute()) {
                        $response = ["success" => true, "message" => "Announcement updated successfully."];
                    } else {
                        $response = ["success" => false, "message" => "Database execution failed."];
                    }
                } else {
                    $response["message"] = "Missing required fields for update (ID, title, message, or type).";
                }
            } 
            
            else if ($action === 'delete') {
                $announcement_id = $data->id ?? null;

                if ($announcement_id) {
                    $conn->beginTransaction(); 

                    try {
                        // 1. Delete user statuses 
                        $query_status = "DELETE FROM user_announcement_status WHERE announcement_id = :id";
                        $stmt_status = $conn->prepare($query_status);
                        $stmt_status->execute([':id' => $announcement_id]);

                        // 2. Delete the announcement itself
                        $query = "DELETE FROM announcements WHERE id = :id";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([':id' => $announcement_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            $conn->commit(); 
                            $response = ["success" => true, "message" => "Announcement deleted successfully."];
                        } else {
                            $conn->rollBack();
                            $response = ["success" => false, "message" => "Announcement not found or already deleted (ID: {$announcement_id})."];
                        }
                    } catch (PDOException $e) {
                        $conn->rollBack();
                        $response = ["success" => false, "message" => "Deletion failed (Database): " . $e->getMessage()];
                    }

                } else {
                    $response["message"] = "Missing announcement ID for deletion.";
                }
            }
            
            else {
                $response["message"] = "Unrecognized POST action.";
            }

            break;
            
        default:
            $response["message"] = "Method not supported.";
            break;
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response = ["success" => false, "message" => "Critical Server Error (PDO): " . $e->getMessage()];
} catch (Exception $e) {
     $response = ["success" => false, "message" => "Critical PHP Execution Error: " . $e->getMessage()];
}

echo json_encode($response);
?>
