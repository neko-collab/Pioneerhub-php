<?php
include 'cors.php';
include 'db.php';
include 'jwt.php';
include 'utilities.php';
session_start();

$request_method = $_SERVER["REQUEST_METHOD"];

if ($request_method === "OPTIONS") {
    header("HTTP/1.1 200 OK");
    exit();
}

if ($request_method === "GET") {
    $user_id = $_GET['id'] ?? null;
    getProfile($user_id);
} elseif ($request_method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $headers = apache_request_headers();
    if (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
        $decoded = validateJWT($token);
        if (!$decoded) {
            sendResponse(401, "Unauthorized");
        }
        $user_role = $decoded->role;
        $user_id = $decoded->user_id;
    } else {
        sendResponse(401, "Authorization header is required");
    }
    
    
    if (isset($data["action"])) {
        switch ($data["action"]) {
            case "updateProfile":
                updateProfile($user_id, $data);
                break;
            case "changePassword":
                changePassword($user_id, $data);
                break;
            default:
                sendResponse(400, "Invalid action");
        }
    } else {
        sendResponse(400, "Action is required");
    }
} else {
    sendResponse(405, "Method Not Allowed");
}

/**
 * Get user profile data
 * 
 * @param int $user_id The user ID
 */
function getProfile($user_id) {
    // Get basic user information
    $user_result = executeQuery("SELECT id, name, email, role, profile_pic, created_at FROM users WHERE id=?", [$user_id], "i");
    if (!$user_result) {
        sendResponse(500, "Database error occurred");
    }
    
    $result = $user_result->get_result();
    if ($user = $result->fetch_assoc()) {
        $user_data = $user;
        
        if ($user['role'] === 'instructor') {
            $instructor_result = executeQuery("SELECT * FROM instructor_details WHERE user_id=?", [$user_id], "i")->get_result();
            if ($instructor_info = $instructor_result->fetch_assoc()) {
                $user_data['bio'] = $instructor_info['bio'];
                $user_data['qualification'] = $instructor_info['qualification'];
                $user_data['specialization'] = $instructor_info['specialization'];
                $user_data['experience_years'] = $instructor_info['experience_years'];
            }
            $instructor_result->close();
        } elseif ($user['role'] === 'admin') {
            $admin_result = executeQuery("SELECT * FROM admin_access WHERE user_id=?", [$user_id], "i")->get_result();
            if ($admin_info = $admin_result->fetch_assoc()) {
                $user_data['access_level'] = $admin_info['access_level'];
                $user_data['access_reason'] = $admin_info['access_reason'];
                $user_data['granted_by'] = $admin_info['granted_by'];
                $user_data['granted_at'] = $admin_info['created_at'];
            }
            $admin_result->close();
        }
        
        $result->close();
        sendResponse(200, "Profile fetched successfully", $user_data);
    } else {
        $result->close();
        sendResponse(404, "User not found");
    }
}

/**
 * Update user profile information
 * 
 * @param int $user_id The user ID
 * @param array $data The profile data to update
 */
function updateProfile($user_id, $data) {
    global $conn;
    
    // First, get the user's role to determine which additional tables need updating
    $user_result = executeQuery("SELECT role FROM users WHERE id=?", [$user_id], "i");
    if (!$user_result) {
        sendResponse(500, "Database error occurred");
    }
    
    $result = $user_result->get_result();
    if (!($user = $result->fetch_assoc())) {
        $result->close();
        sendResponse(404, "User not found");
    }
    
    $role = $user['role'];
    $result->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update users table
        $updates = [];
        $params = [];
        $types = "";
        
        if (isset($data['name'])) {
            $updates[] = "name=?";
            $params[] = $data['name'];
            $types .= "s";
        }
        
        if (isset($data['email'])) {
            // Check if email is already used by another account
            $email_check = executeQuery("SELECT id FROM users WHERE email=? AND id!=?", [$data['email'], $user_id], "si")->get_result();
            if ($email_check->num_rows > 0) {
                $email_check->close();
                sendResponse(400, "Email already used by another account");
            }
            $email_check->close();
            
            $updates[] = "email=?";
            $params[] = $data['email'];
            $types .= "s";
        }
        
        if (isset($data['profile_pic'])) {
            $updates[] = "profile_pic=?";
            $params[] = $data['profile_pic'];
            $types .= "s";
        }
        
        if (!empty($updates)) {                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             
            $params[] = $user_id;
            $types .= "i";
            $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id=?";
            if (!executeQuery($query, $params, $types)) {
                throw new Exception("Failed to update user profile");
            }
        }
        
        if ($role === 'instructor' && isset($data['instructor'])) {
            updateInstructorDetails($user_id, $data['instructor']);
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Fetch updated profile data
        getProfile($user_id);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(500, "Profile update failed: " . $e->getMessage());
    }
}

/**
 * Update employer details
 * 
 * @param int $user_id The user ID
 * @param array $data The employer data to update
 */
function updateEmployerDetails($user_id, $data) {
    $updates = [];
    $params = [];
    $types = "";
    
    $fields = [
        'company_name' => 's',
        'industry' => 's',
        'company_size' => 's',
        'company_website' => 's',
        'company_location' => 's',
        'company_description' => 's'
    ];
    
    foreach ($fields as $field => $type) {
        if (isset($data[$field])) {
            $updates[] = "$field=?";
            $params[] = $data[$field];
            $types .= $type;
        }
    }
    
    if (!empty($updates)) {
        $params[] = $user_id;
        $types .= "i";
        $query = "UPDATE employer_details SET " . implode(", ", $updates) . " WHERE user_id=?";
        if (!executeQuery($query, $params, $types)) {
            throw new Exception("Failed to update employer details");
        }
    }
}

/**
 * Update instructor details
 * 
 * @param int $user_id The user ID
 * @param array $data The instructor data to update
 */
function updateInstructorDetails($user_id, $data) {
    global $conn;
    $updates = [];
    $params = [];
    $types = "";
    
    $fields = [
        'bio' => 's',
        'qualification' => 's',
        'specialization' => 's',
        'experience_years' => 'i'
    ];
    
    foreach ($fields as $field => $type) {
        if (isset($data[$field])) {
            $updates[] = "$field=?";
            $params[] = $data[$field];
            $types .= $type;
        }
    }
    
    if (!empty($updates)) {
        $params[] = $user_id;
        $types .= "i";
        $query = "UPDATE instructor_details SET " . implode(", ", $updates) . " WHERE user_id=?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception("Failed to prepare query");
        }
        
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            error_log("Execution failed: " . $stmt->error);
            throw new Exception("Failed to execute query");
        }
        
        $stmt->close();
    }
}

/**
 * Change user password with verification of current password
 * 
 * @param int $user_id The user ID
 * @param array $data The password change data
 */
function changePassword($user_id, $data) {
    if (!isset($data['current_password'], $data['new_password'], $data['confirm_password'])) {
        sendResponse(400, "Current password, new password, and confirm password are required");
    }
    
    if ($data['new_password'] !== $data['confirm_password']) {
        sendResponse(400, "New password and confirm password do not match");
    }
    
    // Get current password hash
    $result = executeQuery("SELECT password_hash FROM users WHERE id=?", [$user_id], "i")->get_result();
    if ($user = $result->fetch_assoc()) {
        // Verify current password
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            $result->close();
            sendResponse(400, "Current password is incorrect");
        }
        
        $result->close();
        
        // Update password
        $new_password_hash = password_hash($data['new_password'], PASSWORD_BCRYPT);
        if (executeQuery("UPDATE users SET password_hash=? WHERE id=?", [$new_password_hash, $user_id], "si")) {
            sendResponse(200, "Password changed successfully");
        } else {
            sendResponse(500, "Failed to change password");
        }
    } else {
        $result->close();
        sendResponse(404, "User not found");
    }
}
?>