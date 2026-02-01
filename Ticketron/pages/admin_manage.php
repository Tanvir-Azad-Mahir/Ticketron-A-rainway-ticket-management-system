<?php
session_start();
include '../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$message = '';
$error = '';

function safeStr($v) { return trim($v ?? ''); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_station') {
            $id = safeStr($_POST['station_id']);
            $code = safeStr($_POST['station_code']);
            $name = safeStr($_POST['name']);
            $city = safeStr($_POST['city']);
            $division = safeStr($_POST['division']);
            if (!$id || !$code || !$name || !$city || !$division) throw new Exception('All station fields required');
            $stmt = $conn->prepare('INSERT INTO stations (Station_id, station_code, name, city, division) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $id, $code, $name, $city, $division);
            $stmt->execute();
            $stmt->close();
            $message = 'Station added';
        } elseif ($action === 'approve_refund') {
            $booking_id = intval($_POST['booking_id'] ?? 0);
            if ($booking_id <= 0) throw new Exception('Invalid booking id');
            $conn->begin_transaction();
            $u1 = $conn->prepare("UPDATE payment SET payment_status='Refunded' WHERE booking_id=?");
            $u1->bind_param('i', $booking_id);
            $u1->execute();
            $u1->close();
            $u2 = $conn->prepare("UPDATE booking SET status='Cancelled' WHERE booking_id=?");
            $u2->bind_param('i', $booking_id);
            $u2->execute();
            $u2->close();
            $u3 = $conn->prepare("UPDATE ticket SET booking_status='Cancelled' WHERE booking_id=?");
            $u3->bind_param('i', $booking_id);
            $u3->execute();
            $u3->close();
            $conn->commit();
            $message = 'Refund approved for booking #' . $booking_id;
        } elseif ($action === 'decline_refund') {
            $booking_id = intval($_POST['booking_id'] ?? 0);
            if ($booking_id <= 0) throw new Exception('Invalid booking id');
            $conn->begin_transaction();
            $u1 = $conn->prepare("UPDATE payment SET payment_status='Paid' WHERE booking_id=?");
            $u1->bind_param('i', $booking_id);
            $u1->execute();
            $u1->close();
            $u2 = $conn->prepare("UPDATE booking SET status='Completed' WHERE booking_id=?");
            $u2->bind_param('i', $booking_id);
            $u2->execute();
            $u2->close();
            $u3 = $conn->prepare("UPDATE ticket SET booking_status='Booked' WHERE booking_id=?");
            $u3->bind_param('i', $booking_id);
            $u3->execute();
            $u3->close();
            $conn->commit();
            $message = 'Refund declined for booking #' . $booking_id;
        } elseif ($action === 'add_route') {
            $id = safeStr($_POST['route_id']);
            $rname = safeStr($_POST['route_name']);
            $start = safeStr($_POST['start_station_id']);
            $end = safeStr($_POST['end_station_id']);
            $dist = floatval($_POST['total_distance'] ?? 0);
            if (!$id || !$start || !$end || $dist <= 0) throw new Exception('Route id, start, end, distance required');
            $stmt = $conn->prepare('INSERT INTO route (route_id, route_name, start_station_id, end_station_id, total_distance) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssd', $id, $rname, $start, $end, $dist);
            $stmt->execute();
            $stmt->close();
            $message = 'Route added';
        } elseif ($action === 'add_train') {
            $id = safeStr($_POST['train_id']);
            $tname = safeStr($_POST['train_name']);
            $type = safeStr($_POST['type']);
            $status = safeStr($_POST['status'] ?? 'Active');
            $route = safeStr($_POST['route_id']);
            if (!$id || !$tname || !$route) throw new Exception('Train id, name, route required');
            $stmt = $conn->prepare('INSERT INTO train (train_id, name, type, status, route_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $id, $tname, $type, $status, $route);
            $stmt->execute();
            $stmt->close();
            $message = 'Train added';
        } elseif ($action === 'add_schedule') {
            $id = safeStr($_POST['schedule_id']);
            $train = safeStr($_POST['train_id']);
            $route = safeStr($_POST['route_id']);
            $date = safeStr($_POST['travel_date']);
            $start = safeStr($_POST['start_time']);
            if (!$id || !$train || !$route || !$date || !$start) throw new Exception('All schedule fields required');
            $stmt = $conn->prepare('INSERT INTO schedule (schedule_id, train_id, route_id, travel_date, start_time) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $id, $train, $route, $date, $start);
            $stmt->execute();
            $stmt->close();
            $message = 'Schedule added';
        } elseif ($action === 'refund') {
            $booking_id = intval($_POST['booking_id'] ?? 0);
            if ($booking_id <= 0) throw new Exception('Invalid booking id');
            $conn->begin_transaction();
            $u1 = $conn->prepare("UPDATE payment SET payment_status='Refunded' WHERE booking_id=?");
            $u1->bind_param('i', $booking_id);
            $u1->execute();
            $u1->close();
            $u2 = $conn->prepare("UPDATE booking SET status='Cancelled' WHERE booking_id=?");
            $u2->bind_param('i', $booking_id);
            $u2->execute();
            $u2->close();
            $u3 = $conn->prepare("UPDATE ticket SET booking_status='Cancelled' WHERE booking_id=?");
            $u3->bind_param('i', $booking_id);
            $u3->execute();
            $u3->close();
            $conn->commit();
            $message = 'Refund processed for booking #' . $booking_id;
        }
    } catch (Exception $e) {
        if ($conn->errno) { /* noop */ }
        if ($conn->in_transaction) $conn->rollback();
        $error = $e->getMessage();
    }
}

// Fetch lists for selects
$stations = [];
$res = $conn->query('SELECT Station_id, name, city FROM stations ORDER BY name');
if ($res) { while($row=$res->fetch_assoc()) $stations[]=$row; }

$routes = [];
$res = $conn->query('SELECT route_id, route_name FROM route ORDER BY route_id');
if ($res) { while($row=$res->fetch_assoc()) $routes[]=$row; }

$trains = [];
$res = $conn->query('SELECT train_id, name FROM train ORDER BY train_id');
if ($res) { while($row=$res->fetch_assoc()) $trains[]=$row; }

$available_trains = [];
$res = $conn->query("SELECT t.train_id, t.name, t.type, t.status, r.route_name FROM train t LEFT JOIN route r ON t.route_id=r.route_id ORDER BY t.train_id");
if ($res) { while($row=$res->fetch_assoc()) $available_trains[]=$row; }

$refunds = [];
$res = $conn->query("SELECT b.booking_id, b.user_id, b.total_amount, b.status, p.payment_status FROM booking b LEFT JOIN payment p ON b.booking_id=p.booking_id ORDER BY b.booking_id DESC LIMIT 15");
if ($res) { while($row=$res->fetch_assoc()) $refunds[]=$row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Manage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg:#f0f4f8; --card:#ffffff; --accent:#2563eb; --text:#1e293b; --muted:#64748b; --border:#e2e8f0; }
        body { margin:0; font-family:'Inter','Segoe UI',system-ui,sans-serif; background:
            radial-gradient(circle at 18% 20%, rgba(37,99,235,0.12), transparent 28%),
            radial-gradient(circle at 82% 16%, rgba(14,165,233,0.12), transparent 26%),
            linear-gradient(135deg, #f8fafc 0%, #eef2ff 60%, #f8fafc 100%);
            color:var(--text); min-height:100vh; }
        .page { max-width:1180px; margin:28px auto 48px; padding:0 18px; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .title { font-size:26px; font-weight:800; color:var(--text); }
        .nav a { color:var(--text); text-decoration:none; border:1px solid var(--border); padding:10px 12px; border-radius:10px; background:#f8fafc; margin-left:8px; box-shadow:0 6px 18px rgba(15,23,42,0.08); }
        .nav a:hover { border-color:rgba(37,99,235,0.4); box-shadow:0 10px 22px rgba(37,99,235,0.14); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px; box-shadow:0 14px 32px rgba(15,23,42,0.12); }
        .card h3 { margin:0 0 10px; font-size:16px; color:var(--text); }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
        label { font-size:13px; color:var(--muted); }
        input, select { padding:10px; border-radius:10px; border:1px solid var(--border); background:#f8fafc; color:var(--text); }
        input:focus, select:focus { outline:none; border-color:rgba(37,99,235,0.7); box-shadow:0 0 0 3px rgba(37,99,235,0.12); }
        .btn { width:100%; border:none; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#ffffff; padding:11px; font-weight:800; border-radius:10px; cursor:pointer; box-shadow:0 10px 26px rgba(37,99,235,0.22); }
        .btn:hover { transform:translateY(-1px); box-shadow:0 14px 30px rgba(37,99,235,0.28); }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th, td { text-align:left; padding:8px; border-bottom:1px solid var(--border); color:var(--text); }
        .muted { color:var(--muted); }
        .msg { margin-bottom:10px; padding:10px; border-radius:10px; }
        .ok { background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.4); color:#166534; }
        .err { background:rgba(239,68,68,0.1); border:1px solid #fca5a5; color:#b91c1c; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="title">Admin Management</div>
            <div class="nav">
                <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        <?php if ($message): ?><div class="msg ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="grid">
            <div class="card">
                <h3>Add Station</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_station">
                    <div class="field"><label>Station ID</label><input name="station_id" required></div>
                    <div class="field"><label>Code</label><input name="station_code" required></div>
                    <div class="field"><label>Name</label><input name="name" required></div>
                    <div class="field"><label>City</label><input name="city" required></div>
                    <div class="field"><label>Division</label><input name="division" required></div>
                    <button class="btn">Add Station</button>
                </form>
            </div>

            <div class="card">
                <h3>Add Route</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_route">
                    <div class="field"><label>Route ID</label><input name="route_id" required></div>
                    <div class="field"><label>Route Name</label><input name="route_name" placeholder="e.g. Dhaka - Chattogram"></div>
                    <div class="field"><label>Start Station</label>
                        <select name="start_station_id" required>
                            <option value="">Select</option>
                            <?php foreach ($stations as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['Station_id']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>End Station</label>
                        <select name="end_station_id" required>
                            <option value="">Select</option>
                            <?php foreach ($stations as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['Station_id']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Total Distance (km)</label><input type="number" step="0.01" name="total_distance" required></div>
                    <button class="btn">Add Route</button>
                </form>
            </div>

            <div class="card">
                <h3>Add Train</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_train">
                    <div class="field"><label>Train ID</label><input name="train_id" required></div>
                    <div class="field"><label>Name</label><input name="train_name" required></div>
                    <div class="field"><label>Type</label><input name="type" placeholder="Intercity"></div>
                    <div class="field"><label>Status</label>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="field"><label>Route</label>
                        <select name="route_id" required>
                            <option value="">Select</option>
                            <?php foreach ($routes as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['route_id']); ?>"><?php echo htmlspecialchars($r['route_name'] ?? $r['route_id']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn">Add Train</button>
                </form>
            </div>

            <div class="card">
                <h3>Add Schedule</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_schedule">
                    <div class="field"><label>Schedule ID</label><input name="schedule_id" required></div>
                    <div class="field"><label>Train</label>
                        <select name="train_id" required>
                            <option value="">Select</option>
                            <?php foreach ($trains as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['train_id']); ?>"><?php echo htmlspecialchars($t['train_id'] . ' - ' . $t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Route</label>
                        <select name="route_id" required>
                            <option value="">Select</option>
                            <?php foreach ($routes as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['route_id']); ?>"><?php echo htmlspecialchars($r['route_name'] ?? $r['route_id']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Travel Date</label><input type="date" name="travel_date" required></div>
                    <div class="field"><label>Start Time</label><input type="time" name="start_time" required></div>
                    <button class="btn">Add Schedule</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <h3>Available Trains</h3>
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Status</th><th>Route</th></tr></thead>
                <tbody>
                    <?php foreach ($available_trains as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['train_id']); ?></td>
                            <td><?php echo htmlspecialchars($t['name']); ?></td>
                            <td class="muted"><?php echo htmlspecialchars($t['type']); ?></td>
                            <td><?php echo htmlspecialchars($t['status']); ?></td>
                            <td class="muted"><?php echo htmlspecialchars($t['route_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($available_trains)): ?>
                        <tr><td colspan="5" class="muted">No trains</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="margin-top:16px;">
            <h3>Refund / Cancellation Requests</h3>
            <table>
                <thead><tr><th>Booking</th><th>User</th><th>Amount</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($refunds as $r): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($r['booking_id']); ?></td>
                            <td><?php echo htmlspecialchars($r['user_id']); ?></td>
                            <td>৳<?php echo number_format($r['total_amount'], 2); ?></td>
                            <td class="muted"><?php echo htmlspecialchars($r['status']); ?></td>
                            <td class="muted"><?php echo htmlspecialchars($r['payment_status'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (($r['payment_status'] ?? '') === 'Refunded'): ?>
                                    <span class="muted">Refunded</span>
                                <?php elseif ($r['status'] === 'CancelRequested' || ($r['payment_status'] ?? '') === 'RefundRequested'): ?>
                                    <form method="POST" style="margin:0; display:inline-block;">
                                        <input type="hidden" name="action" value="approve_refund">
                                        <input type="hidden" name="booking_id" value="<?php echo (int)$r['booking_id']; ?>">
                                        <button class="btn" style="padding:8px 10px;">Approve</button>
                                    </form>
                                    <form method="POST" style="margin:0; display:inline-block;">
                                        <input type="hidden" name="action" value="decline_refund">
                                        <input type="hidden" name="booking_id" value="<?php echo (int)$r['booking_id']; ?>">
                                        <button class="btn" style="padding:8px 10px;">Decline</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">No request</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($refunds)): ?>
                        <tr><td colspan="6" class="muted">No bookings</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>
</html>
