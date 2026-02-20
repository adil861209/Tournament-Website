<?php
// CRITICAL: Ensure robust error reporting for development
// In production, you might want to turn these off or log to a file
ini_set('display_errors', 0); // Turn off in production
ini_set('display_startup_errors', 0); // Turn off in production
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT); // Hide deprecated warnings

// Set CORS headers for the response
// Note: In production, change "*" to your actual domain
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$db_name = "tournament_db";
$username = "root";
$password = ""; // XAMPP default password is empty

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    // Set error mode to exception to catch problems easily
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Use emulated prepares to avoid some driver issues if necessary, 
    // but default is usually fine for MySQL
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $exception) {
    // If connection fails, show error
    http_response_code(500);
    echo json_encode(["error" => "Connection error: " . $exception->getMessage()]);
    exit();
}

/**
 * Helper function to send JSON response and exit
 */
function send_response($code, $message, $extra = []) {
    http_response_code($code);
    $response = array_merge(["message" => $message], $extra);
    echo json_encode($response);
    exit();
}

/**
 * Basic authentication check
 * This is a simplified version. For better security, use JWT or sessions.
 */
function check_auth($conn) {
    // For now, we'll just check if a user_id is provided.
    // In a real app, you'd check a token.
    $user_id = $_SERVER['HTTP_X_USER_ID'] ?? null;
    if (!$user_id) {
        send_response(401, "Authentication required.");
    }

    // Validate user_id is numeric
    if (!is_numeric($user_id)) {
        send_response(401, "Invalid user ID format.");
    }

    $user_id = (int) $user_id;

    $stmt = $conn->prepare("SELECT id, name, role, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send_response(401, "Invalid user.");
    }

    // Check if user is banned
    if ($user['status'] === 'banned') {
        send_response(403, "Account is banned.");
    }

    return $user;
}

function check_admin($conn) {
    $user = check_auth($conn);
    if ($user['role'] !== 'admin') {
        send_response(403, "Admin access required.");
    }
    return $user;
}
