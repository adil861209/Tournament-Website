<?php
include_once '../config.php';
$conn = getDBConnection();

$data = json_decode(file_get_contents("php://input"));

// 1. Basic Input Validation
if(empty($data->name) || empty($data->email) || empty($data->password) || empty($data->whatsapp_number)) {
    http_response_code(400); 
    echo json_encode(["message" => "Missing required fields (Name, Email, Password, or WhatsApp)."]);
    exit;
}

$email = filter_var(trim($data->email), FILTER_SANITIZE_EMAIL);
$whatsapp_number = trim($data->whatsapp_number);
$name = htmlspecialchars(strip_tags(trim($data->name)));

// 2. Validate Email Format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid email format."]);
    exit;
}

// 3. Validate WhatsApp Number (basic validation)
if (!preg_match('/^[0-9]{10,15}$/', $whatsapp_number)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid WhatsApp number format. Please include country code."]);
    exit;
}

// 4. Password Strength Validation
if (strlen($data->password) < 8) {
    http_response_code(400);
    echo json_encode(["message" => "Password must be at least 8 characters long."]);
    exit;
}

// 5. Prepare and Hash Data
$hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
$default_avatar = "default_player.png"; // Default avatar filename

try {
    // 6. Check for Duplicate Email
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->execute([$email]);
    if ($check_email->fetchColumn()) {
        http_response_code(409); 
        echo json_encode(["message" => "Email is already registered."]);
        exit;
    }

    // 7. Insert New User
    $query = "INSERT INTO users (name, email, password, whatsapp_number, role, avatar) 
              VALUES (:name, :email, :password, :whatsapp, 'player', :avatar)";
    $stmt = $conn->prepare($query);
    
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':whatsapp', $whatsapp_number);
    $stmt->bindParam(':avatar', $default_avatar);

    if($stmt->execute()){
        // SUCCESS PATH (HTTP 201)
        http_response_code(201); 
        echo json_encode(["message" => "Registration successful. Please log in."]);
    } else {
        // FAILURE PATH (HTTP 500)
        http_response_code(500);
        echo json_encode(["message" => "Registration failed due to database error."]);
    }

} catch (PDOException $e) {
    // Database Error Handling
    http_response_code(500);
    error_log("Registration DB Error: " . $e->getMessage());
    echo json_encode(["message" => "Critical server error. Please try again later."]);
}
// Closing ?> tag intentionally omitted.
