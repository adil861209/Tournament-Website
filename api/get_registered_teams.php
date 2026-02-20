<?php
include_once '../config.php';
$conn = getDBConnection();
check_admin($conn);

$tournament_id = $_GET['tournament_id'] ?? null;

if(empty($tournament_id)) {
    echo json_encode(["message" => "Missing tournament ID"]);
    exit;
}

// Select teams registered for this tournament, along with their payment receipt details
// We check for teams that have a corresponding entry in tournament_registrations (tr)
$query = "
    SELECT 
        tr.id as registration_id, 
        t.id as team_id, 
        t.team_name, 
        t.status, 
        t.proof_of_payment, /* Now stores the receipt filename */
        t.transaction_id,   /* Now stores the transaction ID */
        t.game_type,
        u.name as captain_name, 
        u.email as captain_email,
        u.whatsapp_number as captain_whatsapp, /* Include WhatsApp number */
        
        /* Find all team members tied to the team ID */
        (
            SELECT GROUP_CONCAT(m.name SEPARATOR ', ') 
            FROM users m 
            WHERE m.team_id = t.id
        ) as members_list
        
    FROM teams t
    JOIN tournament_registrations tr ON t.id = tr.team_id
    JOIN users u ON t.user_id = u.id /* u is the Captain */
    WHERE tr.tournament_id = :tid
    ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':tid', $tournament_id);
$stmt->execute();

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
