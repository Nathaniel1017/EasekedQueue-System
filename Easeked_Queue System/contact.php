<?php
$page_title = 'Contact Us';
include 'header.php';

$message_sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Basic validation
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if (empty($subject)) {
        $errors[] = 'Subject is required.';
    }

    if (empty($message)) {
        $errors[] = 'Message is required.';
    }

    if (empty($errors)) {
        // In a real application, you'd send an email here
        // For now, we'll just simulate success
        $message_sent = true;
        
        // You could also save to database for admin review
        // $conn = getDBConnection();
        // $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        // etc.
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="text-center mb-5">
            <h1>Contact Us</h1>
            <p class="lead">Get in touch with our municipal services team</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Send us a Message</h3>
            </div>
            <div class="card-body">
                <?php if ($message_sent): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Thank you for your message! We'll get back to you soon.
                    </div>
                <?php elseif (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-custom">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Contact Information</h4>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <i class="fas fa-map-marker-alt text-primary me-2"></i>
                    <strong>Municipal Hall</strong><br>
                    City Center, Municipality<br>
                    Philippines
                </div>
                <div class="mb-3">
                    <i class="fas fa-phone text-primary me-2"></i>
                    <strong>Phone:</strong><br>
                    (123) 456-7890
                </div>
                <div class="mb-3">
                    <i class="fas fa-envelope text-primary me-2"></i>
                    <strong>Email:</strong><br>
                    info@easekedqueue.com
                </div>
                <div class="mb-3">
                    <i class="fas fa-clock text-primary me-2"></i>
                    <strong>Office Hours:</strong><br>
                    Monday - Friday: 7:00 AM - 4:00 PM<br>
                    Saturday: 8:00 AM - 12:00 PM<br>
                    Sunday: Closed
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Emergency Contacts</h4>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Police:</strong> 911<br>
                    <strong>Fire Department:</strong> 912<br>
                    <strong>Medical Emergency:</strong> 913
                </div>
                <div class="alert alert-info">
                    <small>For appointment-related emergencies, please call our main office line during business hours.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Frequently Asked Questions</h3>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I create an account?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Click on "Create Account" from the main menu. Fill in your details and you'll be ready to book appointments.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Can I book appointments for someone else?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, during the booking process you can choose "For someone else" and enter their details.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What documents do I need to bring?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Requirements vary by department and service. Check with the specific department or bring valid ID and any relevant documents.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Can I cancel or reschedule my appointment?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                You can cancel appointments through your dashboard, but must do so at least 2 hours in advance. Contact the office for rescheduling.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>