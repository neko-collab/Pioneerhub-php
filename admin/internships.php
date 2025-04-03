<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle internship deletion
if (isset($_GET['delete_id']) && !empty($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Delete the internship
    $result = executeQuery("DELETE FROM internships WHERE id=?", [$delete_id], "i");
    
    // Redirect back to internships page
    header('Location: internships.php?msg=deleted');
    exit;
}

// Fetch all internships with employer name
$internships = [];
$result = executeQuery(
    "SELECT internships.*, users.name as employer_name 
     FROM internships 
     LEFT JOIN users ON internships.posted_by = users.id 
     ORDER BY internships.created_at DESC", 
    [], 
    ""
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $internships[] = $row;
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
    <h1 class="h3 mb-2 text-gray-800">Internships Management</h1>
    <p class="mb-4">Manage all internships on the Pioneer Hub platform.</p>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Internship deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Internships</h6>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInternshipModal">
                <i class="fas fa-plus fa-sm"></i> Add New Internship
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
                        <?php foreach ($internships as $internship): ?>
                        <tr>
                            <td><?= $internship['id'] ?></td>
                            <td><?= htmlspecialchars($internship['title']) ?></td>
                            <td><?= htmlspecialchars($internship['company']) ?></td>
                            <td><?= htmlspecialchars($internship['location']) ?></td>
                            <td>
                                <span class="badge <?= $internship['internship_type'] === 'paid' ? 'bg-success' : 'bg-warning' ?>">
                                    <?= ucfirst($internship['internship_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($internship['employer_name'] ?? 'None') ?></td>
                            <td><?= date('M d, Y', strtotime($internship['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info edit-btn" 
                                    data-id="<?= $internship['id'] ?>"
                                    data-title="<?= htmlspecialchars($internship['title']) ?>"
                                    data-description="<?= htmlspecialchars($internship['description']) ?>"
                                    data-company="<?= htmlspecialchars($internship['company']) ?>"
                                    data-location="<?= htmlspecialchars($internship['location']) ?>"
                                    data-type="<?= $internship['internship_type'] ?>"
                                    data-employer="<?= $internship['posted_by'] ?>"
                                    data-bs-toggle="modal" data-bs-target="#editInternshipModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="internship_applications.php?id=<?= $internship['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-users"></i>
                                </a>
                                <a href="internships.php?delete_id=<?= $internship['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this internship?')">
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

<!-- Add Internship Modal -->
<div class="modal fade" id="addInternshipModal" tabindex="-1" aria-labelledby="addInternshipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addInternshipModalLabel">Add New Internship</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="internship_process.php" method="post">
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
                        <label for="internship_type" class="form-label">Type</label>
                        <select class="form-control" id="internship_type" name="internship_type" required>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
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
                    <button type="submit" class="btn btn-primary">Add Internship</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Internship Modal -->
<div class="modal fade" id="editInternshipModal" tabindex="-1" aria-labelledby="editInternshipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editInternshipModalLabel">Edit Internship</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="internship_process.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="internship_id" id="edit_internship_id">
                    
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
                        <label for="edit_internship_type" class="form-label">Type</label>
                        <select class="form-control" id="edit_internship_type" name="internship_type" required>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
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
                    <button type="submit" class="btn btn-primary">Update Internship</button>
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
            
            document.getElementById('edit_internship_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_company').value = company;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_internship_type').value = type;
            document.getElementById('edit_posted_by').value = employer;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
