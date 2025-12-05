<?php
// Set timezone to Asia/Bangkok
date_default_timezone_set('Asia/Bangkok');

// Database configuration
define('DB_HOST', 'localhost:3306');
define('DB_USER', 'root'); // Change this to your MySQL username
define('DB_PASS', ''); // Change this to your MySQL password - leave empty if no password
define('DB_NAME', 'easeked_queue');

// Create database connection
function getDBConnection() {
    error_log("Attempting to connect to: " . DB_HOST . " with user: " . DB_USER);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return false;
    }

    // Set MySQL timezone to match PHP timezone (Asia/Bangkok)
    $conn->query("SET time_zone = '+07:00'");

    return $conn;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        error_log("getCurrentUser: User not logged in");
        return null;
    }

    $conn = getDBConnection();
    if (!$conn) {
        error_log("getCurrentUser: Database connection failed");
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("getCurrentUser: Statement preparation failed: " . $conn->error);
        $conn->close();
        return null;
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    if (!$stmt->execute()) {
        error_log("getCurrentUser: Statement execution failed: " . $stmt->error);
        $stmt->close();
        $conn->close();
        return null;
    }

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    error_log("getCurrentUser: Found user: " . ($user ? json_encode($user) : 'null'));

    $stmt->close();
    $conn->close();

    return $user;
}

// Helper function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Helper function to check user role
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Helper function to require specific role
function requireRole($role) {
    if (!hasRole($role)) {
        header("Location: index.php");
        exit();
    }
}

// Helper function to require admin role (admin or super_admin)
function requireAdmin() {
    $user = getCurrentUser();
    error_log("requireAdmin check - User: " . ($user ? json_encode($user) : 'null'));
    if (!$user || !in_array(trim($user['role']), ['admin', 'super_admin'])) {
        error_log("requireAdmin failed - redirecting to index.php");
        header("Location: index.php");
        exit();
    }
    error_log("requireAdmin passed for user: " . $user['id'] . " with role: " . $user['role']);
}

// Helper function to create notifications
function createNotification($user_id, $for_admin_id, $message, $appointment_id) {
    $conn = getDBConnection();
    if (!$conn) {
        error_log("createNotification: Database connection failed");
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, for_admin_id, message, appointment_id) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        error_log("createNotification: Statement preparation failed: " . $conn->error);
        $conn->close();
        return false;
    }

    $stmt->bind_param("iisi", $user_id, $for_admin_id, $message, $appointment_id);
    $result = $stmt->execute();

    if (!$result) {
        error_log("createNotification: Statement execution failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    return $result;
}

// Helper function to create notifications for new appointments (for admins/super_admin)
function notifyNewAppointment($appointment_id, $department_id) {
    $conn = getDBConnection();
    if (!$conn) {
        error_log("notifyNewAppointment: Database connection failed");
        return;
    }

    // Get department name
    $dept_stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
    $dept_stmt->bind_param("i", $department_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    $department = $dept_result->fetch_assoc();
    $dept_stmt->close();

    if (!$department) {
        $conn->close();
        return;
    }

    $message = "New appointment request in " . $department['name'];

    // Notify admins of this department
    $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND department_id = ?");
    $admin_stmt->bind_param("i", $department_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();

    while ($admin = $admin_result->fetch_assoc()) {
        createNotification($admin['id'], null, $message, $appointment_id);
    }
    $admin_stmt->close();

    // Notify super_admins
    $super_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'super_admin'");
    $super_stmt->execute();
    $super_result = $super_stmt->get_result();

    while ($super = $super_result->fetch_assoc()) {
        createNotification($super['id'], null, $message, $appointment_id);
    }
    $super_stmt->close();

    $conn->close();
}

// Helper function to create notifications for status changes (for users)
function notifyStatusChange($appointment_id, $status, $admin_id, $decline_reason = null) {
    $conn = getDBConnection();
    if (!$conn) {
        error_log("notifyStatusChange: Database connection failed");
        return;
    }

    // Get appointment details
    $stmt = $conn->prepare("SELECT user_id, department_id FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        $conn->close();
        return;
    }

    $message = "Your appointment #" . $appointment_id . " has been " . $status;
    if ($status === 'declined' && $decline_reason) {
        $message .= ": " . $decline_reason;
    }

    createNotification($appointment['user_id'], $admin_id, $message, $appointment_id);

    $conn->close();
}

// Security Functions - Rate Limiting and Login Protection
function checkLoginAttempts($username) {
    $max_attempts = 5;
    $lockout_time = 15 * 60; // 15 minutes in seconds
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Clean up old attempts (older than lockout time)
    foreach ($_SESSION['login_attempts'] as $key => $attempt) {
        if (time() - $attempt['time'] > $lockout_time) {
            unset($_SESSION['login_attempts'][$key]);
        }
    }
    
    // Count attempts for this username
    $attempts = 0;
    $last_attempt = 0;
    foreach ($_SESSION['login_attempts'] as $attempt) {
        if ($attempt['username'] === $username) {
            $attempts++;
            $last_attempt = $attempt['time'];
        }
    }
    
    if ($attempts >= $max_attempts) {
        $remaining_time = ceil(($lockout_time - (time() - $last_attempt)) / 60);
        return [
            'locked' => true,
            'message' => "Too many login attempts. Please try again in $remaining_time minutes."
        ];
    }
    
    return ['locked' => false];
}

function recordLoginAttempt($username, $success = false) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if (!$success) {
        $_SESSION['login_attempts'][] = [
            'username' => $username,
            'time' => time()
        ];
    } else {
        // Clear attempts on successful login
        $_SESSION['login_attempts'] = array_filter(
            $_SESSION['login_attempts'],
            fn($attempt) => $attempt['username'] !== $username
        );
    }
}

// Add HTTP Security Headers
function addSecurityHeaders() {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com");
}

// Call security headers on every page load
addSecurityHeaders();
?>