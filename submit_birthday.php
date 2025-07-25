<?php
require 'db_connect.php';

// Price configurations
$PRICES = [
    'venue' => [
        'Indoor Half' => 5000,
        'Indoor Full' => 8000,
        'Outdoor Half' => 7000,
        'Outdoor Full' => 10000,
        'Hybrid' => 15000
    ],
    'theme' => [
        'Tropical Retreat' => ['base' => 6000, 'indoor' => 1.0, 'outdoor' => 1.2, 'hybrid' => 1.3],
        'Superhero Party' => ['base' => 5000, 'indoor' => 1.0, 'outdoor' => 1.1, 'hybrid' => 1.2],
        'Minimalist Luxury' => ['base' => 5500, 'indoor' => 1.0, 'outdoor' => 1.15, 'hybrid' => 1.25],
        'Black and Gold' => ['base' => 4000, 'indoor' => 1.0, 'outdoor' => 1.1, 'hybrid' => 1.2],
        'Candyland Fantasy' => ['base' => 6500, 'indoor' => 1.0, 'outdoor' => 1.2, 'hybrid' => 1.3],
        'Neon Bash' => ['base' => 7000, 'indoor' => 1.0, 'outdoor' => 1.25, 'hybrid' => 1.35]
    ],
    'cake' => [
        'Chocolate' => 1800,
        'Vanilla' => 1000,
        'Strawberry' => 1800,
        'Red Velvet' => 2000,
        'Butterscotch' => 1500,
        'Coffee' => 1850
    ],
    'cuisine' => [
        'Continental' => ['veg' => 400, 'nonveg' => 600],
        'Italian' => ['veg' => 300, 'nonveg' => 500],
        'Indian' => ['veg' => 400, 'nonveg' => 600],
        'British' => ['veg' => 500, 'nonveg' => 700],
        'American' => ['veg' => 550, 'nonveg' => 650],
        'Mughalai' => ['veg' => 450, 'nonveg' => 650]
    ],
    'addons' => [
        'magicShow' => 5000,
        'photography' => 8000,
        'videography' => 12000,
        'games' => 6000,
        'facePainting' => 4000,
        'balloonArt' => 3500
    ],
    'hourlyRate' => 2000
];

// Get form data
$full_name = trim($_POST['full_name']);
$contact = trim($_POST['contact']);
$email = trim($_POST['mail']);
$event_date = $_POST['date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$people = (int)$_POST['people'];
$venue_type = $_POST['typeofvenue'];
$theme = $_POST['theme'];
$cake_flavour = $_POST['cakeflav'];
$name_on_cake = $_POST['nameoncake'];
$age = (int)$_POST['age'];
$cuisine = $_POST['cuisine'];
$food_type = isset($_POST['food']) ? implode(", ", (array)$_POST['food']) : '';
$addons = isset($_POST['addons']) ? (array)$_POST['addons'] : [];

// First, check if user already exists with the same mobile number
$check_user_query = "SELECT id FROM user_details WHERE mobile = ?";
$check_user_stmt = $conn->prepare($check_user_query);
$check_user_stmt->bind_param("s", $contact);
$check_user_stmt->execute();
$check_user_result = $check_user_stmt->get_result();

if ($check_user_result->num_rows > 0) {
    // User exists, get their ID
    $user_row = $check_user_result->fetch_assoc();
    $user_id = $user_row['id'];
    
    // Update user details if they've changed
    $update_user_query = "UPDATE user_details SET name = ?, email = ? WHERE id = ?";
    $update_user_stmt = $conn->prepare($update_user_query);
    $update_user_stmt->bind_param("ssi", $full_name, $email, $user_id);
    $update_user_stmt->execute();
} else {
    // Insert new user
    $insert_user_query = "INSERT INTO user_details (name, mobile, email, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
    $insert_user_stmt = $conn->prepare($insert_user_query);
    $insert_user_stmt->bind_param("sss", $full_name, $contact, $email);
    $insert_user_stmt->execute();
    $user_id = $insert_user_stmt->insert_id;
}

// Calculate prices
$venue_price = $PRICES['venue'][$venue_type];

// Calculate theme price with venue multiplier
$theme_config = $PRICES['theme'][$theme];
$theme_multiplier = 1.0;
if (strpos($venue_type, 'Outdoor') !== false) {
    $theme_multiplier = $theme_config['outdoor'];
} elseif ($venue_type === 'Hybrid') {
    $theme_multiplier = $theme_config['hybrid'];
} else {
    $theme_multiplier = $theme_config['indoor'];
}
if (strpos($venue_type, 'Full') !== false) {
    $theme_multiplier *= 1.2;
}
$theme_price = round($theme_config['base'] * $theme_multiplier);

// Calculate cake price
$cake_price = $PRICES['cake'][$cake_flavour];

// Calculate cuisine price
$cuisine_config = $PRICES['cuisine'][$cuisine];
$cuisine_price = 0;
if (strpos($food_type, 'veg') !== false && strpos($food_type, 'nonveg') !== false) {
    $cuisine_price = (($cuisine_config['veg'] + $cuisine_config['nonveg']) / 2) * $people;
} elseif (strpos($food_type, 'veg') !== false) {
    $cuisine_price = $cuisine_config['veg'] * $people;
} elseif (strpos($food_type, 'nonveg') !== false) {
    $cuisine_price = $cuisine_config['nonveg'] * $people;
}

// Calculate duration price
$start = new DateTime($start_time);
$end = new DateTime($end_time);
$duration = $end->diff($start)->h;
$duration_price = max(0, $duration * $PRICES['hourlyRate']);

// Calculate add-ons price
$addons_price = 0;
foreach ($addons as $addon) {
    $addons_price += $PRICES['addons'][$addon];
}

// Calculate total
$subtotal = $venue_price + $theme_price + $cake_price + $cuisine_price + $duration_price + $addons_price;
$gst = $subtotal * 0.18;
$total = round($subtotal + $gst);

// Validate inputs
if (!preg_match("/^\d{10}$/", $contact)) {
    die("<script>alert('Error: Contact number must be exactly 10 digits.'); window.history.back();</script>");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("<script>alert('Error: Invalid email format.'); window.history.back();</script>");
}

if (strtotime($end_time) <= strtotime($start_time)) {
    die("<script>alert('Error: End time must be later than start time.'); window.history.back();</script>");
}

// Check for existing bookings
$check_query = "SELECT id FROM birthday_events WHERE event_date = ? AND 
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

// Insert into database
$sql = "INSERT INTO birthday_events(full_name, contact, email, event_date, start_time, end_time, number_of_people, 
        venue_type, theme, cake_flavour, name_on_cake, age, cuisine, food_type, addons, 
        venue_price, theme_price, cake_price, cuisine_price, duration_price, addons_price, subtotal, gst, total) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$addons_str = implode(", ", $addons);
$stmt->bind_param("ssssssissssisssiiiiiiiii", 
    $full_name, $contact, $email, $event_date, $start_time, $end_time, $people,
    $venue_type, $theme, $cake_flavour, $name_on_cake, $age, $cuisine, $food_type, $addons_str,
    $venue_price, $theme_price, $cake_price, $cuisine_price, $duration_price, $addons_price, $subtotal, $gst, $total
);

if ($stmt->execute()) {
    echo "<html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap' rel='stylesheet'>
        <script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                background: linear-gradient(135deg, #1e88e5, #64b5f6);
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
                color: #1e88e5;
                font-size: 32px;
                font-weight: 700;
                text-align: center;
                z-index: 1000;
                animation: fadeIn 1.5s ease-out, float 3s ease-in-out infinite;
                border: 3px solid #1e88e5;
                max-width: 80%;
                line-height: 1.4;
            }

            .thank-you-message i {
                font-size: 48px;
                margin-bottom: 20px;
                color: #1e88e5;
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
                color: #1e88e5;
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
                color: #1e88e5;
                margin-bottom: 20px;
                font-size: 20px;
                border-bottom: 2px solid #1e88e5;
                padding-bottom: 10px;
            }

            .order-details p {
                margin: 12px 0;
                font-size: 16px;
                display: flex;
                justify-content: space-between;
            }

            .highlight {
                color: #1e88e5;
                font-weight: 600;
            }

            .price-breakdown {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 30px;
            }

            .price-breakdown h3 {
                color: #1e88e5;
                margin-bottom: 20px;
                font-size: 20px;
                border-bottom: 2px solid #1e88e5;
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
                color: #1e88e5;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #1e88e5;
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
                background: linear-gradient(to right, #1e88e5, #64b5f6);
                color: white;
                border: none;
                box-shadow: 0 4px 15px rgba(30, 136, 229, 0.2);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(30, 136, 229, 0.3);
            }

            .btn-secondary {
                background: #f8f9fa;
                color: #333;
                border: 2px solid #e0e0e0;
            }

            .btn-secondary:hover {
                border-color: #1e88e5;
                color: #1e88e5;
                transform: translateY(-2px);
            }

            .help-section {
                margin-top: 30px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 12px;
            }

            .help-section h3 {
                color: #1e88e5;
                margin-bottom: 15px;
                font-size: 20px;
            }

            .help-section p {
                margin: 8px 0;
                font-size: 16px;
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
                We're excited to make your birthday celebration truly special.
            </div>
        </div>

        <div class='success-container' id='receipt-content'>
            <div class='logo-container'>
                <img src='http://localhost/OCCASIO/logo/logo.png' alt='Company Logo'>
            </div>
            <h2>🎂 Birthday Event Booking Successful!</h2>
            <p>Your event has been booked for <span class='highlight'>$event_date</span> from <span class='highlight'>$start_time</span> to <span class='highlight'>$end_time</span>.</p>

            <div class='order-details'>
                <h3>📋 Your Booking Details:</h3>
                <p><span>👤 Name:</span> <span class='highlight'>$full_name</span></p>
                <p><span>📞 Contact:</span> <span class='highlight'>$contact</span></p>
                <p><span>📧 Email:</span> <span class='highlight'>$email</span></p>
                <p><span>📅 Event Date:</span> <span class='highlight'>$event_date</span></p>
                <p><span>🏢 Venue Type:</span> <span class='highlight'>$venue_type</span></p>
                <p><span>👥 Number of Guests:</span> <span class='highlight'>$people</span></p>
                <p><span>🎂 Cake Flavor:</span> <span class='highlight'>$cake_flavour</span></p>
                <p><span>📝 Name on Cake:</span> <span class='highlight'>$name_on_cake</span></p>
                <p><span>🎈 Age:</span> <span class='highlight'>$age</span></p>
                <p><span>🎨 Theme:</span> <span class='highlight'>$theme</span></p>
                <p><span>🍽 Cuisine:</span> <span class='highlight'>$cuisine</span></p>
                <p><span>🥗 Food Preference:</span> <span class='highlight'>$food_type</span></p>
                <p><span>🎪 Add-ons:</span> <span class='highlight'>" . implode(", ", $addons) . "</span></p>
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
                    <span>Cake:</span>
                    <span>₹" . number_format($cake_price) . "</span>
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
                <p>📧 Email us at: <span style='color: #e5601e; font-weight: bold;'>occasioevents@gmail.com</span></p>
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
                    filename: 'Birthday_Booking_Receipt.pdf',
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
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
