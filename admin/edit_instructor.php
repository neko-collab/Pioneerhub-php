<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if instructor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: instructors.php');
    exit;
}

$instructor_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture updated information
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $experience_years = $_POST['experience_years'] ?? 0;
    
    // Update user information
    $user_result = executeQuery(
        "UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'instructor'",
        [$name, $email, $instructor_id],
        "ssi"
    );
    
    // Check if instructor details exist
    $check_result = executeQuery(
        "SELECT id FROM instructor_details WHERE user_id = ?",
        [$instructor_id],
        "i"
    );
    $check_set = $check_result->get_result();
    
    if ($check_set->num_rows > 0) {
        // Update existing record
        $row = $check_set->fetch_assoc();
        $detail_id = $row['id'];
        
        $details_result = executeQuery(
            "UPDATE instructor_details SET specialization = ?, bio = ?, qualification = ?, experience_years = ?, updated_at = NOW() WHERE id = ?",
            [$specialization, $bio, $qualification, $experience_years, $detail_id],
            "sssii"
        );
    } else {
        // Insert new record
        $details_result = executeQuery(
            "INSERT INTO instructor_details (user_id, specialization, bio, qualification, experience_years) VALUES (?, ?, ?, ?, ?)",
            [$instructor_id, $specialization, $bio, $qualification, $experience_years],
            "isssi"
        );
    }
    
    // Redirect back to instructor profile
    header("Location: instructor_profile.php?id=$instructor_id&msg=updated");
    exit;
}

// Get instructor details
$instructor = null;
$result = executeQuery(
    "SELECT u.*, id.specialization, id.bio, id.qualification, id.experience_years 
     FROM users u 
     LEFT JOIN instructor_details id ON u.id = id.user_id 
     WHERE u.id = ? AND u.role = 'instructor'", 
    [$instructor_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $instructor = $row;
    } else {
        // Instructor not found
        header('Location: instructors.php');
        exit;
    }
    $result_set->close();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Edit Instructor</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="instructors.php">Instructors</a></li>
                    <li class="breadcrumb-item"><a href="instructor_profile.php?id=<?= $instructor_id ?>"><?= htmlspecialchars($instructor['name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="instructor_profile.php?id=<?= $instructor_id ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Profile
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Instructor Information</h6>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Account Information</h5>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($instructor['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($instructor['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="mb-3">Professional Details</h5>
                        <div class="mb-3">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   value="<?= htmlspecialchars($instructor['specialization'] ?? '') ?>"
                                   placeholder="E.g., Web Development, Machine Learning, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?= htmlspecialchars($instructor['qualification'] ?? '') ?>"
                                   placeholder="E.g., PhD in Computer Science, Masters in Data Science, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label for="experience_years" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                   value="<?= intval($instructor['experience_years'] ?? 0) ?>" min="0">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="bio" class="form-label">Professional Bio</label>
                    <textarea class="form-control" id="bio" name="bio" rows="4" 
                              placeholder="Provide a professional background and teaching experience..."><?= htmlspecialchars($instructor['bio'] ?? '') ?></textarea>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="instructor_profile.php?id=<?= $instructor_id ?>" class="btn btn-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
