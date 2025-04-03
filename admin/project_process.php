<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Get form data
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $submitted_by = $_POST['submitted_by'] ?? '';
        
        // Validate input
        if (empty($title) || empty($description) || empty($submitted_by)) {
            header('Location: projects.php?error=missing_fields');
            exit;
        }
        
        // Insert into database
        $result = executeQuery(
            "INSERT INTO projects (title, description, submitted_by, created_at) VALUES (?, ?, ?, NOW())",
            [$title, $description, $submitted_by],
            "ssi"
        );
        
        if ($result) {
            header('Location: projects.php?msg=added');
        } else {
            header('Location: projects.php?error=insert_failed');
        }
        
    } elseif ($action === 'edit') {
        // Get form data
        $project_id = $_POST['project_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $submitted_by = $_POST['submitted_by'] ?? '';
        
        // Validate input
        if (empty($project_id) || empty($title) || empty($description) || empty($submitted_by)) {
            header('Location: projects.php?error=missing_fields');
            exit;
        }
        
        // Update database
        $result = executeQuery(
            "UPDATE projects SET title=?, description=?, submitted_by=? WHERE id=?",
            [$title, $description, $submitted_by, $project_id],
            "ssii"
        );
        
        if ($result) {
            header('Location: projects.php?msg=updated');
        } else {
            header('Location: projects.php?error=update_failed');
        }
    } else {
        header('Location: projects.php?error=invalid_action');
    }
} else {
    header('Location: projects.php');
}
?>
