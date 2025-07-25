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

// SQL query to fetch reviews
$sql = "SELECT name, event_type, theme, review_text, rating, timestamp FROM user_reviews ORDER BY timestamp DESC";
$result = $conn->query($sql);

$reviews = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $reviews[] = [
            'name' => $row['name'],
            'event_type' => $row['event_type'],
            'theme' => $row['theme'],
            'review_text' => $row['review_text'],
            'rating' => $row['rating'],
            'timestamp' => $row['timestamp']
        ];
    }
}

$conn->close();

echo json_encode($reviews);
?> 