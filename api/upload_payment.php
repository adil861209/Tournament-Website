<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

// Check for Team ID: The frontend sends it as 'team_id'
$team_id = $_POST['team_id'] ?? null; 

// Check for Receipt File: The frontend sends it as 'receipt'
$receipt_file = $_FILES['receipt'] ?? null;

// --- CRITICAL CHECK: Ensure both Team ID and the uploaded file are present ---
if ($team_id && $receipt_file && $receipt_file['error'] == 0) {
    
    $transaction_id = $_POST['transaction_id'] ?? null;
    $receipt_filename = null;
    
    // Define the absolute path to the uploads folder
    // Adjust this path if your uploads folder is NOT directly inside the api folder
    $upload_dir = __DIR__ . "/uploads/"; 

    // --- 1. HANDLE FILE UPLOAD ---
    $fileName = $receipt_file['name'];
    $tempPath = $receipt_file['tmp_name'];
    
    // Create unique name
    $uniqueName = "receipt_" . $team_id . "_" . time() . "_" . $fileName;
    $target_file = $upload_dir . $uniqueName;

    if (move_uploaded_file($tempPath, $target_file)) {
        $receipt_filename = $uniqueName;
    } else {
        echo json_encode(["message" => "Failed to save the uploaded receipt file. Check folder permissions."]);
        exit();
    }

    // --- 2. UPDATE TEAM DATABASE ---
    try {
        // We update status to 'pending_approval'
        // Assumes 'teams' table has 'proof_of_payment' and 'transaction_id' columns
        $query = "UPDATE teams SET proof_of_payment = :receipt_file, 
                                  transaction_id = :trx_id, 
                                  status = 'pending_approval' 
                  WHERE id = :team_id";
        
        $stmt = $conn->prepare($query);

        $stmt->bindParam(':receipt_file', $receipt_filename);
        $stmt->bindParam(':trx_id', $transaction_id);
        $stmt->bindParam(':team_id', $team_id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Payment uploaded successfully."]);
        } else {
            // If SQL fails, show the exact error from the database
            $errorInfo = $stmt->errorInfo();
            echo json_encode(["message" => "Database Update Error: Check if proof_of_payment column exists. Error: " . $errorInfo[2]]);
            exit();
        }
    } catch (PDOException $e) {
        echo json_encode(["message" => "Critical Database Exception: " . $e->getMessage()]);
        exit();
    }

} else {
    // --- THIS IS THE ERROR PATH YOU ARE SEEING ---
    $error_msg = "Incomplete data. ";
    if (!$team_id) $error_msg .= "Team ID missing. ";
    if (!$receipt_file) $error_msg .= "Receipt file array missing. ";
    else if ($receipt_file['error'] != 0) $error_msg .= "Receipt file upload error code: " . $receipt_file['error'];

    echo json_encode(["message" => $error_msg]);
}
?>
