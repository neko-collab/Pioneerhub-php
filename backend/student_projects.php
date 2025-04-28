<?php
session_start();
include 'backend/db.php';
include 'backend/utilities.php';
include 'backend/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?login=required');
    exit;
}

$user_id = $_SESSION['user_id'];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Fetch user information
$user = null;
$result = executeQuery("SELECT * FROM users WHERE id=?", [$user_id], "i");
if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $user = $row;
    }
    $result_set->close();
}

// Fetch all projects
$all_projects = [];
$result = executeQuery(
    "SELECT projects.*, users.name as submitter_name, users.email as submitter_email 
     FROM projects 
     JOIN users ON projects.submitted_by = users.id
     ORDER BY projects.created_at DESC", 
    [], 
    ""
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        // Get collaborators count
        $collaborators_result = executeQuery(
            "SELECT COUNT(*) as count 
             FROM project_collaborations 
             WHERE project_id=? AND status='approved'", 
            [$row['id']], 
            "i"
        );
        
        if ($collaborators_result) {
            $collaborators_set = $collaborators_result->get_result();
            if ($count_row = $collaborators_set->fetch_assoc()) {
                $row['collaborator_count'] = $count_row['count'];
            }
            $collaborators_set->close();
        }
        
        $all_projects[] = $row;
    }
    $result_set->close();
}

// Fetch user's own projects
$my_projects = [];
$result = executeQuery(
    "SELECT projects.*, users.name as submitter_name, users.email as submitter_email 
     FROM projects 
     JOIN users ON projects.submitted_by = users.id 
     WHERE projects.submitted_by=?
     ORDER BY projects.created_at DESC", 
    [$user_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        // Get collaborators count
        $collaborators_result = executeQuery(
            "SELECT COUNT(*) as count 
             FROM project_collaborations 
             WHERE project_id=? AND status='approved'", 
            [$row['id']], 
            "i"
        );
        
        if ($collaborators_result) {
            $collaborators_set = $collaborators_result->get_result();
            if ($count_row = $collaborators_set->fetch_assoc()) {
                $row['collaborator_count'] = $count_row['count'];
            }
            $collaborators_set->close();
        }
        
        $my_projects[] = $row;
    }
    $result_set->close();
}

// Fetch projects where user is a collaborator
$collaborating_projects = [];
$result = executeQuery(
    "SELECT projects.*, users.name as submitter_name, users.email as submitter_email,
            project_collaborations.status as collaboration_status
     FROM projects 
     JOIN users ON projects.submitted_by = users.id 
     JOIN project_collaborations ON projects.id = project_collaborations.project_id 
     WHERE project_collaborations.user_id=?
     ORDER BY projects.created_at DESC", 
    [$user_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        // Get collaborators count
        $collaborators_result = executeQuery(
            "SELECT COUNT(*) as count 
             FROM project_collaborations 
             WHERE project_id=? AND status='approved'", 
            [$row['id']], 
            "i"
        );
        
        if ($collaborators_result) {
            $collaborators_set = $collaborators_result->get_result();
            if ($count_row = $collaborators_set->fetch_assoc()) {
                $row['collaborator_count'] = $count_row['count'];
            }
            $collaborators_set->close();
        }
        
        $collaborating_projects[] = $row;
    }
    $result_set->close();
}

// Handle project view
$project_details = null;
$project_collaborators = [];
if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
    $project_id = $_GET['project_id'];
    
    // Fetch project details
    $result = executeQuery(
        "SELECT projects.*, users.name as submitter_name, users.email as submitter_email 
         FROM projects 
         JOIN users ON projects.submitted_by = users.id 
         WHERE projects.id=?", 
        [$project_id], 
        "i"
    );
    
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            $project_details = $row;
            
            // Fetch collaborators
            $collaborators_result = executeQuery(
                "SELECT project_collaborations.*, users.name, users.email, users.profile_pic 
                 FROM project_collaborations 
                 JOIN users ON project_collaborations.user_id = users.id 
                 WHERE project_collaborations.project_id=?
                 ORDER BY project_collaborations.requested_at DESC", 
                [$project_id], 
                "i"
            );
            
            if ($collaborators_result) {
                $collaborators_set = $collaborators_result->get_result();
                while ($collaborator = $collaborators_set->fetch_assoc()) {
                    $project_collaborators[] = $collaborator;
                }
                $collaborators_set->close();
            }
        }
        $result_set->close();
    }
}

// Handle collaboration request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'request_collaboration' && isset($_POST['project_id'])) {
        $project_id = $_POST['project_id'];
        
        // Check if already requested
        $check_result = executeQuery(
            "SELECT * FROM project_collaborations WHERE project_id=? AND user_id=?",
            [$project_id, $user_id],
            "ii"
        );
        
        $already_requested = false;
        if ($check_result) {
            $check_set = $check_result->get_result();
            if ($check_set->num_rows > 0) {
                $already_requested = true;
            }
            $check_set->close();
        }
        
        if (!$already_requested) {
            // Insert collaboration request
            $result = executeQuery(
                "INSERT INTO project_collaborations (project_id, user_id, status, requested_at) 
                 VALUES (?, ?, 'pending', NOW())",
                [$project_id, $user_id],
                "ii"
            );
            
            if ($result) {
                // Redirect to prevent form resubmission
                header("Location: student_projects.php?project_id=$project_id&msg=request_sent");
                exit;
            }
        } else {
            header("Location: student_projects.php?project_id=$project_id&msg=already_requested");
            exit;
        }
    } elseif ($_POST['action'] === 'respond_to_request' && isset($_POST['collab_id'], $_POST['status'])) {
        $collab_id = $_POST['collab_id'];
        $status = $_POST['status'];
        $project_id = $_POST['project_id'];
        
        if ($status === 'approved' || $status === 'rejected') {
            // Update collaboration status
            $result = executeQuery(
                "UPDATE project_collaborations SET status=? WHERE id=?",
                [$status, $collab_id],
                "si"
            );
            
            if ($result) {
                // If the status is 'approved', send email notification
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
                            include_once 'backend/mailer.php';
                            
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
                
                header("Location: student_projects.php?project_id=$project_id&msg=status_updated");
                exit;
            }
        }
    } elseif ($_POST['action'] === 'add_project' && isset($_POST['title'], $_POST['description'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        
        // Add new project
        $result = executeQuery(
            "INSERT INTO projects (title, description, submitted_by, created_at) 
             VALUES (?, ?, ?, NOW())",
            [$title, $description, $user_id],
            "ssi"
        );
        
        if ($result) {
            header("Location: student_projects.php?tab=my&msg=project_added");
            exit;
        }
    }
}

// Page title
$page_title = 'Projects';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Pioneer Hub Projects</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                    <i class="fas fa-plus me-2"></i> Create New Project
                </button>
            </div>
            
            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'project_added'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Your project was added successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($_GET['msg'] === 'request_sent'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Your collaboration request has been sent.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($_GET['msg'] === 'already_requested'): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        You've already requested to collaborate on this project.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($_GET['msg'] === 'status_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Collaboration status has been updated.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($project_details): ?>
                <!-- Project Details View -->
                <div class="mb-4">
                    <a href="student_projects.php" class="btn btn-outline-secondary mb-3">
                        <i class="fas fa-arrow-left me-2"></i> Back to Projects
                    </a>
                    
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h2 class="h4 mb-0"><?= htmlspecialchars($project_details['title']) ?></h2>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Project Owner</h5>
                                    <p>
                                        <strong><?= htmlspecialchars($project_details['submitter_name']) ?></strong><br>
                                        <a href="mailto:<?= htmlspecialchars($project_details['submitter_email']) ?>"><?= htmlspecialchars($project_details['submitter_email']) ?></a>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Created On</h5>
                                    <p><?= date('F j, Y', strtotime($project_details['created_at'])) ?></p>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5>Description</h5>
                                <div class="p-3 bg-light rounded">
                                    <?= nl2br(htmlspecialchars($project_details['description'])) ?>
                                </div>
                            </div>
                            
                            <?php if ($project_details['submitted_by'] != $user_id): ?>
                                <?php
                                // Check if user has already requested to collaborate
                                $already_requested = false;
                                foreach ($project_collaborators as $collaborator) {
                                    if ($collaborator['user_id'] == $user_id) {
                                        $already_requested = true;
                                        $collab_status = $collaborator['status'];
                                        $collab_id = $collaborator['id'];
                                        break;
                                    }
                                }
                                ?>
                                
                                <?php if (!$already_requested): ?>
                                    <form method="post" class="mb-4">
                                        <input type="hidden" name="action" value="request_collaboration">
                                        <input type="hidden" name="project_id" value="<?= $project_details['id'] ?>">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-handshake me-2"></i> Request to Collaborate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-info mb-4">
                                        <p>Your collaboration request status: 
                                            <span class="badge bg-<?= $collab_status === 'approved' ? 'success' : ($collab_status === 'rejected' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($collab_status) ?>
                                            </span>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <h5>Project Collaborators</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Requested</th>
                                            <th>Status</th>
                                            <?php if ($project_details['submitted_by'] == $user_id): ?>
                                                <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($project_collaborators)): ?>
                                            <tr>
                                                <td colspan="<?= $project_details['submitted_by'] == $user_id ? '5' : '4' ?>" class="text-center">No collaborators yet</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($project_collaborators as $collaborator): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($collaborator['name']) ?></td>
                                                    <td><?= htmlspecialchars($collaborator['email']) ?></td>
                                                    <td><?= date('M j, Y', strtotime($collaborator['requested_at'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $collaborator['status'] === 'approved' ? 'success' : ($collaborator['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                            <?= ucfirst($collaborator['status']) ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($project_details['submitted_by'] == $user_id && $collaborator['status'] === 'pending'): ?>
                                                        <td>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="action" value="respond_to_request">
                                                                <input type="hidden" name="collab_id" value="<?= $collaborator['id'] ?>">
                                                                <input type="hidden" name="project_id" value="<?= $project_details['id'] ?>">
                                                                <input type="hidden" name="status" value="approved">
                                                                <button type="submit" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-check me-1"></i> Approve
                                                                </button>
                                                            </form>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="action" value="respond_to_request">
                                                                <input type="hidden" name="collab_id" value="<?= $collaborator['id'] ?>">
                                                                <input type="hidden" name="project_id" value="<?= $project_details['id'] ?>">
                                                                <input type="hidden" name="status" value="rejected">
                                                                <button type="submit" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-times me-1"></i> Reject
                                                                </button>
                                                            </form>
                                                        </td>
                                                    <?php elseif ($project_details['submitted_by'] == $user_id): ?>
                                                        <td>
                                                            <span class="text-muted">No actions available</span>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Projects List View with Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab === 'all' ? 'active' : '' ?>" href="student_projects.php?tab=all">All Projects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab === 'my' ? 'active' : '' ?>" href="student_projects.php?tab=my">My Projects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab === 'collaborating' ? 'active' : '' ?>" href="student_projects.php?tab=collaborating">Collaborating</a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- All Projects Tab -->
                    <div class="tab-pane fade <?= $active_tab === 'all' ? 'show active' : '' ?>">
                        <div class="row">
                            <?php if (empty($all_projects)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        No projects available at the moment.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($all_projects as $project): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($project['title']) ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted">
                                                    By <?= htmlspecialchars($project['submitter_name']) ?>
                                                </h6>
                                                <p class="card-text">
                                                    <?= substr(htmlspecialchars($project['description']), 0, 100) . (strlen($project['description']) > 100 ? '...' : '') ?>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i> <?= $project['collaborator_count'] ?? 0 ?> collaborators
                                                    </small>
                                                    <a href="student_projects.php?project_id=<?= $project['id'] ?>" class="btn btn-sm btn-primary">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- My Projects Tab -->
                    <div class="tab-pane fade <?= $active_tab === 'my' ? 'show active' : '' ?>">
                        <div class="row">
                            <?php if (empty($my_projects)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        You haven't created any projects yet.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($my_projects as $project): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($project['title']) ?></h5>
                                                <p class="card-text">
                                                    <?= substr(htmlspecialchars($project['description']), 0, 100) . (strlen($project['description']) > 100 ? '...' : '') ?>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i> <?= $project['collaborator_count'] ?? 0 ?> collaborators
                                                    </small>
                                                    <a href="student_projects.php?project_id=<?= $project['id'] ?>" class="btn btn-sm btn-primary">
                                                        Manage
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Collaborating Projects Tab -->
                    <div class="tab-pane fade <?= $active_tab === 'collaborating' ? 'show active' : '' ?>">
                        <div class="row">
                            <?php if (empty($collaborating_projects)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        You're not collaborating on any projects yet.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($collaborating_projects as $project): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <h5 class="card-title"><?= htmlspecialchars($project['title']) ?></h5>
                                                    <span class="badge bg-<?= $project['collaboration_status'] === 'approved' ? 'success' : ($project['collaboration_status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                        <?= ucfirst($project['collaboration_status']) ?>
                                                    </span>
                                                </div>
                                                <h6 class="card-subtitle mb-2 text-muted">
                                                    By <?= htmlspecialchars($project['submitter_name']) ?>
                                                </h6>
                                                <p class="card-text">
                                                    <?= substr(htmlspecialchars($project['description']), 0, 100) . (strlen($project['description']) > 100 ? '...' : '') ?>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i> <?= $project['collaborator_count'] ?? 0 ?> collaborators
                                                    </small>
                                                    <a href="student_projects.php?project_id=<?= $project['id'] ?>" class="btn btn-sm btn-primary">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProjectModalLabel">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_project">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Project Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Project Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        <div class="form-text">
                            Describe your project, its goals, and what kind of collaborators you're looking for.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>