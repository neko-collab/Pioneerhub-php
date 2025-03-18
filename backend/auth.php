<?php
include 'cors.php';
include 'db.php';
include 'jwt.php';
include 'mailer.php';
include 'utilities.php';
session_start();

$request_method = $_SERVER["REQUEST_METHOD"];

if ($request_method === "OPTIONS") {
    header("HTTP/1.1 200 OK");
    exit();
}

if ($request_method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (isset($data["action"])) {
        switch ($data["action"]) {
            case "register":
                registerUser($data);
                break;
            case "login":
                loginUser($data);
                break;
            case "forgotPassword":
                forgotPassword($data);
                break;
            case "verifyOTP":
                verifyOTP($data);
                break;
            case "changePassword":
                changePassword($data);
                break;
            case "registerAdmin":
                registerAdmin($data);
                break;
            case "registerInstructor":
                registerInstructor($data);
                break;
            case "registerEmployer":
                registerEmployer($data);
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

function registerUser($data) {
    if (!isset($data["name"], $data["email"], $data["password"])) {
        sendResponse(400, "Name, email, and password are required");
    }

    $name = $data["name"];
    $email = $data["email"];
    $password = password_hash($data["password"], PASSWORD_BCRYPT);

    $check_email = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s");
    $result = $check_email->get_result();
    if ($result->num_rows > 0) {
        $result->close(); // Close the result set
        sendResponse(400, "Email already exists");
    }

    $query = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')";
    if (executeQuery($query, [$name, $email, $password], "sss")) {
        
        //get the user details just created
        $result = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s")->get_result();
        $row = $result->fetch_assoc();
        $result->close(); // Close the result set

        $token = generateJWT($row['id'], $row['email'], $row['role']);
        $user_data = [
            "id" => $row['id'],
            "email" => $row['email'],
            "role" => $row['role'],
            "profile_pic" => $row['profile_pic'],
            "name" => $row['name']
        ];
        sendResponse(200, "Registration successful", ["token" => $token, "user" => $user_data]);
    } else {
        sendResponse(500, "Registration failed");
    }
}

function loginUser($data) {
    if (!isset($data["email"], $data["password"])) {
        sendResponse(400, "Email and password are required");
    }

    $email = $data["email"];
    $password = $data["password"];
    
    $result = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s")->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $token = generateJWT($row['id'], $row['email'], $row['role']);
            $user_data = [
                "id" => $row['id'],
                "email" => $row['email'],
                "role" => $row['role'],
                "profile_pic" => $row['profile_pic'],
                "name" => $row['name']
            ];
            
            // Get additional details based on user role
            if ($row['role'] === 'employer') {
                $employer_result = executeQuery("SELECT * FROM employer_details WHERE user_id=?", [$row['id']], "i")->get_result();
                if ($employer_info = $employer_result->fetch_assoc()) {
                    $user_data['company_name'] = $employer_info['company_name'];
                    $user_data['industry'] = $employer_info['industry'];
                    $user_data['company_website'] = $employer_info['company_website'];
                }
                $employer_result->close();
            } elseif ($row['role'] === 'instructor') {
                $instructor_result = executeQuery("SELECT * FROM instructor_details WHERE user_id=?", [$row['id']], "i")->get_result();
                if ($instructor_info = $instructor_result->fetch_assoc()) {
                    $user_data['bio'] = $instructor_info['bio'];
                    $user_data['qualifications'] = $instructor_info['qualifications'];
                    $user_data['expertise_areas'] = $instructor_info['expertise_areas'];
                }
                $instructor_result->close();
            } elseif ($row['role'] === 'admin') {
                $admin_result = executeQuery("SELECT * FROM admin_access WHERE user_id=?", [$row['id']], "i")->get_result();
                if ($admin_info = $admin_result->fetch_assoc()) {
                    $user_data['access_level'] = $admin_info['access_level'];
                    $user_data['status'] = $admin_info['status'];
                }
                $admin_result->close();
            }
            
            $result->close(); // Close the result set
            sendResponse(200, "Login successful", ["token" => $token, "user" => $user_data]);
        } else {
            $result->close(); // Close the result set
            sendResponse(400, "Invalid password");
        }
    } else {
        $result->close(); // Close the result set
        sendResponse(400, "User not found");
    }
}

function forgotPassword($data) {
    if (!isset($data["email"])) {
        sendResponse(400, "Email is required");
    }

    $email = $data["email"];

    $result = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s")->get_result();
    if ($row = $result->fetch_assoc()) {
        $otp = rand(100000, 999999);
        
        // Delete any existing OTPs for this email
        executeQuery("DELETE FROM password_reset_otps WHERE email=?", [$email], "s");
        
        // Insert new OTP
        $insert_otp = executeQuery(
            "INSERT INTO password_reset_otps (email, otp, created_at, expires_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE))",
            [$email, $otp],
            "ss"
        );
        
        if ($insert_otp && sendEmailOTP($email, $otp)) {
            $result->close();
            sendResponse(200, "OTP sent to email");
        } else {
            $result->close();
            sendResponse(500, "Failed to send OTP");
        }
    } else {
        $result->close();
        sendResponse(400, "User not found");
    }
}

function verifyOTP($data) {
    if (!isset($data["email"], $data["otp"])) {
        sendResponse(400, "Email and OTP are required");
    }

    $email = $data["email"];
    $otp = $data["otp"];
    
    // Check if OTP exists and is valid
    $otp_result = executeQuery(
        "SELECT * FROM password_reset_otps WHERE email=? AND otp=? AND expires_at > NOW()",
        [$email, $otp],
        "ss"
    )->get_result();
    
    if ($otp_row = $otp_result->fetch_assoc()) {
        $otp_result->close();
        sendResponse(200, "OTP verified");
    } else {
        $otp_result->close();
        sendResponse(400, "Invalid or expired OTP");
    }
}

function changePassword($data) {
    if (!isset($data["email"], $data["otp"], $data["new_password"])) {
        sendResponse(400, "Email, OTP, and new password are required");
    }

    $email = $data["email"];
    $otp = $data["otp"];
    $new_password = password_hash($data["new_password"], PASSWORD_BCRYPT);
    
    // Check if OTP exists and is valid
    $otp_result = executeQuery(
        "SELECT * FROM password_reset_otps WHERE email=? AND otp=? AND expires_at > NOW()",
        [$email, $otp],
        "ss"
    )->get_result();
    
    if ($otp_row = $otp_result->fetch_assoc()) {
        $otp_result->close();
        
        // Update password
        $query = "UPDATE users SET password_hash=? WHERE email=?";
        if (executeQuery($query, [$new_password, $email], "ss")) {
            // Delete used OTP
            executeQuery("DELETE FROM password_reset_otps WHERE email=?", [$email], "s");
            sendResponse(200, "Password changed successfully");
        } else {
            sendResponse(500, "Failed to change password");
        }
    } else {
        $otp_result->close();
        sendResponse(400, "Invalid or expired OTP");
    }
}

function registerAdmin($data) {
    if (!isset($data["name"], $data["email"], $data["password"], $data["access_reason"])) {
        sendResponse(400, "Name, email, password, and access reason are required");
    }

    $name = $data["name"];
    $email = $data["email"];
    $password = password_hash($data["password"], PASSWORD_BCRYPT);
    $access_reason = $data["access_reason"];
    $granted_by = isset($data["granted_by"]) ? $data["granted_by"] : null;
    $access_level = isset($data["access_level"]) ? $data["access_level"] : 'limited';

    $check_email = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s");
    $result = $check_email->get_result();
    if ($result->num_rows > 0) {
        $result->close(); // Close the result set
        sendResponse(400, "Email already exists");
    }

    // Begin transaction
    global $conn;
    $conn->begin_transaction();

    try {
        // Insert into users table
        $query = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')";
        executeQuery($query, [$name, $email, $password], "sss");
        
        // Get the newly created user ID
        $user_id = $conn->insert_id;
        
        // Insert into admin_access table
        $query = "INSERT INTO admin_access (user_id, access_reason, granted_by, access_level) VALUES (?, ?, ?, ?)";
        executeQuery($query, [$user_id, $access_reason, $granted_by, $access_level], "isis");
        
        // Commit transaction
        $conn->commit();
        
        sendResponse(200, "Admin registration successful");
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        sendResponse(500, "Admin registration failed: " . $e->getMessage());
    }
}

function registerInstructor($data) {
    if (!isset($data["name"], $data["email"], $data["password"])) {
        sendResponse(400, "Name, email, and password are required");
    }

    $name = $data["name"];
    $email = $data["email"];
    $password = password_hash($data["password"], PASSWORD_BCRYPT);
    $bio = isset($data["bio"]) ? $data["bio"] : null;
    $qualifications = isset($data["qualifications"]) ? $data["qualifications"] : null;
    $expertise_areas = isset($data["expertise_areas"]) ? $data["expertise_areas"] : null;
    $years_experience = isset($data["years_experience"]) ? $data["years_experience"] : null;

    $check_email = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s");
    $result = $check_email->get_result();
    if ($result->num_rows > 0) {
        $result->close(); // Close the result set
        sendResponse(400, "Email already exists");
    }

    // Begin transaction
    global $conn;
    $conn->begin_transaction();

    try {
        // Insert into users table
        $query = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'instructor')";
        executeQuery($query, [$name, $email, $password], "sss");
        
        // Get the newly created user ID
        $user_id = $conn->insert_id;
        
        // Insert into instructor_details table
        $query = "INSERT INTO instructor_details (user_id, bio, qualifications, expertise_areas, years_experience) VALUES (?, ?, ?, ?, ?)";
        executeQuery($query, [$user_id, $bio, $qualifications, $expertise_areas, $years_experience], "isssi");
        
        // Commit transaction
        $conn->commit();
        
        sendResponse(200, "Instructor registration successful");
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        sendResponse(500, "Instructor registration failed: " . $e->getMessage());
    }
}

function registerEmployer($data) {
    if (!isset($data["name"], $data["email"], $data["password"], $data["company_name"], $data["industry"])) {
        sendResponse(400, "Name, email, password, company name, and industry are required");
    }

    $name = $data["name"];
    $email = $data["email"];
    $password = password_hash($data["password"], PASSWORD_BCRYPT);
    $company_name = $data["company_name"];
    $industry = $data["industry"];
    $company_size = isset($data["company_size"]) ? $data["company_size"] : null;
    $company_website = isset($data["company_website"]) ? $data["company_website"] : null;
    $company_location = isset($data["company_location"]) ? $data["company_location"] : null;
    $company_description = isset($data["company_description"]) ? $data["company_description"] : null;

    $check_email = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s");
    $result = $check_email->get_result();
    if ($result->num_rows > 0) {
        $result->close(); // Close the result set
        sendResponse(400, "Email already exists");
    }

    // Begin transaction
    global $conn;
    $conn->begin_transaction();

    try {
        // Insert into users table
        $query = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'employer')";
        executeQuery($query, [$name, $email, $password], "sss");
        
        // Get the newly created user ID
        $user_id = $conn->insert_id;
        
        // Insert into employer_details table
        $query = "INSERT INTO employer_details (user_id, company_name, industry, company_size, company_website, company_location, company_description) VALUES (?, ?, ?, ?, ?, ?, ?)";
        executeQuery($query, [$user_id, $company_name, $industry, $company_size, $company_website, $company_location, $company_description], "issssss");
        
        // Commit transaction
        $conn->commit();
        
        sendResponse(200, "Employer registration successful");
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        sendResponse(500, "Employer registration failed: " . $e->getMessage());
    }
}
?>