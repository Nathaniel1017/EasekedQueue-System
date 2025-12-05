<?php
// Check if database is set up
require_once 'config.php';

$conn = getDBConnection();
if ($conn && $conn !== false) {
    $result = $conn->query("SHOW TABLES LIKE 'departments'");
    if ($result->num_rows === 0) {
        // Database not set up, show setup message
        $show_setup_message = true;
    } else {
        $show_setup_message = false;
    }
    $conn->close();
} else {
    $show_setup_message = true;
}

$page_title = 'Home';
include 'header.php';
?>

<style>
    .hero-section {
        text-align: center;
        margin-bottom: 4rem;
        padding: 3rem 0;
    }

    .feature-card {
        text-align: center;
        padding: 2rem;
        border: none;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        background: white;
    }

    .feature-card:active {
        box-shadow: var(--card-shadow-hover);
        transform: translateY(-4px);
    }

    .feature-icon {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        display: block;
    }

    .feature-card h5 {
        color: var(--text-primary);
        font-weight: 600;
        margin-bottom: 0.75rem;
    }

    .feature-card p {
        color: var(--text-secondary);
        font-size: 0.95rem;
        margin: 0;
        line-height: 1.6;
    }

    .btn-group-hero {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 2rem;
    }

    .btn-group-hero .btn {
        min-width: 180px;
    }

    .departments-section {
        margin-top: 4rem;
    }

    .department-card {
        border: none;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        background: white;
        text-align: center;
        padding: 2rem;
        border-radius: 12px;
    }

    .department-card:active {
        box-shadow: var(--card-shadow-hover);
        transform: translateY(-4px);
    }

    .department-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        display: block;
    }

    .department-card h6 {
        color: var(--text-primary);
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .department-card p {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin: 0;
    }
</style>

<div class="hero-section">
    <div class="container">
        <h1 class="display-4">Welcome to EasekedQueue</h1>
        <p class="lead">Simplified Queueing, Own Your Time</p>
        <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 2rem;">
            Book appointments with municipal services easily and reduce your wait times.
        </p>

        <?php if ($show_setup_message): ?>
            <div class="alert alert-warning">
                <div style="font-size: 1.25rem; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i> System Setup Required
                </div>
                <p style="margin-bottom: 1.5rem; color: inherit;">The database needs to be set up before you can use the system.</p>
                <a href="setup_database.php" class="btn btn-warning btn-custom">
                    <i class="fas fa-cogs"></i> Setup Database
                </a>
            </div>
        <?php elseif (!isLoggedIn()): ?>
            <div class="btn-group-hero">
                <a href="register.php" class="btn btn-primary btn-custom">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
                <a href="login.php" class="btn btn-outline-primary btn-custom">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
        <?php elseif (isLoggedIn() && getCurrentUser()['role'] === 'citizen'): ?>
            <a href="book_appointment.php" class="btn btn-primary btn-custom">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="row mb-5">
        <div class="col-md-4 mb-4">
            <div class="feature-card card">
                <div class="card-body">
                    <i class="fas fa-hourglass-end feature-icon"></i>
                    <h5>Save Time</h5>
                    <p>No more long queues. Book your appointment online and arrive at your scheduled time.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="feature-card card">
                <div class="card-body">
                    <i class="fas fa-hand-holding-heart feature-icon"></i>
                    <h5>User-Friendly</h5>
                    <p>Simple, step-by-step booking process designed for everyone, including seniors.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="feature-card card">
                <div class="card-body">
                    <i class="fas fa-shield-alt feature-icon"></i>
                    <h5>Secure & Private</h5>
                    <p>Your personal information is protected with industry-standard security measures.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$show_setup_message): ?>
    <div class="departments-section">
        <div style="text-align: center; margin-bottom: 3rem;">
            <h2 style="color: var(--primary-color); font-weight: 700; margin-bottom: 0.5rem;">Available Services</h2>
            <p style="color: var(--text-secondary); font-size: 1.05rem;">Select the service you need to book an appointment</p>
        </div>
        <div class="row">
            <?php
            $conn = getDBConnection();
            if ($conn) {
                $result = $conn->query("SELECT * FROM departments ORDER BY name");
                if ($result) {
                    $dept_count = 0;
                    $seen_names = [];
                    while ($department = $result->fetch_assoc()):
                        $dept_count++;
                        // Filter out duplicates by name
                        if (!in_array($department['name'], $seen_names)):
                            $seen_names[] = $department['name'];
            ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="department-card card">
                                    <i class="<?php echo htmlspecialchars($department['icon_class']); ?> department-icon"></i>
                                    <h6><?php echo htmlspecialchars($department['name']); ?></h6>
                                    <p><?php echo htmlspecialchars($department['description']); ?></p>
                                </div>
                            </div>
            <?php
                        endif;
                    endwhile;
                }
                $conn->close();
            } else {
                echo '<div class="col-12"><p class="text-muted" style="text-align: center;">Unable to load service information at this time.</p></div>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>