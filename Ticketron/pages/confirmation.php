<?php
session_start();
include '../db.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$booking_id = $_GET['booking_id'] ?? null;

if (!$booking_id) {
    header("Location: dashboard.php");
    exit();
}

// Get booking details
$stmt = $conn->prepare("
    SELECT b.*, p.payment_id, p.payment_status, p.payment_method
    FROM booking b
    LEFT JOIN payment p ON b.booking_id = p.booking_id
    WHERE b.booking_id = ? AND b.user_id = ?
");
$stmt->bind_param("is", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// Get tickets
$tickets = [];
$stmt = $conn->prepare("
    SELECT t.*, s.seat_number, c.coach_type, c.name as coach_name, tr.name as train_name
    FROM ticket t
    JOIN seat s ON t.seat_id = s.seat_id
    JOIN coach c ON s.coach_id = c.coach_id
    JOIN train tr ON c.train_id = tr.train_id
    WHERE t.booking_id = ?
");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}
$stmt->close();

// Get station names
$from_station_name = 'Station';
$to_station_name = 'Station';

if (!empty($tickets)) {
    $from_station = $tickets[0]['from_station_id'];
    $to_station = $tickets[0]['to_station_id'];
    
    $stmt = $conn->prepare("SELECT name, city FROM stations WHERE station_id = ?");
    $stmt->bind_param("s", $from_station);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $from_station_name = $row['name'] . ', ' . $row['city'];
    }
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT name, city FROM stations WHERE station_id = ?");
    $stmt->bind_param("s", $to_station);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $to_station_name = $row['name'] . ', ' . $row['city'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Ticketron</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --success: #10b981;
            --gray-100: #f1f5f9;
            --gray-600: #475569;
            --gray-800: #1e293b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .success-animation {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeInDown 0.6s ease-out;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: var(--success);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            animation: scaleIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .confirmation-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }

        h1 {
            font-size: 32px;
            color: var(--gray-800);
            text-align: center;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: var(--gray-600);
            margin-bottom: 30px;
        }

        .booking-info {
            background: var(--gray-100);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray-600);
        }

        .info-value {
            font-weight: 700;
            color: var(--gray-800);
        }

        .tickets-section {
            margin: 25px 0;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--gray-800);
        }

        .ticket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .ticket-card {
            background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .ticket-seat {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .ticket-info {
            font-size: 12px;
            opacity: 0.9;
        }

        .total-section {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            margin: 25px 0;
        }

        .total-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .total-amount {
            font-size: 36px;
            font-weight: 900;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        @media print {
            body {
                background: white;
            }
            .action-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="success-animation">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
    </div>

    <div class="confirmation-card">
        <h1>Booking Confirmed!</h1>
        <p class="subtitle">Your tickets have been successfully booked</p>

        <div class="booking-info">
            <div class="info-row">
                <span class="info-label">Booking ID:</span>
                <span class="info-value">#<?php echo htmlspecialchars($booking_id); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Booking Date:</span>
                <span class="info-value"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value" style="color: var(--success);"><?php echo htmlspecialchars($booking['status']); ?></span>
            </div>
            <?php if (!empty($tickets)): ?>
            <div class="info-row">
                <span class="info-label">Train:</span>
                <span class="info-value"><?php echo htmlspecialchars($tickets[0]['train_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Journey:</span>
                <span class="info-value"><?php echo htmlspecialchars($from_station_name); ?> → <?php echo htmlspecialchars($to_station_name); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Travel Date:</span>
                <span class="info-value"><?php echo date('M d, Y', strtotime($tickets[0]['booking_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Coach:</span>
                <span class="info-value"><?php echo htmlspecialchars($tickets[0]['coach_type']); ?> - <?php echo htmlspecialchars($tickets[0]['coach_name']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="tickets-section">
            <h2 class="section-title">Your Tickets</h2>
            <div class="ticket-grid">
                <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-card">
                    <div class="ticket-seat">
                        <i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($ticket['seat_number']); ?>
                    </div>
                    <div class="ticket-info">
                        Ticket: <?php echo htmlspecialchars($ticket['ticket_id']); ?>
                    </div>
                    <div class="ticket-info">
                        ৳<?php echo number_format($ticket['fare'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="total-section">
            <div class="total-label">Total Amount</div>
            <div class="total-amount">৳<?php echo number_format($booking['total_amount'], 2); ?></div>
            <div class="total-label" style="margin-top: 10px;">
                Payment Status: <?php echo htmlspecialchars($booking['payment_status']); ?>
            </div>
        </div>

        <div class="action-buttons">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Tickets
            </button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Back to Home
            </a>
            <a href="ticket.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary">
                <i class="fas fa-ticket-alt"></i> View Tickets
            </a>
        </div>
    </div>
</div>

</body>
</html>
