<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once '../config.php';
$conn = getDBConnection();

header("Content-Type: application/json; charset=UTF-8");

// Handle debug endpoint
if (isset($_GET['debug'])) {
    try {
        $conn->query("SELECT 1");
        $tables = ['users', 'teams', 'team_members', 'tournaments', 'tournament_registrations'];
        $missing_tables = [];

        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->rowCount() == 0) {
                $missing_tables[] = $table;
            }
        }

        echo json_encode([
            "status" => "ok",
            "message" => "Database connection successful",
            "missing_tables" => $missing_tables
        ]);
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
    exit;
}

// Get and validate input data
$data = json_decode(file_get_contents("php://input"));
if ($data === null) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON data"]);
    exit;
}

$tournament_id = $data->tournament_id ?? null;
$team_id = $data->team_id ?? null;

if (!$tournament_id || !is_numeric($tournament_id) || !$team_id || !is_numeric($team_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Valid tournament ID and team ID required"]);
    exit;
}

$tournament_id = (int) $tournament_id;
$team_id = (int) $team_id;

// Authenticate user
$user = check_auth($conn);

// Log input data for debugging
error_log("Registration attempt - User ID: {$user['id']}, Tournament ID: $tournament_id, Team ID: $team_id");

try {
    $step = "table_check";
    // Verify database tables exist
    $required_tables = ['tournaments', 'teams', 'team_members', 'tournament_registrations', 'users'];
    $missing_tables = [];
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    if (!empty($missing_tables)) {
        http_response_code(500);
        echo json_encode(["error" => "Missing database tables: " . implode(', ', $missing_tables) . ". Please run database_schema.sql"]);
        exit;
    }

    $step = "get_tournament";
    // Get tournament details
    $tournament_stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    $tournament_stmt->execute([$tournament_id]);
    $tournament = $tournament_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournament) {
        http_response_code(404);
        echo json_encode(["error" => "Tournament not found"]);
        exit;
    }

    $step = "check_status";
    // Check tournament status
    if ($tournament['status'] !== 'open' && $tournament['status'] !== 'registration') {
        http_response_code(400);
        echo json_encode(["error" => "Tournament is not open for registration"]);
        exit;
    }

    $step = "get_team";
    // Get team details with member count
    $team_stmt = $conn->prepare("
        SELECT t.*,
               (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
        FROM teams t
        WHERE t.id = ?
    ");
    $team_stmt->execute([$team_id]);
    $team = $team_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        http_response_code(404);
        echo json_encode(["error" => "Team not found"]);
        exit;
    }

    $step = "check_captain";
    // Check if the authenticated user is the captain of the team
    if ($team['user_id'] != $user['id']) {
        http_response_code(403);
        echo json_encode(["error" => "You are not authorized to register this team"]);
        exit;
    }

    $step = "check_registered";
    // Check if team is already registered
    $check_stmt = $conn->prepare("SELECT id FROM tournament_registrations WHERE tournament_id = ? AND team_id = ?");
    $check_stmt->execute([$tournament_id, $team_id]);

    if ($check_stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Team is already registered for this tournament"]);
        exit;
    }

    $step = "validate_members";
    // Validate team requirements
    $required_members = $tournament['min_players'] ?? 1; // Default to 1 member (captain only)
    $errors = [];

    if ($team['member_count'] != $required_members) {
        $errors[] = "Team must have exactly {$required_members} members to register (current: {$team['member_count']})";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Team validation failed",
            "details" => $errors
        ]);
        exit;
    }

    $step = "insert_registration";
    // Register team for tournament
    $insert_stmt = $conn->prepare("INSERT INTO tournament_registrations (tournament_id, team_id, registration_time) VALUES (?, ?, NOW())");

    if ($insert_stmt->execute([$tournament_id, $team_id])) {
        echo json_encode([
            "success" => true,
            "message" => "Team successfully registered for tournament"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to register team for tournament"]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Tournament Registration DB Error: " . $e->getMessage());
    echo json_encode(["error" => "Database error occurred during registration: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Tournament Registration General Error: " . $e->getMessage());
    echo json_encode(["error" => "Server error occurred during registration: " . $e->getMessage()]);
}
?>

