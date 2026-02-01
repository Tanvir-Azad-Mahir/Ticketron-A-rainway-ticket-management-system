<?php
session_start();
include '../db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../pages/login.php');
    exit();
}

$booking_id = intval($_POST['booking_id'] ?? 0);
if ($booking_id <= 0) {
    $_SESSION['error'] = 'Invalid booking';
    header('Location: ../pages/ticket.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Ensure booking belongs to user
$stmt = $conn->prepare('SELECT booking_id, status FROM booking WHERE booking_id = ? AND user_id = ?');
if (!$stmt) {
    $_SESSION['error'] = 'Server error';
    header('Location: ../pages/ticket.php');
    exit();
}

$stmt->bind_param('is', $booking_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$booking = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$booking) {
    $_SESSION['error'] = 'Booking not found';
    header('Location: ../pages/ticket.php');
    exit();
}

// Block if already cancelled/refunded
if (in_array($booking['status'], ['Cancelled', 'Refunded'])) {
    $_SESSION['error'] = 'This booking is already cancelled/refunded';
    header('Location: ../pages/ticket.php');
    exit();
}

// Mark as cancel requested and flag payment
$conn->begin_transaction();
try {
    $u1 = $conn->prepare("UPDATE booking SET status = 'CancelRequested' WHERE booking_id = ?");
    $u1->bind_param('i', $booking_id);
    $u1->execute();
    $u1->close();

    $u2 = $conn->prepare("UPDATE payment SET payment_status = 'RefundRequested' WHERE booking_id = ?");
    if ($u2) {
        $u2->bind_param('i', $booking_id);
        $u2->execute();
        $u2->close();
    }

    $conn->commit();
    $_SESSION['message'] = 'Cancellation request sent. Admin will review.';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Could not submit cancellation request.';
}

header('Location: ../pages/ticket.php');
exit();
?>