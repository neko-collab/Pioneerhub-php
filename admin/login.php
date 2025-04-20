<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $result = executeQuery("SELECT * FROM users WHERE email = ? AND role IN ('admin')", [$email], "s");
        $result_set = $result->get_result();
        
        if ($result_set->num_rows === 1) {
            $user = $result_set->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_role'] = $user['role'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'User not found or not an admin';
        }
        $result_set->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Pioneer Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #4568dc, #b06ab3);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 30px;
            width: 100%;
            max-width: 400px;
        }
        .login-title {
            text-align: center;
            color: #4568dc;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(to right, #4568dc, #b06ab3);
            border: none;
            padding: 10px 15px;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo h1 {
            color: #4568dc;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Pioneer Hub</h1>
            <p class="text-primary">Admin Portal</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
