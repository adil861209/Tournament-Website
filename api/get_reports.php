<?php
include_once '../config.php';
$conn = getDBConnection();

// Check if user is admin
check_admin($conn);

try {
    // Select all report columns, including is_read and proof_link
    $query = "SELECT 
                id, 
                reporter_name, 
                reporter_email, 
                category, 
                description, 
                status, 
                created_at,
                is_read,
                proof_link  /* NEW COLUMN SELECTED */
              FROM reports 
              /* Order by is_read (0=unread first), then by created_at (newest first) */
              ORDER BY is_read ASC, created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error while fetching reports: " . $e->getMessage(), "reports" => []]);
}
