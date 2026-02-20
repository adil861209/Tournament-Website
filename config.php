<?php
/**
 * Tournament Website Configuration
 * Infinity Free Production Configuration
 */

// Infinity Free Database Configuration
define('DB_HOST', 'sql213.infinityfree.com');
define('DB_NAME', 'if0_40423376_tournament_db');
define('DB_USER', 'if0_40423376');
define('DB_PASS', 'iNI6K7qf7xfeO');

// Application Settings
define('APP_NAME', 'Tournament Website');
define('APP_URL', 'https://tournamentwebsite.kesug.com'); // Update with your actual domain

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/api/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Security Settings
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 days
define('PASSWORD_MIN_LENGTH', 8);

// Tournament Settings
define('MAX_TEAMS_PER_TOURNAMENT', 32);
define('DEFAULT_MIN_PLAYERS', 5);
define('TOURNAMENT_CLEANUP_HOURS', 1);

// Error Reporting (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Timezone
date_default_timezone_set('Asia/Karachi');

// Database Connection Function
function getDBConnection() {
    static $conn = null;

    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode([
                "success" => false,
                "message" => "Database connection failed. Please try again later."
            ]));
        }
    }

    return $conn;
}

// Utility Functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

function formatDateTime($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}

function getCurrentTimestamp() {
    return date('Y-m-d H:i:s');
}

// Helper function to send JSON response and exit
function send_response($code, $message, $extra = []) {
    http_response_code($code);
    $response = array_merge(["message" => $message], $extra);
    echo json_encode($response);
    exit();
}

// Authentication functions
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
?>
