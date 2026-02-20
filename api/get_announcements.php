<?php
include_once '../config.php';
$conn = getDBConnection();

$user_id = $_GET['user_id'] ?? null;
$now = date('Y-m-d H:i:s');

if ($user_id) {
    // Fetch announcements, LEFT JOIN user status, only show non-expired ones
    $query = "
        SELECT 
            a.*,
            COALESCE(uas.is_read, '0') AS is_read
        FROM announcements a
        LEFT JOIN user_announcement_status uas ON a.id = uas.announcement_id AND uas.user_id = :user_id
        WHERE 
            a.expires_at IS NULL OR a.expires_at > :now
        ORDER BY a.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user_id, ':now' => $now]);
} else {
    // Public fetch: only show non-expired ones
    $query = "
        SELECT 
            *, '0' as is_read 
        FROM announcements 
        WHERE 
            expires_at IS NULL OR expires_at > :now
        ORDER BY created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([':now' => $now]);
}

$news = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($news);
