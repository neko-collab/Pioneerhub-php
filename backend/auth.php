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
            case "registerInstructor":
                registerInstructor($data);
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
    
    $stmt = executeQuery("SELECT * FROM users WHERE email=?", [$email], "s");
    if (!$stmt) {
        sendResponse(500, "Database error occurred");
    }
    
    $result = $stmt->get_result();
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
                    $user_data['specialization'] = $instructor_info['specialization'];
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
    
    $result = executeQuery("SELECT id FROM users WHERE email=?", [$email], "s")->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $otp = rand(100000, 999999);
        
        // Delete any existing OTPs for this email
        executeQuery("DELETE FROM user_tokens WHERE email=?", [$email], "s");
        
        // Insert new OTP
        $insert_otp = executeQuery(
            "INSERT INTO user_tokens (user_id, token, created_at, expires_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY))",
            [$user_id, $otp],
            "is"
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
    
    // Find user ID from email
    $user_result = executeQuery("SELECT id FROM users WHERE email=?", [$email], "s")->get_result();
    if ($user_row = $user_result->fetch_assoc()) {
        $user_id = $user_row['id'];
        $user_result->close();
        
        // Check if OTP exists and is valid
        $otp_result = executeQuery( 
            "SELECT * FROM user_tokens WHERE user_id=? AND token=? AND expires_at > NOW()",
            [$user_id, $otp],
            "is"
        )->get_result();
        
        if ($otp_row = $otp_result->fetch_assoc()) {
            $otp_result->close();
            sendResponse(200, "OTP verified");
        } else {
            $otp_result->close();
            sendResponse(400, "Invalid or expired OTP");
        }
    } else {
        $user_result->close();
        sendResponse(400, "User not found");
    }
}
function changePassword($data) {
    if (!isset($data["email"], $data["otp"], $data["new_password"])) {
        sendResponse(400, "Email, token, and new password are required");
    }

    $email = $data["email"];
    $token = $data["otp"];
    $new_password = password_hash($data["new_password"], PASSWORD_BCRYPT);
    
    // Find user ID from email
    $user_result = executeQuery("SELECT id FROM users WHERE email=?", [$email], "s")->get_result();
    if ($user_row = $user_result->fetch_assoc()) {
        $user_id = $user_row['id'];
        $user_result->close();
        
        // Check if token exists and is valid
        $token_result = executeQuery(
            "SELECT * FROM user_tokens WHERE user_id=? AND token=? AND expires_at > NOW()",
            [$user_id, $token],
            "is"
        )->get_result();
        
        if ($token_row = $token_result->fetch_assoc()) {
            $token_result->close();
            
            // Update password
            $query = "UPDATE users SET password_hash=? WHERE id=?";
            if (executeQuery($query, [$new_password, $user_id], "si")) {
                // Delete used token
                executeQuery("DELETE FROM user_tokens WHERE user_id=?", [$user_id], "i");
                sendResponse(200, "Password changed successfully");
            } else {
                sendResponse(500, "Failed to change password");
            }
        } else {
            $token_result->close();
            sendResponse(400, "Invalid or expired token");
        }
    } else {
        $user_result->close();
        sendResponse(400, "User not found");
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
    $qualifications = isset($data["qualification"]) ? $data["qualification"] : null;
    $specialization = isset($data["specialization"]) ? $data["specialization"] : null;
    $experience_years = isset($data["experience_years"]) ? $data["experience_years"] : null;

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

        $conn->commit(); // Commit the transaction
        // Begin a new transaction for instructor details
        $conn->begin_transaction();
        // Insert into instructor_details table
        $query = "INSERT INTO instructor_details (user_id, bio, qualification, specialization, experience_years) VALUES (?, ?, ?, ?, ?)";
        executeQuery($query, [$user_id, $bio, $qualifications, $specialization, $experience_years], "isssi");
        
        // Commit transaction
        $conn->commit();

        // Generate token and prepare user data
        $token = generateJWT($user_id, $email, 'instructor');
        $user_data = [
            "id" => $user_id,
            "email" => $email,
            "role" => 'instructor',
            "profile_pic" => null,
            "name" => $name,
            "bio" => $bio,
            "qualifications" => $qualifications,
            "specialization" => $specialization,
            "experience_years" => $experience_years
        ];

        sendResponse(200, "Instructor registration successful", ["token" => $token, "user" => $user_data]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        sendResponse(500, "Instructor registration failed: " . $e->getMessage());
    }
}

?>