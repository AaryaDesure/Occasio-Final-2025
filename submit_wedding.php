<?php
require 'db_connect.php';

// Get form data
$name = trim($_POST['name']);
$mobile = trim($_POST['contact']);
$email = trim($_POST['mail']);
$date = $_POST['date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$people = (int) $_POST['people'];
$venue_type = $_POST['typeofvenue'];
$theme = $_POST['theme'];
$cuisine = $_POST['cuisine'];
$food = isset($_POST['food']) ? implode(", ", (array)$_POST['food']) : '';
$addons = isset($_POST['addons']) ? implode(", ", (array)$_POST['addons']) : '';

// Get price values from hidden inputs with default values
$venue_price = isset($_POST['venue_price']) ? floatval($_POST['venue_price']) : 0;
$theme_price = isset($_POST['theme_price']) ? floatval($_POST['theme_price']) : 0;
$cuisine_price = isset($_POST['cuisine_price']) ? floatval($_POST['cuisine_price']) : 0;
$duration_price = isset($_POST['duration_price']) ? floatval($_POST['duration_price']) : 0;
$addons_price = isset($_POST['addons_price']) ? floatval($_POST['addons_price']) : 0;
$subtotal = isset($_POST['subtotal_price']) ? floatval($_POST['subtotal_price']) : 0;
$gst = isset($_POST['gst_amount']) ? floatval($_POST['gst_amount']) : 0;
$total = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;

// Validate mobile number
if (!preg_match("/^\d{10}$/", $mobile)) {
    die("<script>alert('Error: Contact number must be exactly 10 digits.'); window.history.back();</script>");
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("<script>alert('Error: Invalid email format.'); window.history.back();</script>");
}

// Validate time
if (strtotime($end_time) <= strtotime($start_time)) {
    die("<script>alert('Error: End time must be later than start time.'); window.history.back();</script>");
}

// Validate booking duration (max 6 hours)
$duration = (strtotime($end_time) - strtotime($start_time)) / 3600;
if ($duration > 6) {
    die("<script>alert('Error: Maximum booking duration is 6 hours.'); window.history.back();</script>");
}

// Validate booking time (8 AM to 10 PM)
$start_hour = (int)date('H', strtotime($start_time));
$end_hour = (int)date('H', strtotime($end_time));
if ($start_hour < 8 || $end_hour > 22) {
    die("<script>alert('Error: Events can only be booked between 8 AM and 10 PM.'); window.history.back();</script>");
}

// Validate advance booking (max 6 months)
$booking_date = strtotime($date);
$max_advance_date = strtotime('+6 months');
if ($booking_date > $max_advance_date) {
    die("<script>alert('Error: Bookings can only be made up to 6 months in advance.'); window.history.back();</script>");
}
// Store user details in `user_details` (If New User)
$check_user_query = "SELECT id FROM user_details WHERE mobile = ?";
$check_user_stmt = $conn->prepare($check_user_query);
$check_user_stmt->bind_param("s", $mobile);
$check_user_stmt->execute();
$check_user_result = $check_user_stmt->get_result();

if ($check_user_result->num_rows > 0) {
    // User exists, get their ID
    $user_row = $check_user_result->fetch_assoc();
    $user_id = $user_row['id'];
    
    // Update user details if they've changed
    $update_user_query = "UPDATE user_details SET name = ?, email = ? WHERE id = ?";
    $update_user_stmt = $conn->prepare($update_user_query);
    $update_user_stmt->bind_param("ssi", $name, $email, $user_id);
    $update_user_stmt->execute();
} else {
    // Insert new user
    $insert_user_query = "INSERT INTO user_details (name, mobile, email, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
    $insert_user_stmt = $conn->prepare($insert_user_query);
    $insert_user_stmt->bind_param("sss", $name, $mobile, $email);
    $insert_user_stmt->execute();
    $user_id = $insert_user_stmt->insert_id;
}

$check_user_stmt->close();
// Check for existing user
$user_check = $conn->prepare("SELECT id FROM user_details WHERE mobile = ? OR email = ?");
$user_check->bind_param("ss", $mobile, $email);
$user_check->execute();
$user_check->store_result();

if ($user_check->num_rows == 0) {
    $insert_user = $conn->prepare("INSERT INTO user_details (name, mobile, email) VALUES (?, ?, ?)");
    $insert_user->bind_param("sss", $name, $mobile, $email);
    $insert_user->execute();
    $insert_user->close();
}

$user_check->close();

// Check for booking conflicts
$check_conflict = $conn->prepare("SELECT id FROM wedding_events WHERE event_date = ? AND (
    (start_time < ? AND end_time > ?) OR
    (start_time = ? AND end_time = ?) OR
    (start_time > ? AND start_time < ?)
)");
$check_conflict->bind_param("sssssss", $date, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time);
$check_conflict->execute();
$check_conflict->store_result();

if ($check_conflict->num_rows > 0) {
    die("<script>alert('The selected time slot is already booked. Please choose a different one.'); window.history.back();</script>");
}
$check_conflict->close();

// Insert booking details
$sql = "INSERT INTO wedding_events (
    name, mobile, email, event_date, start_time, end_time, 
    venue_type, num_people, theme, cuisine, food_preference,
    addons, venue_price, theme_price, cuisine_price, duration_price,
    addons_price, subtotal, gst, total, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

// Convert all price values to integers
$venue_price = (int)$venue_price;
$theme_price = (int)$theme_price;
$cuisine_price = (int)$cuisine_price;
$duration_price = (int)$duration_price;
$addons_price = (int)$addons_price;
$subtotal = (int)$subtotal;
$gst = (int)$gst;
$total = (int)$total;

// Bind parameters
$stmt->bind_param(
    "sssssssisssssiiiiiii", 
    $name, $mobile, $email, $date, $start_time, $end_time,
    $venue_type, $people, $theme, $cuisine, $food,
    $addons, $venue_price, $theme_price, $cuisine_price, $duration_price,
    $addons_price, $subtotal, $gst, $total
);

if ($stmt->execute()) {
    echo "<html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap' rel='stylesheet'>
        <script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                background: linear-gradient(to right, #ff758c, #ff7eb3);
                text-align: center;
                margin: 0;
                padding: 40px 20px;
                color: #333;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .thank-you-message {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 255, 255, 0.98);
                padding: 40px 60px;
                border-radius: 20px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                color: #ff758c;
                font-size: 32px;
                font-weight: 700;
                text-align: center;
                z-index: 1000;
                animation: fadeIn 1.5s ease-out, float 3s ease-in-out infinite;
                border: 3px solid #ff758c;
                max-width: 80%;
                line-height: 1.4;
            }

            .thank-you-message i {
                font-size: 48px;
                margin-bottom: 20px;
                color: #ff758c;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translate(-50%, -60%); }
                to { opacity: 1; transform: translate(-50%, -50%); }
            }

            @keyframes float {
                0% { transform: translate(-50%, -50%); }
                50% { transform: translate(-50%, -53%); }
                100% { transform: translate(-50%, -50%); }
            }

            .success-container {
                padding: 40px;
                background: white;
                width: 90%;
                max-width: 800px;
                margin: auto;
                border-radius: 15px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.5s ease-out;
            }

            .success-container.show {
                opacity: 1;
                transform: translateY(0);
            }

            .logo-container {
                text-align: center;
                margin-bottom: 30px;
            }

            .logo-container img {
                width: 150px;
                height: auto;
            }

            h2 {
                color: #ff758c;
                font-size: 28px;
                margin-bottom: 20px;
            }

            .order-details {
                padding: 25px;
                background: #f8f9fa;
                border-radius: 12px;
                margin-bottom: 30px;
                text-align: left;
            }

            .order-details h3 {
                color: #ff758c;
                margin-bottom: 20px;
                font-size: 20px;
                border-bottom: 2px solid #ff758c;
                padding-bottom: 10px;
            }

            .order-details p {
                margin: 12px 0;
                font-size: 16px;
                display: flex;
                justify-content: space-between;
            }

            .highlight {
                color: #ff758c;
                font-weight: 600;
            }

            .price-breakdown {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 30px;
            }

            .price-breakdown h3 {
                color: #ff758c;
                margin-bottom: 20px;
                font-size: 20px;
                border-bottom: 2px solid #ff758c;
                padding-bottom: 10px;
            }

            .price-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 12px;
                font-size: 16px;
            }

            .total-price {
                font-size: 24px;
                font-weight: 600;
                color: #ff758c;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #ff758c;
                display: flex;
                justify-content: space-between;
            }

            .button-container {
                display: flex;
                justify-content: center;
                gap: 20px;
                margin-top: 30px;
            }

            .btn {
                padding: 15px 30px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }

            .btn-primary {
                background: linear-gradient(to right, #ff758c, #ff7eb3);
                color: white;
                border: none;
                box-shadow: 0 4px 15px rgba(255, 117, 140, 0.2);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(255, 117, 140, 0.3);
            }

            .btn-secondary {
                background: #f8f9fa;
                color: #333;
                border: 2px solid #e0e0e0;
            }

            .btn-secondary:hover {
                border-color: #ff758c;
                color: #ff758c;
                transform: translateY(-2px);
            }

            .help-section {
                margin-top: 40px;
                padding: 25px;
                background: #f8f9fa;
                border-radius: 12px;
                text-align: center;
            }

            .help-section h3 {
                color: #ff758c;
                margin-bottom: 15px;
                font-size: 20px;
            }

            .help-section p {
                margin: 8px 0;
                color: #666;
            }

            @media (max-width: 768px) {
                .success-container {
                    padding: 20px;
                }

                .button-container {
                    flex-direction: column;
                    gap: 15px;
                }

                .btn {
                    width: 100%;
                }
            }
        </style>
    </head>

    <body>
        <div class='thank-you-message'>
            <i class='fas fa-check-circle'></i>
            <div>Thank you for choosing OCCASIO Events!</div>
            <div style='font-size: 24px; margin-top: 15px; color: #666;'>
                We're excited to be part of your special day.
            </div>
        </div>

        <div class='success-container' id='receipt-content'>
            <div class='logo-container'>
                <img src='http://localhost/OCCASIO/logo/logo.png' alt='Company Logo'>
            </div>
            <h2>💍 Wedding Event Booking Successful!</h2>
            <p>Your wedding has been booked for <span class='highlight'>$date</span> from <span class='highlight'>$start_time</span> to <span class='highlight'>$end_time</span>.</p>

            <div class='order-details'>
                <h3>📋 Your Booking Details:</h3>
                <p><span>🙍🏻Name:</span> <span class='highlight'>" . htmlspecialchars($name) . "</span></p>
                <p><span>📞 Contact:</span> <span class='highlight'>" . htmlspecialchars($mobile) . "</span></p>
                <p><span>📧 Email:</span> <span class='highlight'>$email</span></p>
                <p><span>📅 Event Date:</span> <span class='highlight'>$date</span></p>
                <p><span>🏢 Venue Type:</span> <span class='highlight'>$venue_type</span></p>
                <p><span>👥 Number of Guests:</span> <span class='highlight'>$people</span></p>
                <p><span>🎨 Theme:</span> <span class='highlight'>$theme</span></p>
                <p><span>🍽 Cuisine:</span> <span class='highlight'>$cuisine</span></p>
                <p><span>🥗 Food Preference:</span> <span class='highlight'>$food</span></p>
                <p><span>➕ Add-ons:</span> <span class='highlight'>$addons</span></p>
            </div>

            <div class='price-breakdown'>
                <h3>💰 Price Breakdown:</h3>
                <div class='price-item'>
                    <span>Venue:</span>
                    <span>₹" . number_format($venue_price) . "</span>
                </div>
                <div class='price-item'>
                    <span>Theme:</span>
                    <span>₹" . number_format($theme_price) . "</span>
                </div>
                <div class='price-item'>
                    <span>Cuisine:</span>
                    <span>₹" . number_format($cuisine_price) . "</span>
                </div>
                <div class='price-item'>
                    <span>Duration:</span>
                    <span>₹" . number_format($duration_price) . "</span>
                </div>
                <div class='price-item'>
                    <span>Add-ons:</span>
                    <span>₹" . number_format($addons_price) . "</span>
                </div>
                <div class='price-item'>
                    <span>Subtotal:</span>
                    <span>₹" . number_format($subtotal) . "</span>
                </div>
                <div class='price-item'>
                    <span>GST (18%):</span>
                    <span>₹" . number_format($gst) . "</span>
                </div>
                <div class='total-price'>
                    <span>Total:</span>
                    <span>₹" . number_format($total) . "</span>
                </div>
            </div>

            <div class='button-container'>
                <a href='Homepage.html' class='btn btn-primary'>Go to Home</a>
                <button onclick='downloadReceipt()' class='btn btn-secondary'>Download Receipt</button>
            </div>

            <div class='help-section'>
                <h3>Need Help?</h3>
                <p>📞 Call us at: <span style='color: #ff758c; font-weight: bold;'>50000-60000</span></p>
                <p>📧 Email us at: <span style='color: #ff758c; font-weight: bold;'>occasioevents@gmail.com</span></p>
                <p style='margin-top: 10px; color: #666;'>Our team is available to assist you with any queries or concerns regarding your wedding booking.</p>
            </div>
        </div>

        <script>
            setTimeout(function() {
                document.querySelector('.thank-you-message').style.display = 'none';
                document.querySelector('.success-container').classList.add('show');
            }, 5000);

            function downloadReceipt() {
                const element = document.getElementById('receipt-content');
                const opt = {
                    margin: 10,
                    filename: 'Wedding_Booking_Receipt.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { 
                        scale: 2,
                        useCORS: true,
                        logging: true
                    },
                    jsPDF: { 
                        unit: 'mm', 
                        format: 'a4', 
                        orientation: 'portrait' 
                    }
                };

                html2pdf().set(opt).from(element).save();
            }
        </script>
    </body>
    </html>";
} else {
    echo "<script>
            alert('Error: " . $stmt->error . "');
            window.history.back();
          </script>";
}

$stmt->close();
$conn->close();
