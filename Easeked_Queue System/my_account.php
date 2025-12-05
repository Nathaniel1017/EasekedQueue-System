<?php
require_once 'config.php';
requireLogin();

$page_title = 'My Account';
include 'header.php';

$user = getCurrentUser();
$errors = [];
$success = '';
$user_department = null;

// Get department info if user is an admin
if ($user && in_array($user['role'], ['admin', 'super_admin']) && !empty($user['department_id'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name FROM departments WHERE id = ?");
    $stmt->bind_param("i", $user['department_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_department = $result->fetch_assoc();
    }
    $stmt->close();
    $conn->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if (empty($phone_number)) {
        $errors[] = 'Phone number is required.';
    }

    // Check if email is already taken by another user
    if (empty($errors) && $email !== $user['email']) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = 'Email is already taken by another user.';
        }
        $stmt->close();
        $conn->close();
    }

    // Password change validation
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password.';
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }
    }

    // Update user information
    if (empty($errors)) {
        $conn = getDBConnection();
        
        if (!empty($new_password)) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $full_name, $email, $phone_number, $new_password_hash, $user['id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $email, $phone_number, $user['id']);
        }

        if ($stmt->execute()) {
            $success = 'Account information updated successfully!';
            // Refresh user data
            $user = getCurrentUser();
        } else {
            $errors[] = 'Failed to update account information.';
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card" style="border: none; box-shadow: var(--card-shadow); border-radius: 12px;">
            <div class="card-header" style="background-color: white; border-bottom: 1px solid var(--border-color); padding: 2rem;">
                <h3 class="card-title mb-0" style="color: var(--primary-color); font-weight: 700; font-size: 1.5rem;">
                    <i class="fas fa-user-circle"></i> 
                    <?php 
                        $role_labels = [
                            'citizen' => 'My Account',
                            'admin' => 'Admin Account',
                            'super_admin' => 'Super Admin Account'
                        ];
                        echo $role_labels[$user['role']] ?? 'My Account';
                    ?>
                </h3>
            </div>
            <div class="card-body" style="padding: 2rem;">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" style="background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; padding: 1.25rem;">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="background-color: #f0fdf4; border: 1px solid #86efac; color: #166534; border-radius: 8px; padding: 1.25rem;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <h5 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle"></i> Personal Information
                    </h5>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                    </div>

                    <div class="mb-3">
                        <label for="phone_number" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Phone Number</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Account Type</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" readonly style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--light-bg);">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Account Status</label>
                            <input type="text" class="form-control" value="<?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>" readonly style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--light-bg);">
                        </div>
                    </div>

                    <?php if ($user_department): ?>
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Assigned Department</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_department['name']); ?>" readonly style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--light-bg);">
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Member Since</label>
                        <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--light-bg);">
                    </div>

                    <hr style="border-color: var(--border-color); margin: 2rem 0;">

                    <h5 style="color: var(--primary-color); font-weight: 700; margin-bottom: 1.5rem;">
                        <i class="fas fa-key"></i> Change Password
                    </h5>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter your current password" style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                        <div style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.25rem;">Leave blank if you don't want to change your password</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password (minimum 6 characters)" style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label" style="font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem;">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter your new password" style="padding: 0.875rem 1rem; border: 1px solid var(--border-color); border-radius: 8px;">
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <a href="<?php echo ($user['role'] === 'citizen') ? 'dashboard.php' : 'admin_dashboard.php'; ?>" class="btn btn-outline-primary" style="border: 1px solid var(--primary-color); color: var(--primary-color); background: white;">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary btn-custom" style="flex: 1;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>