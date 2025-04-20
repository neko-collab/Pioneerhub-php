<?php
session_start();

// Check if the admin is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Redirect to dashboard
    header('Location: admin/dashboard.php');
    exit;
} else {
    // Redirect to login page
    header('Location: admin/login.php');
    exit;
}
