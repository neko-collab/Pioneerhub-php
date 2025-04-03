<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get admin ID from session
$admin_id = $_SESSION['admin_id'];

// Initialize message variables
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Get form data
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Basic validation
        if (empty($name) || empty($email)) {
            $message = 'Name and email are required.';
            $message_type = 'danger';
        } else {
            // Check if email exists for other users
            $check_email = executeQuery("SELECT * FROM users WHERE email=? AND id!=?", [$email, $admin_id], "si");
            $result = $check_email->get_result();
            if ($result->num_rows > 0) {
                $message = 'Email is already in use by another account.';
                $message_type = 'danger';
            } else {
                // Start with basic update (name and email)
                $update_fields = "name=?, email=?";
                $params = [$name, $email];
                $types = "ss";
                
                // Handle profile picture upload
                $profile_pic = null;
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    if (in_array($_FILES['profile_pic']['type'], $allowed_types) && $_FILES['profile_pic']['size'] <= $max_size) {
                        $upload_dir = '../uploads/';
                        
                        // Create upload directory if it doesn't exist
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $filename = 'profile_' . time() . '_' . basename($_FILES['profile_pic']['name']);
                        $target_file = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                            $profile_pic = $filename;
                            $update_fields .= ", profile_pic=?";
                            $params[] = $profile_pic;
                            $types .= "s";
                        } else {
                            $message = 'Failed to upload profile picture.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Invalid profile picture. Please upload a valid image file (JPG, PNG, GIF) under 2MB.';
                        $message_type = 'danger';
                    }
                }
                
                // Handle password change if requested
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    $user_result = executeQuery("SELECT password_hash FROM users WHERE id=?", [$admin_id], "i");
                    $user = $user_result->get_result()->fetch_assoc();
                    
                    if (password_verify($current_password, $user['password_hash'])) {
                        // Check if new password and confirmation match
                        if ($new_password === $confirm_password) {
                            // Password strength validation
                            if (strlen($new_password) < 8) {
                                $message = 'New password must be at least 8 characters long.';
                                $message_type = 'danger';
                            } else {
                                // Add password to update
                                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                                $update_fields .= ", password_hash=?";
                                $params[] = $password_hash;
                                $types .= "s";
                            }
                        } else {
                            $message = 'New password and confirmation do not match.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Current password is incorrect.';
                        $message_type = 'danger';
                    }
                }
                
                // If no error messages set, proceed with update
                if (empty($message)) {
                    // Add admin_id to params
                    $params[] = $admin_id;
                    $types .= "i";
                    
                    // Execute update query
                    $result = executeQuery(
                        "UPDATE users SET $update_fields WHERE id=?",
                        $params,
                        $types
                    );
                    
                    if ($result) {
                        // Update session variables
                        $_SESSION['admin_name'] = $name;
                        $_SESSION['admin_email'] = $email;
                        
                        $message = 'Profile updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update profile.';
                        $message_type = 'danger';
                    }
                }
            }
        }
    }
}

// Fetch current admin info
$admin = null;
$result = executeQuery("SELECT * FROM users WHERE id=?", [$admin_id], "i");
if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $admin = $row;
    }
    $result_set->close();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-2 text-gray-800">Profile Settings</h1>
    <p class="mb-4">Manage your personal information and account settings.</p>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($admin['profile_pic'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($admin['profile_pic']) ?>" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin['name']) ?>&background=random" class="rounded-circle img-thumbnail" width="150" height="150">
                        <?php endif; ?>
                        <h4 class="mt-3"><?= htmlspecialchars($admin['name']) ?></h4>
                        <p class="text-muted"><?= ucfirst($admin['role']) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <p class="mb-1 text-muted">Email Address:</p>
                        <p><?= htmlspecialchars($admin['email']) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <p class="mb-1 text-muted">Account Created:</p>
                        <p><?= date('F d, Y', strtotime($admin['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Profile</h6>
                </div>
                <div class="card-body">
                    <form action="" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="profile_pic" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                            <div class="form-text">Upload a new profile picture (JPG, PNG, GIF, max 2MB)</div>
                        </div>
                        
                        <hr class="my-4">
                        <h5>Change Password</h5>
                        <p class="text-muted small mb-4">Leave password fields blank if you don't want to change your password</p>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                            <div class="form-text">Password must be at least 8 characters long</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);
});
</script>

<?php include 'includes/footer.php'; ?>
