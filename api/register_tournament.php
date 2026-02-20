<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);
$data = json_decode(file_get_contents("php://input"));

if(!empty($data->tournament_id) && !empty($data->team_id)) {
    // Check if already registered
    $chk = $conn->prepare("SELECT id FROM tournament_registrations WHERE tournament_id=? AND team_id=?");
    $chk->execute([$data->tournament_id, $data->team_id]);
    if($chk->rowCount() > 0) { echo json_encode(["message" => "Already registered."]); exit; }

    $query = "INSERT INTO tournament_registrations (tournament_id, team_id) VALUES (?, ?)";
    $conn->prepare($query)->execute([$data->tournament_id, $data->team_id]);
    echo json_encode(["message" => "Registration Successful!"]);
}
?>
