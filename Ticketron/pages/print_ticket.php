<?php
session_start();
include '../db.php';

// Require login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$booking_id = $_GET['booking_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Verify booking belongs to user
$verify_sql = "SELECT b.booking_id, b.booking_date, b.total_amount, b.status,
               p.payment_status, p.payment_method, p.payment_date
               FROM booking b
               LEFT JOIN payment p ON b.booking_id = p.booking_id
               WHERE b.booking_id = ? AND b.user_id = ?";
$stmt = $conn->prepare($verify_sql);
$booking = null;
if ($stmt) {
    $stmt->bind_param('is', $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$booking) {
    die('Booking not found or access denied.');
}

// Fetch ticket details
$ticket_sql = "SELECT t.ticket_id, t.seat_id, t.fare, t.booking_date, 
               t.from_station_id, t.to_station_id, t.schedule_id,
               s.seat_number, c.coach_type, c.name AS coach_name, c.coach_id,
               tr.name AS train_name, tr.type AS train_type, tr.train_id,
               sc.start_time
               FROM ticket t
               JOIN seat s ON t.seat_id = s.seat_id
               JOIN coach c ON s.coach_id = c.coach_id
               JOIN train tr ON c.train_id = tr.train_id
               LEFT JOIN schedule sc ON t.schedule_id = sc.schedule_id
               WHERE t.booking_id = ?
               ORDER BY t.ticket_id ASC";
$tstmt = $conn->prepare($ticket_sql);
$tickets = [];
if ($tstmt) {
    $tstmt->bind_param('i', $booking_id);
    $tstmt->execute();
    $tres = $tstmt->get_result();
    while ($trow = $tres->fetch_assoc()) {
        $tickets[] = $trow;
    }
    $tstmt->close();
}

if (empty($tickets)) {
    die('No tickets found for this booking.');
}

$train_name = $tickets[0]['train_name'];
$train_type = $tickets[0]['train_type'];
$travel_date = $tickets[0]['booking_date'];
$start_time = $tickets[0]['start_time'];
$from_station_id = $tickets[0]['from_station_id'];
$to_station_id = $tickets[0]['to_station_id'];

// Get station details
$from_station_name = '';
$from_city = '';
$to_station_name = '';
$to_city = '';

$sstmt = $conn->prepare('SELECT name, city FROM stations WHERE station_id = ?');
if ($sstmt) {
    $sstmt->bind_param('s', $from_station_id);
    $sstmt->execute();
    $sres = $sstmt->get_result();
    if ($srow = $sres->fetch_assoc()) {
        $from_station_name = $srow['name'];
        $from_city = $srow['city'];
    }
    $sstmt->close();
}

$sstmt = $conn->prepare('SELECT name, city FROM stations WHERE station_id = ?');
if ($sstmt) {
    $sstmt->bind_param('s', $to_station_id);
    $sstmt->execute();
    $sres = $sstmt->get_result();
    if ($srow = $sres->fetch_assoc()) {
        $to_station_name = $srow['name'];
        $to_city = $srow['city'];
    }
    $sstmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Ticket - Booking #<?php echo $booking_id; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 32px;
            font-weight: 800;
            color: #2563eb;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #64748b;
            font-size: 14px;
        }
        .booking-info {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .info-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }
        .journey-section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        .route-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .station {
            flex: 1;
            text-align: center;
        }
        .station-name {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .station-city {
            font-size: 14px;
            opacity: 0.9;
        }
        .arrow {
            font-size: 32px;
            margin: 0 20px;
        }
        .tickets-grid {
            display: grid;
            gap: 16px;
        }
        .ticket-card {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            background: white;
        }
        .ticket-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px dashed #e2e8f0;
        }
        .ticket-id {
            font-size: 16px;
            font-weight: 800;
            color: #2563eb;
        }
        .ticket-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-value {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }
        .barcode {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .barcode-text {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 4px;
            color: #1e293b;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        .print-btn {
            text-align: center;
            margin: 20px 0;
        }
        .print-btn button {
            padding: 12px 32px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .print-btn button:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
        @media print {
            body { background: white; padding: 0; }
            .container { box-shadow: none; border-radius: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🚂 TICKETRON</div>
            <div class="subtitle">Railway Ticket Management System</div>
        </div>

        <div class="print-btn">
            <button onclick="window.print()">🖨️ Print Ticket</button>
        </div>

        <div class="booking-info">
            <div class="info-item">
                <div class="info-label">Booking ID</div>
                <div class="info-value">#<?php echo htmlspecialchars($booking_id); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Booking Date</div>
                <div class="info-value"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Passenger</div>
                <div class="info-value"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value"><?php echo htmlspecialchars($booking['status']); ?></div>
            </div>
        </div>

        <div class="journey-section">
            <div class="section-title">Journey Details</div>
            
            <div class="route-display">
                <div class="station">
                    <div class="station-name"><?php echo htmlspecialchars($from_station_name); ?></div>
                    <div class="station-city"><?php echo htmlspecialchars($from_city); ?></div>
                </div>
                <div class="arrow">→</div>
                <div class="station">
                    <div class="station-name"><?php echo htmlspecialchars($to_station_name); ?></div>
                    <div class="station-city"><?php echo htmlspecialchars($to_city); ?></div>
                </div>
            </div>

            <div class="booking-info">
                <div class="info-item">
                    <div class="info-label">Train Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($train_name); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Train Type</div>
                    <div class="info-value"><?php echo htmlspecialchars($train_type); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Travel Date</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($travel_date)); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Departure Time</div>
                    <div class="info-value"><?php echo $start_time ? date('h:i A', strtotime($start_time)) : 'N/A'; ?></div>
                </div>
            </div>
        </div>

        <div class="section-title">Tickets (<?php echo count($tickets); ?>)</div>
        <div class="tickets-grid">
            <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-card">
                    <div class="ticket-header">
                        <div class="ticket-id">Ticket #<?php echo htmlspecialchars($ticket['ticket_id']); ?></div>
                        <div class="ticket-id">৳<?php echo number_format($ticket['fare'], 2); ?></div>
                    </div>
                    <div class="ticket-details">
                        <div class="detail-item">
                            <div class="detail-label">Coach</div>
                            <div class="detail-value"><?php echo htmlspecialchars($ticket['coach_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Coach Type</div>
                            <div class="detail-value"><?php echo htmlspecialchars($ticket['coach_type']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Seat Number</div>
                            <div class="detail-value"><?php echo htmlspecialchars($ticket['seat_number']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Schedule ID</div>
                            <div class="detail-value"><?php echo htmlspecialchars($ticket['schedule_id']); ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="booking-info" style="margin-top: 24px;">
            <div class="info-item">
                <div class="info-label">Total Amount</div>
                <div class="info-value" style="font-size: 20px; color: #2563eb;">৳<?php echo number_format($booking['total_amount'], 2); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Payment Status</div>
                <div class="info-value"><?php echo htmlspecialchars($booking['payment_status'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Payment Method</div>
                <div class="info-value"><?php echo htmlspecialchars($booking['payment_method'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <div class="barcode">
            <div class="barcode-text">TKT-<?php echo str_pad($booking_id, 8, '0', STR_PAD_LEFT); ?></div>
        </div>

        <div class="footer">
            <p><strong>Important:</strong> Please carry a valid ID proof along with this ticket while traveling.</p>
            <p>For any queries, contact support at support@ticketron.com</p>
            <p style="margin-top: 12px;">Generated on <?php echo date('M d, Y h:i A'); ?></p>
        </div>
    </div>

    <script>
        // Auto print on load if requested
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>
