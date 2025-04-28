<?php
include 'cors.php';
include 'db.php';
include 'jwt.php';
include 'utilities.php';
session_start();

$request_method = $_SERVER["REQUEST_METHOD"];

if ($request_method === "POST") {
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
            case "addCourse":
                addCourse($data, $user_role);
                break;
            case "editCourse":
                editCourse($data, $user_role);
                break;
            case "deleteCourse":
                deleteCourse($data, $user_role);
                break;
            case "viewCourse":
                viewCourse($data);
                break;
            case "instructorsCourses":
                instructorsCourses($data);
                break;
            case "registerCourse":
                registerCourse($data, $user_id, $user_role);
                break;
            case "courseUsers":
                courseUsers($data, $user_role);
                break;
            case "toggleVerification":
                toggleVerification($data, $user_role);
                break;
            case "myEnrolledCourses":
                myEnrolledCourses($user_id);
                break;
            case "processKhaltiPayment":
                processKhaltiPayment($data, $user_id);
                break;
            default:
                sendResponse(400, "Invalid action");
        }
    } else {
        sendResponse(400, "Action is required");
    }
} elseif ($request_method === "GET") {

    // if id is set, view course details
    if (isset($_GET["id"])) {
        viewCourse($_GET);
    } 
    else if(isset($_GET['trending'])){
        $result = executeQuery("SELECT courses.*, users.name as instructor_name, users.email as instructor_email FROM courses JOIN users ON courses.instructor_id = users.id WHERE courses.is_trending=1", [], "");
        if ($result) {
            $courses = [];
            $result_set = $result->get_result();
            while ($row = $result_set->fetch_assoc()) {
                $courses[] = $row;
            }
            $result_set->close(); // Close the result set
            
            // Get additional information for each course
            foreach ($courses as $key => $course) {
                // Get instructor's additional details
                $instructor_id = $course['instructor_id'];
                $instructor_details = getInstructorDetails($instructor_id);
                $courses[$key]['instructor_bio'] = $instructor_details['bio'];
                $courses[$key]['instructor_qualifications'] = isset($instructor_details['qualifications']) ? $instructor_details['qualifications'] : '';
                $courses[$key]['instructor_expertise'] = isset($instructor_details['expertise_areas']) ? $instructor_details['expertise_areas'] : '';
                $courses[$key]['instructor_experience'] = isset($instructor_details['years_experience']) ? $instructor_details['years_experience'] : 0;
                
                // Get number of students enrolled in the course
                $course_id = $course['id'];
                $students_result = executeQuery("SELECT COUNT(*) as student_count FROM course_registrations WHERE course_id=?", [$course_id], "i");
                if ($students_result) {
                    $students_result_set = $students_result->get_result();
                    if ($students_count = $students_result_set->fetch_assoc()) {
                        $courses[$key]['student_count'] = $students_count['student_count'];
                    }
                    $students_result_set->close(); // Close the result set
                }
            }
            
            sendResponse(200, "Trending Courses list", $courses);
        } else {
            sendResponse(500, "Failed to execute query");
        }
    }
    else {
        listCourses();
    }
} else {
    sendResponse(405, "Method Not Allowed");
}

function addCourse($data, $user_role) {
    if (!isset($data["title"], $data["description"], $data["price"], $data["instructor_id"])) {
        sendResponse(400, "Title, description, price, and instructor_id are required");
    }

    if ($user_role !== 'admin') {
        sendResponse(403, "Permission denied");
    }

    $title = $data["title"];
    $description = $data["description"];
    $price = $data["price"];
    $instructor_id = $data["instructor_id"];
    $is_trending = isset($data["is_trending"]) ? $data["is_trending"] : 0;

    $query = "INSERT INTO courses (title, description, price, instructor_id, is_trending, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    if (executeQuery($query, [$title, $description, $price, $instructor_id, $is_trending], "ssdii")) {
        sendResponse(200, "Course added successfully");
    } else {
        sendResponse(500, "Failed to add course");
    }
}

function editCourse($data, $user_role) {
    if (!isset($data["id"], $data["title"], $data["description"], $data["price"], $data["instructor_id"])) {
        sendResponse(400, "ID, title, description, price, and instructor_id are required");
    }

    if ($user_role !== 'admin') {
        sendResponse(403, "Permission denied");
    }

    $id = $data["id"];
    $title = $data["title"];
    $description = $data["description"];
    $price = $data["price"];
    $instructor_id = $data["instructor_id"];
    $is_trending = isset($data["is_trending"]) ? $data["is_trending"] : 0;

    $query = "UPDATE courses SET title=?, description=?, price=?, instructor_id=?, is_trending=? WHERE id=?";
    if (executeQuery($query, [$title, $description, $price, $instructor_id, $is_trending, $id], "ssdiid")) {
        sendResponse(200, "Course updated successfully");
    } else {
        sendResponse(500, "Failed to update course");
    }
}

function deleteCourse($data, $user_role) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    if ($user_role !== 'admin') {
        sendResponse(403, "Permission denied");
    }

    $id = $data["id"];

    $query = "DELETE FROM courses WHERE id=?";
    if (executeQuery($query, [$id], "i")) {
        sendResponse(200, "Course deleted successfully");
    } else {
        sendResponse(500, "Failed to delete course");
    }
}

function viewCourse($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];

    // Get course details with instructor's basic information
    $result = executeQuery("SELECT courses.*, users.name as instructor_name, users.email as instructor_email FROM courses JOIN users ON courses.instructor_id = users.id WHERE courses.id=?", [$id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            $course = $row;
            $result_set->close(); // Close the result set
            
            // Get instructor's additional details
            $instructor_id = $course['instructor_id'];
            $instructor_details = getInstructorDetails($instructor_id);
            $course['instructor_bio'] = $instructor_details['bio'];
            $course['instructor_qualifications'] = isset($instructor_details['qualifications']) ? $instructor_details['qualifications'] : '';
            $course['instructor_expertise'] = isset($instructor_details['expertise_areas']) ? $instructor_details['expertise_areas'] : '';
            $course['instructor_experience'] = isset($instructor_details['years_experience']) ? $instructor_details['years_experience'] : 0;
            
            // Get number of students enrolled in the course
            $students_result = executeQuery("SELECT COUNT(*) as student_count FROM course_registrations WHERE course_id=?", [$id], "i");
            if ($students_result) {
                $students_result_set = $students_result->get_result();
                if ($students_count = $students_result_set->fetch_assoc()) {
                    $course['student_count'] = $students_count['student_count'];
                }
                $students_result_set->close(); // Close the result set
            }
            
            sendResponse(200, "Course details", $course);
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Course not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function listCourses() {
    // Get all courses with basic instructor information
    $result = executeQuery("SELECT courses.*, users.name as instructor_name, users.email as instructor_email FROM courses JOIN users ON courses.instructor_id = users.id", [], "");
    if ($result) {
        $courses = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $courses[] = $row;
        }
        $result_set->close(); // Close the result set
        
        // Get additional information for each course
        foreach ($courses as $key => $course) {
            // Get instructor's additional details
            $instructor_id = $course['instructor_id'];
            $instructor_details = getInstructorDetails($instructor_id);
            $courses[$key]['instructor_bio'] = $instructor_details['bio'];
            $courses[$key]['instructor_qualifications'] = isset($instructor_details['qualifications']) ? $instructor_details['qualifications'] : '';
            $courses[$key]['instructor_expertise'] = isset($instructor_details['expertise_areas']) ? $instructor_details['expertise_areas'] : '';
            $courses[$key]['instructor_experience'] = isset($instructor_details['years_experience']) ? $instructor_details['years_experience'] : 0;
            
            // Get number of students enrolled in the course
            $course_id = $course['id'];
            $students_result = executeQuery("SELECT COUNT(*) as student_count FROM course_registrations WHERE course_id=?", [$course_id], "i");
            if ($students_result) {
                $students_result_set = $students_result->get_result();
                if ($students_count = $students_result_set->fetch_assoc()) {
                    $courses[$key]['student_count'] = $students_count['student_count'];
                }
                $students_result_set->close(); // Close the result set
            }
        }
        
        sendResponse(200, "Courses list", $courses);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function instructorsCourses($data) {
    if (!isset($data["instructor_id"])) {
        sendResponse(400, "Instructor ID is required");
    }

    $instructor_id = $data["instructor_id"];

    // Get instructor's courses
    $result = executeQuery("SELECT * FROM courses WHERE instructor_id=?", [$instructor_id], "i");
    if ($result) {
        $courses = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $courses[] = $row;
        }
        $result_set->close(); // Close the result set
        
        // Get instructor's details
        $instructor_result = executeQuery("SELECT users.name, users.email, instructor_details.* FROM users JOIN instructor_details ON users.id = instructor_details.user_id WHERE users.id=?", [$instructor_id], "i");
        if ($instructor_result) {
            $instructor_result_set = $instructor_result->get_result();
            if ($instructor = $instructor_result_set->fetch_assoc()) {
                // Add instructor details
                $instructor_details = $instructor;
            }
            $instructor_result_set->close(); // Close the result set
        }
        
        // Get student count for each course
        foreach ($courses as $key => $course) {
            $course_id = $course['id'];
            $students_result = executeQuery("SELECT COUNT(*) as student_count FROM course_registrations WHERE course_id=?", [$course_id], "i");
            if ($students_result) {
                $students_result_set = $students_result->get_result();
                if ($students_count = $students_result_set->fetch_assoc()) {
                    $courses[$key]['student_count'] = $students_count['student_count'];
                }
                $students_result_set->close(); // Close the result set
            }
        }
        
        sendResponse(200, "Instructor's courses list", [
            "instructor" => isset($instructor_details) ? $instructor_details : null,
            "courses" => $courses
        ]);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function registerCourse($data, $user_id, $user_role) {
    if ($user_role !== 'user') {
        sendResponse(403, "You must be logged in as a user");
    }

    if (!isset($data["course_id"])) {
        sendResponse(400, "Course ID is required");
    }

    $course_id = $data["course_id"];

    $query = "INSERT INTO course_registrations (user_id, course_id, registered_at, verified) VALUES (?, ?, NOW(), 0)";
    if (executeQuery($query, [$user_id, $course_id], "ii")) {
        sendResponse(200, "Course registration successful");
    } else {
        sendResponse(500, "Failed to register for course");
    }
}

function courseUsers($data, $user_role) {
    if ($user_role !== 'admin' && $user_role !== 'instructor') {
        sendResponse(403, "Permission denied");
    }

    if (!isset($data["course_id"])) {
        sendResponse(400, "Course ID is required");
    }

    $course_id = $data["course_id"];

    $result = executeQuery("SELECT users.id, users.name, users.email, course_registrations.verified FROM users JOIN course_registrations ON users.id = course_registrations.user_id WHERE course_registrations.course_id=?", [$course_id], "i");
    if ($result) {
        $users = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $users[] = $row;
        }
        $result_set->close(); // Close the result set
        sendResponse(200, "Course users list", $users);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function toggleVerification($data, $user_role) {
    if ($user_role !== 'admin') {
        sendResponse(403, "Permission denied");
    }

    if (!isset($data["registration_id"], $data["verified"])) {
        sendResponse(400, "Registration ID and verified status are required");
    }

    $registration_id = $data["registration_id"];
    $verified = $data["verified"];

    $query = "UPDATE course_registrations SET verified=? WHERE id=?";
    if (executeQuery($query, [$verified, $registration_id], "ii")) {
        // If the course is being verified/approved, send a notification email
        if ($verified == 1) {
            // Get user and course details
            $info_result = executeQuery(
                "SELECT u.name, u.email, c.title 
                 FROM course_registrations cr
                 JOIN users u ON cr.user_id = u.id
                 JOIN courses c ON cr.course_id = c.id
                 WHERE cr.id=?",
                [$registration_id],
                "i"
            );
            
            if ($info_result) {
                $info_set = $info_result->get_result();
                if ($info = $info_set->fetch_assoc()) {
                    // Include mailer functions
                    include_once 'mailer.php';
                    
                    // Send email notification
                    sendCourseRegistrationApproval(
                        $info['email'],
                        $info['name'],
                        $info['title']
                    );
                }
                $info_set->close();
            }
        }
        
        sendResponse(200, "Verification status updated successfully");
    } else {
        sendResponse(500, "Failed to update verification status");
    }
}

// Function to get user's enrolled courses
function myEnrolledCourses($user_id) {
    // Get all courses the user is enrolled in
    $result = executeQuery(
        "SELECT courses.*, users.name as instructor_name, users.email as instructor_email, 
         course_registrations.registered_at, course_registrations.verified 
         FROM course_registrations 
         JOIN courses ON course_registrations.course_id = courses.id 
         JOIN users ON courses.instructor_id = users.id 
         WHERE course_registrations.user_id = ?", 
        [$user_id], 
        "i"
    );
    
    if ($result) {
        $courses = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $courses[] = $row;
        }
        $result_set->close();
        
        // Get additional information for each course
        foreach ($courses as $key => $course) {
            // Get instructor's additional details
            $instructor_id = $course['instructor_id'];
            $instructor_details = getInstructorDetails($instructor_id);
            $courses[$key]['instructor_bio'] = $instructor_details['bio'];
            $courses[$key]['instructor_qualifications'] = isset($instructor_details['qualifications']) ? $instructor_details['qualifications'] : '';
            $courses[$key]['instructor_expertise'] = isset($instructor_details['expertise_areas']) ? $instructor_details['expertise_areas'] : '';
            $courses[$key]['instructor_experience'] = isset($instructor_details['years_experience']) ? $instructor_details['years_experience'] : 0;
        }
        
        sendResponse(200, "My enrolled courses", $courses);
    } else {
        sendResponse(500, "Failed to retrieve enrolled courses");
    }
}

// This section is for retrieving instructor details
function getInstructorDetails($instructor_id) {
    $result = executeQuery(
        "SELECT * FROM instructor_details WHERE user_id = ?",
        [$instructor_id],
        "i"
    );
    
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            $result_set->close();
            return $row;
        }
        $result_set->close();
    }
    
    // Return default empty structure if no details found
    return [
        'specialization' => '',
        'bio' => '',
        'qualification' => '',
        'experience_years' => 0
    ];
}

// When you need to access the instructor details, use the function
function displayInstructorInfo($instructor_id) {
    $instructor_details = getInstructorDetails($instructor_id);
    
    // Use safeArrayGet to prevent warnings
    $qualification = safeArrayGet($instructor_details, 'qualification', 'Not specified');
    $specialization = safeArrayGet($instructor_details, 'specialization', 'Not specified');
    $experience_years = safeArrayGet($instructor_details, 'experience_years', 0);
    
    echo "Qualification: " . htmlspecialchars($qualification) . "<br>";
    echo "Specialization: " . htmlspecialchars($specialization) . "<br>";
    echo "Years of Experience: " . intval($experience_years) . "<br>";
}

// Returns a list of students in a specific course
function getCourseStudents($course_id) {
    $result = executeQuery(
        "SELECT users.id, users.name, users.email, course_registrations.verified, course_registrations.registered_at FROM users JOIN course_registrations ON users.id = course_registrations.user_id WHERE course_registrations.course_id=?",
        [$course_id],
        "i"
    );
    $students = [];
    if ($result) {
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $students[] = $row;
        }
        $result_set->close();
    }
    return $students;
}

// Process Khalti payment and activate course
function processKhaltiPayment($data, $user_id) {
    if (!isset($data["course_id"], $data["token"], $data["transaction_id"])) {
        sendResponse(400, "Course ID, token, and transaction ID are required");
    }

    $course_id = $data["course_id"];
    $token = $data["token"];
    $transaction_id = $data["transaction_id"];
    
    // Check if the course exists
    $course_result = executeQuery(
        "SELECT * FROM courses WHERE id = ?", 
        [$course_id], 
        "i"
    );
    
    if (!$course_result) {
        sendResponse(404, "Course not found");
    }
    
    $course_data = $course_result->get_result()->fetch_assoc();
    if (!$course_data) {
        sendResponse(404, "Course not found");
    }
    
    // Begin transaction
    global $conn;
    $conn->begin_transaction();
    
    try {
        // Check if the user is already registered for this course
        $registration_check = executeQuery(
            "SELECT id, verified FROM course_registrations WHERE user_id = ? AND course_id = ?",
            [$user_id, $course_id],
            "ii"
        );
        
        $registration_result = $registration_check->get_result();
        $registration_id = null;
        
        if ($registration_result->num_rows > 0) {
            // If already registered, get the registration ID
            $registration = $registration_result->fetch_assoc();
            $registration_id = $registration['id'];
            
            // Update existing registration to verified
            executeQuery(
                "UPDATE course_registrations SET verified = 1 WHERE id = ?",
                [$registration_id],
                "i"
            );
        } else {
            // Create new registration
            $register_result = executeQuery(
                "INSERT INTO course_registrations (user_id, course_id, registered_at, verified) VALUES (?, ?, NOW(), 1)",
                [$user_id, $course_id],
                "ii"
            );
            
            $registration_id = $conn->insert_id;
        }
        
        // Check if payment already exists for this transaction
        $payment_check = executeQuery(
            "SELECT id FROM payments WHERE transaction_id = ? AND user_id = ? AND course_id = ?",
            [$transaction_id, $user_id, $course_id],
            "sii"
        );
        
        $payment_result = $payment_check->get_result();
        
        if ($payment_result->num_rows == 0) {
            // Create payment record
            executeQuery(
                "INSERT INTO payments (user_id, course_id, amount, payment_status, payment_gateway, transaction_id, created_at) 
                 VALUES (?, ?, ?, 'completed', 'Khalti', ?, NOW())",
                [$user_id, $course_id, $course_data['price'], $transaction_id],
                "iids"
            );
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Get user details for notification purposes (can be used later)
        $user_result = executeQuery(
            "SELECT name, email FROM users WHERE id = ?",
            [$user_id],
            "i"
        );
        
        $user_data = $user_result->get_result()->fetch_assoc();
        
        if ($user_data) {
            include_once 'mailer.php';
            sendCourseRegistrationApproval($user_data['email'], $user_data['name'], $course_data['title']);
        }
        
        sendResponse(200, "Payment processed successfully, course activated");
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        sendResponse(500, "Failed to process payment: " . $e->getMessage());
    }
}
?>