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
        $company = $_POST['company'] ?? '';
        $location = $_POST['location'] ?? '';
        $job_type = $_POST['job_type'] ?? '';
        $posted_by = $_POST['posted_by'] ?? '';
        
        // Validate input
        if (empty($title) || empty($description) || empty($company) || empty($location) || empty($job_type) || empty($posted_by)) {
            header('Location: jobs.php?error=missing_fields');
            exit;
        }
        
        // Insert into database
        $result = executeQuery(
            "INSERT INTO jobs (title, description, company, location, job_type, posted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$title, $description, $company, $location, $job_type, $posted_by],
            "sssssi"
        );
        
        if ($result) {
            header('Location: jobs.php?msg=added');
        } else {
            header('Location: jobs.php?error=insert_failed');
        }
        
    } elseif ($action === 'edit') {
        // Get form data
        $job_id = $_POST['job_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $company = $_POST['company'] ?? '';
        $location = $_POST['location'] ?? '';
        $job_type = $_POST['job_type'] ?? '';
        $posted_by = $_POST['posted_by'] ?? '';
        
        // Validate input
        if (empty($job_id) || empty($title) || empty($description) || empty($company) || empty($location) || empty($job_type) || empty($posted_by)) {
            header('Location: jobs.php?error=missing_fields');
            exit;
        }
        
        // Update database
        $result = executeQuery(
            "UPDATE jobs SET title=?, description=?, company=?, location=?, job_type=?, posted_by=? WHERE id=?",
            [$title, $description, $company, $location, $job_type, $posted_by, $job_id],
            "sssssii"
        );
        
        if ($result) {
            header('Location: jobs.php?msg=updated');
        } else {
            header('Location: jobs.php?error=update_failed');
        }
    } else {
        header('Location: jobs.php?error=invalid_action');
    }
} else {
    header('Location: jobs.php');
}
?>
