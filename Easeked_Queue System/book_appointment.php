<?php
require_once 'config.php';
requireLogin();

$page_title = 'Book Appointment';
error_log("book_appointment.php processing started");

// Get current user info
$user = getCurrentUser();
if (!$user) {
    die("User information could not be retrieved. Please log in again.");
}

// Store user_id in session if not already there
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $user['id'];
}

// Get current step from URL or default to 1
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
error_log("book_appointment.php: Current step from GET: $step");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Headers sent at start of POST: " . (headers_sent($file, $line) ? "yes by $file:$line" : 'no'));
    if ($step === 1 && isset($_POST['department_id'])) {
        // Store department selection in session
        $dept_id = (int)$_POST['department_id'];
        
        // Validate that the department exists
        $conn = getDBConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT id FROM departments WHERE id = ?");
            $stmt->bind_param("i", $dept_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows > 0) {
                $_SESSION['booking_department_id'] = $dept_id;
                error_log("Department selected: ID=$dept_id");
                if (headers_sent($file, $line)) {
                    error_log("Headers already sent by $file:$line before redirect to step 2");
                }
                header("Location: book_appointment.php?step=2");
                exit();
            } else {
                error_log("Invalid department ID selected: $dept_id");
                $error_message = "Invalid department selected. Please try again.";
            }
            $conn->close();
        } else {
            error_log("Database connection failed when validating department");
            $error_message = "Database connection failed. Please try again.";
        }
    } elseif ($step === 2 && isset($_POST['is_pwd'])) {
        // Store PWD information in session
        $_SESSION['booking_is_pwd'] = (int)$_POST['is_pwd'];
        $pwd_proof_path = null;
        if ($_POST['is_pwd'] == 1 && isset($_FILES['pwd_proof']) && $_FILES['pwd_proof']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_extension = pathinfo($_FILES['pwd_proof']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            if (in_array(strtolower($file_extension), $allowed_extensions) && $_FILES['pwd_proof']['size'] <= 2 * 1024 * 1024) {
                $file_name = 'pwd_' . uniqid() . '.' . $file_extension;
                $pwd_proof_path = $upload_dir . $file_name;
                move_uploaded_file($_FILES['pwd_proof']['tmp_name'], $pwd_proof_path);
            }
        }
        $_SESSION['booking_pwd_proof'] = $pwd_proof_path;
        header("Location: book_appointment.php?step=3");
        exit();
    } elseif ($step === 3 && isset($_POST['appointment_for'])) {
        // Store appointment details in session
        $_SESSION['booking_appointment_for'] = $_POST['appointment_for'];
        if ($_POST['appointment_for'] === 'someone_else') {
            $_SESSION['booking_recipient_name'] = trim($_POST['recipient_name']);
        if (headers_sent($file, $line)) {
            error_log("Headers already sent by $file:$line before redirect to step 4");
        }
            $_SESSION['booking_recipient_phone'] = trim($_POST['recipient_phone']);
            $_SESSION['booking_recipient_email'] = trim($_POST['recipient_email']);
        }
        header("Location: book_appointment.php?step=4");
        exit();
    } elseif ($step === 4 && isset($_POST['agenda'])) {
        if (headers_sent($file, $line)) {
            error_log("Headers already sent by $file:$line before redirect to step 5");
        }
        // Store agenda in session
        $_SESSION['booking_agenda'] = $_POST['agenda'];
        // If "Other" was selected, also store the custom agenda
        if (headers_sent($file, $line)) {
            error_log("Headers already sent by $file:$line before redirect to step 6");
        }
        if ($_POST['agenda'] === 'Other' && isset($_POST['custom_agenda'])) {
            $_SESSION['booking_custom_agenda'] = trim($_POST['custom_agenda']);
        }
        header("Location: book_appointment.php?step=5");
        exit();
    } elseif ($step === 5 && isset($_POST['appointment_date']) && isset($_POST['appointment_time'])) {
        // Validate and check availability before proceeding
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $department_id = $_SESSION['booking_department_id'] ?? null;
        
        if (!$department_id) {
            $error_message = 'Department information is missing. Please start over.';
            $step = 5; // Stay on this step
        } else {
            // Verify that the selected time slot is still available
            $conn = getDBConnection();
            if (!$conn) {
                $error_message = 'Database connection failed. Please try again.';
                $step = 5;
            } else {
                $verify_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND department_id = ? AND status = 'confirmed'");
                
                if (!$verify_stmt) {
                    $error_message = 'Database error. Please try again.';
                    $step = 5;
                } else {
                    $verify_stmt->bind_param("ssi", $appointment_date, $appointment_time, $department_id);
                    $verify_stmt->execute();
                    $result = $verify_stmt->get_result();
                    $row = $result->fetch_assoc();
                    $verify_stmt->close();
                    $conn->close();
                    
                    if ($row['count'] > 0) {
                        // Time slot is already booked
                        $error_message = 'Sorry! This time slot has just been booked by another user. Please select a different time or date.';
                        $step = 5; // Stay on this step
                    } else {
                        // Time slot is available, proceed
                        $_SESSION['booking_date'] = $appointment_date;
                        $_SESSION['booking_time'] = $appointment_time;
                        header("Location: book_appointment.php?step=6");
                        exit();
                    }
                }
            }
        }
    } elseif ($step === 6) {
        // Handle file upload if provided
        $uploaded_file_path = null;
        if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
        if (headers_sent($file, $line)) {
            error_log("Headers already sent by $file:$line before redirect to step 7");
        }
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_extension = pathinfo($_FILES['uploaded_file']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            if (in_array(strtolower($file_extension), $allowed_extensions) && $_FILES['uploaded_file']['size'] <= 2 * 1024 * 1024) {
                $file_name = uniqid() . '.' . $file_extension;
                $uploaded_file_path = $upload_dir . $file_name;
                move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $uploaded_file_path);
            }
        }
        $_SESSION['booking_uploaded_file'] = $uploaded_file_path;
        header("Location: book_appointment.php?step=7");
        exit();
    } elseif ($step === 7 && isset($_POST['confirm'])) {
        // Validate all required session data exists
        $validation_errors = [];

        if (empty($_SESSION['booking_department_id'])) {
            $validation_errors[] = "Department ID is missing. Please start over.";
        }
        if (!isset($_SESSION['booking_is_pwd'])) {
            $validation_errors[] = "PWD information is missing. Please start over.";
        }
        if ($_SESSION['booking_is_pwd'] == 1 && empty($_SESSION['booking_pwd_proof'])) {
            $validation_errors[] = "PWD proof is required. Please start over.";
        }
        if (empty($_SESSION['booking_date'])) {
            $validation_errors[] = "Appointment date is missing. Please start over.";
        }
        if (empty($_SESSION['booking_time'])) {
            $validation_errors[] = "Appointment time is missing. Please start over.";
        }
        if (empty($_SESSION['booking_appointment_for'])) {
            $validation_errors[] = "Appointment type is missing. Please start over.";
        }
        if (empty($_SESSION['booking_agenda'])) {
            $validation_errors[] = "Purpose/agenda is missing. Please start over.";
        }
        if (!isset($_SESSION['user_id'])) {
            $validation_errors[] = "User information is missing. Please log in again.";
        }
        
        if (!empty($validation_errors)) {
            $error_message = "Booking validation failed: " . implode(" ", $validation_errors);
        } else {
            // Check if the time slot is still available before saving
            $conn = getDBConnection();
            if (!$conn) {
                $error_message = 'Database connection failed. Please try again later.';
            } else {
                // Check for existing confirmed appointments at the same date, time, and department
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND department_id = ? AND status = 'confirmed'");
                if (!$check_stmt) {
                    $error_message = 'Database error (check): ' . $conn->error . '. Please try again.';
                } else {
                    $check_stmt->bind_param("ssi", $_SESSION['booking_date'], $_SESSION['booking_time'], $_SESSION['booking_department_id']);
                    if (!$check_stmt->execute()) {
                        $error_message = 'Database error (execute check): ' . $check_stmt->error . '. Please try again.';
                    } else {
                        $check_result = $check_stmt->get_result();
                        $existing_count = $check_result->fetch_assoc()['count'];
                        $check_stmt->close();

                        if ($existing_count > 0) {
                            $error_message = 'This time slot is already taken. Please select a different date or time.';
                        } else {
                            // Save appointment to database
                            $stmt = $conn->prepare("INSERT INTO appointments (user_id, department_id, service_id, appointment_for, recipient_name, recipient_phone, recipient_email, appointment_date, appointment_time, agenda, custom_agenda, uploaded_file_path, is_pwd, pwd_proof, priority_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmt) {
                                $error_message = 'Database query preparation failed: ' . $conn->error . '. Please try again.';
                            } else {
                                $recipient_name = $_SESSION['booking_recipient_name'] ?? $user['full_name'];
                                $recipient_phone = $_SESSION['booking_recipient_phone'] ?? $user['phone_number'];
                                $recipient_email = $_SESSION['booking_recipient_email'] ?? $user['email'];

                                $status = 'pending';
                                $service_id = NULL;
                                // Prepare custom agenda value
                                $custom_agenda = $_SESSION['booking_custom_agenda'] ?? NULL;
                                $uploaded_file = $_SESSION['booking_uploaded_file'] ?? NULL;
                                $is_pwd = $_SESSION['booking_is_pwd'] ?? 0;
                                $pwd_proof = $_SESSION['booking_pwd_proof'] ?? NULL;
                                $priority_status = ($is_pwd == 1) ? 'priority' : 'normal';

                                // Log the data being inserted for debugging
                                error_log("Inserting appointment: user_id=" . $_SESSION['user_id'] . ", dept_id=" . $_SESSION['booking_department_id'] . ", date=" . $_SESSION['booking_date'] . ", time=" . $_SESSION['booking_time']);

                                $stmt->bind_param("iissssssssssssss",
                                    $_SESSION['user_id'],
                                    $_SESSION['booking_department_id'],
                                    $service_id,
                                    $_SESSION['booking_appointment_for'],
                                    $recipient_name,
                                    $recipient_phone,
                                    $recipient_email,
                                    $_SESSION['booking_date'],
                                    $_SESSION['booking_time'],
                                    $_SESSION['booking_agenda'],
                                    $custom_agenda,
                                    $uploaded_file,
                                    $is_pwd,
                                    $pwd_proof,
                                    $priority_status,
                                    $status
                                );

                                if ($stmt->execute()) {
                                    $appointment_id = $conn->insert_id;
                                    error_log("Appointment inserted successfully with ID: " . $appointment_id . ", department_id: " . $_SESSION['booking_department_id']);

                                    // Create notifications for admins/super_admin
                                    notifyNewAppointment($appointment_id, $_SESSION['booking_department_id']);

                                    // Clear session data
                                    unset($_SESSION['booking_department_id'], $_SESSION['booking_is_pwd'], $_SESSION['booking_pwd_proof'], $_SESSION['booking_appointment_for'], $_SESSION['booking_recipient_name'], $_SESSION['booking_recipient_phone'], $_SESSION['booking_recipient_email'], $_SESSION['booking_date'], $_SESSION['booking_time'], $_SESSION['booking_agenda'], $_SESSION['booking_custom_agenda'], $_SESSION['booking_uploaded_file']);

                                    $success_message = 'Appointment booked successfully! You will receive a confirmation soon.';
                                } else {
                                    $error_message = 'Failed to book appointment: ' . $stmt->error . '. Please try again.';
                                    error_log("Appointment insert failed: " . $stmt->error);
                                }

                                $stmt->close();
                            }
                        }
                    }
                }
                $conn->close();
            }
        }
    }
}

include 'header.php';
// Get departments for step 1
$departments = [];
if ($step === 1) {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM departments ORDER BY name");
    $dept_count = 0;
    $seen_names = [];
    while ($row = $result->fetch_assoc()) {
        $dept_count++;
        error_log("Book appointment - Department $dept_count: ID=" . $row['id'] . ", Name=" . $row['name']);
        // Filter out duplicates by name
        if (!in_array($row['name'], $seen_names)) {
            $seen_names[] = $row['name'];
            $departments[] = $row;
        }
    }
    $conn->close();
}

// Get department-specific agendas for step 4
$department_agendas = [];
error_log("Agendas section: Current step $step, booking_department_id set: " . (isset($_SESSION['booking_department_id']) ? $_SESSION['booking_department_id'] : 'no'));
if ($step === 4 && isset($_SESSION['booking_department_id'])) {
    error_log("Step 4: Fetching services for department_id = " . $_SESSION['booking_department_id']);
    $conn = getDBConnection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT service_name FROM services WHERE department_id = ? ORDER BY service_name");
        $stmt->bind_param("i", $_SESSION['booking_department_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $department_agendas[] = $row['service_name'];
        }
        $stmt->close();
        $conn->close();
        error_log("Step 4: Department services fetched: " . count($department_agendas) . " items - " . implode(', ', $department_agendas));
    } else {
        error_log("Step 4: Database connection failed when fetching services");
    }
}

// Get available time slots for step 4 or 5
$available_slots = [];
if ($step === 4 || $step === 5) {
    $date = $_SESSION['booking_date'] ?? date('Y-m-d');
    $day_of_week = date('N', strtotime($date)); // 1=Monday, 7=Sunday

    error_log("DEBUG: Calculating available slots for step $step, date: $date, department_id: " . ($_SESSION['booking_department_id'] ?? 'not set'));

    // Department-specific time slots
    $department_slots = [];
    if (isset($_SESSION['booking_department_id'])) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['booking_department_id']);
        $stmt->execute();
        $dept_result = $stmt->get_result();
        $department = $dept_result->fetch_assoc();
        $stmt->close();

        if ($department) {
            $dept_name = $department['name'];
            switch ($dept_name) {
                case "Mayor's Office / BPLO":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Municipal Health Office":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Civil Registry":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Treasurer's Office":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Assessor's Office":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Social Welfare & Development Office (MSWDO)":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Engineering Office":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Agriculture Office":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                case "Sangguniang Bayan Secretary":
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
                    break;
                default:
                    $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
            }
        } else {
            $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
            error_log("Book appointment - Department not found for ID: " . $_SESSION['booking_department_id']);
        }
        $conn->close();
    } else {
        $department_slots = ['08:00', '09:00', '10:00', '13:00', '14:00', '15:00'];
        error_log("Book appointment - No department selected, using default slots");
    }

    error_log("Book appointment - Department slots for " . ($department['name'] ?? 'unknown') . ": " . implode(', ', $department_slots));

    $conn = getDBConnection();
    if (!$conn) {
        error_log("DEBUG: Database connection failed for availability check");
    } else {
        foreach ($department_slots as $slot) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND department_id = ? AND status = 'confirmed'");
            if (!$stmt) {
                error_log("DEBUG: Prepare failed for slot $slot: " . $conn->error);
                continue;
            }
            $stmt->bind_param("ssi", $date, $slot, $_SESSION['booking_department_id']);
            if (!$stmt->execute()) {
                error_log("DEBUG: Execute failed for slot $slot: " . $stmt->error);
                $stmt->close();
                continue;
            }
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $stmt->close();

            error_log("DEBUG: Slot $slot on $date for dept " . $_SESSION['booking_department_id'] . ": count = $count");

            if ($count == 0) {
                $available_slots[] = $slot;
            }
        }
        $conn->close();
    }

    error_log("Book appointment - Available slots for $date: " . implode(', ', $available_slots));
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card" style="border: none; box-shadow: var(--card-shadow); border-radius: 12px;">
            <div class="card-header" style="background-color: white; border-bottom: 1px solid var(--border-color); padding: 2rem;">
                <h3 class="card-title mb-0" style="color: var(--primary-color); font-weight: 700; font-size: 1.5rem;">
                    Book Appointment - Step <?php echo $step; ?> of 7
                </h3>
            </div>
            <div class="card-body" style="padding: 2rem;">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success" style="background-color: #f0fdf4; border: 1px solid #86efac; color: #166534; border-radius: 8px; padding: 1.25rem;">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                    <a href="dashboard.php" class="btn btn-primary btn-custom">
                        <i class="fas fa-arrow-left"></i> View My Appointments
                    </a>
                <?php elseif (isset($error_message)): ?>
                    <div class="alert alert-danger" style="background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; padding: 1.25rem;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php else: ?>
                    <!-- Progress indicator -->
                    <div class="mb-4">
                        <div class="progress" style="height: 6px; background-color: var(--border-color); border-radius: 4px; overflow: hidden;">
                            <div class="progress-bar" style="width: <?php echo ($step / 7) * 100; ?>%; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-4" style="font-size: 0.85rem;">
                            <?php $steps = ['Service', 'PWD', 'Details', 'Purpose', 'Date & Time', 'Documents', 'Review']; ?>
                            <?php foreach ($steps as $idx => $label): ?>
                                <div style="flex: 1; text-align: center; position: relative;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background-color: <?php echo $step > $idx + 1 ? 'var(--success-color)' : ($step == $idx + 1 ? 'var(--primary-color)' : 'var(--border-color)'); ?>; margin: 0 auto 0.5rem; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                        <?php echo $step > $idx + 1 ? '<i class="fas fa-check" style="font-size: 0.875rem;"></i>' : ($idx + 1); ?>
                                    </div>
                                    <small style="color: <?php echo $step >= $idx + 1 ? 'var(--primary-color)' : 'var(--text-secondary)'; ?>; font-weight: <?php echo $step == $idx + 1 ? '700' : '500'; ?>; display: block;"><?php echo $label; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($step === 1): ?>
                        <!-- Step 1: Choose Department -->
                        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">Select a Service</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Choose the municipal service you need an appointment for:</p>
                        <form method="POST">
                            <div class="row">
                                <?php foreach ($departments as $dept): ?>
                                    <div class="col-md-6 mb-3">
                                        <button type="submit" name="department_id" value="<?php echo $dept['id']; ?>" class="card h-100" style="border: none; background: white; box-shadow: var(--card-shadow); cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: inherit; text-align: center; padding: 0; width: 100%;">
                                            <div class="card-body" style="padding: 2rem;">
                                                <i class="<?php echo htmlspecialchars($dept['icon_class']); ?> fa-2x" style="color: var(--primary-color); margin-bottom: 1rem;"></i>
                                                <h6 style="color: var(--text-primary); font-weight: 600; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($dept['name']); ?></h6>
                                                <p class="small" style="color: var(--text-secondary); margin: 0;"><?php echo htmlspecialchars($dept['description']); ?></p>
                                            </div>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>

                    <?php elseif ($step === 2): ?>
                        <!-- Step 2: PWD Verification -->
                        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">PWD Verification</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Are you a Person With Disability (PWD)? If yes, you may receive priority scheduling.</p>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <div style="display: flex; gap: 2rem;">
                                    <label style="flex: 1; padding: 1.5rem; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
                                        <input type="radio" name="is_pwd" value="0" checked style="width: 20px; height: 20px; cursor: pointer;">
                                        <span style="font-weight: 500; color: var(--text-primary);">No</span>
                                    </label>
                                    <label style="flex: 1; padding: 1.5rem; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
                                        <input type="radio" name="is_pwd" value="1" style="width: 20px; height: 20px; cursor: pointer;">
                                        <span style="font-weight: 500; color: var(--text-primary);">Yes</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3" id="pwd_proof_section" style="display: none;">
                                <label for="pwd_proof" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Upload PWD Proof</label>
                                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">Please upload a valid PWD ID, Certificate, or similar document (JPG, PNG, PDF - Max 2MB)</p>
                                <input type="file" class="form-control" id="pwd_proof" name="pwd_proof" accept=".jpg,.jpeg,.png,.pdf" style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                            </div>
                            <div style="display: flex; gap: 1rem;">
                                <a href="?step=1" class="btn btn-outline-primary btn-custom" style="color: var(--primary-color); background: white; flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Back</a>
                                <button type="submit" class="btn btn-primary btn-custom" style="flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Next</button>
                            </div>
                        </form>

                    <?php elseif ($step === 3): ?>
                        <!-- Step 3: Who is this appointment for? -->
                        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">Who is this appointment for?</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Select who you're booking this appointment for:</p>
                        <form method="POST">
                            <div class="mb-4">
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <label style="padding: 1.5rem; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
                                        <input type="radio" name="appointment_for" value="me" checked style="width: 20px; height: 20px; cursor: pointer;">
                                        <span style="font-weight: 500; color: var(--text-primary);">For myself</span>
                                    </label>
                                    <label style="padding: 1.5rem; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
                                        <input type="radio" name="appointment_for" value="someone_else" style="width: 20px; height: 20px; cursor: pointer;">
                                        <span style="font-weight: 500; color: var(--text-primary);">For someone else</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="someone_else_details" style="display: none;">
                                <div class="mb-3">
                                    <label for="recipient_name" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Full Name</label>
                                    <input type="text" class="form-control" id="recipient_name" name="recipient_name" placeholder="Enter full name" style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                                </div>
                                <div class="mb-3">
                                    <label for="recipient_phone" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Phone Number</label>
                                    <input type="tel" class="form-control" id="recipient_phone" name="recipient_phone" placeholder="Enter phone number" style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                                </div>
                                <div class="mb-3">
                                    <label for="recipient_email" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Email (Optional)</label>
                                    <input type="email" class="form-control" id="recipient_email" name="recipient_email" placeholder="Enter email address" style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 1rem;">
                                <a href="?step=2" class="btn btn-outline-primary btn-custom" style="color: var(--primary-color); background: white; flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Back</a>
                                <button type="submit" class="btn btn-primary btn-custom" style="flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Next</button>
                            </div>
                        </form>
                        
                        <script>
                            document.querySelectorAll('input[name="appointment_for"]').forEach(radio => {
                                radio.addEventListener('change', function() {
                                    document.getElementById('someone_else_details').style.display = this.value === 'someone_else' ? 'block' : 'none';
                                });
                            });
                        </script>

                    <?php elseif ($step === 4): ?>
                        <!-- Step 4: Choose Agenda/Purpose -->
                        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">What's the Purpose?</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Select the purpose or reason for your appointment:</p>
                        <form method="POST" id="step4-form">
                            <div class="mb-3">
                                <label for="agenda" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.75rem; display: block;">Purpose</label>
                                
                                <!-- Simple Native Select styled with Bootstrap -->
                                <select id="agenda" name="agenda" required class="form-select" style="padding: 0.875rem 1rem; border: 2px solid var(--border-color); border-radius: 8px; font-size: 1rem; color: var(--text-primary); transition: all 0.3s ease; box-shadow: 0 2px 6px rgba(0, 102, 204, 0.08); background-image: url('data:image/svg+xml,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 16 16%22%3e%3cpath fill=%22none%22 stroke=%22%230066cc%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%222%22 d=%22M2 5l6 6 6-6%22/%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 20px; padding-right: 2.5rem; appearance: none; -webkit-appearance: none; -moz-appearance: none; width: 100%; max-width: 100%;" onchange="this.style.width='100%'">>
                                    <option value="">-- Select purpose --</option>
                                    <?php 
                                    if (!empty($department_agendas)) {
                                        foreach ($department_agendas as $agenda): ?>
                                            <option value="<?php echo htmlspecialchars($agenda); ?>"><?php echo htmlspecialchars($agenda); ?></option>
                                        <?php endforeach;
                                    } else {
                                        // Fallback options if database query fails
                                    ?>
                                        <option value="Consultation">Consultation</option>
                                        <option value="Follow-up">Follow-up</option>
                                        <option value="Treatment">Treatment</option>
                                        <option value="Check-up">Check-up</option>
                                    <?php } ?>
                                    <option value="Other">Other (please specify)</option>
                                </select>
                            </div>
                            
                            <!-- Custom input for "Other" option -->
                            <div class="mb-3" id="other_agenda" style="display: none; opacity: 0; transform: translateY(-10px); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
                                <label for="custom_agenda" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.75rem; display: block;">Please specify your purpose:</label>
                                <input type="text" class="form-control" id="custom_agenda" name="custom_agenda" placeholder="Describe your purpose..." style="padding: 0.875rem 1rem; border: 2px solid var(--border-color); border-radius: 8px; background-color: white; font-size: 1rem; color: var(--text-primary); transition: all 0.3s ease; box-shadow: 0 2px 6px rgba(0, 102, 204, 0.08);">
                            </div>
                            
                            <div style="display: flex; gap: 1rem;">
                                <a href="?step=3" class="btn btn-outline-primary btn-custom" style="color: var(--primary-color); background: white; flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Back</a>
                                <button type="submit" class="btn btn-primary btn-custom" style="flex: 1; pointer-events: auto; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Next</button>
                            </div>
                        </form>
                        <script>
                            (function() {
                                const form = document.getElementById('step4-form');
                                const agendaSelect = document.getElementById('agenda');
                                const customAgendaInput = document.getElementById('custom_agenda');
                                const otherAgendaDiv = document.getElementById('other_agenda');
                                
                                if (!form || !agendaSelect) return;
                                
                                // Add focus and hover styling to select element
                                agendaSelect.addEventListener('focus', function() {
                                    this.style.borderColor = 'var(--primary-color)';
                                    this.style.boxShadow = '0 0 0 3px rgba(0, 102, 204, 0.15), 0 2px 12px rgba(0, 102, 204, 0.14)';
                                });
                                
                                agendaSelect.addEventListener('blur', function() {
                                    this.style.borderColor = 'var(--border-color)';
                                    this.style.boxShadow = '0 2px 6px rgba(0, 102, 204, 0.08)';
                                });
                                
                                // Show/hide custom input based on selection
                                agendaSelect.addEventListener('change', function() {
                                    if (this.value === 'Other') {
                                        otherAgendaDiv.style.display = 'block';
                                        setTimeout(() => {
                                            otherAgendaDiv.style.opacity = '1';
                                            otherAgendaDiv.style.transform = 'translateY(0)';
                                            customAgendaInput.focus();
                                        }, 50);
                                    } else {
                                        customAgendaInput.value = '';
                                        otherAgendaDiv.style.opacity = '0';
                                        otherAgendaDiv.style.transform = 'translateY(-10px)';
                                        setTimeout(() => {
                                            otherAgendaDiv.style.display = 'none';
                                        }, 300);
                                    }
                                });
                                
                                // Custom input styling
                                customAgendaInput.addEventListener('focus', function() {
                                    this.style.borderColor = 'var(--primary-color)';
                                    this.style.boxShadow = '0 0 0 3px rgba(0, 102, 204, 0.15), 0 2px 12px rgba(0, 102, 204, 0.14)';
                                });
                                
                                customAgendaInput.addEventListener('blur', function() {
                                    this.style.borderColor = 'var(--border-color)';
                                    this.style.boxShadow = '0 2px 6px rgba(0, 102, 204, 0.08)';
                                });
                                
                                // Form submit validation
                                form.addEventListener('submit', function(e) {
                                    if (!agendaSelect.value) {
                                        e.preventDefault();
                                        alert('Please select a purpose');
                                        agendaSelect.focus();
                                        return false;
                                    }
                                    if (agendaSelect.value === 'Other' && !customAgendaInput.value.trim()) {
                                        e.preventDefault();
                                        alert('Please specify your purpose');
                                        customAgendaInput.focus();
                                        return false;
                                    }
                                    return true;
                                });
                            })();
                        </script>

                    <?php elseif ($step === 5): ?>
                        <!-- Step 5: Select Date and Time with Interactive Calendar -->
                        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">Choose Your Date & Time</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Select a weekday from the calendar below (weekends and Philippine holidays are disabled). Then choose your preferred time slot.</p>
                        <form method="POST">
                            <div class="row">
                                <!-- Calendar Section -->
                                <div class="col-lg-6 mb-4">
                                    <label style="font-weight: 500; color: var(--text-primary); margin-bottom: 1rem; display: block;">Select Date</label>
                                    
                                    <!-- Calendar Header with Navigation -->
                                    <div style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 1rem; overflow: hidden;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                            <button type="button" id="prev-month" style="background: var(--light-bg); border: 1px solid var(--border-color); color: var(--text-primary); padding: 0.4rem 0.75rem; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; font-size: 0.9rem;">← Prev</button>
                                            <h6 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary);" id="calendar-header">December 2025</h6>
                                            <button type="button" id="next-month" style="background: var(--light-bg); border: 1px solid var(--border-color); color: var(--text-primary); padding: 0.4rem 0.75rem; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; font-size: 0.9rem;">Next →</button>
                                        </div>
                                        
                                        <!-- Weekday Headers -->
                                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem; margin-bottom: 0.5rem;">
                                            <div style="text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.75rem;">Sun</div>
                                            <div style="text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.75rem;">Mon</div>
                                            <div style="text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.75rem;">Tue</div>
                                            <div style="text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.75rem;">Wed</div>
                                            <div style="text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.75rem;">Thu</div>
                                            <div style="text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.75rem;">Fri</div>
                                            <div style="text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.75rem;">Sat</div>
                                        </div>
                                        
                                        <!-- Calendar Grid -->
                                        <div id="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem;">
                                            <!-- Calendar dates will be populated by JavaScript -->
                                            <div style="text-align: center; padding: 0.5rem; grid-column: 1/-1; color: var(--text-secondary); font-size: 0.85rem;">Loading calendar...</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Selected Date Display -->
                                    <div id="selected-date-display" style="margin-top: 0.75rem; padding: 0.75rem; background-color: var(--light-bg); border-radius: 8px; border-left: 4px solid var(--border-color); display: none;">
                                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.85rem;">Selected Date:</p>
                                        <p style="margin: 0.3rem 0 0 0; color: var(--text-primary); font-weight: 700; font-size: 0.95rem;" id="selected-date-text"></p>
                                    </div>
                                </div>
                                
                                <!-- Time Slots Section -->
                                <div class="col-lg-6 mb-4">
                                    <label style="font-weight: 500; color: var(--text-primary); margin-bottom: 1rem; display: block;">Select Time</label>
                                    
                                    <div style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 1rem;">
                                        <p id="time-selection-message" style="text-align: center; color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">Please select a date first to see available time slots</p>
                                        
                                        <!-- Time Slots Grid -->
                                        <div id="time-slots-container" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; min-height: 180px; align-content: start; margin-bottom: 0.75rem;">
                                            <!-- Time slots will be populated by JavaScript -->
                                        </div>
                                        
                                        <!-- Time Slots Legend -->
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                                            <div style="padding: 0.5rem; background: rgba(6, 214, 160, 0.2); border: 2px solid rgb(6, 214, 160); border-radius: 6px;">
                                                <p style="margin: 0; font-size: 0.75rem; color: rgb(6, 214, 160);">Available</p>
                                                <p style="margin: 0.2rem 0 0 0; font-weight: 600; color: rgb(6, 214, 160); font-size: 0.85rem;">Slot</p>
                                            </div>
                                            <div style="padding: 0.5rem; background: rgba(239, 71, 111, 0.2); border: 2px solid rgb(239, 71, 111); border-radius: 6px;">
                                                <p style="margin: 0; font-size: 0.75rem; color: rgb(239, 71, 111);">Booked</p>
                                                <p style="margin: 0.2rem 0 0 0; font-weight: 600; color: rgb(239, 71, 111); font-size: 0.85rem;">Slot</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Selected Time Display -->
                                    <div id="selected-time-display" style="margin-top: 0.75rem; padding: 0.75rem; background-color: var(--light-bg); border-radius: 8px; border-left: 4px solid var(--border-color); display: none;">
                                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.85rem;">Selected Time:</p>
                                        <p style="margin: 0.3rem 0 0 0; color: var(--text-primary); font-weight: 700; font-size: 0.95rem;" id="selected-time-text"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hidden inputs for form submission -->
                            <input type="hidden" id="appointment_date" name="appointment_date" required>
                            <input type="hidden" id="appointment_time" name="appointment_time" required>

                            <!-- Status messages -->
                            <div id="calendar-status-message" style="display: none; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--warning-color); background-color: rgba(255, 193, 7, 0.1); color: var(--text-primary);"></div>
                            
                            <div style="display: flex; gap: 1rem;">
                                <a href="?step=4" class="btn btn-outline-primary btn-custom" style="color: var(--primary-color); background: white; flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Back</a>
                                <button type="submit" class="btn btn-primary btn-custom" style="flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Next</button>
                            </div>
                        </form>
                        <script>
                            (function() {
                                // DOM Elements
                                const dateInput = document.getElementById('appointment_date');
                                const timeInput = document.getElementById('appointment_time');
                                const calendarGrid = document.getElementById('calendar-grid');
                                const calendarHeader = document.getElementById('calendar-header');
                                const prevMonthBtn = document.getElementById('prev-month');
                                const nextMonthBtn = document.getElementById('next-month');
                                const timeSlotContainer = document.getElementById('time-slots-container');
                                const timeSelectionMsg = document.getElementById('time-selection-message');
                                const selectedDateDisplay = document.getElementById('selected-date-display');
                                const selectedDateText = document.getElementById('selected-date-text');
                                const selectedTimeDisplay = document.getElementById('selected-time-display');
                                const selectedTimeText = document.getElementById('selected-time-text');
                                const statusMessage = document.getElementById('calendar-status-message');
                                
                                // Get department ID from session
                                const departmentId = <?php echo $_SESSION['booking_department_id'] ?? 'null'; ?>;
                                
                                // Current calendar month/year for navigation
                                let currentMonth = new Date().getMonth();
                                let currentYear = new Date().getFullYear();
                                
                                // Month names
                                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                    'July', 'August', 'September', 'October', 'November', 'December'];
                                
                                // Time slots available per day (8-11 AM and 1-4 PM)
                                const availableTimeSlots = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];
                                
                                // Format date for display
                                function formatDateDisplay(dateStr) {
                                    const date = new Date(dateStr + 'T00:00:00');
                                    return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                                }
                                
                                // Format time for display (HH:MM AM/PM)
                                function formatTimeDisplay(timeStr) {
                                    const [hours, minutes] = timeStr.split(':');
                                    const hour = parseInt(hours);
                                    const period = hour >= 12 ? 'PM' : 'AM';
                                    const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                                    return `${displayHour}:${minutes} ${period}`;
                                }
                                
                                // Fetch calendar data for current month/year
                                async function loadCalendar() {
                                    try {
                                        const response = await fetch('get_available_dates.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({
                                                month: currentMonth + 1, // PHP expects 1-12
                                                year: currentYear
                                            })
                                        });
                                        
                                        const data = await response.json();
                                        if (data.success) {
                                            renderCalendar(data);
                                        } else {
                                            showError('Error loading calendar. Please try again.');
                                        }
                                    } catch (error) {
                                        console.error('Error fetching calendar:', error);
                                        showError('Error loading calendar. Please try again.');
                                    }
                                }
                                
                                // Render calendar grid
                                function renderCalendar(data) {
                                    // Update header
                                    calendarHeader.textContent = `${data.month_name} ${data.year}`;
                                    
                                    // Clear grid
                                    calendarGrid.innerHTML = '';
                                    
                                    // Render each date cell
                                    data.calendar_dates.forEach(dateInfo => {
                                        const dateCell = document.createElement('button');
                                        dateCell.type = 'button';
                                        dateCell.className = 'calendar-date-button';
                                        dateCell.textContent = dateInfo.day;
                                        
                                        // Determine if date is available
                                        const isAvailable = dateInfo.is_available && dateInfo.is_current_month && !dateInfo.is_past;
                                        const isWeekend = dateInfo.is_weekend;
                                        const isHoliday = dateInfo.is_holiday;
                                        const isDisabled = isWeekend || isHoliday || dateInfo.is_past || !dateInfo.is_current_month;
                                        const isSelected = dateInput.value === dateInfo.date;
                                        
                                        // Styling
                                        let bgColor = 'white';
                                        let textColor = 'var(--text-primary)';
                                        let borderColor = 'var(--border-color)';
                                        let cursor = 'pointer';
                                        let opacity = '1';
                                        
                                        if (!dateInfo.is_current_month) {
                                            textColor = 'var(--text-secondary)';
                                            opacity = '0.3';
                                            cursor = 'default';
                                        } else if (isDisabled) {
                                            textColor = 'var(--text-secondary)';
                                            opacity = '0.5';
                                            cursor = 'not-allowed';
                                            borderColor = '#e2e8f0';
                                        }
                                        
                                        if (isSelected && isAvailable) {
                                            bgColor = 'var(--primary-color)';
                                            textColor = 'white';
                                            borderColor = 'var(--primary-color)';
                                        } else if (isAvailable && dateInfo.is_current_month) {
                                            bgColor = 'rgba(6, 214, 160, 0.1)';
                                            borderColor = 'var(--success-color)';
                                        }
                                        
                                        dateCell.style.cssText = `
                                            padding: 0.5rem 0.35rem;
                                            border: 1px solid ${borderColor};
                                            background-color: ${bgColor};
                                            color: ${textColor};
                                            border-radius: 6px;
                                            cursor: ${cursor};
                                            font-weight: 600;
                                            font-size: 0.8rem;
                                            transition: all 0.3s ease;
                                            opacity: ${opacity};
                                            text-align: center;
                                        `;
                                        
                                        if (!isDisabled) {
                                            dateCell.addEventListener('click', (e) => {
                                                e.preventDefault();
                                                selectDate(dateInfo.date);
                                            });
                                            
                                            // Hover effects
                                            dateCell.addEventListener('mouseenter', function() {
                                                if (dateInput.value !== dateInfo.date) {
                                                    this.style.backgroundColor = 'rgba(0, 102, 204, 0.1)';
                                                    this.style.transform = 'translateY(-2px)';
                                                    this.style.boxShadow = '0 4px 12px rgba(0, 102, 204, 0.15)';
                                                }
                                            });
                                            
                                            dateCell.addEventListener('mouseleave', function() {
                                                if (dateInput.value !== dateInfo.date) {
                                                    this.style.backgroundColor = 'rgba(6, 214, 160, 0.1)';
                                                    this.style.transform = 'translateY(0)';
                                                    this.style.boxShadow = 'none';
                                                }
                                            });
                                        } else {
                                            dateCell.disabled = true;
                                        }
                                        
                                        calendarGrid.appendChild(dateCell);
                                    });
                                }
                                
                                // Select a date
                                async function selectDate(dateStr) {
                                    dateInput.value = dateStr;
                                    timeInput.value = '';
                                    
                                    // Update UI
                                    selectedDateDisplay.style.display = 'block';
                                    selectedDateText.textContent = formatDateDisplay(dateStr);
                                    selectedTimeDisplay.style.display = 'none';
                                    
                                    // Reload calendar to update highlighting
                                    await loadCalendar();
                                    
                                    // Load time slots
                                    await loadTimeSlots(dateStr);
                                }
                                
                                // Load time slots for selected date
                                async function loadTimeSlots(dateStr) {
                                    try {
                                        timeSlotContainer.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Loading available times...</div>';
                                        timeSelectionMsg.style.display = 'none';
                                        
                                        const response = await fetch('get_available_slots.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({
                                                date: dateStr,
                                                department_id: departmentId
                                            })
                                        });
                                        
                                        const data = await response.json();
                                        
                                        if (data.success) {
                                            renderTimeSlots(data.slots);
                                            
                                            if (data.is_fully_booked) {
                                                timeSelectionMsg.innerHTML = `<i class="fas fa-exclamation-circle" style="color: var(--danger-color); margin-right: 0.5rem;"></i> <strong>No available time slots</strong> for this date. Please select another date.`;
                                                timeSelectionMsg.style.display = 'block';
                                            }
                                        } else {
                                            timeSlotContainer.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 1rem; color: var(--danger-color);">Error loading time slots. Please try again.</div>';
                                        }
                                    } catch (error) {
                                        console.error('Error fetching time slots:', error);
                                        timeSlotContainer.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 1rem; color: var(--danger-color);">Error loading time slots. Please try again.</div>';
                                    }
                                }
                                
                                // Render time slot buttons
                                function renderTimeSlots(slots) {
                                    timeSlotContainer.innerHTML = '';
                                    
                                    if (!slots || slots.length === 0) {
                                        timeSelectionMsg.innerHTML = '<i class="fas fa-exclamation-circle" style="color: var(--danger-color); margin-right: 0.5rem;"></i> No available time slots for this date.';
                                        timeSelectionMsg.style.display = 'block';
                                        return;
                                    }
                                    
                                    // Get current time for real-time filtering
                                    const now = new Date();
                                    const currentHour = now.getHours();
                                    const currentMinute = now.getMinutes();
                                    const currentTime = currentHour * 60 + currentMinute; // Convert to minutes
                                    const fourPMTime = 16 * 60; // 4 PM in minutes (16:00)
                                    
                                    // Check if it's past 4 PM today
                                    const isToday = dateInput.value === '<?php echo date("Y-m-d"); ?>';
                                    const isAfterFourPM = isToday && currentTime >= fourPMTime;
                                    
                                    slots.forEach(slot => {
                                        // Parse slot time
                                        const [hours, minutes] = slot.time.split(':');
                                        const slotHour = parseInt(hours);
                                        const slotMinute = parseInt(minutes);
                                        
                                        // Check if slot is after 4 PM and we're filtering today
                                        let isHiddenAfter4PM = false;
                                        if (isAfterFourPM) {
                                            // If today is after 4 PM, hide all remaining time slots
                                            isHiddenAfter4PM = true;
                                        }
                                        
                                        // Skip rendering this slot if it should be hidden
                                        if (isHiddenAfter4PM) {
                                            return;
                                        }
                                        
                                        const button = document.createElement('button');
                                        button.type = 'button';
                                        button.className = 'time-slot-button';
                                        
                                        const isAvailable = slot.available;
                                        const bgColor = isAvailable ? 'var(--success-color)' : 'var(--danger-color)';
                                        const isSelected = timeInput.value === slot.time;
                                        
                                        button.style.cssText = `
                                            padding: 0.6rem 0.5rem;
                                            border: 1px solid ${bgColor};
                                            background-color: ${isSelected ? bgColor : 'white'};
                                            color: ${isSelected ? 'white' : bgColor};
                                            border-radius: 6px;
                                            cursor: ${isAvailable ? 'pointer' : 'not-allowed'};
                                            font-weight: 600;
                                            font-size: 0.85rem;
                                            transition: all 0.3s ease;
                                            opacity: ${isAvailable ? '1' : '0.5'};
                                            text-align: center;
                                        `;
                                        
                                        button.textContent = slot.display_time;
                                        
                                        if (isAvailable) {
                                            button.addEventListener('click', (e) => {
                                                e.preventDefault();
                                                selectTime(slot.time, slot.display_time);
                                            });
                                            
                                            button.addEventListener('mouseenter', function() {
                                                if (timeInput.value !== slot.time) {
                                                    this.style.backgroundColor = 'rgba(6, 214, 160, 0.1)';
                                                    this.style.transform = 'translateY(-2px)';
                                                    this.style.boxShadow = '0 4px 12px rgba(6, 214, 160, 0.2)';
                                                }
                                            });
                                            
                                            button.addEventListener('mouseleave', function() {
                                                if (timeInput.value !== slot.time) {
                                                    this.style.backgroundColor = 'white';
                                                    this.style.transform = 'translateY(0)';
                                                    this.style.boxShadow = 'none';
                                                } else {
                                                    this.style.backgroundColor = 'var(--success-color)';
                                                    this.style.transform = 'translateY(0)';
                                                    this.style.boxShadow = 'none';
                                                }
                                            });
                                        } else {
                                            button.disabled = true;
                                            button.title = 'This slot is fully booked';
                                        }
                                        
                                        timeSlotContainer.appendChild(button);
                                    });
                                    
                                    // Show message if all slots are hidden due to 4 PM cutoff
                                    if (isAfterFourPM && timeSlotContainer.children.length === 0) {
                                        timeSelectionMsg.innerHTML = '<i class="fas fa-clock" style="color: var(--warning-color); margin-right: 0.5rem;"></i> <strong>Booking cutoff passed:</strong> Appointments can no longer be scheduled for today after 4:00 PM. Please select a future date.';
                                        timeSelectionMsg.style.display = 'block';
                                    }
                                }
                                
                                // Select a time slot
                                function selectTime(timeStr, displayTimeStr) {
                                    timeInput.value = timeStr;
                                    
                                    // Update display
                                    selectedTimeDisplay.style.display = 'block';
                                    selectedTimeText.textContent = displayTimeStr;
                                    
                                    // Update button styling
                                    document.querySelectorAll('.time-slot-button').forEach(btn => {
                                        if (btn.textContent === displayTimeStr) {
                                            btn.style.backgroundColor = 'var(--success-color)';
                                            btn.style.color = 'white';
                                        } else if (btn.disabled === false) {
                                            btn.style.backgroundColor = 'white';
                                            btn.style.color = 'var(--success-color)';
                                        }
                                    });
                                    
                                    // Show success message
                                    statusMessage.style.display = 'block';
                                    statusMessage.style.borderLeftColor = 'var(--success-color)';
                                    statusMessage.style.backgroundColor = 'rgba(6, 214, 160, 0.1)';
                                    statusMessage.innerHTML = `<i class="fas fa-check-circle" style="color: var(--success-color); margin-right: 0.5rem;"></i> <strong>${displayTimeStr}</strong> selected for <strong>${formatDateDisplay(dateInput.value)}</strong>`;
                                }
                                
                                // Month navigation
                                function goToPreviousMonth() {
                                    currentMonth--;
                                    if (currentMonth < 0) {
                                        currentMonth = 11;
                                        currentYear--;
                                    }
                                    loadCalendar();
                                }
                                
                                function goToNextMonth() {
                                    currentMonth++;
                                    if (currentMonth > 11) {
                                        currentMonth = 0;
                                        currentYear++;
                                    }
                                    loadCalendar();
                                }
                                
                                // Show error message
                                function showError(message) {
                                    statusMessage.style.display = 'block';
                                    statusMessage.style.borderLeftColor = 'var(--danger-color)';
                                    statusMessage.style.backgroundColor = 'rgba(239, 71, 111, 0.1)';
                                    statusMessage.innerHTML = `<i class="fas fa-exclamation-circle" style="color: var(--danger-color); margin-right: 0.5rem;"></i> ${message}`;
                                }
                                
                                // Event listeners for month navigation
                                prevMonthBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    goToPreviousMonth();
                                });
                                
                                nextMonthBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    goToNextMonth();
                                });
                                
                                // Form validation on submit
                                const form = document.querySelector('form');
                                if (form) {
                                    form.addEventListener('submit', function(e) {
                                        if (!dateInput.value) {
                                            e.preventDefault();
                                            alert('Please select a date.');
                                            return false;
                                        }
                                        if (!timeInput.value) {
                                            e.preventDefault();
                                            alert('Please select a time slot.');
                                            return false;
                                        }
                                        return true;
                                    });
                                }
                                
                                // Initialize calendar on page load
                                loadCalendar();
                            })();
                        </script>

                    <?php elseif ($step === 6): ?>
                        <!-- Step 6: Upload File (Optional) -->
                        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">Upload Documents</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">You can upload supporting documents (optional). Maximum 2MB, JPG/PNG/PDF only.</p>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="uploaded_file" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 1rem;">Select File (Optional)</label>
                                <div style="border: 2px dashed var(--border-color); border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s ease;" id="drop-area">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1rem; display: block;"></i>
                                    <p style="color: var(--text-primary); font-weight: 500; margin-bottom: 0.5rem;">Drag & drop your file here</p>
                                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">or</p>
                                    <input type="file" class="form-control" id="uploaded_file" name="uploaded_file" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                                    <button type="button" onclick="document.getElementById('uploaded_file').click();" class="btn btn-secondary" style="background-color: var(--secondary-color); border: none;">Browse Files</button>
                                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 1rem; margin-bottom: 0;">JPG, PNG, PDF up to 2MB</p>
                                </div>
                            </div>
                            <div style="display: flex; gap: 1rem;">
                                <a href="?step=5" class="btn btn-outline-primary btn-custom" style="color: var(--primary-color); background: white; flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;\">Back</a>
                                <button type="submit" class="btn btn-primary btn-custom" style="flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;\">Next</button>
                            </div>
                        </form>

                    <?php elseif ($step === 7): ?>
                        <!-- Step 7: Confirm Appointment -->
                        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 2rem;">Review & Submit</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Please review your appointment details before submitting:</p>
                        <div class="card" style="border: none; box-shadow: var(--card-shadow); border-radius: 12px; margin-bottom: 2rem;">
                            <div class="card-body" style="padding: 2rem;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">Service</p>
                                        <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 0;">
                                            <?php
                                                $conn = getDBConnection();
                                                $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
                                                $stmt->bind_param("i", $_SESSION['booking_department_id']);
                                                $stmt->execute();
                                                $dept_name = $stmt->get_result()->fetch_assoc()['name'];
                                                echo htmlspecialchars($dept_name);
                                                $conn->close();
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">For</p>
                                        <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 0;">
                                            <?php echo $_SESSION['booking_appointment_for'] === 'me' ? 'Myself' : htmlspecialchars($_SESSION['booking_recipient_name']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">Purpose</p>
                                        <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 0;">
                                            <?php echo htmlspecialchars($_SESSION['booking_agenda']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">Date & Time</p>
                                        <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 0;">
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($_SESSION['booking_date'] . ' ' . $_SESSION['booking_time'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">PWD Status</p>
                                        <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 0;">
                                            <?php echo ($_SESSION['booking_is_pwd'] ?? 0) == 1 ? 'Yes' : 'No'; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">Documents</p>
                                        <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 0;">
                                            <?php echo (isset($_SESSION['booking_pwd_proof']) || isset($_SESSION['booking_uploaded_file'])) ? 'Uploaded' : 'None'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <form method="POST">
                            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                                <a href="?step=6" class="btn btn-outline-primary btn-custom" style="color: var(--primary-color); background: white; flex: 1; padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Back</a>
                                <button type="submit" name="confirm" class="btn btn-success btn-custom" style="flex: 1; background-color: var(--success-color); padding: 0.75rem 1rem; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 500; width: 100%; min-width: 0;">Submit Appointment</button>
                            </div>
                            <div style="display: flex; gap: 1rem;">
                                <a href="book_appointment.php?step=1" class="btn btn-secondary btn-custom" style="flex: 1; background-color: #6c757d; border: none; color: white; padding: 0.75rem 1rem; height: 44px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; font-size: 1rem; display: flex; align-items: center; justify-content: center; width: 100%; min-width: 0;">Start Over</a>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide PWD proof upload
document.querySelectorAll('input[name="is_pwd"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('pwd_proof_section').style.display = this.value === '1' ? 'block' : 'none';
    });
});
</script>

<script>
// Philippine public holidays (sample - you may want to expand this list)
const philippineHolidays = [
    '2025-01-01', // New Year's Day
    '2025-01-29', // Chinese New Year (estimated)
    '2025-04-09', // Maundy Thursday
    '2025-04-10', // Good Friday
    '2025-04-11', // Black Saturday
    '2025-04-18', // Araw ng Kagitingan
    '2025-05-01', // Labor Day
    '2025-06-12', // Independence Day
    '2025-08-25', // National Heroes Day
    '2025-11-30', // Bonifacio Day
    '2025-12-25', // Christmas Day
    '2025-12-30', // Rizal Day
    '2025-12-31', // New Year's Eve
    // Add more holidays as needed
];

// Function to check if a date is a weekend or holiday
function isDateDisabled(dateString) {
    const date = new Date(dateString);
    const dayOfWeek = date.getDay(); // 0=Sunday, 6=Saturday

    // Allow weekends but highlight them
    // Only disable holidays
    if (philippineHolidays.includes(dateString)) {
        return true;
    }

    return false;
}

// Function to check if a date is a weekend
function isWeekend(dateString) {
    const date = new Date(dateString);
    const dayOfWeek = date.getDay(); // 0=Sunday, 6=Saturday
    return dayOfWeek === 0 || dayOfWeek === 6;
}

// Show/hide someone else details
document.querySelectorAll('input[name="appointment_for"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('someone_else_details').style.display = this.value === 'someone_else' ? 'block' : 'none';
    });
});

// Show/hide custom agenda input
document.querySelector('select[name="agenda"]').addEventListener('change', function() {
    document.getElementById('other_agenda').style.display = this.value === 'Other' ? 'block' : 'none';
});

// Update available time slots when date changes
document.getElementById('appointment_date')?.addEventListener('change', function() {
    const selectedDate = this.value;
    const date = new Date(selectedDate);
    const dayOfWeek = date.getDay();

    // Check if date is disabled (only holidays)
    if (isDateDisabled(selectedDate)) {
        alert('Selected date is not available for appointments (holiday). Please choose a different date.');
        this.value = '<?php echo $_SESSION['booking_date'] ?? date('Y-m-d', strtotime('+1 day')); ?>';
        return;
    }

    // Highlight weekend dates in red
    if (isWeekend(selectedDate)) {
        this.style.color = 'red';
    } else {
        this.style.color = '';
    }

    // This would need AJAX to update slots dynamically
    // For now, we'll reload the page with the new date
    window.location.href = 'book_appointment.php?step=4&date=' + selectedDate;
});

// Initialize date picker with disabled dates
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('appointment_date');
    if (dateInput) {
        // Set initial color if it's a weekend
        const initialDate = dateInput.value;
        if (initialDate && isWeekend(initialDate)) {
            dateInput.style.color = 'red';
        }

        dateInput.addEventListener('input', function() {
            const selectedDate = this.value;
            if (selectedDate && isDateDisabled(selectedDate)) {
                alert('Selected date is not available for appointments (holiday). Please choose a different date.');
                this.value = '<?php echo $_SESSION['booking_date'] ?? date('Y-m-d', strtotime('+1 day')); ?>';
                this.style.color = '';
            } else if (selectedDate && isWeekend(selectedDate)) {
                this.style.color = 'red';
            } else {
                this.style.color = '';
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>