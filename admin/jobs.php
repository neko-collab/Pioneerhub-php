<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle job deletion
if (isset($_GET['delete_id']) && !empty($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Delete the job
    $result = executeQuery("DELETE FROM jobs WHERE id=?", [$delete_id], "i");
    
    // Redirect back to jobs page
    header('Location: jobs.php?msg=deleted');
    exit;
}

// Fetch all jobs with employer name
$jobs = [];
$result = executeQuery(
    "SELECT jobs.*, users.name as employer_name 
     FROM jobs 
     LEFT JOIN users ON jobs.posted_by = users.id 
     ORDER BY jobs.created_at DESC", 
    [], 
    ""
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $jobs[] = $row;
    }
    $result_set->close();
}

// Fetch all employers for the add/edit forms
$employers = [];
$result = executeQuery("SELECT id, name, email FROM users WHERE role='employer' OR role='admin'", [], "");
if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $employers[] = $row;
    }
    $result_set->close();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-2 text-gray-800">Jobs Management</h1>
    <p class="mb-4">Manage all jobs on the Pioneer Hub platform.</p>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Job deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Jobs</h6>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJobModal">
                <i class="fas fa-plus fa-sm"></i> Add New Job
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Posted By</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?= $job['id'] ?></td>
                            <td><?= htmlspecialchars($job['title']) ?></td>
                            <td><?= htmlspecialchars($job['company']) ?></td>
                            <td><?= htmlspecialchars($job['location']) ?></td>
                            <td>
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
                            </td>
                            <td><?= htmlspecialchars($job['employer_name'] ?? 'None') ?></td>
                            <td><?= date('M d, Y', strtotime($job['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info edit-btn" 
                                    data-id="<?= $job['id'] ?>"
                                    data-title="<?= htmlspecialchars($job['title']) ?>"
                                    data-description="<?= htmlspecialchars($job['description']) ?>"
                                    data-company="<?= htmlspecialchars($job['company']) ?>"
                                    data-location="<?= htmlspecialchars($job['location']) ?>"
                                    data-type="<?= $job['job_type'] ?>"
                                    data-employer="<?= $job['posted_by'] ?>"
                                    data-bs-toggle="modal" data-bs-target="#editJobModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="job_applications.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-users"></i>
                                </a>
                                <a href="jobs.php?delete_id=<?= $job['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this job?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Job Modal -->
<div class="modal fade" id="addJobModal" tabindex="-1" aria-labelledby="addJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addJobModalLabel">Add New Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="job_process.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company" class="form-label">Company</label>
                        <input type="text" class="form-control" id="company" name="company" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="job_type" class="form-label">Type</label>
                        <select class="form-control" id="job_type" name="job_type" required>
                            <option value="full-time">Full Time</option>
                            <option value="part-time">Part Time</option>
                            <option value="remote">Remote</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="posted_by" class="form-label">Posted By</label>
                        <select class="form-control" id="posted_by" name="posted_by" required>
                            <option value="">Select Employer</option>
                            <?php foreach ($employers as $employer): ?>
                            <option value="<?= $employer['id'] ?>"><?= htmlspecialchars($employer['name']) ?> (<?= htmlspecialchars($employer['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Job Modal -->
<div class="modal fade" id="editJobModal" tabindex="-1" aria-labelledby="editJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editJobModalLabel">Edit Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="job_process.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="job_id" id="edit_job_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_company" class="form-label">Company</label>
                        <input type="text" class="form-control" id="edit_company" name="company" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="edit_location" name="location" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_job_type" class="form-label">Type</label>
                        <select class="form-control" id="edit_job_type" name="job_type" required>
                            <option value="full-time">Full Time</option>
                            <option value="part-time">Part Time</option>
                            <option value="remote">Remote</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_posted_by" class="form-label">Posted By</label>
                        <select class="form-control" id="edit_posted_by" name="posted_by" required>
                            <option value="">Select Employer</option>
                            <?php foreach ($employers as $employer): ?>
                            <option value="<?= $employer['id'] ?>"><?= htmlspecialchars($employer['name']) ?> (<?= htmlspecialchars($employer['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit button clicks
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const description = this.getAttribute('data-description');
            const company = this.getAttribute('data-company');
            const location = this.getAttribute('data-location');
            const type = this.getAttribute('data-type');
            const employer = this.getAttribute('data-employer');
            
            document.getElementById('edit_job_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_company').value = company;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_job_type').value = type;
            document.getElementById('edit_posted_by').value = employer;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
