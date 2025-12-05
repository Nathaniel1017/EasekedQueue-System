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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check login attempts
    $attempt_check = checkLoginAttempts($username);
    if ($attempt_check['locked']) {
        $errors[] = $attempt_check['message'];
    } else {
        // Validation
        if (empty($username)) {
            $errors[] = 'Username (email or phone number) is required.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        }

        // Authenticate user
        if (empty($errors)) {
            $conn = getDBConnection();
            if ($conn->connect_error) {
                $errors[] = 'Database connection failed. Please try again later.';
            } else {
                $stmt = $conn->prepare("SELECT id, password_hash, role, is_active FROM users WHERE email = ? OR phone_number = ?");
                if ($stmt === false) {
                    $errors[] = 'Database query preparation failed: ' . $conn->error . '. Please try again later.';
                } else {
                    $stmt->bind_param("ss", $username, $username);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 1) {
                        $user = $result->fetch_assoc();

                        if (!$user['is_active']) {
                            $errors[] = 'Your account is inactive. Please contact support.';
                            recordLoginAttempt($username, false);
                        } elseif (password_verify($password, $user['password_hash'])) {
                            // Login successful - clear attempts and create session
                            recordLoginAttempt($username, true);
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

                            // Redirect based on role
                            if ($user['role'] === 'super_admin' || $user['role'] === 'admin') {
                                header("Location: admin_dashboard.php");
                            } else {
                                header("Location: dashboard.php");
                            }
                            exit();
                        } else {
                            $errors[] = 'Invalid username or password.';
                            recordLoginAttempt($username, false);
                        }
                    } else {
                        $errors[] = 'Invalid username or password.';
                        recordLoginAttempt($username, false);
                    }

                    $stmt->close();
                }
                $conn->close();
            }
        } else {
            recordLoginAttempt($username, false);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Easeked Queue</title>
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

        .login-container {
            width: 100%;
            max-width: 450px;
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

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header-icon {
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

        .login-header h2 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
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

        .btn-login {
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

        .btn-login:active {
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

        .alert ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .alert li {
            margin-bottom: 0.25rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .login-footer p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-footer a:active {
            color: var(--secondary-color);
        }

        /* Accessibility improvements */
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
            }

            .login-header h2 {
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
    <div class="login-container">
        <div class="login-header">
            <div class="login-header-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h2>Welcome Back</h2>
            <p>Sign in to manage your appointments</p>
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

        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">Email or Phone Number</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Enter your email or phone number" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="login-footer">
            <p>Don't have an account? <a href="register.php">Create one now</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>