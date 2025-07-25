<?php
require 'db_connect.php';

// Get user details from form
$name = trim($_POST['name']);
$mobile = trim($_POST['contact']);
$email = trim($_POST['mail']);
$event_date = $_POST['date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$people = (int) $_POST['people'];
$venue_type = $_POST['typeofvenue'];
$theme = $_POST['theme'];
$brand = trim($_POST['brand']);
$cuisine = $_POST['cuisine'];
$food = isset($_POST['food']) ? implode(", ", (array)$_POST['food']) : '';

// Get add-ons
$addons = isset($_POST['addons']) ? implode(", ", (array)$_POST['addons']) : '';

// Calculate total price
$venue_price = 0;
$theme_price = 0;
$cuisine_price = 0;
$duration_price = 0;
$addons_price = 0;

// Venue prices
$venue_prices = [
    'Indoor Half' => 5000,
    'Indoor Full' => 8000,
    'Outdoor Half' => 7000,
    'Outdoor Full' => 10000,
    'Hybrid' => 15000
];

// Theme prices with venue multipliers
$theme_prices = [
    'Tech Conference' => ['base' => 6000, 'indoor' => 1.0, 'outdoor' => 1.2, 'hybrid' => 1.3],
    'Networking Night' => ['base' => 5000, 'indoor' => 1.0, 'outdoor' => 1.1, 'hybrid' => 1.2],
    'Seminar' => ['base' => 4000, 'indoor' => 1.0, 'outdoor' => 1.1, 'hybrid' => 1.2],
    'Award Ceremony' => ['base' => 5000, 'indoor' => 1.0, 'outdoor' => 1.1, 'hybrid' => 1.2],
    'Press Conference' => ['base' => 7000, 'indoor' => 1.0, 'outdoor' => 1.2, 'hybrid' => 1.3],
    'Product Launch' => ['base' => 8000, 'indoor' => 1.0, 'outdoor' => 1.25, 'hybrid' => 1.35]
];

// Cuisine prices per plate
$cuisine_prices = [
    'Continental' => ['veg' => 400, 'nonveg' => 600],
    'Italian' => ['veg' => 300, 'nonveg' => 500],
    'Indian' => ['veg' => 400, 'nonveg' => 600],
    'British' => ['veg' => 500, 'nonveg' => 700],
    'American' => ['veg' => 550, 'nonveg' => 650],
    'Mughalai' => ['veg' => 450, 'nonveg' => 650]
];

// Add-ons prices
$addon_prices = [
    'avEquipment' => 15000,
    'photography' => 6000,
    'videography' => 8000,
    'stageSetup' => 5000,
    'branding' => 2000,
    'host' => 10000
];

// Calculate venue price
$venue_price = $venue_prices[$venue_type] ?? 0;

// Calculate theme price with venue multipliers
$theme_config = $theme_prices[$theme] ?? ['base' => 0, 'indoor' => 1.0, 'outdoor' => 1.0, 'hybrid' => 1.0];
$multiplier = 1.0;

if (strpos($venue_type, 'Outdoor') !== false) {
    $multiplier = $theme_config['outdoor'];
} elseif ($venue_type === 'Hybrid') {
    $multiplier = $theme_config['hybrid'];
} else {
    $multiplier = $theme_config['indoor'];
}

if (strpos($venue_type, 'Full') !== false) {
    $multiplier *= 1.2;
}

$theme_price = round($theme_config['base'] * $multiplier);

// Calculate cuisine price
$cuisine_base_price = 0;
if (strpos($food, 'veg') !== false && strpos($food, 'nonveg') !== false) {
    $cuisine_base_price = ($cuisine_prices[$cuisine]['veg'] + $cuisine_prices[$cuisine]['nonveg']) / 2;
} elseif (strpos($food, 'veg') !== false) {
    $cuisine_base_price = $cuisine_prices[$cuisine]['veg'];
} elseif (strpos($food, 'nonveg') !== false) {
    $cuisine_base_price = $cuisine_prices[$cuisine]['nonveg'];
}
$cuisine_price = $cuisine_base_price * $people;

// Calculate duration price
$start = new DateTime($start_time);
$end = new DateTime($end_time);
$duration = $end->diff($start)->h;
$duration_price = $duration * 3000; // ₹3000 per hour

// Calculate add-ons price
if (!empty($_POST['addons'])) {
    foreach ($_POST['addons'] as $addon) {
        $addons_price += $addon_prices[$addon] ?? 0;
    }
}

// Calculate total price
$subtotal = $venue_price + $theme_price + $cuisine_price + $duration_price + $addons_price;
$gst = $subtotal * 0.18;
$total = $subtotal + $gst;

// Validate Inputs
if (!preg_match("/^\d{10}$/", $mobile)) {
    die("<script>alert('Error: Contact number must be exactly 10 digits.'); window.history.back();</script>");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("<script>alert('Error: Invalid email format.'); window.history.back();</script>");
}

if (strtotime($end_time) <= strtotime($start_time)) {
    die("<script>alert('Error: End time must be later than start time.'); window.history.back();</script>");
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

// Check for Date & Time Conflicts
$check_query = "SELECT id FROM corporate_events WHERE event_date = ? AND 
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

// Insert New Booking
$sql = "INSERT INTO corporate_events (name, mobile, email, event_date, start_time, end_time, venue_type, num_people, theme, brand_name, cuisine, food_preference, addons, venue_price, theme_price, cuisine_price, duration_price, addons_price, subtotal, gst, total) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssisssssiiiiiiiii", 
    $name, $mobile, $email, $event_date, $start_time, $end_time, 
    $venue_type, $people, $theme, $brand, $cuisine, $food, $addons,
    $venue_price, $theme_price, $cuisine_price, $duration_price, $addons_price,
    $subtotal, $gst, $total
);

if ($stmt->execute()) {
    echo "<html>
        <head>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap' rel='stylesheet'>
        <script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                background: linear-gradient(to right, #f6a664, #ea740c);
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
                color: #ea740c;
                font-size: 32px;
                font-weight: 700;
                text-align: center;
                z-index: 1000;
                animation: fadeIn 1.5s ease-out, float 3s ease-in-out infinite;
                border: 3px solid #ea740c;
                max-width: 80%;
                line-height: 1.4;
            }

            .thank-you-message i {
                font-size: 48px;
                margin-bottom: 20px;
                color: #ea740c;
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
                color: #ea740c;
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
                color: #ea740c;
                margin-bottom: 20px;
                font-size: 20px;
                border-bottom: 2px solid #ea740c;
                padding-bottom: 10px;
            }

            .order-details p {
                margin: 12px 0;
                font-size: 16px;
                display: flex;
                justify-content: space-between;
            }

            .highlight {
                color: #ea740c;
                font-weight: 600;
            }

            .price-breakdown {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 30px;
            }

            .price-breakdown h3 {
                color: #ea740c;
                margin-bottom: 20px;
                font-size: 20px;
                border-bottom: 2px solid #ea740c;
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
                color: #ea740c;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #ea740c;
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
                background: linear-gradient(to right, #ea740c, #f6a664);
                color: white;
                border: none;
                box-shadow: 0 4px 15px rgba(234, 116, 12, 0.2);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(234, 116, 12, 0.3);
            }

            .btn-secondary {
                background: #f8f9fa;
                color: #333;
                border: 2px solid #e0e0e0;
            }

            .btn-secondary:hover {
                border-color: #ea740c;
                color: #ea740c;
                transform: translateY(-2px);
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
                We're excited to host your corporate event.
            </div>
        </div>

        <div class='success-container' id='receipt-content'>
            <div class='logo-container'>
                <img src='http://localhost/OCCASIO/logo/logo.png' alt='Company Logo'>
            </div>
            <h2>🏢 Corporate Event Booking Successful!</h2>
            <p>Your event has been booked for <span class='highlight'>$event_date</span> from <span class='highlight'>$start_time</span> to <span class='highlight'>$end_time</span>.</p>

            <div class='order-details'>
                <h3>📋 Your Booking Details:</h3>
                <p><span>👤 Name:</span> <span class='highlight'>$name</span></p>
                <p><span>📞 Contact:</span> <span class='highlight'>$mobile</span></p>
                <p><span>📧 Email:</span> <span class='highlight'>$email</span></p>
                <p><span>📅 Event Date:</span> <span class='highlight'>$event_date</span></p>
                <p><span>🏢 Venue Type:</span> <span class='highlight'>$venue_type</span></p>
                <p><span>👥 Number of Guests:</span> <span class='highlight'>$people</span></p>
                <p><span>🎤 Event Type:</span> <span class='highlight'>$theme</span></p>
                <p><span>🏢 Brand/Company:</span> <span class='highlight'>$brand</span></p>
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
                <p>📞 Call us at: <span style='color: #e5601e; font-weight: bold;'>50000-60000</span></p>
                <p> Email us at: <span style='color: #e5601e; font-weight: bold;'>occasioevents@gmail.com</span></p>
                <p style='margin-top: 10px; color: #666;'>Our team is available to assist you with any queries or concerns regarding your booking.</p>
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
                    filename: 'Corporate_Booking_Receipt.pdf',
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
