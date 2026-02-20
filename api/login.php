<?php
include_once '../config.php';

// Debug endpoint for login
if (isset($_GET['debug'])) {
    try {
        $conn = getDBConnection();
        $conn->query("SELECT 1");
        echo json_encode([
            "status" => "ok",
            "message" => "Database connection successful",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Database connection failed: " . $e->getMessage(),
            "config" => [
                "host" => DB_HOST,
                "database" => DB_NAME,
                "user" => DB_USER
            ]
        ]);
    }
    exit;
}

$conn = getDBConnection();

// Reading the raw body for JSON data
$rawBody = file_get_contents("php://input");
$data = json_decode($rawBody);

if(empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["message" => "Missing email or password."]);
    exit;
}

$email = $data->email;
$password = $data->password;

// Log the login attempt
error_log("Login attempt for email: $email");

try {
    // === CRITICAL FIX: Define the correct password column name ===
    $password_column = 'password'; 
    // =============================================================
    
    // Select required fields (id, name, email, role, status, and the password column)
    $query = "SELECT id, name, email, role, status, avatar, team_id, {$password_column} FROM users WHERE email = :email LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 1. Verify Password
        if (password_verify($password, $user[$password_column])) {
            
            // 2. Check for Ban Status
            if ($user['status'] === 'banned') {
                send_response(403, "Your account is banned. Access denied.", ["status" => "banned"]);
            }

            // 3. Login Success: Prepare user data for the frontend
            unset($user[$password_column]);
            
            send_response(200, "Login successful.", ["user" => $user]);
        } else {
            // Password mismatch
            send_response(401, "Invalid email or password.");
        }
    } else {
        // User not found
        send_response(401, "Invalid email or password.");
    }

} catch (PDOException $e) {
    // Detailed error message for debugging database issues
    error_log("Login DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Database error occurred during login."]);
} catch (Exception $e) {
    // Catch all other PHP execution errors
    error_log("Login PHP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error occurred during login."]);
}
// Closing ?> tag intentionally omitted.
