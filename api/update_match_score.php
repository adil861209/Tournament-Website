<?php
include_once '../config.php';
$conn = getDBConnection();
check_admin($conn);

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->match_id) || !isset($data->tournament_id) || !isset($data->score_a) || !isset($data->score_b) || !isset($data->winner_id)) {
    echo json_encode(["success" => false, "message" => "Missing required match data."]);
    exit;
}

$match_id = $data->match_id;
$tournament_id = $data->tournament_id;
$score_a = $data->score_a;
$score_b = $data->score_b;
$winner_id = $data->winner_id;

// --- 1. Update Current Match Score, Winner, and Status ---
try {
    $conn->beginTransaction();

    $update_query = "UPDATE matches SET score_a = :sa, score_b = :sb, winner_id = :wid, status = 'completed' WHERE id = :mid";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->execute([
        ':sa' => $score_a,
        ':sb' => $score_b,
        ':wid' => $winner_id,
        ':mid' => $match_id
    ]);

    // --- 2. Find Next Match to Advance the Winner ---
    $current_match_query = "SELECT round_number, match_number FROM matches WHERE id = :mid";
    $current_match_stmt = $conn->prepare($current_match_query);
    $current_match_stmt->execute([':mid' => $match_id]);
    $current_match = $current_match_stmt->fetch(PDO::FETCH_ASSOC);

    $next_round = null;
    $next_match_number = null;
    $slot_field = null;
    $message_suffix = "Match score updated.";
    
    if ($current_match) {
        $next_round = $current_match['round_number'] + 1;
        
        // Calculate the next match number (e.g., Match 1 & 2 feed into Match 1 of the next round)
        $next_match_number = ceil($current_match['match_number'] / 2);
        
        // Find the slot to place the winner (A or B)
        $is_slot_a = ($current_match['match_number'] % 2 != 0); // Odd match number goes to slot A

        // --- 3. Advance the Winner ---
        $slot_field = $is_slot_a ? 'team_a_id' : 'team_b_id';

        $advance_query = "
            UPDATE matches 
            SET {$slot_field} = :winner_id,
                status = 'scheduled' 
            WHERE tournament_id = :tid 
            AND round_number = :next_round 
            AND match_number = :next_match_number
        ";
        $advance_stmt = $conn->prepare($advance_query);
        $advance_stmt->execute([
            ':winner_id' => $winner_id,
            ':tid' => $tournament_id,
            ':next_round' => $next_round,
            ':next_match_number' => $next_match_number
        ]);
        
        $message_suffix = "Winner advanced to round {$next_round}, match {$next_match_number}.";
    }
    
    // Check if this is the final match (no next_match_id)
    $isFinalMatch = !$current_match || !$current_match['next_match_id'];

    if ($isFinalMatch) {
        // Mark tournament as completed with completion timestamp
        $conn->prepare("UPDATE tournaments SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$tournament_id]);
        $message_suffix .= " Tournament completed! Data will be automatically deleted in 1 hour.";
    }

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Match updated. " . $message_suffix]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("Score Update DB Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("Score Update PHP Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "General error: " . $e->getMessage()]);
}
?>
