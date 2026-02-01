<?php
session_start();
include '../db.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../pages/login.php");
    exit();
}

// Debug: Log POST data
error_log("POST Data: " . print_r($_POST, true));

// Validate POST data
$required_fields = ['schedule_id', 'train_id', 'coach_id', 'selected_seats', 'total_amount', 'price_per_seat'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $_SESSION['error'] = "Missing required field: $field";
        error_log("Missing field: $field");
        header("Location: ../pages/dashboard.php?error=" . urlencode("Missing: $field"));
        exit();
    }
}

// Get booking details
$user_id = $_SESSION['user_id'];
$schedule_id = $_POST['schedule_id'];
$train_id = $_POST['train_id'];
$coach_id = $_POST['coach_id'];
$coach_type = $_POST['coach_type'] ?? '';
$travel_date = $_POST['travel_date'] ?? date('Y-m-d');
$from_station = $_POST['from_station'] ?? '';
$to_station = $_POST['to_station'] ?? '';
$selected_seats = explode(',', $_POST['selected_seats']);
$total_amount = floatval($_POST['total_amount']);
$price_per_seat = floatval($_POST['price_per_seat']);

// Validate seats
if (empty($selected_seats) || count($selected_seats) === 0) {
    $_SESSION['error'] = "No seats selected";
    header("Location: ../pages/dashboard.php");
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Create booking record
    $booking_date = date('Y-m-d');
    $booking_id = null;
    
    $booking_sql = "INSERT INTO booking (user_id, booking_date, total_amount, status) VALUES (?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($booking_sql);
    if (!$stmt) {
        throw new Exception("Prepare booking failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssd", $user_id, $booking_date, $total_amount);
    if (!$stmt->execute()) {
        throw new Exception("Booking insert failed: " . $stmt->error);
    }
    
    $booking_id = $conn->insert_id;
    $stmt->close();
    
    if (!$booking_id) {
        throw new Exception("Failed to get booking ID");
    }
    
    // 2. Check if seats are still available and create tickets
    $booked_count = 0;
    foreach ($selected_seats as $seat_id) {
        $seat_id = trim($seat_id);
        
        // Check if seat is already booked
        $check_sql = "SELECT ticket_id FROM ticket WHERE schedule_id = ? AND seat_id = ? AND booking_status = 'Booked'";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Check seat failed: " . $conn->error);
        }
        
        $check_stmt->bind_param("ss", $schedule_id, $seat_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            throw new Exception("Seat $seat_id is already booked. Please select different seats.");
        }
        $check_stmt->close();
        
        // Insert ticket (matching your actual table structure)
        $ticket_sql = "INSERT INTO ticket (booking_id, schedule_id, user_id, from_station_id, to_station_id, seat_id, quantity, booking_status, fare, booking_date) 
                      VALUES (?, ?, ?, ?, ?, ?, 1, 'Booked', ?, ?)";
        $ticket_stmt = $conn->prepare($ticket_sql);
        if (!$ticket_stmt) {
            throw new Exception("Prepare ticket failed: " . $conn->error);
        }
        
        $ticket_stmt->bind_param("isssssds", $booking_id, $schedule_id, $user_id, $from_station, $to_station, $seat_id, $price_per_seat, $booking_date);
        
        if (!$ticket_stmt->execute()) {
            throw new Exception("Ticket insert failed: " . $ticket_stmt->error);
        }
        
        $ticket_stmt->close();
        $booked_count++;
        
        // Create booking_detail record
        $detail_sql = "INSERT INTO booking_detail (booking_id, seat_id, price, status) VALUES (?, ?, ?, 'Booked')";
        $detail_stmt = $conn->prepare($detail_sql);
        if (!$detail_stmt) {
            throw new Exception("Prepare booking detail failed: " . $conn->error);
        }
        
        $detail_stmt->bind_param("isd", $booking_id, $seat_id, $price_per_seat);
        $detail_stmt->execute();
        $detail_stmt->close();
    }
    
    // 3. Create payment record
    // For now, mark payment as paid immediately (no external gateway)
    $payment_method = 'Cash';
    $payment_date = date('Y-m-d H:i:s');
    $payment_status = 'Paid';
    
    $payment_sql = "INSERT INTO payment (booking_id, user_id, amount, payment_method, payment_date, payment_status) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $payment_stmt = $conn->prepare($payment_sql);
    if (!$payment_stmt) {
        throw new Exception("Prepare payment failed: " . $conn->error);
    }
    
    $payment_stmt->bind_param("isdsss", $booking_id, $user_id, $total_amount, $payment_method, $payment_date, $payment_status);
    
    if (!$payment_stmt->execute()) {
        throw new Exception("Payment insert failed: " . $payment_stmt->error);
    }
    
    $payment_id = $conn->insert_id;
    $payment_stmt->close();

    // 4. Update booking status to Completed
    $update_booking = $conn->prepare("UPDATE booking SET status = 'Completed' WHERE booking_id = ?");
    if (!$update_booking) {
        throw new Exception("Update booking status failed: " . $conn->error);
    }
    $update_booking->bind_param("i", $booking_id);
    if (!$update_booking->execute()) {
        throw new Exception("Booking status update failed: " . $update_booking->error);
    }
    $update_booking->close();
    
    // Commit transaction
    $conn->commit();
    
    // Store booking info in session for confirmation page
    $_SESSION['booking_success'] = [
        'booking_id' => $booking_id,
        'payment_id' => $payment_id,
        'total_amount' => $total_amount,
        'seats_count' => $booked_count,
        'travel_date' => $travel_date,
        'from_station' => $from_station,
        'to_station' => $to_station
    ];
    
    // Redirect to confirmation page
    header("Location: ../pages/confirmation.php?booking_id=$booking_id");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['error'] = $e->getMessage();
    header("Location: ../pages/dashboard.php");
    exit();
}
?>
