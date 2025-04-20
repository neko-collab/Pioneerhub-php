<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture basic user information
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Capture instructor details
    $specialization = $_POST['specialization'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $experience_years = $_POST['experience_years'] ?? 0;
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Name, email, and password are required fields.";
    } else {
        // Check if email already exists
        $email_check = executeQuery("SELECT id FROM users WHERE email = ?", [$email], "s");
        $email_result = $email_check->get_result();
        
        if ($email_result->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        } else {
            // Begin transaction
            global $conn;
            $conn->begin_transaction();
            
            try {
                // Create password hash
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                // Insert into users table
                $user_result = executeQuery(
                    "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'instructor')",
                    [$name, $email, $password_hash],
                    "sss"
                );
                
                if ($user_result) {
                    $user_id = $conn->insert_id;
                    
                    // Insert into instructor_details table
                    $details_result = executeQuery(
                        "INSERT INTO instructor_details (user_id, specialization, bio, qualification, experience_years) VALUES (?, ?, ?, ?, ?)",
                        [$user_id, $specialization, $bio, $qualification, $experience_years],
                        "isssi"
                    );
                    
                    if ($details_result) {
                        // Commit the transaction
                        $conn->commit();
                        $success = "Instructor added successfully.";
                    } else {
                        throw new Exception("Failed to add instructor details.");
                    }
                } else {
                    throw new Exception("Failed to create user account.");
                }
            } catch (Exception $e) {
                // Rollback the transaction on error
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
        $email_result->close();
    }
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Add New Instructor</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="instructors.php">Instructors</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Instructor</li>
                </ol>
            </nav>
        </div>
        <a href="instructors.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Instructors
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Instructor Information</h6>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Account Information</h5>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="mb-3">Professional Details</h5>
                        <div class="mb-3">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   placeholder="E.g., Web Development, Machine Learning, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   placeholder="E.g., PhD in Computer Science, Masters in Data Science, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label for="experience_years" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" min="0" value="0">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="bio" class="form-label">Professional Bio</label>
                    <textarea class="form-control" id="bio" name="bio" rows="4" 
                              placeholder="Provide a professional background and teaching experience..."></textarea>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="reset" class="btn btn-secondary me-md-2">Clear Form</button>
                    <button type="submit" class="btn btn-primary">Add Instructor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
