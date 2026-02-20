<?php
include_once '../config.php';
$conn = getDBConnection();

// Get active payment information
$query = "SELECT account_type, account_holder_name, account_number FROM payment_info WHERE is_active = 1 ORDER BY id DESC";
$stmt = $conn->prepare($query);
$stmt->execute();

$payment_info = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($payment_info);
?>
