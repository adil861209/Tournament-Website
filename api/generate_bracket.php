<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

$data = json_decode(file_get_contents("php://input"));

if(empty($data->tournament_id)) send_response(400, "Missing ID");

$tid = $data->tournament_id;

// 1. Get Registered Teams
$q = "SELECT t.id, t.team_name FROM tournament_registrations tr 
      JOIN teams t ON tr.team_id = t.id 
      WHERE tr.tournament_id = ?";
$stmt = $conn->prepare($q);
$stmt->execute([$tid]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = count($teams);
if($count < 2) die(json_encode(["message" => "Not enough teams to generate bracket (need 2+)."]));

// Calculate bracket size (next power of 2)
$bracketSize = pow(2, ceil(log($count, 2)));
$rounds = ceil(log($bracketSize, 2));

// 2. Create Single-Elimination Bracket
$matchesCreated = 0;

try {
    $conn->beginTransaction();

    // Shuffle teams randomly
    shuffle($teams);

    // Create matches for each round
    $matchCounter = 1;
    $matchesByRound = [];

    // Round 1: Initial matches
    $round1Matches = [];
    $nextRoundTeams = [];

    // Handle byes for non-power-of-2
    $actualTeams = $count;
    $byeTeams = $bracketSize - $actualTeams;

    // Create Round 1 matches
    for($i = 0; $i < $bracketSize / 2; $i++) {
        $teamA = $teams[$i * 2] ?? null;
        $teamB = $teams[$i * 2 + 1] ?? null;

        $teamA_id = $teamA ? $teamA['id'] : null;
        $teamB_id = $teamB ? $teamB['id'] : null;

        // Handle byes
        if (!$teamA_id && !$teamB_id) continue;
        if (!$teamA_id || !$teamB_id) {
            // Bye - advance the existing team
            if ($teamA_id) $nextRoundTeams[] = $teamA_id;
            if ($teamB_id) $nextRoundTeams[] = $teamB_id;
            continue;
        }

        $insert = "INSERT INTO matches (tournament_id, team_a_id, team_b_id, match_time, game_type, status, round_number, match_number, next_match_id)
                   VALUES (:tid, :a, :b, NOW() + INTERVAL 1 DAY, 'Valorant', 'scheduled', 1, :matchNum, NULL)";

        $stmtIns = $conn->prepare($insert);
        $stmtIns->execute([
            ':tid' => $tid,
            ':a' => $teamA_id,
            ':b' => $teamB_id,
            ':matchNum' => $matchCounter
        ]);

        $matchId = $conn->lastInsertId();
        $round1Matches[] = $matchId;
        $matchesCreated++;
        $matchCounter++;
    }

    // Create subsequent rounds
    $currentRoundMatches = $round1Matches;
    for($round = 2; $round <= $rounds; $round++) {
        $nextRoundMatches = [];
        $matchesInRound = count($currentRoundMatches) / 2;

        for($i = 0; $i < $matchesInRound; $i++) {
            $matchA = $currentRoundMatches[$i * 2] ?? null;
            $matchB = $currentRoundMatches[$i * 2 + 1] ?? null;

            // Update next_match_id for previous round matches
            if ($matchA) {
                $conn->prepare("UPDATE matches SET next_match_id = NULL WHERE id = ?")->execute([$matchA]);
            }
            if ($matchB) {
                $conn->prepare("UPDATE matches SET next_match_id = NULL WHERE id = ?")->execute([$matchB]);
            }

            $insert = "INSERT INTO matches (tournament_id, team_a_id, team_b_id, match_time, game_type, status, round_number, match_number, next_match_id)
                       VALUES (:tid, NULL, NULL, NULL, 'Valorant', 'scheduled', :roundNum, :matchNum, NULL)";

            $stmtIns = $conn->prepare($insert);
            $stmtIns->execute([
                ':tid' => $tid,
                ':roundNum' => $round,
                ':matchNum' => $i + 1
            ]);

            $newMatchId = $conn->lastInsertId();
            $nextRoundMatches[] = $newMatchId;

            // Set next_match_id for the two matches that feed into this one
            if ($matchA) {
                $conn->prepare("UPDATE matches SET next_match_id = ? WHERE id = ?")->execute([$newMatchId, $matchA]);
            }
            if ($matchB) {
                $conn->prepare("UPDATE matches SET next_match_id = ? WHERE id = ?")->execute([$newMatchId, $matchB]);
            }
        }

        $currentRoundMatches = $nextRoundMatches;
    }

    // 4. Update Tournament Status
    $conn->prepare("UPDATE tournaments SET status = 'generated' WHERE id = ?")->execute([$tid]);
    
    $conn->commit();
    echo json_encode(["message" => "Bracket Generated! Created $matchesCreated first-round matches."]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("Bracket Generation Error: " . $e->getMessage());
    die(json_encode(["message" => "Database Error during bracket generation: " . $e->getMessage()]));
}
