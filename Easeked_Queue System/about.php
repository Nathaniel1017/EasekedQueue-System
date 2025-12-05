<?php
$page_title = 'About Us';
include 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="text-center mb-5">
            <h1>About Easeked Queue</h1>
            <p class="lead">Simplifying municipal services for our community</p>
        </div>
    </div>
</div>

<div class="row mb-5">
    <div class="col-lg-6">
        <h3>Our Mission</h3>
        <p>Easeked Queue is designed to reduce wait times and improve access to municipal services. We believe that government services should be accessible to everyone, regardless of age, technical ability, or mobility.</p>
        
        <h3>Key Features</h3>
        <ul class="list-unstyled">
            <li><i class="fas fa-check text-success me-2"></i> Simple, step-by-step appointment booking</li>
            <li><i class="fas fa-check text-success me-2"></i> Mobile-first responsive design</li>
            <li><i class="fas fa-check text-success me-2"></i> High contrast accessibility compliance</li>
            <li><i class="fas fa-check text-success me-2"></i> Real-time appointment availability</li>
            <li><i class="fas fa-check text-success me-2"></i> Secure file upload capabilities</li>
            <li><i class="fas fa-check text-success me-2"></i> Comprehensive admin management tools</li>
        </ul>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">System Statistics</h4>
                <?php
                $conn = getDBConnection();
                if ($conn) {
                    $total_users_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'citizen'");
                    $total_users = $total_users_result ? $total_users_result->fetch_assoc()['count'] : 0;

                    $total_appointments_result = $conn->query("SELECT COUNT(*) as count FROM appointments");
                    $total_appointments = $total_appointments_result ? $total_appointments_result->fetch_assoc()['count'] : 0;

                    $confirmed_appointments_result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'confirmed'");
                    $confirmed_appointments = $confirmed_appointments_result ? $confirmed_appointments_result->fetch_assoc()['count'] : 0;

                    $conn->close();
                } else {
                    $total_users = $total_appointments = $confirmed_appointments = 0;
                }
                ?>
                <div class="row text-center">
                    <div class="col-4">
                        <h2 class="text-primary"><?php echo $total_users; ?></h2>
                        <p class="mb-0">Registered Users</p>
                    </div>
                    <div class="col-4">
                        <h2 class="text-success"><?php echo $total_appointments; ?></h2>
                        <p class="mb-0">Total Appointments</p>
                    </div>
                    <div class="col-4">
                        <h2 class="text-info"><?php echo $confirmed_appointments; ?></h2>
                        <p class="mb-0">Confirmed</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-5">
    <div class="col-12">
        <h3>Available Departments</h3>
        <p>Book appointments with any of our municipal departments:</p>
        
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
                        error_log("About page - Department $dept_count: ID=" . $department['id'] . ", Name=" . $department['name']);
                        // Filter out duplicates by name
                        if (!in_array($department['name'], $seen_names)):
                            $seen_names[] = $department['name'];
            ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="<?php echo htmlspecialchars($department['icon_class']); ?> fa-2x text-primary mb-3"></i>
                                    <h5 class="card-title"><?php echo htmlspecialchars($department['name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($department['description']); ?></p>
                                </div>
                            </div>
                        </div>
            <?php
                        endif;
                    endwhile;
                }
                $conn->close();
            } else {
                echo '<div class="col-12"><p class="text-muted">Unable to load department information at this time.</p></div>';
            }
            ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Accessibility & Usability</h3>
                <p>Our system is designed with accessibility in mind:</p>
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-universal-access text-primary me-2"></i>WCAG 2.1 AA Compliant</h5>
                        <p>Meets web accessibility guidelines for color contrast, keyboard navigation, and screen reader compatibility.</p>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-mobile-alt text-primary me-2"></i>Mobile-First Design</h5>
                        <p>Optimized for mobile devices with large touch targets and responsive layouts.</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h5><i class="fas fa-font text-primary me-2"></i>Large, Readable Fonts</h5>
                        <p>16px base font size with clear typography for easy reading.</p>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-hand-pointer text-primary me-2"></i>Touch-Friendly Interface</h5>
                        <p>Minimum 60px button height for comfortable touch interaction.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>