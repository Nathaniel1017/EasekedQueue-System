    </div> <!-- End container -->
    <footer style="background: linear-gradient(135deg, #0066cc 0%, #00b4d8 100%); color: white; margin-top: 5rem; padding: 3rem 0 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 style="font-weight: 700; margin-bottom: 1rem; color: white;">
                        <i class="fas fa-calendar-check" style="color: #06d6a0;"></i> Easeked Queue
                    </h5>
                    <p style="color: rgba(255,255,255,0.85); font-size: 0.95rem; line-height: 1.6;">
                        <strong>Simplified Queueing, Own Your Time</strong>
                    </p>
                    <p style="color: rgba(255,255,255,0.75); font-size: 0.9rem; margin-bottom: 0;">
                        Reduce wait times and improve access to municipal services.
                    </p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 style="font-weight: 700; margin-bottom: 1rem; color: white;">Quick Links</h5>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin-bottom: 0.5rem;"><a href="index.php" style="color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.95rem; transition: all 0.3s ease;">Home</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="about.php" style="color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.95rem; transition: all 0.3s ease;">About Us</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="contact.php" style="color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.95rem; transition: all 0.3s ease;">Contact</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="dashboard.php" style="color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.95rem; transition: all 0.3s ease;">My Appointments</a></li>
                        <?php else: ?>
                            <li><a href="register.php" style="color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.95rem; transition: all 0.3s ease;">Create Account</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 style="font-weight: 700; margin-bottom: 1rem; color: white;">Contact Info</h5>
                    <p style="color: rgba(255,255,255,0.85); font-size: 0.95rem; margin-bottom: 0.5rem;"><i class="fas fa-phone"></i> (123) 456-7890</p>
                    <p style="color: rgba(255,255,255,0.85); font-size: 0.95rem; margin-bottom: 0.5rem;"><i class="fas fa-envelope"></i> info@easekedqueue.com</p>
                    <p style="color: rgba(255,255,255,0.85); font-size: 0.95rem; margin-bottom: 0;"><i class="fas fa-map-marker-alt"></i> Municipal Hall, City Center</p>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 2rem 0;">
            <div style="text-align: center;">
                <p style="color: rgba(255,255,255,0.75); font-size: 0.9rem; margin-bottom: 0;">&copy; <?php echo date('Y'); ?> Easeked Queue. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Notification check interval
        setInterval(function() {
            if (typeof checkNotifications === 'function') {
                checkNotifications();
            }
        }, 30000);
    </script>
</body>
</html>