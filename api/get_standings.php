<?php
include_once '../config.php';
$conn = getDBConnection();

// 1. Get all APPROVED teams
$queryTeams = "SELECT id, team_name, game_type FROM teams WHERE status = 'approved'";
$stmt = $conn->prepare($queryTeams);
$stmt->execute();
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Initialize stats for every team
$standings = [];
foreach($teams as $team) {
    $standings[$team['id']] = [
        'id' => $team['id'],
        'name' => $team['team_name'],
        'game' => $team['game_type'],
        'played' => 0,
        'won' => 0,
        'drawn' => 0,
        'lost' => 0,
        'gf' => 0, // Goals For (Rounds won)
        'ga' => 0, // Goals Against (Rounds lost)
        'gd' => 0, // Goal Difference
        'points' => 0
    ];
}

// 3. Get all COMPLETED matches
$queryMatches = "SELECT * FROM matches WHERE status = 'completed'";
$stmt = $conn->prepare($queryMatches);
$stmt->execute();
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Calculate Stats
foreach($matches as $match) {
    $idA = $match['team_a_id'];
    $idB = $match['team_b_id'];

    // Skip if team IDs don't exist in our approved list (safety check)
    if(!isset($standings[$idA]) || !isset($standings[$idB])) continue;

    // Update Rounds/Goals
    $standings[$idA]['played']++;
    $standings[$idB]['played']++;
    
    $standings[$idA]['gf'] += $match['score_a'];
    $standings[$idA]['ga'] += $match['score_b'];
    $standings[$idA]['gd'] = $standings[$idA]['gf'] - $standings[$idA]['ga'];

    $standings[$idB]['gf'] += $match['score_b'];
    $standings[$idB]['ga'] += $match['score_a'];
    $standings[$idB]['gd'] = $standings[$idB]['gf'] - $standings[$idB]['ga'];

    // Update Points & W/D/L
    if ($match['score_a'] > $match['score_b']) {
        // Team A Wins
        $standings[$idA]['won']++;
        $standings[$idA]['points'] += 3;
        $standings[$idB]['lost']++;
    } elseif ($match['score_a'] < $match['score_b']) {
        // Team B Wins
        $standings[$idB]['won']++;
        $standings[$idB]['points'] += 3;
        $standings[$idA]['lost']++;
    } else {
        // Draw
        $standings[$idA]['drawn']++;
        $standings[$idA]['points'] += 1;
        $standings[$idB]['drawn']++;
        $standings[$idB]['points'] += 1;
    }
}

// 5. Sort the Leaderboard
// Priority: Points (High to Low) -> GD (High to Low) -> GF (High to Low)
usort($standings, function($a, $b) {
    if ($a['points'] === $b['points']) {
        if ($a['gd'] === $b['gd']) {
            return $b['gf'] <=> $a['gf'];
        }
        return $b['gd'] <=> $a['gd'];
    }
    return $b['points'] <=> $a['points'];
});

echo json_encode(array_values($standings));
