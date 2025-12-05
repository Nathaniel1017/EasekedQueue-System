<?php
// Start session first, before anything else
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if already logged in BEFORE requiring config
if (isset($_SESSION['user_id'])) {
    // Need to get user role to redirect properly
    require_once 'config.php';
    $user = getCurrentUser();
    if ($user) {
        // Prevent caching before redirect
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        // Redirect based on role
        if ($user['role'] === 'super_admin' || $user['role'] === 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }
}

// Now load config normally
require_once 'config.php';

// Prevent browser caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$conn = getDBConnection();
if ($conn && $conn !== false) {
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows === 0) {
        // Database not set up, redirect to setup
        header("Location: setup_database.php");
        exit();
    }
    $conn->close();
}
?>
<?php
require_once 'config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    // Enhanced password requirements
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } else if (strlen($password) < 9) {
        $errors[] = 'Password must be at least 9 characters long.';
    } else if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } else if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } else if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    } else if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&*).';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($phone_number)) {
        $errors[] = 'Phone number is required.';
    }

    // Check if email already exists
    if (empty($errors)) {
        $conn = getDBConnection();
        if ($conn->connect_error) {
            $errors[] = 'Database connection failed. Please try again later.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($stmt === false) {
                $errors[] = 'Database query preparation failed: ' . $conn->error . '. Please try again later.';
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $errors[] = 'Email already exists.';
                }
                $stmt->close();
            }
            $conn->close();
        }
    }

    // Register user
    if (empty($errors)) {
        // Use stronger bcrypt cost (default is 10, we use 12 for better security)
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $conn = getDBConnection();
        if ($conn->connect_error) {
            $errors[] = 'Database connection failed. Please try again later.';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, role) VALUES (?, ?, ?, ?, 'citizen')");
            if ($stmt === false) {
                $errors[] = 'Database query preparation failed: ' . $conn->error . '. Please try again later.';
            } else {
                $stmt->bind_param("ssss", $full_name, $email, $password_hash, $phone_number);

                if ($stmt->execute()) {
                    $success = 'Registration successful! You can now <a href="login.php">login</a>.';
                } else {
                    $errors[] = 'Registration failed. Please try again.';
                }

                $stmt->close();
            }
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Easeked Queue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0066cc;
            --secondary-color: #00b4d8;
            --success-color: #06d6a0;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 12px rgba(0, 102, 204, 0.08);
            --border-color: #e2e8f0;
            --text-primary: #1a202c;
            --text-secondary: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #e8f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(0, 102, 204, 0.12);
            padding: 3rem;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            background-color: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            box-shadow: var(--card-shadow);
        }

        .register-header h2 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.3s ease;
            background-color: #ffffff;
            color: var(--text-primary);
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .btn-register {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 44px;
            margin-top: 1rem;
        }

        .btn-register:active {
            box-shadow: 0 8px 24px rgba(0, 102, 204, 0.12);
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid;
        }

        .alert-danger {
            background-color: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .alert-success {
            background-color: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }

        .alert ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .alert li {
            margin-bottom: 0.25rem;
        }

        .alert a {
            color: inherit;
            text-decoration: underline;
        }

        .register-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .register-footer p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .register-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-footer a:active {
            color: var(--secondary-color);
        }

        /* Accessibility improvements */
        @media (max-width: 480px) {
            .register-container {
                padding: 2rem 1.5rem;
            }

            .register-header h2 {
                font-size: 1.5rem;
            }

            .form-label {
                font-size: 0.9rem;
            }

            .form-control {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="register-header-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Create Account</h2>
            <p>Join us to book your appointments easily</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone_number" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone_number" name="phone_number" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                    Must be at least 9 characters with: uppercase, lowercase, number, and special character
                </p>
                <input type="password" class="form-control" id="password" name="password" placeholder="Create a strong password" required oninput="checkPasswordStrength()">
                <div id="password-strength-meter" style="margin-top: 0.5rem; height: 6px; background-color: var(--border-color); border-radius: 3px; overflow: hidden; display: none;">
                    <div id="password-strength-bar" style="height: 100%; width: 0%; background-color: #ef476f; transition: all 0.3s ease;"></div>
                </div>
                <p id="password-requirements" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">
                    <span id="req-length" style="display: block;">❌ At least 9 characters</span>
                    <span id="req-upper" style="display: block;">❌ Uppercase letter (A-Z)</span>
                    <span id="req-lower" style="display: block;">❌ Lowercase letter (a-z)</span>
                    <span id="req-number" style="display: block;">❌ Number (0-9)</span>
                    <span id="req-special" style="display: block;">❌ Special character (!@#$%^&*)</span>
                </p>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div style="position: relative;">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required oninput="checkPasswordMatch()">
                    <div id="password-match-indicator" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 1.2rem; display: none;">
                        <span id="match-icon">❌</span>
                    </div>
                </div>
                <p id="password-match-message" style="font-size: 0.85rem; color: #ef476f; margin-top: 0.5rem; display: none;">
                    Passwords do not match
                </p>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="register-footer">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const meter = document.getElementById('password-strength-meter');
            const bar = document.getElementById('password-strength-bar');
            
            // Check requirements
            const hasLength = password.length >= 9;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            
            // Update requirement indicators
            updateRequirement('req-length', hasLength);
            updateRequirement('req-upper', hasUpper);
            updateRequirement('req-lower', hasLower);
            updateRequirement('req-number', hasNumber);
            updateRequirement('req-special', hasSpecial);
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength += 20;
            if (hasUpper) strength += 20;
            if (hasLower) strength += 20;
            if (hasNumber) strength += 20;
            if (hasSpecial) strength += 20;
            
            // Show meter and update bar
            if (password.length > 0) {
                meter.style.display = 'block';
                bar.style.width = strength + '%';
                
                if (strength < 40) {
                    bar.style.backgroundColor = '#ef476f'; // Red
                } else if (strength < 80) {
                    bar.style.backgroundColor = '#ffc107'; // Yellow
                } else {
                    bar.style.backgroundColor = '#06d6a0'; // Green
                }
            } else {
                meter.style.display = 'none';
            }
        }
        
        function updateRequirement(elementId, met) {
            const element = document.getElementById(elementId);
            if (met) {
                element.style.color = '#06d6a0';
                element.textContent = element.textContent.replace('❌', '✅');
            } else {
                element.style.color = '#ef476f';
                element.textContent = element.textContent.replace('✅', '❌');
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchIndicator = document.getElementById('password-match-indicator');
            const matchIcon = document.getElementById('match-icon');
            const matchMessage = document.getElementById('password-match-message');
            
            if (confirmPassword.length === 0) {
                // Empty field - hide indicator
                matchIndicator.style.display = 'none';
                matchMessage.style.display = 'none';
                document.getElementById('confirm_password').style.borderColor = '';
                return;
            }
            
            if (password === confirmPassword) {
                // Passwords match
                matchIndicator.style.display = 'block';
                matchIcon.textContent = '✅';
                matchIcon.style.color = '#06d6a0';
                matchMessage.style.display = 'none';
                document.getElementById('confirm_password').style.borderColor = '#06d6a0';
            } else {
                // Passwords don't match - hide indicator, just show error message
                matchIndicator.style.display = 'none';
                matchMessage.style.display = 'block';
                matchMessage.style.color = '#ef476f';
                document.getElementById('confirm_password').style.borderColor = '#ef476f';
            }
        }

        // Trigger password match check when main password changes
        document.getElementById('password').addEventListener('input', checkPasswordMatch);
        
        // Prevent form submission if passwords don't match
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please confirm your password.');
                return false;
            }
            
            
            if (password.length < 9) {
                e.preventDefault();
                alert('Password must be at least 9 characters long.');
                return false;
            }
            
            if (!/[A-Z]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter.');
                return false;
            }
            
            if (!/[a-z]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one lowercase letter.');
                return false;
            }
            
            if (!/[0-9]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one number.');
                return false;
            }
            
            if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one special character (!@#$%^&*).');
                return false;
            }
        });
    </script>
</body>
</html>