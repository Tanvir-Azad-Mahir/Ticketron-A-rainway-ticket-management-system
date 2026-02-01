<?php
include '../db.php'; 
$message = "";

// Initialize form values array
$form_values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'nid' => ''
];

// 1. ONLY run if the POST 'register' button was actually clicked
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    
    // 2. Get the form data
    $nid = $_POST['nid'];
    $email = $_POST['email'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $pass = $_POST['password'];

    // 3. IMPORTANT: Only query DB if the user actually typed an NID
    if (!empty($nid)) {
        // Check if NID already exists - using prepared statement
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE nid = ? LIMIT 1");
        $stmt->bind_param("s", $nid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // NID exists - set message and continue to show form
            $message = "<div class='alert error'>
                <div class='alert-icon'>⚠️</div>
                <div class='alert-content'>
                    <strong>Registration Failed</strong><br>
                    A user with this NID already exists.
                </div>
            </div>";
            
            // Keep form values for error display
            $form_values = [
                'name' => htmlspecialchars($name),
                'email' => htmlspecialchars($email),
                'phone' => htmlspecialchars($phone),
                'nid' => htmlspecialchars($nid)
            ];
        } else {
            // GO: NID is unique, proceed with registration
            
            // Generate Custom ID like U01, U02, U03 automatically
            $res = $conn->query("SELECT MAX(user_id) as max_user_id FROM users");
            
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $max_user_id = $row['max_user_id'];
                
                if ($max_user_id) {
                    // Extract the number part (remove 'U' prefix)
                    $last_number = intval(substr($max_user_id, 1));
                    $new_number = $last_number + 1;
                } else {
                    // First user
                    $new_number = 1;
                }
            } else {
                // First user
                $new_number = 1;
            }
            
            $custom_user_id = "U" . sprintf('%02d', $new_number); // Auto-generated: U01, U02, etc.

            // Insert with prepared statement
            $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, phone, nid, pass) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $custom_user_id, $name, $email, $phone, $nid, $pass);

            if ($stmt->execute()) {
                // ✅ Redirect to login page with success message
                $stmt->close();
                header("Location: login.php?success=1&email=" . urlencode($email));
                exit(); // Stop script execution immediately
            } else {
                $message = "<div class='alert error'>
                    <div class='alert-icon'>⚠️</div>
                    <div class='alert-content'>
                        <strong>Registration Error</strong><br>
                        Could not create account. Please try again.
                    </div>
                </div>";
                $stmt->close();
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Ticketron</title>
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
            font-size: 32px;
            margin-right: 12px;
            background: white;
            color: var(--accent-color);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-content h1 {
            font-size: 36px;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .brand-content p {
            font-size: 16px;
            opacity: 0.9;
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
            margin-bottom: 15px;
            font-size: 14px;
        }

        .features i {
            margin-right: 10px;
            background: rgba(255, 255, 255, 0.2);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }



        /* Right Side - Registration Form */
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

        /* Password warning */
        .password-warning {
            font-size: 12px;
            color: #dc3545;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Auto-generated ID info */
        .auto-id-info {
            background: #e8f4fd;
            border: 1px solid #b6d4fe;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            text-align: center;
            font-size: 13px;
            color: #084298;
        }

        .auto-id-info i {
            margin-right: 8px;
            color: #0d6efd;
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
            <h1>Join Our Community</h1>
            <p>Create your Ticketron account to access exclusive events, easy booking, and personalized recommendations.</p>
            
            
            <ul class="features">
                <li><i class="fas fa-check"></i> Instant ticket booking</li>
                <li><i class="fas fa-check"></i> Secure payment processing</li>
                <li><i class="fas fa-check"></i> Event recommendations</li>
                <li><i class="fas fa-check"></i> Digital ticket management</li>
                <li><i class="fas fa-check"></i> 24/7 customer support</li>
                <li><i class="fas fa-check"></i> Auto-generated User ID</li>
            </ul>
        </div>
    </div>

    <!-- Right Registration Form Side -->
    <div class="form-side">
        <div class="form-header">
            <h2>Create Account</h2>
            <p>Fill in your details to get started</p>
        </div>

        <?php echo $message; ?>

    

        <form method="post" id="registrationForm">
            <div class="form-group">
                <label for="name">Full Name</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="name" name="name" placeholder="John Doe" required 
                           value="<?php echo $form_values['name']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-with-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="john@example.com" required
                           value="<?php echo $form_values['email']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <div class="input-with-icon">
                    <i class="fas fa-phone"></i>
                    <input type="text" id="phone" name="phone" placeholder="+880 17XX XXXXXX" required
                           value="<?php echo $form_values['phone']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="nid">NID Number</label>
                <div class="input-with-icon">
                    <i class="fas fa-id-card"></i>
                    <input type="text" id="nid" name="nid" placeholder="Enter your 10-digit NID" required
                           value="<?php echo $form_values['nid']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
            </div>

            <button type="submit" name="register" class="btn-submit">
                <i class="fas fa-user-plus"></i> CREATE ACCOUNT
            </button>
        </form>

        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Sign In</a></p>
            <p>By registering, you agree to our <a href="#">Terms & Conditions</a></p>
        </div>
    </div>
</div>

<script>
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

    // Form validation
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        const nid = document.getElementById('nid').value;
        const password = document.getElementById('password').value;
        const email = document.getElementById('email').value;
        const phone = document.getElementById('phone').value;
        
        // NID validation (10 digits)
        if (!/^\d{10}$/.test(nid)) {
            alert('Please enter a valid 10-digit NID number');
            e.preventDefault();
            return;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert('Please enter a valid email address');
            e.preventDefault();
            return;
        }
        
        // Phone validation (at least 10 digits)
        const phoneDigits = phone.replace(/\D/g, '');
        if (phoneDigits.length < 10) {
            alert('Please enter a valid phone number with at least 10 digits');
            e.preventDefault();
            return;
        }
        
        // Password validation (at least 4 characters)
        if (password.length < 4) {
            alert('Password must be at least 4 characters long');
            e.preventDefault();
            return;
        }
        
       
        
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return;
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