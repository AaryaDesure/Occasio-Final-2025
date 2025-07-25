<?php
include 'db_connect.php'; // Ensure the database connection is set up

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form_type = $_POST["form_type"];
    $date = $_POST["date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];

    // Determine the correct table based on form type
    if ($form_type === "birthday") {
        $table_name = "birthday_orders";
    } elseif ($form_type === "corporate") {
        $table_name = "corporate_orders";
    } elseif ($form_type === "wedding") {
        $table_name = "wedding_orders";
    } elseif ($form_type === "anniversary") {
        $table_name = "anniversary_orders";
    } else {
        echo "error"; // Invalid form type
        exit;
    }

    // Check if the selected time slot is already booked
    $query = "SELECT * FROM $table_name WHERE event_date = ? 
              AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss", $date, $start_time, $start_time, $end_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "unavailable"; // Slot is already booked
    } else {
        echo "available"; // Slot is free
    }

    $stmt->close();
    $conn->close();
}
