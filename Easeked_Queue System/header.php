<?php
require_once 'config.php';
$user = getCurrentUser();
error_log("header.php processing started");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="cache-control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="expires" content="0" />
    <title><?php echo $page_title ?? 'Easeked Queue'; ?> - Simplified Queueing, Own Your Time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #0066cc;
            --secondary-color: #00b4d8;
            --success-color: #06d6a0;
            --warning-color: #ffc107;
            --danger-color: #ef476f;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 12px rgba(0, 102, 204, 0.08);
            --card-shadow-hover: 0 8px 24px rgba(0, 102, 204, 0.12);
            --border-color: #e2e8f0;
            --text-primary: #1a202c;
            --text-secondary: #64748b;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--light-bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
            box-shadow: var(--card-shadow);
            border: none;
            padding: 0.75rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
            color: white !important;
            margin-right: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0;
        }

        .navbar-brand-logo {
            width: 44px;
            height: 44px;
            background-color: white;
            border-radius: 50%;
            padding: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .navbar-brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .navbar-brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .navbar-brand-main {
            font-weight: 700;
            font-size: 1rem;
        }

        .navbar-brand-sub {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--success-color);
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            font-size: 0.95rem;
            margin-left: 1rem;
            transition: all 0.3s ease;
            position: relative;
            padding: 0.4rem 0.5rem !important;
            border-radius: 6px;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0px;
            left: 50%;
            width: 0;
            height: 3px;
            background-color: white;
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .navbar-nav .nav-link:hover::after {
            width: 70%;
        }

        .navbar-nav .nav-link.active,
        .navbar-nav .nav-link:focus {
            color: white !important;
        }

        .navbar-nav .nav-link.active::after {
            width: 70%;
        }

        .navbar-text {
            color: rgba(255, 255, 255, 0.85) !important;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            height: 100%;
            white-space: nowrap;
            padding: 0.4rem 0.5rem;
            transition: all 0.3s ease;
            cursor: default;
            margin: 0;
        }

        .navbar-text:hover {
            color: white !important;
        }

        .navbar-nav .nav-item:has(.navbar-text) {
            display: flex;
            align-items: center;
            margin-right: 0.75rem;
        }

        .navbar-nav .nav-link .fa-bell {
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link .fa-inbox {
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover .fa-bell,
        .navbar-nav .nav-link:hover .fa-inbox {
            transform: scale(1.1);
        }

        .navbar-nav .dropdown > .nav-link {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .notification-badge {
            display: block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--danger-color);
            position: absolute;
            top: -3px;
            right: -3px;
            padding: 0;
            line-height: 1;
            letter-spacing: 0;
            font-size: 0;
        }

        .notification-badge:empty::before {
            content: '';
        }

        .dropdown-menu {
            border: none;
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            padding: 0.5rem 0;
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            color: var(--text-primary);
            transition: all 0.2s ease;
            border: none;
        }

        .dropdown-item:active,
        .dropdown-item:focus {
            background-color: var(--light-bg);
            color: var(--primary-color);
        }

        .container {
            max-width: 1200px;
            flex: 1;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            background-color: white;
            overflow: hidden;
        }

        .card:active {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .form-control,
        .form-select {
            font-size: 16px;
            padding: 0.875rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            background-color: white;
            color: var(--text-primary);
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%230066cc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 1rem !important;
            padding-right: 2.5rem !important;
        }

        .form-select-sm {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%230066cc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.5rem center !important;
            background-size: 0.875rem !important;
            padding-right: 2rem !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
            outline: none;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 44px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:active {
            background-color: #0052a3;
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:active {
            background-color: #0099b8;
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:active {
            background-color: #05b881;
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:active {
            background-color: #d63a57;
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background-color: white;
        }

        .btn-outline-primary:active {
            background-color: var(--primary-color);
            color: white;
            box-shadow: var(--card-shadow-hover);
        }

        .btn-custom {
            min-height: 50px;
            font-size: 1rem;
            padding: 0.875rem 2rem;
        }

        .alert {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-warning {
            background-color: #fffbeb;
            border-color: #fcd34d;
            color: #92400e;
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

        .alert-info {
            background-color: #f0f9ff;
            border-color: #bae6fd;
            color: #0c4a6e;
        }

        .text-center {
            text-align: center;
        }

        .text-secondary {
            color: var(--text-secondary);
        }

        .display-4 {
            font-weight: 700;
            font-size: 3rem;
            color: var(--primary-color);
            letter-spacing: -1px;
        }

        .lead {
            font-size: 1.25rem;
            color: var(--secondary-color);
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .mb-3 { margin-bottom: 1rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .mb-5 { margin-bottom: 3rem; }
        .mt-3 { margin-top: 1rem; }
        .mt-5 { margin-top: 3rem; }

        /* Badge styling */
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 6px;
        }

        /* Responsive tables */
        .table {
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .table thead th {
            background-color: var(--light-bg);
            border-bottom: 2px solid var(--border-color);
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:active {
            background-color: var(--light-bg);
        }

        /* Badge colors */
        .bg-primary { background-color: var(--primary-color) !important; }
        .bg-success { background-color: var(--success-color) !important; }
        .bg-danger { background-color: var(--danger-color) !important; }
        .bg-warning { background-color: var(--warning-color) !important; }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .display-4 {
                font-size: 2rem;
            }

            .lead {
                font-size: 1.1rem;
            }

            .btn-custom {
                font-size: 0.95rem;
                padding: 0.75rem 1.5rem;
                min-height: 44px;
            }

            .card-body {
                padding: 1.5rem;
            }

            .navbar-nav .nav-link {
                margin-left: 0;
                padding: 0.3rem 0.25rem !important;
                font-size: 0.9rem;
            }

            .navbar-text {
                margin-right: 0.5rem !important;
                font-size: 0.85rem;
                padding: 0.3rem 0.25rem !important;
            }

            .navbar-nav .nav-item:has(.navbar-text) {
                margin-right: 0.25rem;
            }

            .navbar-brand {
                margin-right: 1rem;
            }

            .navbar-brand-logo {
                width: 40px;
                height: 40px;
                padding: 2px;
            }

            .navbar-brand-main {
                font-size: 0.95rem;
            }

            .navbar-brand-sub {
                font-size: 0.65rem;
            }

            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="navbar-brand-logo">
                    <img src="municipalLogo.png" alt="Municipality of Malinao Logo">
                </div>
                <div class="navbar-brand-text">
                    <span class="navbar-brand-main">EasekedQueue</span>
                    <span class="navbar-brand-sub">Queue Management</span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <?php if ($user && $user['role'] === 'citizen'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="dashboard.php">Appointments</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="my_account.php">My Account</a>
                            </li>
                        <?php elseif ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="my_account.php">My Account</a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="about.php">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.php">Contact</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <!-- Notification Bell -->
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge" id="notificationBadge" style="display: none;"></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" id="notificationList">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <li><a class="dropdown-item text-center" href="notifications.php">View All Notifications</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <span class="navbar-text">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Create Account</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Appointment Details Modal -->
    <div class="modal fade" id="appointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Scripts -->
    <script>
    function updateNotificationCount() {
        fetch('get_notification_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('notificationBadge');
                    if (data.unread_count > 0) {
                        badge.textContent = '';
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error fetching notification count:', error));
    }

    function loadRecentNotifications() {
        fetch('get_recent_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const list = document.getElementById('notificationList');
                    // Clear existing notifications, keep header and view all link
                    const header = list.querySelector('.dropdown-header');
                    const viewAll = list.querySelector('a[href="notifications.php"]');

                    // Remove old notifications
                    Array.from(list.children).forEach(child => {
                        if (!child.contains(header) && !child.contains(viewAll)) {
                            list.removeChild(child);
                        }
                    });

                    // Add new notifications before view all link
                    data.notifications.forEach(notification => {
                        const li = document.createElement('li');
                        const button = document.createElement('button');
                        button.className = 'dropdown-item' + (notification.is_read ? '' : ' fw-bold');
                        button.type = 'button';
                        button.onclick = () => viewAppointment(notification.appointment_id);
                        button.innerHTML = '<small>' + notification.message.substring(0, 50) + (notification.message.length > 50 ? '...' : '') + '</small>';
                        li.appendChild(button);
                        list.insertBefore(li, viewAll.parentElement);
                    });

                    if (data.notifications.length === 0) {
                        const li = document.createElement('li');
                        li.innerHTML = '<span class="dropdown-item-text text-muted">No new notifications</span>';
                        list.insertBefore(li, viewAll.parentElement);
                    }
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    // Update count on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateNotificationCount();
        loadRecentNotifications();

        // Poll every 10 seconds
        setInterval(function() {
            updateNotificationCount();
        }, 10000);
    });

    // Load notifications when dropdown is opened
    document.getElementById('notificationDropdown').addEventListener('click', function() {
        loadRecentNotifications();
    });

    function viewAppointment(appointmentId) {
        fetch('get_appointment_details.php?id=' + appointmentId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('appointmentDetails').innerHTML = data;
                new bootstrap.Modal(document.getElementById('appointmentModal')).show();
            })
            .catch(error => console.error('Error loading appointment details:', error));
    }

    function updatePriority(appointmentId, priority) {
        if (confirm(`Are you sure you want to set this appointment as ${priority}?`)) {
            fetch('update_appointment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'appointment_id=' + appointmentId + '&priority_status=' + priority
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the modal content
                    viewAppointment(appointmentId);
                } else {
                    alert('Failed to update priority: ' + data.message);
                }
            })
            .catch(error => console.error('Error updating priority:', error));
        }
    }
    </script>

    <div class="container mt-4">