<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Create folder structure
$folders = [
    '../uploads',
    '../uploads/cvs',
    '../uploads/profiles',
    '../uploads/documents'
];

$results = [];
foreach ($folders as $folder) {
    if (!file_exists($folder)) {
        $created = mkdir($folder, 0777, true);
        $results[] = [
            'path' => $folder,
            'status' => $created ? 'Created successfully' : 'Failed to create'
        ];
    } else {
        $results[] = [
            'path' => $folder,
            'status' => 'Already exists'
        ];
    }
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-2 text-gray-800">Setup Upload Folders</h1>
    <p class="mb-4">This page creates the necessary folder structure for file uploads.</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Folder Status</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Path</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?= htmlspecialchars($result['path']) ?></td>
                                <td>
                                    <?php if ($result['status'] === 'Created successfully'): ?>
                                        <span class="badge bg-success">Created Successfully</span>
                                    <?php elseif ($result['status'] === 'Already exists'): ?>
                                        <span class="badge bg-info">Already Exists</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Failed to Create</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
