<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "registration";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop the existing user_reviews table if it exists
$drop_table_sql = "DROP TABLE IF EXISTS user_reviews";
if ($conn->query($drop_table_sql) === TRUE) {
    echo "Old table deleted successfully.<br>";
} else {
    echo "Error deleting old table: " . $conn->error . "<br>";
}

// Drop the existing review_images table if it exists
$drop_images_table_sql = "DROP TABLE IF EXISTS review_images";
if ($conn->query($drop_images_table_sql) === TRUE) {
    echo "Old images table deleted successfully.<br>";
} else {
    echo "Error deleting old images table: " . $conn->error . "<br>";
}

// Create new user_reviews table
$create_table_sql = "CREATE TABLE user_reviews (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    theme VARCHAR(100) NOT NULL,
    review_text TEXT NOT NULL,
    rating INT(1) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($create_table_sql) === TRUE) {
    echo "New user_reviews table created successfully.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create new review_images table
$create_images_table_sql = "CREATE TABLE review_images (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    review_id INT(11) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES user_reviews(id) ON DELETE CASCADE
)";

if ($conn->query($create_images_table_sql) === TRUE) {
    echo "New review_images table created successfully.<br>";
} else {
    echo "Error creating images table: " . $conn->error . "<br>";
}

// Insert sample data (optional)
$insert_sample = "INSERT INTO user_reviews (name, event_type, theme, review_text, rating) VALUES
('John Doe', 'Wedding', 'Royal Imperial', 'Amazing experience! The venue was beautiful and the service was excellent.', 5),
('Jane Smith', 'Birthday', 'Neon Bash', 'Had a blast at my birthday party. The decorations were perfect!', 4),
('Robert Johnson', 'Corporate', 'Tech Conference', 'Professional setup and great coordination. Would definitely recommend.', 5)";

if ($conn->query($insert_sample) === TRUE) {
    echo "Sample data inserted successfully.<br>";
    
    // Get the IDs of the inserted reviews
    $review_ids = [
        $conn->insert_id,
        $conn->insert_id + 1,
        $conn->insert_id + 2
    ];
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/reviews/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "Created uploads directory.<br>";
    }
    
} else {
    echo "Error inserting sample data: " . $conn->error . "<br>";
}

echo "<br>Database setup completed. You can now use the review system.";

$conn->close();
?> 