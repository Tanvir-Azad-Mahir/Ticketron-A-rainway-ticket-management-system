<?php
session_start();
include '../db.php';

// Require login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch bookings for this user
$bookings = [];
$booking_sql = "SELECT b.booking_id, b.booking_date, b.total_amount, b.status, p.payment_status, p.payment_method
                FROM booking b
                LEFT JOIN payment p ON b.booking_id = p.booking_id
                WHERE b.user_id = ?
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
    <title>My Bookings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f6f8fb;
            --card: #ffffff;
            --accent: #1d4ed8;
            --accent-2: #0ea5e9;
            --text: #0f172a;
            --muted: #64748b;
            --success: #16a34a;
            --warn: #f59e0b;
            --border: #e5e7eb;
            --shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #f9fbff, #f6f8fb 35%, #eef2f7);
            color: var(--text);
            min-height: 100vh;
        }
        .page {
            max-width: 1120px;
            margin: 32px auto 64px;
            padding: 0 18px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .actions a {
            text-decoration: none;
            color: var(--text);
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #ffffff;
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .actions a:hover {
            border-color: var(--accent);
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.16);
            transform: translateY(-1px);
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 16px 18px;
            box-shadow: var(--shadow);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .badge {
            padding: 6px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .badge.success { background: #dcfce7; }
        .badge.pending { background: #fef9c3; }
        .badge.failed { background: #fee2e2; }
        .meta { color: var(--muted); font-size: 13px; margin-bottom: 6px; }
        .section-title { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin: 12px 0 8px; }
        .pill-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill { background: #f8fafc; border: 1px solid var(--border); padding: 6px 10px; border-radius: 12px; font-size: 13px; color: var(--text); }
        .seats { display: flex; flex-wrap: wrap; gap: 8px; }
        .seat-tag { background: #ebf3ff; border: 1px solid rgba(37, 99, 235, 0.35); color: #1d4ed8; padding: 6px 10px; border-radius: 12px; font-weight: 600; }
        .amount { font-size: 18px; font-weight: 800; margin-top: 6px; }
        .link-row { margin-top: 12px; display: flex; gap: 8px; }
        .link-row a {
            flex: 1;
            text-align: center;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 10px;
            background: #ffffff;
            color: var(--accent);
            border: 1px solid rgba(29, 78, 216, 0.35);
            transition: transform 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
        }
        .link-row a:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(29, 78, 216, 0.16); color: #0f172a; }
        .toggle-btn {
            flex: 1;
            text-align: center;
            padding: 10px 12px;
            border-radius: 10px;
            background: #ffffff;
            color: var(--accent);
            border: 1px solid rgba(29, 78, 216, 0.35);
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
        }
        .toggle-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(29, 78, 216, 0.16); color: #0f172a; }
        .empty { text-align: center; color: var(--muted); padding: 50px 0; border: 1px dashed var(--border); border-radius: 14px; background: #ffffff; box-shadow: var(--shadow); }
        .ticket-strip { margin-top: 12px; padding: 14px; border-radius: 12px; background: #fff; color: #0f172a; border: 1px solid var(--border); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05); }
        .ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ticket-train { font-weight: 800; font-size: 16px; color: #0f172a; }
        .ticket-id { font-size: 12px; color: var(--muted); font-weight: 700; }
        .ticket-route { display: flex; align-items: center; gap: 8px; font-weight: 700; color: #1d4ed8; margin-bottom: 10px; }
        .ticket-route .dot { width: 8px; height: 8px; border-radius: 50%; background: #1d4ed8; display: inline-block; }
        .ticket-route .arrow { color: #0f172a; font-weight: 800; }
        .ticket-row { display: flex; gap: 14px; margin-bottom: 8px; flex-wrap: wrap; }
        .ticket-chunk { flex: 1; min-width: 120px; }
        .ticket-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 3px; }
        .ticket-value { font-weight: 800; font-size: 15px; color: #0f172a; }
        .chip-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
        .chip { background: #f1f5f9; border: 1px solid var(--border); padding: 6px 10px; border-radius: 10px; font-weight: 700; color: #0f172a; font-size: 13px; }
        .ticket-strip small { color: var(--muted); }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="title">My Bookings</div>
            <div class="actions">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty">No bookings yet. Book a trip from the dashboard.</div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                           $ticket_sql = "SELECT t.ticket_id, t.seat_id, t.fare, t.booking_date, t.from_station_id, t.to_station_id, t.schedule_id,
                                        s.seat_number, c.coach_type, c.name AS coach_name, tr.name AS train_name, sc.start_time
                                    FROM ticket t
                                    JOIN seat s ON t.seat_id = s.seat_id
                                    JOIN coach c ON s.coach_id = c.coach_id
                                    JOIN train tr ON c.train_id = tr.train_id
                                    LEFT JOIN schedule sc ON t.schedule_id = sc.schedule_id
                                    WHERE t.booking_id = ?
                                    ORDER BY t.ticket_id ASC";
                        $tstmt = $conn->prepare($ticket_sql);
                        $tickets = [];
                        $from_station_name = 'Station';
                        $to_station_name = 'Station';
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
                            $from_station_id = $tickets[0]['from_station_id'];
                            $to_station_id = $tickets[0]['to_station_id'];

                            $sstmt = $conn->prepare('SELECT name, city FROM stations WHERE station_id = ?');
                            if ($sstmt) {
                                $sstmt->bind_param('s', $from_station_id);
                                $sstmt->execute();
                                $sres = $sstmt->get_result();
                                if ($srow = $sres->fetch_assoc()) {
                                    $from_station_name = $srow['name'] . ', ' . $srow['city'];
                                }
                                $sstmt->close();
                            }

                            $sstmt = $conn->prepare('SELECT name, city FROM stations WHERE station_id = ?');
                            if ($sstmt) {
                                $sstmt->bind_param('s', $to_station_id);
                                $sstmt->execute();
                                $sres = $sstmt->get_result();
                                if ($srow = $sres->fetch_assoc()) {
                                    $to_station_name = $srow['name'] . ', ' . $srow['city'];
                                }
                                $sstmt->close();
                            }
                        }
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <div>Booking #<?php echo htmlspecialchars($booking['booking_id']); ?></div>
                            <?php
                                $status_class = 'pending';
                                $status_text = $booking['status'];
                                
                                if ($booking['status'] === 'Completed') {
                                    $status_class = 'success';
                                } elseif ($booking['status'] === 'CancelRequested') {
                                    $status_text = 'Cancellation Pending';
                                } elseif ($booking['status'] === 'Cancelled') {
                                    $status_class = 'failed';
                                    $status_text = 'Cancellation Approved';
                                }
                            ?>
                            <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                        </div>
                        <div class="meta">Booked on <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></div>
                        <?php if (!empty($tickets)): ?>
                            <div class="meta" style="font-weight:600; color: #0f172a;">Train: <?php echo htmlspecialchars($tickets[0]['train_name']); ?></div>
                            <div class="meta">Route: <?php echo htmlspecialchars($from_station_name); ?> → <?php echo htmlspecialchars($to_station_name); ?></div>
                            <div class="meta">Travel Date: <?php echo date('M d, Y', strtotime($tickets[0]['booking_date'])); ?></div>
                            <div class="section-title">Seats</div>
                            <div class="seats">
                                <?php foreach ($tickets as $t): ?>
                                    <span class="seat-tag"><?php echo htmlspecialchars($t['seat_number']); ?> • <?php echo htmlspecialchars($t['coach_name']); ?> (<?php echo htmlspecialchars($t['coach_type']); ?>)</span>
                                <?php endforeach; ?>
                            </div>
                            <div class="section-title">Tickets</div>
                            <div class="ticket-wrapper" id="tickets-<?php echo $booking['booking_id']; ?>" style="display:none;">
                            <?php foreach ($tickets as $t): ?>
                                <?php $start = $t['start_time'] ? date('h:i A', strtotime($t['start_time'])) : 'N/A'; ?>
                                <div class="ticket-strip">
                                    <div class="ticket-header">
                                        <div class="ticket-train"><?php echo htmlspecialchars($t['train_name']); ?></div>
                                        <div class="ticket-id">Ticket #<?php echo htmlspecialchars($t['ticket_id']); ?></div>
                                    </div>
                                    <div class="ticket-route">
                                        <span class="dot"></span> <?php echo htmlspecialchars($from_station_name); ?>
                                        <span class="arrow">→</span>
                                        <span class="dot"></span> <?php echo htmlspecialchars($to_station_name); ?>
                                    </div>
                                    <div class="ticket-row">
                                        <div class="ticket-chunk">
                                            <div class="ticket-label">Passenger</div>
                                            <div class="ticket-value"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                                        </div>
                                        <div class="ticket-chunk">
                                            <div class="ticket-label">Date</div>
                                            <div class="ticket-value"><?php echo date('M d, Y', strtotime($t['booking_date'])); ?></div>
                                        </div>
                                        <div class="ticket-chunk">
                                            <div class="ticket-label">Departure</div>
                                            <div class="ticket-value"><?php echo htmlspecialchars($start); ?></div>
                                        </div>
                                    </div>
                                    <div class="chip-row">
                                        <span class="chip">Coach <?php echo htmlspecialchars($t['coach_name']); ?></span>
                                        <span class="chip">Seat <?php echo htmlspecialchars($t['seat_number']); ?></span>
                                        <span class="chip">Fare ৳<?php echo number_format($t['fare'], 2); ?></span>
                                    </div>
                                    <div class="ticket-row" style="margin-bottom: 0;">
                                        <div class="ticket-chunk">
                                            <div class="ticket-label">Booking</div>
                                            <div class="ticket-value">#<?php echo htmlspecialchars($booking['booking_id']); ?></div>
                                        </div>
                                        <div class="ticket-chunk">
                                            <div class="ticket-label">Schedule</div>
                                            <div class="ticket-value"><?php echo htmlspecialchars($t['schedule_id']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="amount">Total ৳<?php echo number_format($booking['total_amount'], 2); ?></div>
                        <div class="link-row">
                            <a href="confirmation.php?booking_id=<?php echo $booking['booking_id']; ?>">View Confirmation</a>
                            <button type="button" class="toggle-btn" onclick="toggleTickets(<?php echo (int)$booking['booking_id']; ?>)">Show Tickets</button>
                            <?php if ($booking['status'] !== 'CancelRequested' && $booking['status'] !== 'Cancelled' && $booking['status'] !== 'Refunded'): ?>
                                <form method="POST" action="../process/process_cancel_request.php" style="flex:1; margin:0;">
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking['booking_id']; ?>">
                                    <button class="toggle-btn" type="submit">Request Cancellation</button>
                                </form>
                            <?php else: ?>
                                <?php
                                    $cancel_status_text = '';
                                    if ($booking['status'] === 'CancelRequested') {
                                        $cancel_status_text = 'Cancellation Pending';
                                    } elseif ($booking['status'] === 'Cancelled') {
                                        $cancel_status_text = 'Cancellation Approved';
                                    } elseif ($booking['status'] === 'Refunded') {
                                        $cancel_status_text = 'Refund Completed';
                                    }
                                ?>
                                <span class="pill" style="flex:1; text-align:center;"><?php echo htmlspecialchars($cancel_status_text); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
<script>
function toggleTickets(id) {
    const section = document.getElementById('tickets-' + id);
    const btn = event.target;
    if (!section) return;
    const isHidden = section.style.display === 'none' || section.style.display === '';
    section.style.display = isHidden ? 'block' : 'none';
    btn.textContent = isHidden ? 'Hide Tickets' : 'Show Tickets';
}
</script>
</html>
