<?php
require 'db_connect.php';

// Get user details from form
$name = trim($_POST['name']);
$contact = trim($_POST['contact']);
$email = trim($_POST['mail']);
$event_date = $_POST['date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$people = (int) $_POST['people'];
$venue_type = $_POST['typeofvenue'];
$budget = (int) $_POST['budget'];
$cuisine = $_POST['cuisine'];
$food_type = isset($_POST['food']) ? implode(", ", (array)$_POST['food']) : '';
$theme = $_POST['theme'];

// ✅ Step 1: Validate Inputs
if (!preg_match("/^\d{10}$/", $contact)) {
    die("<script>alert('Error: Contact number must be exactly 10 digits.'); window.history.back();</script>");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("<script>alert('Error: Invalid email format.'); window.history.back();</script>");
}

if (strtotime($end_time) <= strtotime($start_time)) {
    die("<script>alert('Error: End time must be later than start time.'); window.history.back();</script>");
}

// ✅ Step 2: Store user details in `user_details` (If New User)
$user_check = $conn->prepare("SELECT id FROM user_details WHERE `mobile` = ? OR `email` = ?");
$user_check->bind_param("ss", $contact, $email);
$user_check->execute();
$user_check->store_result();
if (empty($contact)) {
    die("❌ Mobile number is empty!");
}

if ($user_check->num_rows == 0) {
    $insert_user = $conn->prepare("INSERT INTO user_details (name, mobile, email) VALUES (?, ?, ?)");
    $insert_user->bind_param("sss", $name, $contact, $email);
    $insert_user->execute();
    $insert_user->close();
}

$user_check->close();

// ✅ Step 3: Check for Date & Time Conflicts
$check_query = "SELECT id FROM anniversary_events WHERE event_date = ? AND 
                ((start_time < ? AND end_time > ?) OR 
                (start_time = ? AND end_time = ?) OR 
                (start_time > ? AND start_time < ?))";

$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("sssssss", $event_date, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo "<script>
            alert('The selected date and time are already booked. Please choose a different date or time.');
            window.history.back();
          </script>";
    exit();
}

$check_stmt->close();
$duplicate_user_event = $conn->prepare("SELECT id FROM anniversary_events WHERE contact = ? AND event_date = ?");
$duplicate_user_event->bind_param("ss", $contact, $event_date);
$duplicate_user_event->execute();
$duplicate_user_event->store_result();
if ($duplicate_user_event->num_rows > 0) {
    die("<script>alert('You already have a booking on this date.'); window.history.back();</script>");
}
$duplicate_user_event->close();

// ✅ Step 4: Insert New Anniversary Order
$sql = "INSERT INTO anniversary_events(name, contact, email, event_date, start_time, end_time, people, venue_type, budget, theme, cuisine, food_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssisssss", $name, $contact, $email, $event_date, $start_time, $end_time, $people, $venue_type, $budget, $theme, $cuisine, $food_type);

if ($stmt->execute()) {
    // Show Confirmation Page
    echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Booking Confirmation</title>
            <script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>
            <style>
                body {
                    background: linear-gradient(to right, #fdd835, #ff9800);
                    margin: 0;
                    font-family: 'Oswald', sans-serif;
                    text-align: center;
                    color: #fff;
                }
                .success-container {
                    padding: 40px;
                    background: rgba(255, 255, 255, 0.9);
                    width: 60%;
                    margin: auto;
                    border-radius: 15px;
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
                    color: #333;
                }
                .order-details {
                    margin-top: 20px;
                    padding: 20px;
                    background: rgba(240, 240, 240, 0.9);
                    border-radius: 10px;
                    font-size: 18px;
                    text-align: left;
                }
                .highlight {
                    color: #e5601e;
                    font-weight: bold;
                }
                .logo-container img {
                    width: 120px;
                    height: auto;
                }
                .button-container {
                    margin-top: 20px;
                    display: flex;
                    justify-content: center;
                    gap: 15px;
                }
                .btn {
                    padding: 12px 24px;
                    border-radius: 25px;
                    background-color: #007bff;
                    color: white;
                    font-size: 18px;
                    font-weight: bold;
                    cursor: pointer;
                    border: none;
                }
                .btn:hover {
                    background-color: #0056b3;
                }
            </style>
        </head>

        <body>
            <div class='success-container' id='receipt-content'>
                <div class='logo-container'>
                    <img src='http://localhost/OCCASIO/logo/logo.png' alt='Company Logo'>
                </div>
                <h2>🎉 Anniversary Event Booking Successful!</h2>
                <p>Your event has been booked for <span class='highlight'>$event_date</span> from <span class='highlight'>$start_time</span> to <span class='highlight'>$end_time</span>.</p>

                <div class='order-details'>
                    <h3>📋 Your Booking Details:</h3>
                    <p>👤 Name: <span class='highlight'>" . htmlspecialchars($name) . "</span></p>
                    <p>📞 Contact: <span class='highlight'>$contact</span></p>
                    <p>📧 Email: <span class='highlight'>" . htmlspecialchars($email) . "</span></p>
                    <p>📅 Event Date: <span class='highlight'>$event_date</span></p>
                    <p>🏢 Venue Type: <span class='highlight'>$venue_type</span></p>
                    <p>👥 Number of Guests: <span class='highlight'>$people</span></p>
                    <p>💰 Budget: <span class='highlight'>₹$budget</span></p>
                    <p>🍽 Cuisine: <span class='highlight'>$cuisine</span></p>
                    <p>🎨 Theme: <span class='highlight'>$theme</span></p>
                    <p>🥗 Food Preference: <span class='highlight'>$food_type</span></p>
                </div>

                <div class='button-container'>
                    <a href='Homepage.html' class='btn'>Go to Home</a>
                    <button onclick='downloadReceipt()' class='btn'>Download Receipt</button>
                </div>
            </div>

            <script>
                function downloadReceipt() {
                    const element = document.getElementById('receipt-content');
                    html2pdf(element, {
                        margin: 10,
                        filename: 'Anniversary_Booking_Receipt.pdf',
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2 },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    });
                }
            </script>
        </body>
        </html>";
} else {
    die("Error: " . $conn->error);
}
