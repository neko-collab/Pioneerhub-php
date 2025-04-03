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
        $internship_type = $_POST['internship_type'] ?? '';
        $posted_by = $_POST['posted_by'] ?? '';
        
        // Validate input
        if (empty($title) || empty($description) || empty($company) || empty($location) || empty($internship_type) || empty($posted_by)) {
            header('Location: internships.php?error=missing_fields');
            exit;
        }
        
        // Insert into database
        $result = executeQuery(
            "INSERT INTO internships (title, description, company, location, internship_type, posted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$title, $description, $company, $location, $internship_type, $posted_by],
            "sssssi"
        );
        
        if ($result) {
            header('Location: internships.php?msg=added');
        } else {
            header('Location: internships.php?error=insert_failed');
        }
        
    } elseif ($action === 'edit') {
        // Get form data
        $internship_id = $_POST['internship_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $company = $_POST['company'] ?? '';
        $location = $_POST['location'] ?? '';
        $internship_type = $_POST['internship_type'] ?? '';
        $posted_by = $_POST['posted_by'] ?? '';
        
        // Validate input
        if (empty($internship_id) || empty($title) || empty($description) || empty($company) || empty($location) || empty($internship_type) || empty($posted_by)) {
            header('Location: internships.php?error=missing_fields');
            exit;
        }
        
        // Update database
        $result = executeQuery(
            "UPDATE internships SET title=?, description=?, company=?, location=?, internship_type=?, posted_by=? WHERE id=?",
            [$title, $description, $company, $location, $internship_type, $posted_by, $internship_id],
            "sssssii"
        );
        
        if ($result) {
            header('Location: internships.php?msg=updated');
        } else {
            header('Location: internships.php?error=update_failed');
        }
    } else {
        header('Location: internships.php?error=invalid_action');
    }
} else {
    header('Location: internships.php');
}
?>
