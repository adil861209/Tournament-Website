<?php
include_once '../config.php';
$conn = getDBConnection();
check_auth($conn);

$data = json_decode(file_get_contents("php://input"));

// Safety: Use null coalescing for properties that might not exist
$reporter_name = $data->reporter_name ?? null;
$reporter_email = $data->reporter_email ?? null;
$category = $data->category ?? null;
$description = $data->description ?? null;
$proof_link = $data->proof_link ?? NULL; 
$status = 'pending'; 

if(!empty($reporter_name) && !empty($reporter_email) && !empty($category) && !empty($description)) {

    // Input sanitization is still important before using as a display/string, 
    // even with prepared statements. But PDO is the key here.
    $reporter_name = htmlspecialchars(strip_tags($reporter_name));
    $reporter_email = htmlspecialchars(strip_tags($reporter_email));
    $category = htmlspecialchars(strip_tags($category));
    $description = htmlspecialchars(strip_tags($description));
    $proof_link = $proof_link ? htmlspecialchars(strip_tags($proof_link)) : NULL;
    
    try {
        $query = "INSERT INTO reports (reporter_name, reporter_email, category, description, status, proof_link) 
                  VALUES (:name, :email, :category, :description, :status, :proof_link)";
        
        $stmt = $conn->prepare($query);

        $stmt->bindParam(':name', $reporter_name);
        $stmt->bindParam(':email', $reporter_email);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':proof_link', $proof_link);

        if($stmt->execute()) {
            echo json_encode(["message" => "Report submitted."]);
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Report DB Error: " . $errorInfo[2]);
            echo json_encode(["message" => "Database submission failed."]);
        }
    } catch (PDOException $e) {
        error_log("Critical DB Error: " . $e->getMessage());
        echo json_encode(["message" => "Critical Database Error during insert."]);
    }
} else {
    echo json_encode(["message" => "Missing required fields (Name, Email, category, or description)."]);
}
?>
