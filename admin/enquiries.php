<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get all enquiries with user information
$enquiries = [];
$result = executeQuery(
    "SELECT e.*, u.name as user_name, u.email as user_email 
     FROM enquiries e 
     LEFT JOIN users u ON e.user_id = u.id 
     ORDER BY e.created_at DESC", 
    [], 
    ""
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $enquiries[] = $row;
    }
    $result_set->close();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $enquiry_id = $_POST['enquiry_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $admin_response = $_POST['admin_response'] ?? '';
    
    if (!empty($enquiry_id) && !empty($status)) {
        $result = executeQuery(
            "UPDATE enquiries SET status=?, admin_response=? WHERE id=?", 
            [$status, $admin_response, $enquiry_id], 
            "ssi"
        );
        
        if ($result) {
            header('Location: enquiries.php?msg=updated');
            exit;
        } else {
            header('Location: enquiries.php?error=update_failed');
            exit;
        }
    }
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-2 text-gray-800">Enquiry Management</h1>
    <p class="mb-4">Manage all enquiries on the Pioneer Hub platform.</p>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Enquiry status updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Enquiries</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enquiries)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No enquiries found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enquiries as $enquiry): ?>
                                <tr>
                                    <td><?= $enquiry['id'] ?></td>
                                    <td><?= htmlspecialchars($enquiry['title']) ?></td>
                                    <td><?= htmlspecialchars($enquiry['user_name'] ?? 'Anonymous') ?></td>
                                    <td><?= htmlspecialchars($enquiry['email']) ?></td>
                                    <td><?= htmlspecialchars($enquiry['phone'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'pending' => 'bg-warning',
                                            'in_progress' => 'bg-primary',
                                            'resolved' => 'bg-success',
                                            'closed' => 'bg-secondary'
                                        ];
                                        $status_class = $status_classes[$enquiry['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $status_class ?>">
                                            <?= ucfirst(str_replace('_', ' ', $enquiry['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($enquiry['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-enquiry" 
                                                data-id="<?= $enquiry['id'] ?>"
                                                data-title="<?= htmlspecialchars($enquiry['title']) ?>"
                                                data-content="<?= htmlspecialchars($enquiry['content']) ?>"
                                                data-email="<?= htmlspecialchars($enquiry['email']) ?>"
                                                data-phone="<?= htmlspecialchars($enquiry['phone'] ?? '') ?>"
                                                data-status="<?= $enquiry['status'] ?>"
                                                data-response="<?= htmlspecialchars($enquiry['admin_response'] ?? '') ?>"
                                                data-username="<?= htmlspecialchars($enquiry['user_name'] ?? 'Anonymous') ?>"
                                                data-bs-toggle="modal" data-bs-target="#viewEnquiryModal">
                                            <i class="fas fa-eye"></i> View
                                        </button>
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

<!-- View Enquiry Modal -->
<div class="modal fade" id="viewEnquiryModal" tabindex="-1" aria-labelledby="viewEnquiryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewEnquiryModalLabel">Enquiry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="enquiry_id" id="enquiry_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <h5 id="modal_title"></h5>
                        </div>
                        <div class="col-md-4 text-end">
                            <select class="form-select" name="status" id="enquiry_status">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>From:</strong> <span id="modal_username"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email:</strong> <span id="modal_email"></span></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <p><strong>Phone:</strong> <span id="modal_phone"></span></p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label"><strong>Enquiry Content:</strong></label>
                        <div class="p-3 bg-light rounded" id="modal_content"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_response" class="form-label"><strong>Admin Response:</strong></label>
                        <textarea class="form-control" id="admin_response" name="admin_response" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Status & Response</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal data when opened
    document.querySelectorAll('.view-enquiry').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const content = this.getAttribute('data-content');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            const status = this.getAttribute('data-status');
            const response = this.getAttribute('data-response');
            const username = this.getAttribute('data-username');
            
            document.getElementById('enquiry_id').value = id;
            document.getElementById('modal_title').textContent = title;
            document.getElementById('modal_content').textContent = content;
            document.getElementById('modal_email').textContent = email;
            document.getElementById('modal_phone').textContent = phone || 'Not provided';
            document.getElementById('modal_username').textContent = username;
            document.getElementById('enquiry_status').value = status;
            document.getElementById('admin_response').value = response;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
