<?php
session_start();
include '../db.php';

// If already logged in as admin, go to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

$error = '';
if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        :root {
            --bg: #f0f4f8;
            --panel: #ffffff;
            --accent: #2563eb;
            --accent-2: #1e40af;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --error: #ef4444;
        }

        body {
            margin: 0;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
            background:
                radial-gradient(circle at 18% 20%, rgba(37,99,235,0.12), transparent 28%),
                radial-gradient(circle at 82% 16%, rgba(14,165,233,0.12), transparent 26%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 60%, #f8fafc 100%);
            color: var(--text);
        }

        .shell {
            width: min(420px, 100%);
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 14px 38px rgba(15,23,42,0.12);
            position: relative;
            overflow: hidden;
        }

        .glow {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(circle at 30% 8%, rgba(37,99,235,0.14), transparent 42%),
                        radial-gradient(circle at 82% 78%, rgba(14,165,233,0.12), transparent 38%);
        }

        .header {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .icon-badge {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            display: grid;
            place-items: center;
            color: #ffffff;
            font-size: 18px;
            box-shadow: 0 8px 22px rgba(37,99,235,0.35);
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            margin: 0;
            color: var(--text);
        }

        .subtitle {
            color: var(--muted);
            font-size: 14px;
            margin: 4px 0 18px;
        }

        .field { margin-bottom: 14px; position: relative; z-index: 1; }
        .label { font-size: 13px; color: var(--muted); margin-bottom: 6px; display: block; }
        .input-wrap { position: relative; }
        .input-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px 12px 12px 38px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #f8fafc;
            color: var(--text);
            font-size: 14px;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: rgba(37,99,235,0.7);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }

        .btn {
            width: 100%;
            border: none;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            padding: 12px;
            font-weight: 800;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 10px 26px rgba(37,99,235,0.22);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(37,99,235,0.28); }

        .error {
            background: rgba(239,68,68,0.08);
            border: 1px solid #fca5a5;
            color: #b91c1c;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }

        .hint {
            margin-top: 12px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .switch-user {
            margin-top: 14px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .switch-user a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="glow"></div>
        <div class="header">
            <div class="icon-badge">🔒</div>
            <div>
                <div class="title">Admin Portal</div>
                <div class="subtitle">Secure access for authorized personnel</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="../process/process_admin_login.php">
            <div class="field">
                <label class="label">Username</label>
                <div class="input-wrap">
                    <i>👤</i>
                    <input type="text" name="username" placeholder="Enter your username" required>
                </div>
            </div>
            <div class="field">
                <label class="label">Password</label>
                <div class="input-wrap">
                    <i>🔑</i>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit" class="btn">Sign in</button>
        </form>

        <div class="hint">Use your admin credentials to proceed.</div>
        <div class="switch-user">
            <a href="login.php">← Back to user login</a>
        </div>
    </div>
</body>
</html>
