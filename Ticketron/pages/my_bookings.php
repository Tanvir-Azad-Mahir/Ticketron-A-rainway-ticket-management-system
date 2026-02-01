<?php
session_start();
include '../db.php';

// Require login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';

// Build SQL based on filter
$filter_sql = '';
if ($filter === 'upcoming') {
    $filter_sql = " AND b.status = 'Completed' AND t.booking_date >= CURDATE()";
} elseif ($filter === 'completed') {
    $filter_sql = " AND b.status = 'Completed' AND t.booking_date < CURDATE()";
} elseif ($filter === 'cancelled') {
    $filter_sql = " AND (b.status = 'Cancelled' OR b.status = 'CancelRequested')";
}

// Fetch bookings with ticket info
$bookings = [];
$booking_sql = "SELECT DISTINCT b.booking_id, b.booking_date, b.total_amount, b.status, 
                p.payment_status, p.payment_method,
                MIN(t.booking_date) as travel_date
                FROM booking b
                LEFT JOIN payment p ON b.booking_id = p.booking_id
                LEFT JOIN ticket t ON b.booking_id = t.booking_id
                WHERE b.user_id = ? $filter_sql
                GROUP BY b.booking_id
                ORDER BY b.booking_date DESC, b.booking_id DESC";
$stmt = $conn->prepare($booking_sql);
if ($stmt) {
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | Ticketron</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --bg: #f0f4f8;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background:
                radial-gradient(circle at 18% 20%, rgba(37,99,235,0.12), transparent 28%),
                radial-gradient(circle at 82% 16%, rgba(14,165,233,0.12), transparent 26%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 60%, #f8fafc 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 24px 20px 60px;
        }
        .container { max-width: 1120px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .title { font-size: 28px; font-weight: 800; color: var(--text); }
        .nav { display: flex; gap: 10px; }
        .nav a {
            text-decoration: none;
            color: var(--text);
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 6px 18px rgba(15,23,42,0.08);
        }
        .nav a:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 24px rgba(37,99,235,0.16);
            transform: translateY(-1px);
        }
        .filters {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .filter-btn:hover {
            border-color: var(--primary);
            background: #eff6ff;
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 16px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 14px 32px rgba(15,23,42,0.1);
        }
        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .booking-id {
            font-size: 16px;
            font-weight: 800;
            color: var(--text);
        }
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge.completed { background: #dcfce7; color: #166534; }
        .badge.cancelled { background: #fee2e2; color: #991b1b; }
        .badge.pending { background: #fef9c3; color: #854d0e; }
        .info-row {
            display: flex;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--muted);
        }
        .info-row i { width: 16px; }
        .route {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin: 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .route .arrow { color: var(--primary); }
        .amount {
            font-size: 20px;
            font-weight: 800;
            color: var(--text);
            margin: 12px 0;
        }
        .actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
        }
        .btn {
            flex: 1;
            text-align: center;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn:hover {
            border-color: var(--primary);
            background: #eff6ff;
            transform: translateY(-1px);
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
            background: var(--card);
            border-radius: 14px;
            border: 2px dashed var(--border);
        }
        .empty i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">My Bookings</div>
            <div class="nav no-print">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            </div>
        </div>

        <div class="filters no-print">
            <a href="my_bookings.php?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All Bookings
            </a>
            <a href="my_bookings.php?filter=upcoming" class="filter-btn <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Upcoming
            </a>
            <a href="my_bookings.php?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Completed
            </a>
            <a href="my_bookings.php?filter=cancelled" class="filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Cancelled
            </a>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty">
                <i class="fas fa-ticket-alt"></i>
                <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No bookings found</p>
                <p>Book your first train journey from the dashboard</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                        // Fetch ticket details
                        $ticket_sql = "SELECT t.ticket_id, t.seat_id, t.fare, t.booking_date, 
                                       t.from_station_id, t.to_station_id, t.schedule_id,
                                       s.seat_number, c.coach_type, c.name AS coach_name, 
                                       tr.name AS train_name, tr.type AS train_type,
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
                        $train_name = '';
                        $from_station_name = '';
                        $to_station_name = '';
                        
                        if ($tstmt) {
                            $tstmt->bind_param('i', $booking['booking_id']);
                            $tstmt->execute();
                            $tres = $tstmt->get_result();
                            while ($trow = $tres->fetch_assoc()) {
                                $tickets[] = $trow;
                            }
                            $tstmt->close();
                        }

                        if (!empty($tickets)) {
                            $train_name = $tickets[0]['train_name'];
                            $from_station_id = $tickets[0]['from_station_id'];
                            $to_station_id = $tickets[0]['to_station_id'];

                            // Get station names
                            $sstmt = $conn->prepare('SELECT name, city FROM stations WHERE station_id = ?');
                            if ($sstmt) {
                                $sstmt->bind_param('s', $from_station_id);
                                $sstmt->execute();
                                $sres = $sstmt->get_result();
                                if ($srow = $sres->fetch_assoc()) {
                                    $from_station_name = $srow['name'];
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
                                }
                                $sstmt->close();
                            }
                        }

                        $badge_class = 'pending';
                        $status_text = $booking['status'];
                        
                        if ($booking['status'] === 'Completed') {
                            $badge_class = 'completed';
                        } elseif ($booking['status'] === 'CancelRequested') {
                            $badge_class = 'pending';
                            $status_text = 'Cancellation Pending';
                        } elseif ($booking['status'] === 'Cancelled') {
                            $badge_class = 'cancelled';
                            $status_text = 'Cancellation Approved';
                        }
                    ?>
                    <div class="card">
                        <div class="card-top">
                            <div class="booking-id">Booking #<?php echo htmlspecialchars($booking['booking_id']); ?></div>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                        </div>

                        <div class="info-row">
                            <i class="fas fa-calendar"></i>
                            <span>Booked: <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                        </div>

                        <?php if (!empty($tickets)): ?>
                            <div class="info-row">
                                <i class="fas fa-train"></i>
                                <span><?php echo htmlspecialchars($train_name); ?></span>
                            </div>

                            <div class="info-row">
                                <i class="fas fa-clock"></i>
                                <span>Travel: <?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></span>
                            </div>

                            <div class="route">
                                <?php echo htmlspecialchars($from_station_name); ?>
                                <span class="arrow">→</span>
                                <?php echo htmlspecialchars($to_station_name); ?>
                            </div>

                            <div class="info-row">
                                <i class="fas fa-chair"></i>
                                <span><?php echo count($tickets); ?> Seat(s): 
                                    <?php 
                                    $seat_numbers = array_map(function($t) { 
                                        return $t['seat_number']; 
                                    }, $tickets);
                                    echo htmlspecialchars(implode(', ', $seat_numbers)); 
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="amount">৳<?php echo number_format($booking['total_amount'], 2); ?></div>

                        <div class="actions no-print">
                            <a href="print_ticket.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-print"></i> Print
                            </a>
                            <a href="confirmation.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            <?php if ($booking['status'] !== 'CancelRequested' && $booking['status'] !== 'Cancelled'): ?>
                                <form method="POST" action="../process/process_cancel_request.php" style="flex:1; margin:0;">
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking['booking_id']; ?>">
                                    <button class="btn" type="submit" style="width:100%;">
                                        <i class="fas fa-ban"></i> Cancel
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
