<?php
session_start();
include '../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$stats = [
    'users' => 0,
    'bookings' => 0,
    'trains' => 0,
    'payments' => 0
];

// Fetch quick counts
$count_sql = [
    'users' => 'SELECT COUNT(*) AS c FROM users',
    'bookings' => 'SELECT COUNT(*) AS c FROM booking',
    'trains' => 'SELECT COUNT(*) AS c FROM train',
    'payments' => 'SELECT COUNT(*) AS c FROM payment'
];

foreach ($count_sql as $key => $sql) {
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) {
        $stats[$key] = (int)$row['c'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f0f4f8;
            --card: #ffffff;
            --accent: #2563eb;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #22c55e;
        }
        body {
            margin: 0;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background:
                radial-gradient(circle at 18% 20%, rgba(37,99,235,0.12), transparent 28%),
                radial-gradient(circle at 82% 16%, rgba(14,165,233,0.12), transparent 26%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 60%, #f8fafc 100%);
            color: var(--text);
            min-height: 100vh;
        }
        .page { max-width: 1180px; margin: 32px auto 48px; padding: 0 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .title { font-size: 26px; font-weight: 800; color: var(--text); }
        .user { color: var(--muted); font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
        .card-box { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 16px; box-shadow: 0 14px 32px rgba(15,23,42,0.12); }
        .metric { font-size: 13px; color: var(--muted); }
        .value { font-size: 28px; font-weight: 800; margin-top: 6px; }
        .actions { margin-top: 16px; display: flex; gap: 10px; }
        .btn { text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); color: var(--text); background: #f8fafc; transition: all 0.15s ease; box-shadow: 0 6px 18px rgba(15,23,42,0.08); }
        .btn:hover { border-color: rgba(37,99,235,0.4); box-shadow: 0 10px 24px rgba(37,99,235,0.16); }
        .logout { margin-left: auto; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <div class="title">Admin Dashboard</div>
                <div class="user">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></div>
            </div>
            <div class="actions">
                <a class="btn" href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="grid">
            <div class="card-box">
                <div class="metric">Users</div>
                <div class="value"><?php echo $stats['users']; ?></div>
            </div>
            <div class="card-box">
                <div class="metric">Bookings</div>
                <div class="value"><?php echo $stats['bookings']; ?></div>
            </div>
            <div class="card-box">
                <div class="metric">Trains</div>
                <div class="value"><?php echo $stats['trains']; ?></div>
            </div>
            <div class="card-box">
                <div class="metric">Payments</div>
                <div class="value"><?php echo $stats['payments']; ?></div>
            </div>
        </div>

        <div class="actions" style="margin-top:24px;">
            <a class="btn" href="admin_manage.php"><i class="fas fa-sliders-h"></i> Manage Data</a>
        </div>
    </div>
</body>
</html>
