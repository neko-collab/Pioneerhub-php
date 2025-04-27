<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if course ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: courses.php');
    exit;
}

$course_id = $_GET['id'];

// Handle student verification status toggle
if (isset($_GET['verify_id']) && !empty($_GET['verify_id'])) {
    $registration_id = $_GET['verify_id'];
    $status = isset($_GET['status']) ? (int)$_GET['status'] : 0;
    
    // Update verification status
    $result = executeQuery(
        "UPDATE course_registrations SET verified=? WHERE id=?",
        [$status, $registration_id],
        "ii"
    );
    
    // If verifying the registration, send email notification
    if ($status == 1) {
        // Get user information and course title for the email
        $info_result = executeQuery(
            "SELECT u.name, u.email, c.title 
             FROM course_registrations cr
             JOIN users u ON cr.user_id = u.id
             JOIN courses c ON cr.course_id = c.id
             WHERE cr.id=?",
            [$registration_id],
            "i"
        );
        
        if ($info_result) {
            $info_set = $info_result->get_result();
            if ($user_info = $info_set->fetch_assoc()) {
                // Include mailer functions
                include_once '../backend/mailer.php';
                
                // Send email notification
                sendCourseRegistrationApproval(
                    $user_info['email'],
                    $user_info['name'],
                    $user_info['title']
                );
            }
            $info_set->close();
        }
    }
    
    // Redirect back to course details
    header("Location: course_details.php?id=$course_id&msg=status_updated");
    exit;
}

// Handle registration deletion
if (isset($_GET['delete_reg_id']) && !empty($_GET['delete_reg_id'])) {
    $registration_id = $_GET['delete_reg_id'];
    
    // Delete the registration
    $result = executeQuery(
        "DELETE FROM course_registrations WHERE id=?", 
        [$registration_id], 
        "i"
    );
    
    // Redirect back to course details
    header("Location: course_details.php?id=$course_id&msg=registration_deleted");
    exit;
}

// Fetch course details
$course = null;
$result = executeQuery(
    "SELECT courses.*, users.name as instructor_name 
     FROM courses 
     LEFT JOIN users ON courses.instructor_id = users.id 
     WHERE courses.id=?", 
    [$course_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $course = $row;
    } else {
        // Course not found
        header('Location: courses.php');
        exit;
    }
    $result_set->close();
}

// Fetch registrations for this course
$registrations = [];
$result = executeQuery(
    "SELECT cr.*, u.name, u.email, p.payment_status, p.payment_gateway, p.transaction_id
     FROM course_registrations cr
     JOIN users u ON cr.user_id = u.id
     LEFT JOIN payments p ON cr.user_id = p.user_id AND cr.course_id = p.course_id
     WHERE cr.course_id=?
     ORDER BY cr.registered_at DESC", 
    [$course_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $registrations[] = $row;
    }
    $result_set->close();
}

// Handle adding a new registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_registration') {
    $user_id = $_POST['user_id'] ?? '';
    $verified = isset($_POST['verified']) ? 1 : 0;
    $payment_status = $_POST['payment_status'] ?? 'pending';
    $payment_gateway = $_POST['payment_gateway'] ?? '';
    $transaction_id = $_POST['transaction_id'] ?? '';
    
    if (!empty($user_id)) {
        // Check if registration already exists
        $check_result = executeQuery(
            "SELECT id FROM course_registrations WHERE user_id=? AND course_id=?",
            [$user_id, $course_id],
            "ii"
        );
        
        $check_set = $check_result->get_result();
        if ($check_set->num_rows > 0) {
            $check_set->close();
            header("Location: course_details.php?id=$course_id&error=registration_exists");
            exit;
        }
        $check_set->close();
        
        // Begin transaction
        global $conn;
        $conn->begin_transaction();
        
        try {
            // Add new registration
            $result = executeQuery(
                "INSERT INTO course_registrations (user_id, course_id, registered_at, verified) VALUES (?, ?, NOW(), ?)",
                [$user_id, $course_id, $verified],
                "iii"
            );
            
            // Add payment record
            $amount = $course['price'];
            executeQuery(
                "INSERT INTO payments (user_id, course_id, amount, payment_status, payment_gateway, transaction_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$user_id, $course_id, $amount, $payment_status, $payment_gateway, $transaction_id],
                "iidsss"
            );
            
            // Commit transaction
            $conn->commit();
            
            header("Location: course_details.php?id=$course_id&msg=registration_added");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            header("Location: course_details.php?id=$course_id&error=db_error");
            exit;
        }
    } else {
        header("Location: course_details.php?id=$course_id&error=invalid_user");
        exit;
    }
}

// Fetch available users for dropdown
$available_users = [];
$result = executeQuery(
    "SELECT id, name, email FROM users 
     WHERE id NOT IN (
         SELECT user_id FROM course_registrations WHERE course_id=?
     )",
    [$course_id],
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $available_users[] = $row;
    }
    $result_set->close();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Course Details</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Course Details</li>
                </ol>
            </nav>
        </div>
        <a href="courses.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Courses
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'status_updated'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Verification status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'registration_deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Registration deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'registration_added'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Registration added successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <?php if ($_GET['error'] === 'registration_exists'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                This user is already registered for this course.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['error'] === 'invalid_user'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Please select a valid user.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['error'] === 'db_error'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                A database error occurred. Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Course Information</h6>
                </div>
                <div class="card-body">
                    <h2 class="h4 mb-3"><?= htmlspecialchars($course['title']) ?></h2>
                    
                    <div class="mb-3">
                        <p class="text-muted mb-1">Instructor:</p>
                        <p><strong><?= htmlspecialchars($course['instructor_name']) ?></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <p class="text-muted mb-1">Price:</p>
                        <p><strong>Rs. <?= number_format($course['price'], 2) ?></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <p class="text-muted mb-1">Trending Status:</p>
                        <p>
                            <?= $course['is_trending'] ? 
                                '<span class="badge bg-success">Trending</span>' : 
                                '<span class="badge bg-secondary">Not Trending</span>' ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <p class="text-muted mb-1">Created:</p>
                        <p><?= date('F d, Y', strtotime($course['created_at'])) ?></p>
                    </div>
                    
                    <hr>
                    
                    <div>
                        <p class="text-muted mb-1">Description:</p>
                        <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Course Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Registrations</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($registrations) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Verified Students</div>
                                            <?php
                                            $verified_count = 0;
                                            foreach ($registrations as $reg) {
                                                if ($reg['verified'] == 1) {
                                                    $verified_count++;
                                                }
                                            }
                                            ?>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $verified_count ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Verification Rate</div>
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto">
                                                    <?php 
                                                    $percentage = count($registrations) > 0 ? 
                                                        round(($verified_count / count($registrations)) * 100) : 0;
                                                    ?>
                                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?= $percentage ?>%</div>
                                                </div>
                                                <div class="col">
                                                    <div class="progress progress-sm mr-2">
                                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?= $percentage ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRegistrationModal">
                            <i class="fas fa-plus fa-sm"></i> Add New Registration
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Course Registrations</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Registration Date</th>
                            <th>Verification</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No registrations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td><?= $reg['id'] ?></td>
                                    <td><?= htmlspecialchars($reg['name']) ?></td>
                                    <td><?= htmlspecialchars($reg['email']) ?></td>
                                    <td><?= date('M d, Y', strtotime($reg['registered_at'])) ?></td>
                                    <td>
                                        <?php if ($reg['verified'] == 1): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $payment_status_class = 'bg-secondary';
                                        if ($reg['payment_status'] === 'completed') {
                                            $payment_status_class = 'bg-success';
                                        } elseif ($reg['payment_status'] === 'pending') {
                                            $payment_status_class = 'bg-warning';
                                        } elseif ($reg['payment_status'] === 'failed') {
                                            $payment_status_class = 'bg-danger';
                                        }
                                        ?>
                                        <span class="badge <?= $payment_status_class ?>">
                                            <?= ucfirst($reg['payment_status'] ?? 'N/A') ?>
                                        </span>
                                        <?php if (!empty($reg['transaction_id'])): ?>
                                            <span class="badge bg-info" title="Transaction ID"><?= htmlspecialchars($reg['transaction_id']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($reg['verified'] == 1): ?>
                                            <a href="course_details.php?id=<?= $course_id ?>&verify_id=<?= $reg['id'] ?>&status=0" class="btn btn-sm btn-warning">
                                                <i class="fas fa-times-circle"></i> Unverify
                                            </a>
                                        <?php else: ?>
                                            <a href="course_details.php?id=<?= $course_id ?>&verify_id=<?= $reg['id'] ?>&status=1" class="btn btn-sm btn-success">
                                                <i class="fas fa-check-circle"></i> Verify
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="course_details.php?id=<?= $course_id ?>&delete_reg_id=<?= $reg['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this registration?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Registration Modal -->
<div class="modal fade" id="addRegistrationModal" tabindex="-1" aria-labelledby="addRegistrationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRegistrationModalLabel">Add New Registration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_registration">
                    
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Select User</label>
                        <select class="form-control" id="user_id" name="user_id" required>
                            <option value="">Select User</option>
                            <?php foreach ($available_users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="verified" name="verified">
                        <label class="form-check-label" for="verified">Mark as Verified</label>
                    </div>
                    
                    <hr>
                    <h6>Payment Information</h6>
                    
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-control" id="payment_status" name="payment_status" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_gateway" class="form-label">Payment Gateway</label>
                        <input type="text" class="form-control" id="payment_gateway" name="payment_gateway" placeholder="e.g., Esewa, Khalti, Cash">
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction_id" class="form-label">Transaction ID</label>
                        <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="Transaction reference number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Registration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
