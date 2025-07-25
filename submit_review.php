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

// Get form data
$name = $_POST['name'] ?? '';
$event_type = $_POST['event_type'] ?? '';
$theme = $_POST['selectedTheme'] ?? '';
$review_text = $_POST['review_text'] ?? '';
$rating = $_POST['rating'] ?? '';

// Validate required fields
if (empty($name) || empty($event_type) || empty($theme) || empty($review_text) || empty($rating)) {
    die(json_encode(['error' => 'All fields are required']));
}

// Prepare and execute the SQL query
$sql = "INSERT INTO user_reviews (name, event_type, theme, review_text, rating) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $name, $event_type, $theme, $review_text, $rating);

if (!$stmt->execute()) {
    die(json_encode(['error' => 'Error saving review: ' . $stmt->error]));
}

// Handle image uploads if any
$review_id = $conn->insert_id;
$uploaded_images = [];

if (!empty($_FILES['event_images']['name'][0])) {
    $upload_dir = 'uploads/reviews/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    foreach ($_FILES['event_images']['tmp_name'] as $key => $tmp_name) {
        $file_name = $_FILES['event_images']['name'][$key];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $new_filename = uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;

        // Move uploaded file
        if (move_uploaded_file($tmp_name, $upload_path)) {
            $uploaded_images[] = $upload_path;
            
            // Save image path to database
            $img_sql = "INSERT INTO review_images (review_id, image_path) VALUES (?, ?)";
            $img_stmt = $conn->prepare($img_sql);
            $img_stmt->bind_param("is", $review_id, $upload_path);
            $img_stmt->execute();
        }
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Review submitted successfully',
    'images' => $uploaded_images
]);
?>
