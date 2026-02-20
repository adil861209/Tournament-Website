<?php
include_once '../config.php';
$conn = getDBConnection();

// Debug endpoint to check database connection
if (isset($_GET['debug'])) {
    try {
        $conn->query("SELECT 1");
        echo json_encode([
            "status" => "ok",
            "message" => "Database connection successful",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Database connection failed: " . $e->getMessage()
        ]);
    }
    exit;
}

check_auth($conn);

$user_id = $_GET['id'] ?? null;

if ($user_id) {
    try {
        // Select user details and their current team details if available
        $query = "
            SELECT
                u.id, u.name, u.email, u.role, u.avatar, u.whatsapp_number, u.team_id,
                t.team_name, t.status AS team_status
            FROM users u
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE u.id = ?
        ";

        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_details) {
            echo json_encode($user_details);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    } catch (PDOException $e) {
        error_log("get_user_details error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "User ID missing."]);
}
