<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to admin login
    header('Location: admin/login.php');
    exit;
} else {
    // Redirect to admin dashboard
    header('Location: admin/dashboard.php');
    exit;
}
?>
