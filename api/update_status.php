<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->team_id) && !empty($data->status)) {
    $team_id = $data->team_id;
    $new_status = $data->status;
    $rejection_reason = $data->reason ?? "The admin did not provide a specific reason, but the team status has been updated.";

    // 1. Update the team status
    $query = "UPDATE teams SET status = :status WHERE id = :team_id";
    $stmt = $conn->prepare($query);
    
    if($stmt->execute([':status' => $new_status, ':team_id' => $team_id])) {
        
        // 2. Fetch necessary details for notification
        $details_query = "
            SELECT 
                u.id as captain_id, 
                t.team_name,
                tour.name as tournament_name
            FROM teams t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN tournament_registrations tr ON tr.team_id = t.id
            LEFT JOIN tournaments tour ON tr.tournament_id = tour.id
            WHERE t.id = :team_id
        ";
        $details_stmt = $conn->prepare($details_query);
        $details_stmt->execute([':team_id' => $team_id]);
        $details = $details_stmt->fetch(PDO::FETCH_ASSOC);

        if ($details && $details['captain_id']) {
            $captain_id = $details['captain_id'];
            $team_name = $details['team_name'];
            $tourney_name = $details['tournament_name'] ?? "an Unspecified Tournament";
            $message = "";
            $type = "";

            if ($new_status === 'approved') {
                $message = "Congratulations! Your team '{$team_name}' has been officially **APPROVED** to play in the tournament: **{$tourney_name}**.";
                $type = 'success';
            } elseif ($new_status === 'rejected') {
                $message = "Your team '{$team_name}' was **REJECTED** for the tournament '{$tourney_name}'.<br>Reason: {$rejection_reason}";
                $type = 'error';
            } else {
                 $message = "The status for your team '{$team_name}' has been updated to '{$new_status}'.";
                 $type = 'info';
            }

            // 3. Insert notification into the notifications table
            $notify_query = "INSERT INTO notifications (user_id, message, type) VALUES (:user_id, :message, :type)";
            $notify_stmt = $conn->prepare($notify_query);
            $notify_stmt->execute([':user_id' => $captain_id, ':message' => $message, ':type' => $type]);
        }

        echo json_encode(["message" => "Team status updated to '{$new_status}' and captain notified."]);

    } else {
        echo json_encode(["message" => "Failed to update team status in the database."]);
    }

} else {
    echo json_encode(["message" => "Missing team ID or status in request."]);
}
