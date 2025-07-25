<?php
header('Content-Type: application/json');

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "registration";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Get the name from POST request
$name = $_POST['name'] ?? '';

if (empty($name)) {
    die(json_encode(['error' => 'Name is required']));
}

// Verify if user exists in user_details
$verify_sql = "SELECT name FROM user_details WHERE name = ?";
$stmt = $conn->prepare($verify_sql);
$stmt->bind_param("s", $name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die(json_encode(['error' => 'User not found in our records']));
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'User verified successfully'
]);
?> 