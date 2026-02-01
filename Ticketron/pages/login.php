<?php
// Add output buffering at the very top
ob_start();
session_start();

include '../db.php'; 

$message = "";
$remembered_input = "";

// Check if user just logged out
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $message = "<div class='alert success'>
        <div class='alert-icon'>✅</div>
        <div class='alert-content'>
            <strong>Logged Out Successfully!</strong><br>
            You have been logged out. Please login again to continue.
        </div>
    </div>";
}

// Check if redirected from successful registration
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['email'])) {
    $registered_email = htmlspecialchars($_GET['email']);
    $message = "<div class='alert success'>
        <div class='alert-icon'>✅</div>
        <div class='alert-content'>
            <strong>Registration Successful!</strong><br>
            Your account has been created.<br>
            Please login with your email/phone and password.
        </div>
    </div>";
    $remembered_input = $registered_email;
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $login_input = $_POST['login_input'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    if (!empty($login_input) && !empty($password)) {
        // Check if input is email or phone
        $is_email = filter_var($login_input, FILTER_VALIDATE_EMAIL);
        
        $user = null;
        
        if ($is_email) {
            // Login with email - using prepared statement
            $stmt = $conn->prepare("SELECT user_id, name, email, phone, pass FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $login_input);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows == 1) {
                $user = $result->fetch_assoc();
            }
            $stmt->close();
        } else {
            // Login with phone - using prepared statement
            // Clean phone number (remove spaces, dashes, parentheses)
            $clean_phone = preg_replace('/[^0-9]/', '', $login_input);
            $stmt = $conn->prepare("SELECT user_id, name, email, phone, pass FROM users WHERE phone = ? LIMIT 1");
            $stmt->bind_param("s", $login_input);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows == 1) {
                $user = $result->fetch_assoc();
            } else {
                // Try searching with cleaned phone number
                $search_phone = '%' . $clean_phone . '%';
                $stmt = $conn->prepare("SELECT user_id, name, email, phone, pass FROM users WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ? LIMIT 1");
                $stmt->bind_param("s", $search_phone);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                }
            }
            $stmt->close();
        }
        
        // Check password - plain text only
        $password_correct = false;
        if ($user && $password === $user['pass']) {
            $password_correct = true;
        }
        
        if ($password_correct) {
            // Login successful - set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Set cookie for "Remember me" if checked
            if ($remember) {
                $cookie_value = base64_encode($user['user_id'] . ':' . hash('sha256', $user['email']));
                setcookie('remember_login', $cookie_value, time() + (30 * 24 * 60 * 60), "/"); // 30 days
            }
            
            // Clear output buffer and redirect
            ob_clean();
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "<div class='alert error'>
                <div class='alert-icon'>⚠️</div>
                <div class='alert-content'>
                    <strong>Login Failed</strong><br>
                    Invalid email/phone or password.
                </div>
            </div>";
            $remembered_input = $login_input;
        }
    } else {
        $message = "<div class='alert error'>
            <div class='alert-icon'>⚠️</div>
            <div class='alert-content'>
                <strong>Missing Information</strong><br>
                Please enter both Email/Phone and Password.
            </div>
        </div>";
    }
}

// Auto-login from remember me cookie
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_login'])) {
    $cookie_data = base64_decode($_COOKIE['remember_login']);
    list($user_id, $hash) = explode(':', $cookie_data);
    
    // Use prepared statement
    $stmt = $conn->prepare("SELECT user_id, name, email, phone FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result && $result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $expected_hash = hash('sha256', $user['email']);
        
        if (hash_equals($expected_hash, $hash)) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            ob_clean();
            header("Location: dashboard.php");
            exit();
        }
    }
}

// Get remembered input from cookie
if (isset($_COOKIE['remembered_input'])) {
    $remembered_input = $_COOKIE['remembered_input'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Ticketron</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --secondary-gradient: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #2563eb;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --card-shadow-lg: 0 20px 25px rgba(0, 0, 0, 0.15);
            --input-border: #e2e8f0;
            --success-color: #10b981;
            --error-color: #ef4444;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --blue-50: #eff6ff;
            --blue-100: #dbeafe;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: var(--text-primary);
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            min-height: 600px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow-lg);
            background: white;
            border: 1px solid var(--input-border);
        }

        /* Left Side - Branding */
        .brand-side {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><circle cx="50" cy="50" r="40" fill="white" opacity="0.05"/></svg>');
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }

        .logo-icon {
            font-size: 28px;
            margin-right: 12px;
            background: rgba(255,255,255,0.2);
            color: white;
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .logo-text {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-content h1 {
            font-size: 34px;
            margin-bottom: 18px;
            line-height: 1.2;
            font-weight: 700;
        }

        .brand-content p {
            font-size: 15px;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .features {
            list-style: none;
            margin-top: 40px;
        }

        .features li {
            display: flex;
            align-items: center;
            margin-bottom: 14px;
            font-size: 14px;
            opacity: 0.95;
        }

        .features i {
            margin-right: 12px;
            background: rgba(255, 255, 255, 0.2);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        /* Right Side - Login Form */
        .form-side {
            flex: 1;
            padding: 50px 40px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Alert Messages */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-icon {
            font-size: 20px;
            margin-right: 12px;
            margin-top: 2px;
        }

        .alert-content {
            flex: 1;
        }

        .alert-content strong {
            display: block;
            margin-bottom: 4px;
        }

        /* Login Options Toggle */
        .login-options {
            display: flex;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 25px;
        }

        .login-option-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-option-btn.active {
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
        }

        .input-with-icon input {
            width: 100%;
            padding: 14px 14px 14px 44px;
            border: 2px solid var(--input-border);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
        }

        .input-with-icon input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .input-with-icon input::placeholder {
            color: #94a3b8;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 16px;
        }

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            margin-bottom: 22px;
            font-size: 13px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid var(--input-border);
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        /* Submit Button */
        .btn-login {
            width: 100%;
            padding: 12px 16px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Login Help */
        .login-help {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid var(--input-border);
        }

        .login-help h4 {
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-help h4 i {
            color: var(--accent-color);
        }

        .login-help p {
            font-size: 13px;
            margin-bottom: 8px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .login-help ul {
            padding-left: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .login-help li {
            margin-bottom: 5px;
        }

        /* Admin switch link */
        .admin-switch {
            margin-top: 18px;
            text-align: center;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .admin-switch a {
            color: var(--accent-color);
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid var(--input-border);
            border-radius: 10px;
            transition: all 0.2s ease;
            background: #f8fafc;
        }

        .admin-switch a:hover {
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.15);
            transform: translateY(-1px);
        }

        /* Footer Links */
        .form-footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .form-footer a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                max-width: 450px;
            }
            
            .brand-side {
                padding: 30px;
            }
            
            .brand-side h1 {
                font-size: 28px;
            }
            
            .form-side {
                padding: 30px;
            }
        }

        /* Animation for form */
        .form-side {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Input validation styles */
        .input-with-icon.valid input {
            border-color: var(--success-color);
        }

        .input-with-icon.invalid input {
            border-color: var(--error-color);
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Left Branding Side -->
    <div class="brand-side">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div class="logo-text">Ticketron</div>
        </div>
        
        <div class="brand-content">
            <h1>Welcome Back!</h1>
            <p>Sign in to your Ticketron account to access your tickets, manage bookings, and discover new events.</p>
            
            <ul class="features">
                <li><i class="fas fa-envelope"></i> Login with email address</li>
                <li><i class="fas fa-phone"></i> Login with phone number</li>
                <li><i class="fas fa-ticket"></i> Access all your tickets</li>
                <li><i class="fas fa-calendar-alt"></i> View upcoming events</li>
                <li><i class="fas fa-history"></i> Check booking history</li>
                <li><i class="fas fa-shield-alt"></i> Secure login system</li>
            </ul>
        </div>
    </div>

    <!-- Right Login Form Side -->
    <div class="form-side">
        <div class="form-header">
            <h2>Sign In</h2>
            <p>Use your email or phone number to login</p>
        </div>

        <?php echo $message; ?>

        <!-- Login Options Toggle -->
        <div class="login-options">
            <button type="button" class="login-option-btn active" id="emailLoginBtn">
                <i class="fas fa-envelope"></i> Email
            </button>
            <button type="button" class="login-option-btn" id="phoneLoginBtn">
                <i class="fas fa-phone"></i> Phone
            </button>
        </div>

        <form method="post" id="loginForm">
            <div class="form-group" id="inputGroup">
                <label for="login_input" id="loginLabel">Email Address</label>
                <div class="input-with-icon" id="loginInputContainer">
                    <i class="fas fa-envelope" id="loginIcon"></i>
                    <input type="text" id="login_input" name="login_input" 
                           placeholder="name@example.com" required 
                           value="<?php echo htmlspecialchars($remembered_input); ?>"
                           data-type="email">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="Enter your password" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember" 
                           <?php echo isset($_COOKIE['remember_login']) ? 'checked' : ''; ?>>
                    Remember me
                </label>
            </div>

            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> SIGN IN
            </button>

            <!-- Login Help Section -->
            <div class="login-help">
                <h4><i class="fas fa-info-circle"></i> Login Instructions:</h4>
                <p><strong>For Email Login:</strong> Enter your registered email address</p>
                <p><strong>For Phone Login:</strong> Enter your registered phone number</p>
                <p><strong>Note:</strong> System will check against users database table</p>
                <ul>
                    <li>Check CAPS LOCK is off</li>
                    <li>Try the exact password from registration</li>
                    <li>Make sure you're using the correct email/phone</li>
                </ul>
            </div>
        </form>

        <div class="form-footer">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>

        <div class="admin-switch">
            <a href="admin_login.php"><i class="fas fa-user-shield"></i> Switch to Admin Login</a>
        </div>
    </div>
</div>

<script>
    // Toggle between email and phone login
    const emailLoginBtn = document.getElementById('emailLoginBtn');
    const phoneLoginBtn = document.getElementById('phoneLoginBtn');
    const loginLabel = document.getElementById('loginLabel');
    const loginIcon = document.getElementById('loginIcon');
    const loginInput = document.getElementById('login_input');
    const loginInputContainer = document.getElementById('loginInputContainer');

    emailLoginBtn.addEventListener('click', function() {
        emailLoginBtn.classList.add('active');
        phoneLoginBtn.classList.remove('active');
        loginLabel.textContent = 'Email Address';
        loginIcon.className = 'fas fa-envelope';
        loginInput.placeholder = 'name@example.com';
        loginInput.dataset.type = 'email';
        loginInput.focus();
        
        // Update input validation
        validateLoginInput();
    });

    phoneLoginBtn.addEventListener('click', function() {
        phoneLoginBtn.classList.add('active');
        emailLoginBtn.classList.remove('active');
        loginLabel.textContent = 'Phone Number';
        loginIcon.className = 'fas fa-phone';
        loginInput.placeholder = '01712345678 or +8801712345678';
        loginInput.dataset.type = 'phone';
        loginInput.focus();
        
        // Update input validation
        validateLoginInput();
    });

    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Input validation
    function validateLoginInput() {
        const value = loginInput.value.trim();
        const type = loginInput.dataset.type;
        
        // Remove previous validation classes
        loginInputContainer.classList.remove('valid', 'invalid');
        
        if (value === '') return;
        
        if (type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(value)) {
                loginInputContainer.classList.add('valid');
            } else {
                loginInputContainer.classList.add('invalid');
            }
        } else if (type === 'phone') {
            // Basic phone validation (allows numbers, spaces, dashes, plus, parentheses)
            const phoneRegex = /^[0-9+\-\s()]+$/;
            const digitCount = value.replace(/[^0-9]/g, '').length;
            
            if (phoneRegex.test(value) && digitCount >= 10) {
                loginInputContainer.classList.add('valid');
            } else {
                loginInputContainer.classList.add('invalid');
            }
        }
    }

    // Validate on input change
    loginInput.addEventListener('input', validateLoginInput);

    // Auto-detect input type on page load
    document.addEventListener('DOMContentLoaded', function() {
        const inputValue = loginInput.value.trim();
        
        if (inputValue) {
            // Check if it looks like an email
            if (inputValue.includes('@') && inputValue.includes('.')) {
                emailLoginBtn.click();
            } else if (/[0-9]/.test(inputValue) && inputValue.length >= 10) {
                phoneLoginBtn.click();
            }
        }
        
        // Validate initial input
        validateLoginInput();
        
        // Auto-focus on login input
        loginInput.focus();
        
        // Set cookie for remembered input if "Remember me" was checked
        const rememberCheckbox = document.getElementById('remember');
        if (rememberCheckbox.checked && loginInput.value) {
            document.cookie = "remembered_input=" + encodeURIComponent(loginInput.value) + "; path=/; max-age=" + (30 * 24 * 60 * 60);
        }
    });

    // Form submission validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const loginValue = loginInput.value.trim();
        const passwordValue = document.getElementById('password').value;
        const type = loginInput.dataset.type;
        
        // Basic validation
        if (!loginValue) {
            e.preventDefault();
            alert('Please enter your ' + (type === 'email' ? 'email address' : 'phone number'));
            loginInput.focus();
            return;
        }
        
        if (!passwordValue) {
            e.preventDefault();
            alert('Please enter your password');
            document.getElementById('password').focus();
            return;
        }
        
        // Type-specific validation
        if (type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(loginValue)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                loginInput.focus();
                return;
            }
        } else if (type === 'phone') {
            const digitCount = loginValue.replace(/[^0-9]/g, '').length;
            if (digitCount < 10) {
                e.preventDefault();
                alert('Please enter a valid phone number with at least 10 digits');
                loginInput.focus();
                return;
            }
        }
        
        // Save to cookie if remember me is checked
        const rememberCheckbox = document.getElementById('remember');
        if (rememberCheckbox.checked) {
            document.cookie = "remembered_input=" + encodeURIComponent(loginValue) + "; path=/; max-age=" + (30 * 24 * 60 * 60);
        } else {
            // Clear cookie if not checked
            document.cookie = "remembered_input=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC";
        }
    });

    // Input focus effects
    const inputs = document.querySelectorAll('.input-with-icon input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
</script>

</body>
</html>