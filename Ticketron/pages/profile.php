<?php
session_start();
include '../db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$user = null;
$stmt = $conn->prepare('SELECT user_id, name, email, phone, nid, created_at FROM users WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f6f8fb;
            --card: #ffffff;
            --accent: #1d4ed8;
            --accent-2: #0ea5e9;
            --text: #0f172a;
            --muted: #64748b;
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
        .page { max-width: 960px; margin: 32px auto 64px; padding: 0 18px; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .title { font-size: 28px; font-weight: 800; letter-spacing: 0.3px; }
        .actions a { text-decoration: none; color: var(--text); padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: #ffffff; box-shadow: var(--shadow); transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px; }
        .actions a:hover { border-color: var(--accent); box-shadow: 0 12px 28px rgba(37, 99, 235, 0.16); transform: translateY(-1px); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px; box-shadow: var(--shadow); }
        .profile-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 10px; }
        .field { padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: #f8fafc; }
        .label { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 4px; }
        .value { font-weight: 800; color: var(--text); font-size: 15px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 999px; background: #ebf3ff; color: #1d4ed8; border: 1px solid rgba(29, 78, 216, 0.35); font-weight: 700; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="title">Profile</div>
            <div class="actions">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </div>
        </div>

        <div class="card">
            <div class="badge"><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['name']); ?></div>
            <div class="profile-row">
                <div class="field">
                    <div class="label">User ID</div>
                    <div class="value">#<?php echo htmlspecialchars($user['user_id']); ?></div>
                </div>
                <div class="field">
                    <div class="label">Email</div>
                    <div class="value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="field">
                    <div class="label">Phone</div>
                    <div class="value"><?php echo htmlspecialchars($user['phone']); ?></div>
                </div>
                <div class="field">
                    <div class="label">NID</div>
                    <div class="value"><?php echo htmlspecialchars($user['nid']); ?></div>
                </div>
                <div class="field">
                    <div class="label">Member Since</div>
                    <div class="value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
