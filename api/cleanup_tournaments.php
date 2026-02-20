<?php
/**
 * Tournament Cleanup Script
 * Deletes completed tournaments and all related data after 1 hour
 * Designed to be called by cron job every 15-30 minutes
 */

// Include database connection
include_once '../config.php';
$conn = getDBConnection();

// Allow web access for automatic cleanup (with admin check)
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    // Web access - require admin authentication
    check_admin($conn);
    // Set content type for web response
    header("Content-Type: application/json; charset=UTF-8");
}

try {
    // Find completed tournaments older than 1 hour
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

    $query = "
        SELECT id, name
        FROM tournaments
        WHERE status = 'completed'
        AND updated_at < ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([$oneHourAgo]);
    $tournamentsToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tournamentsToDelete)) {
        echo "No tournaments to clean up.\n";
        exit(0);
    }

    echo "Found " . count($tournamentsToDelete) . " completed tournaments older than 1 hour:\n";

    foreach ($tournamentsToDelete as $tournament) {
        echo "- Deleting tournament: {$tournament['name']} (ID: {$tournament['id']})\n";

        $conn->beginTransaction();

        try {
            $tournamentId = $tournament['id'];

            // Delete in correct order to respect foreign keys

            // 1. Delete tournament registrations (references tournament_registrations.team_id and tournament_registrations.tournament_id)
            $conn->prepare("DELETE FROM tournament_registrations WHERE tournament_id = ?")->execute([$tournamentId]);

            // 2. Delete matches (references matches.tournament_id, matches.team_a_id, matches.team_b_id, matches.winner_id)
            $conn->prepare("DELETE FROM matches WHERE tournament_id = ?")->execute([$tournamentId]);

            // 3. Delete notifications related to this tournament (if any)
            // This is optional, but good to clean up
            $conn->prepare("DELETE FROM notifications WHERE message LIKE CONCAT('%', ?, '%')")->execute([$tournament['name']]);

            // 4. Delete the tournament itself
            $conn->prepare("DELETE FROM tournaments WHERE id = ?")->execute([$tournamentId]);

            // Note: We don't delete teams or users, only tournament-specific data
            // Teams can participate in future tournaments

            $conn->commit();
            echo "  ✓ Successfully deleted tournament: {$tournament['name']}\n";

        } catch (PDOException $e) {
            $conn->rollBack();
            echo "  ✗ Error deleting tournament {$tournament['id']}: " . $e->getMessage() . "\n";
            error_log("Tournament cleanup error for ID {$tournament['id']}: " . $e->getMessage());
        }
    }

    echo "\nCleanup completed.\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    error_log("Tournament cleanup script error: " . $e->getMessage());
    exit(1);
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    error_log("Tournament cleanup script error: " . $e->getMessage());
    exit(1);
}
?>

