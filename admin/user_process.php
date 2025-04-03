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
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        
        // Validate input
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            header('Location: users.php?error=missing_fields');
            exit;
        }
        
        // Check if email already exists
        $check_email = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s");
        $result = $check_email->get_result();
        if ($result->num_rows > 0) {
            $result->close();
            header('Location: users.php?error=email_exists');
            exit;
        }
        $result->close();
        
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert into database
        $result = executeQuery(
            "INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$name, $email, $password_hash, $role],
            "ssss"
        );
        
        if ($result) {
            header('Location: users.php?msg=added');
        } else {
            header('Location: users.php?error=insert_failed');
        }
        
    } elseif ($action === 'edit') {
        // Get form data
        $user_id = $_POST['user_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        
        // Validate input
        if (empty($user_id) || empty($name) || empty($email) || empty($role)) {
            header('Location: users.php?error=missing_fields');
            exit;
        }
        
        // Check if email already exists for another user
        $check_email = executeQuery("SELECT * FROM users WHERE email=? AND id!=?", [$email, $user_id], "si");
        $result = $check_email->get_result();
        if ($result->num_rows > 0) {
            $result->close();
            header('Location: users.php?error=email_exists');
            exit;
        }
        $result->close();
        
        // If password is provided, update it
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $result = executeQuery(
                "UPDATE users SET name=?, email=?, password_hash=?, role=? WHERE id=?",
                [$name, $email, $password_hash, $role, $user_id],
                "ssssi"
            );
        } else {
            // Otherwise, don't update the password
            $result = executeQuery(
                "UPDATE users SET name=?, email=?, role=? WHERE id=?",
                [$name, $email, $role, $user_id],
                "sssi"
            );
        }
        
        if ($result) {
            // If editing the current admin user, update session
            if ($user_id == $_SESSION['admin_id']) {
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_role'] = $role;
            }
            
            header('Location: users.php?msg=updated');
        } else {
            header('Location: users.php?error=update_failed');
        }
    } else {
        header('Location: users.php?error=invalid_action');
    }
} else {
    header('Location: users.php');
}
?>
