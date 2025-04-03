<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if job ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: jobs.php');
    exit;
}

$job_id = $_GET['id'];

// Handle application status update
if (isset($_GET['application_id']) && !empty($_GET['application_id']) && isset($_GET['status'])) {
    $application_id = $_GET['application_id'];
    $status = $_GET['status'];
    
    if (in_array($status, ['pending', 'reviewed', 'accepted', 'rejected'])) {
        // Update status
        $result = executeQuery(
            "UPDATE job_applications SET status=? WHERE id=?",
            [$status, $application_id],
            "si"
        );
        
        // Redirect back to applications page
        header("Location: job_applications.php?id=$job_id&msg=status_updated");
        exit;
    }
}

// Handle application deletion
if (isset($_GET['delete_id']) && !empty($_GET['delete_id'])) {
    $application_id = $_GET['delete_id'];
    
    // Delete the application
    $result = executeQuery(
        "DELETE FROM job_applications WHERE id=?", 
        [$application_id], 
        "i"
    );
    
    // Redirect back to applications page
    header("Location: job_applications.php?id=$job_id&msg=application_deleted");
    exit;
}

// Fetch job details
$job = null;
$result = executeQuery(
    "SELECT jobs.*, users.name as employer_name 
     FROM jobs 
     LEFT JOIN users ON jobs.posted_by = users.id 
     WHERE jobs.id=?", 
    [$job_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $job = $row;
    } else {
        // Job not found
        header('Location: jobs.php');
        exit;
    }
    $result_set->close();
}

// Fetch applications for this job
$applications = [];
$result = executeQuery(
    "SELECT ja.*, u.name, u.email, u.profile_pic, u.cv
     FROM job_applications ja
     JOIN users u ON ja.user_id = u.id
     WHERE ja.job_id=?
     ORDER BY ja.applied_at DESC", 
    [$job_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $applications[] = $row;
    }
    $result_set->close();
}

// Handle adding a new application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_application') {
    $user_id = $_POST['user_id'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    
    if (!empty($user_id)) {
        // Check if application already exists
        $check_result = executeQuery(
            "SELECT id FROM job_applications WHERE user_id=? AND job_id=?",
            [$user_id, $job_id],
            "ii"
        );
        
        $check_set = $check_result->get_result();
        if ($check_set->num_rows > 0) {
            $check_set->close();
            header("Location: job_applications.php?id=$job_id&error=application_exists");
            exit;
        }
        $check_set->close();
        
        // Add new application
        $result = executeQuery(
            "INSERT INTO job_applications (user_id, job_id, applied_at, status) VALUES (?, ?, NOW(), ?)",
            [$user_id, $job_id, $status],
            "iis"
        );
        
        header("Location: job_applications.php?id=$job_id&msg=application_added");
        exit;
    } else {
        header("Location: job_applications.php?id=$job_id&error=invalid_user");
        exit;
    }
}

// Fetch available users for dropdown (who haven't applied yet)
$available_users = [];
$result = executeQuery(
    "SELECT id, name, email FROM users 
     WHERE role='user' AND id NOT IN (
         SELECT user_id FROM job_applications WHERE job_id=?
     )",
    [$job_id],
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
            <h1 class="h3 mb-0 text-gray-800">Job Applications</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="jobs.php">Jobs</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Applications</li>
                </ol>
            </nav>
        </div>
        <a href="jobs.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Jobs
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'status_updated'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Application status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'application_deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Application deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'application_added'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Application added successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <?php if ($_GET['error'] === 'application_exists'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                This user has already applied for this job.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['error'] === 'invalid_user'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Please select a valid user.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Job Information</h6>
                </div>
                <div class="card-body">
                    <h2 class="h4 mb-3"><?= htmlspecialchars($job['title']) ?></h2>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Company:</p>
                            <p><strong><?= htmlspecialchars($job['company']) ?></strong></p>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Location:</p>
                            <p><strong><?= htmlspecialchars($job['location']) ?></strong></p>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Type:</p>
                            <p>
                                <?php
                                $badge_class = '';
                                switch ($job['job_type']) {
                                    case 'full-time':
                                        $badge_class = 'bg-success';
                                        break;
                                    case 'part-time':
                                        $badge_class = 'bg-info';
                                        break;
                                    case 'remote':
                                        $badge_class = 'bg-primary';
                                        break;
                                    case 'contract':
                                        $badge_class = 'bg-warning';
                                        break;
                                    default:
                                        $badge_class = 'bg-secondary';
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= ucfirst(str_replace('-', ' ', $job['job_type'])) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Posted By:</p>
                            <p><strong><?= htmlspecialchars($job['employer_name']) ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Posted On:</p>
                            <p><?= date('F d, Y', strtotime($job['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div>
                        <p class="text-muted mb-1">Description:</p>
                        <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Application Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Applications</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($applications) ?></div>
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
                                                Accepted</div>
                                            <?php
                                            $accepted_count = 0;
                                            foreach ($applications as $app) {
                                                if ($app['status'] == 'accepted') {
                                                    $accepted_count++;
                                                }
                                            }
                                            ?>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $accepted_count ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Pending</div>
                                            <?php
                                            $pending_count = 0;
                                            foreach ($applications as $app) {
                                                if ($app['status'] == 'pending') {
                                                    $pending_count++;
                                                }
                                            }
                                            ?>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $pending_count ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                Rejected</div>
                                            <?php
                                            $rejected_count = 0;
                                            foreach ($applications as $app) {
                                                if ($app['status'] == 'rejected') {
                                                    $rejected_count++;
                                                }
                                            }
                                            ?>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $rejected_count ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApplicationModal">
                            <i class="fas fa-plus fa-sm"></i> Add New Application
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Job Applications</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>CV</th>
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No applications found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?= $app['id'] ?></td>
                                    <td>
                                        <?php if ($app['profile_pic']): ?>
                                            <img src="../uploads/<?= htmlspecialchars($app['profile_pic']) ?>" class="rounded-circle me-2" width="25" height="25">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($app['name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($app['email']) ?></td>
                                    <td>
                                        <?php if ($app['cv']): ?>
                                            <a href="../uploads/<?= htmlspecialchars($app['cv']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-file-pdf"></i> View CV
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No CV</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch ($app['status']) {
                                            case 'accepted':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-danger';
                                                break;
                                            case 'reviewed':
                                                $status_class = 'bg-info';
                                                break;
                                            default:
                                                $status_class = 'bg-warning';
                                        }
                                        ?>
                                        <span class="badge <?= $status_class ?>">
                                            <?= ucfirst($app['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="statusDropdown<?= $app['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                Change Status
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="statusDropdown<?= $app['id'] ?>">
                                                <li><a class="dropdown-item" href="job_applications.php?id=<?= $job_id ?>&application_id=<?= $app['id'] ?>&status=pending">Pending</a></li>
                                                <li><a class="dropdown-item" href="job_applications.php?id=<?= $job_id ?>&application_id=<?= $app['id'] ?>&status=reviewed">Reviewed</a></li>
                                                <li><a class="dropdown-item" href="job_applications.php?id=<?= $job_id ?>&application_id=<?= $app['id'] ?>&status=accepted">Accepted</a></li>
                                                <li><a class="dropdown-item" href="job_applications.php?id=<?= $job_id ?>&application_id=<?= $app['id'] ?>&status=rejected">Rejected</a></li>
                                            </ul>
                                        </div>
                                        
                                        <a href="job_applications.php?id=<?= $job_id ?>&delete_id=<?= $app['id'] ?>" class="btn btn-sm btn-danger mt-1" onclick="return confirm('Are you sure you want to delete this application?')">
                                            <i class="fas fa-trash"></i> Delete
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

<!-- Add Application Modal -->
<div class="modal fade" id="addApplicationModal" tabindex="-1" aria-labelledby="addApplicationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addApplicationModalLabel">Add New Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_application">
                    
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
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="pending">Pending</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
