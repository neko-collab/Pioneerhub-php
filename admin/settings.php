<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Fetch PioneerHub info
$pioneerhub_info = null;
$result = executeQuery("SELECT * FROM pioneerhub_info LIMIT 1", [], "");
if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $pioneerhub_info = $row;
    }
    $result_set->close();
}

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_hub_info') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $website = $_POST['website'] ?? '';
        $description = $_POST['description'] ?? '';
        
        // Handle logo upload
        $logo = $pioneerhub_info['logo'] ?? ''; // Default to current logo
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (in_array($_FILES['logo']['type'], $allowed_types) && $_FILES['logo']['size'] <= $max_size) {
                $upload_dir = '../uploads/';
                
                // Create upload directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'logo_' . time() . '_' . basename($_FILES['logo']['name']);
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                    $logo = $filename;
                } else {
                    $message = 'Failed to upload logo.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Invalid logo file. Please upload a valid image file (JPG, PNG, GIF) under 2MB.';
                $message_type = 'danger';
            }
        }
        
        if (empty($message)) {
            if ($pioneerhub_info) {
                // Update existing record
                $result = executeQuery(
                    "UPDATE pioneerhub_info SET name=?, email=?, logo=?, address=?, phone=?, website=?, description=? WHERE id=?",
                    [$name, $email, $logo, $address, $phone, $website, $description, $id],
                    "sssssssi"
                );
            } else {
                // Insert new record
                $result = executeQuery(
                    "INSERT INTO pioneerhub_info (name, email, logo, address, phone, website, description) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$name, $email, $logo, $address, $phone, $website, $description],
                    "sssssss"
                );
            }
            
            if ($result) {
                $message = 'PioneerHub information updated successfully.';
                $message_type = 'success';
                
                // Refresh the data
                $result = executeQuery("SELECT * FROM pioneerhub_info LIMIT 1", [], "");
                if ($result) {
                    $result_set = $result->get_result();
                    if ($row = $result_set->fetch_assoc()) {
                        $pioneerhub_info = $row;
                    }
                    $result_set->close();
                }
            } else {
                $message = 'Failed to update PioneerHub information.';
                $message_type = 'danger';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-2 text-gray-800">Settings</h1>
    <p class="mb-4">Manage PioneerHub platform settings and information.</p>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">PioneerHub Information</h6>
                </div>
                <div class="card-body">
                    <form action="" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_hub_info">
                        <?php if ($pioneerhub_info): ?>
                            <input type="hidden" name="id" value="<?= $pioneerhub_info['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Organization Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= $pioneerhub_info ? htmlspecialchars($pioneerhub_info['name']) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= $pioneerhub_info ? htmlspecialchars($pioneerhub_info['email']) : '' ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= $pioneerhub_info ? htmlspecialchars($pioneerhub_info['phone']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="website" class="form-label">Website</label>
                                <input type="url" class="form-control" id="website" name="website" value="<?= $pioneerhub_info ? htmlspecialchars($pioneerhub_info['website']) : '' ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?= $pioneerhub_info ? htmlspecialchars($pioneerhub_info['address']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= $pioneerhub_info ? htmlspecialchars($pioneerhub_info['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="logo" class="form-label">Logo</label>
                            <?php if ($pioneerhub_info && !empty($pioneerhub_info['logo'])): ?>
                                <div class="mb-2">
                                    <img src="../uploads/<?= htmlspecialchars($pioneerhub_info['logo']) ?>" alt="Current Logo" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                            <div class="form-text">Upload a new logo (JPG, PNG, GIF, max 2MB)</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
