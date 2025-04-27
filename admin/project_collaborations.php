<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if project ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: projects.php');
    exit;
}

$project_id = $_GET['id'];

// Handle collaboration status update
if (isset($_GET['collab_id']) && !empty($_GET['collab_id']) && isset($_GET['status'])) {
    $collab_id = $_GET['collab_id'];
    $status = $_GET['status'];
    
    if (in_array($status, ['pending', 'approved', 'rejected'])) {
        // Update status
        $result = executeQuery(
            "UPDATE project_collaborations SET status=? WHERE id=?",
            [$status, $collab_id],
            "si"
        );
        
        // Send email notification if the status is 'approved'
        if ($status === 'approved') {
            // Get collaborator info and project title
            $info_result = executeQuery(
                "SELECT u.name, u.email, p.title 
                 FROM project_collaborations pc
                 JOIN users u ON pc.user_id = u.id
                 JOIN projects p ON pc.project_id = p.id
                 WHERE pc.id=?",
                [$collab_id],
                "i"
            );
            
            if ($info_result) {
                $info_set = $info_result->get_result();
                if ($info = $info_set->fetch_assoc()) {
                    // Include mailer functions
                    include_once '../backend/mailer.php';
                    
                    // Send email notification
                    sendProjectCollaborationApproval(
                        $info['email'],
                        $info['name'],
                        $info['title']
                    );
                }
                $info_set->close();
            }
        }
        
        // Redirect back to collaborations page
        header("Location: project_collaborations.php?id=$project_id&msg=status_updated");
        exit;
    }
}

// Handle collaboration deletion
if (isset($_GET['delete_id']) && !empty($_GET['delete_id'])) {
    $collab_id = $_GET['delete_id'];
    
    // Delete the collaboration
    $result = executeQuery(
        "DELETE FROM project_collaborations WHERE id=?", 
        [$collab_id], 
        "i"
    );
    
    // Redirect back to collaborations page
    header("Location: project_collaborations.php?id=$project_id&msg=collab_deleted");
    exit;
}

// Fetch project details
$project = null;
$result = executeQuery(
    "SELECT projects.*, users.name as submitter_name 
     FROM projects 
     LEFT JOIN users ON projects.submitted_by = users.id 
     WHERE projects.id=?", 
    [$project_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $project = $row;
    } else {
        // Project not found
        header('Location: projects.php');
        exit;
    }
    $result_set->close();
}

// Fetch collaborations for this project
$collaborations = [];
$result = executeQuery(
    "SELECT pc.*, u.name, u.email, u.profile_pic
     FROM project_collaborations pc
     JOIN users u ON pc.user_id = u.id
     WHERE pc.project_id=?
     ORDER BY pc.requested_at DESC", 
    [$project_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $collaborations[] = $row;
    }
    $result_set->close();
}

// Handle adding a new collaboration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_collaboration') {
    $user_id = $_POST['user_id'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    
    if (!empty($user_id)) {
        // Check if collaboration already exists
        $check_result = executeQuery(
            "SELECT id FROM project_collaborations WHERE user_id=? AND project_id=?",
            [$user_id, $project_id],
            "ii"
        );
        
        $check_set = $check_result->get_result();
        if ($check_set->num_rows > 0) {
            $check_set->close();
            header("Location: project_collaborations.php?id=$project_id&error=collab_exists");
            exit;
        }
        $check_set->close();
        
        // Add new collaboration
        $result = executeQuery(
            "INSERT INTO project_collaborations (project_id, user_id, status, requested_at) VALUES (?, ?, ?, NOW())",
            [$project_id, $user_id, $status],
            "iis"
        );
        
        header("Location: project_collaborations.php?id=$project_id&msg=collab_added");
        exit;
    } else {
        header("Location: project_collaborations.php?id=$project_id&error=invalid_user");
        exit;
    }
}

// Fetch available users for dropdown (who aren't already collaborators)
$available_users = [];
$result = executeQuery(
    "SELECT id, name, email FROM users 
     WHERE id NOT IN (
         SELECT user_id FROM project_collaborations WHERE project_id=?
     ) AND id != ?", // Exclude the project submitter
    [$project_id, $project['submitted_by']],
    "ii"
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
            <h1 class="h3 mb-0 text-gray-800">Project Collaborations</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Collaborations</li>
                </ol>
            </nav>
        </div>
        <a href="projects.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Projects
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'status_updated'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Collaboration status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'collab_deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Collaboration removed successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'collab_added'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Collaborator added successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <?php if ($_GET['error'] === 'collab_exists'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                This user is already a collaborator on this project.
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
                    <h6 class="m-0 font-weight-bold text-primary">Project Information</h6>
                </div>
                <div class="card-body">
                    <h2 class="h4 mb-3"><?= htmlspecialchars($project['title']) ?></h2>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Submitted By:</p>
                            <p><strong><?= htmlspecialchars($project['submitter_name']) ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Created On:</p>
                            <p><?= date('F d, Y', strtotime($project['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div>
                        <p class="text-muted mb-1">Description:</p>
                        <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Collaboration Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Collaborators</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($collaborations) ?></div>
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
                                                Approved</div>
                                            <?php
                                            $approved_count = 0;
                                            foreach ($collaborations as $collab) {
                                                if ($collab['status'] == 'approved') {
                                                    $approved_count++;
                                                }
                                            }
                                            ?>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $approved_count ?></div>
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
                                            foreach ($collaborations as $collab) {
                                                if ($collab['status'] == 'pending') {
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
                                            foreach ($collaborations as $collab) {
                                                if ($collab['status'] == 'rejected') {
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
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollaborationModal">
                            <i class="fas fa-plus fa-sm"></i> Add New Collaborator
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Project Collaborators</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Collaborator</th>
                            <th>Email</th>
                            <th>Requested Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($collaborations)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No collaborators found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($collaborations as $collab): ?>
                                <tr>
                                    <td><?= $collab['id'] ?></td>
                                    <td>
                                        <?php if ($collab['profile_pic']): ?>
                                            <img src="../uploads/<?= htmlspecialchars($collab['profile_pic']) ?>" class="rounded-circle me-2" width="25" height="25">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($collab['name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($collab['email']) ?></td>
                                    <td><?= date('M d, Y', strtotime($collab['requested_at'])) ?></td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch ($collab['status']) {
                                            case 'approved':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-danger';
                                                break;
                                            default:
                                                $status_class = 'bg-warning';
                                        }
                                        ?>
                                        <span class="badge <?= $status_class ?>">
                                            <?= ucfirst($collab['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="statusDropdown<?= $collab['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                Change Status
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="statusDropdown<?= $collab['id'] ?>">
                                                <li><a class="dropdown-item" href="project_collaborations.php?id=<?= $project_id ?>&collab_id=<?= $collab['id'] ?>&status=pending">Pending</a></li>
                                                <li><a class="dropdown-item" href="project_collaborations.php?id=<?= $project_id ?>&collab_id=<?= $collab['id'] ?>&status=approved">Approve</a></li>
                                                <li><a class="dropdown-item" href="project_collaborations.php?id=<?= $project_id ?>&collab_id=<?= $collab['id'] ?>&status=rejected">Reject</a></li>
                                            </ul>
                                        </div>
                                        
                                        <a href="project_collaborations.php?id=<?= $project_id ?>&delete_id=<?= $collab['id'] ?>" class="btn btn-sm btn-danger mt-1" onclick="return confirm('Are you sure you want to remove this collaborator?')">
                                            <i class="fas fa-trash"></i> Remove
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

<!-- Add Collaboration Modal -->
<div class="modal fade" id="addCollaborationModal" tabindex="-1" aria-labelledby="addCollaborationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCollaborationModalLabel">Add New Collaborator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_collaboration">
                    
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
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Collaborator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
