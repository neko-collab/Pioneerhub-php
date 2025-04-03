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
        $price = $_POST['price'] ?? 0;
        $instructor_id = $_POST['instructor_id'] ?? '';
        $is_trending = isset($_POST['is_trending']) ? 1 : 0;
        
        // Validate input
        if (empty($title) || empty($description) || empty($instructor_id)) {
            header('Location: courses.php?error=missing_fields');
            exit;
        }
        
        // Insert into database
        $result = executeQuery(
            "INSERT INTO courses (title, description, price, instructor_id, is_trending, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$title, $description, $price, $instructor_id, $is_trending],
            "ssdii"
        );
        
        if ($result) {
            header('Location: courses.php?msg=added');
        } else {
            header('Location: courses.php?error=insert_failed');
        }
        
    } elseif ($action === 'edit') {
        // Get form data
        $course_id = $_POST['course_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? 0;
        $instructor_id = $_POST['instructor_id'] ?? '';
        $is_trending = isset($_POST['is_trending']) ? 1 : 0;
        
        // Validate input
        if (empty($course_id) || empty($title) || empty($description) || empty($instructor_id)) {
            header('Location: courses.php?error=missing_fields');
            exit;
        }
        
        // Update database
        $result = executeQuery(
            "UPDATE courses SET title=?, description=?, price=?, instructor_id=?, is_trending=? WHERE id=?",
            [$title, $description, $price, $instructor_id, $is_trending, $course_id],
            "ssdiii"
        );
        
        if ($result) {
            header('Location: courses.php?msg=updated');
        } else {
            header('Location: courses.php?error=update_failed');
        }
    } else {
        header('Location: courses.php?error=invalid_action');
    }
} else {
    header('Location: courses.php');
}
?>
