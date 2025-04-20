<?php
include 'cors.php';
include 'db.php';
include 'jwt.php';
include 'utilities.php';

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
            case "updateProfile":
                if ($user_role === 'instructor') {
                    updateInstructorProfile($data, $user_id);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "getInstructorDetails":
                getInstructorDetails($data);
                break;
            default:
                sendResponse(400, "Invalid action");
        }
    } else {
        sendResponse(400, "Action is required");
    }
} elseif ($request_method === "GET") {
    if (isset($_GET["id"])) {
        getInstructorDetails($_GET);
    } else {
        getAllInstructors();
    }
} else {
    sendResponse(405, "Method Not Allowed");
}

function updateInstructorProfile($data, $user_id) {
    if (!isset($data["specialization"], $data["bio"], $data["qualification"])) {
        sendResponse(400, "Specialization, bio, and qualification are required");
    }
    
    $specialization = $data["specialization"];
    $bio = $data["bio"];
    $qualification = $data["qualification"];
    $experience_years = isset($data["experience_years"]) ? intval($data["experience_years"]) : 0;
    
    // Check if instructor details exist
    $check = executeQuery("SELECT id FROM instructor_details WHERE user_id = ?", [$user_id], "i");
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $row = $result->fetch_assoc();
        $details_id = $row['id'];
        
        $query = "UPDATE instructor_details SET specialization = ?, bio = ?, qualification = ?, experience_years = ?, updated_at = NOW() WHERE id = ?";
        if (executeQuery($query, [$specialization, $bio, $qualification, $experience_years, $details_id], "sssii")) {
            sendResponse(200, "Instructor profile updated successfully");
        } else {
            sendResponse(500, "Failed to update instructor profile");
        }
    } else {
        // Insert new record
        $query = "INSERT INTO instructor_details (user_id, specialization, bio, qualification, experience_years) VALUES (?, ?, ?, ?, ?)";
        if (executeQuery($query, [$user_id, $specialization, $bio, $qualification, $experience_years], "isssi")) {
            sendResponse(200, "Instructor profile created successfully");
        } else {
            sendResponse(500, "Failed to create instructor profile");
        }
    }
    
    $result->close();
}

function getInstructorDetails($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "Instructor ID is required");
    }
    
    $instructor_id = $data["id"];
    
    $query = "SELECT u.id, u.name, u.email, u.profile_pic, u.created_at, 
             id.specialization, id.bio, id.qualification, id.experience_years 
             FROM users u 
             LEFT JOIN instructor_details id ON u.id = id.user_id 
             WHERE u.id = ? AND u.role = 'instructor'";
             
    $result = executeQuery($query, [$instructor_id], "i");
    
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            // Get courses taught by this instructor
            $courses_result = executeQuery(
                "SELECT c.*, COUNT(cr.id) as student_count, u.name as instructor_name, u.email as instructor_email 
                 FROM courses c 
                 LEFT JOIN course_registrations cr ON c.id = cr.course_id 
                 LEFT JOIN users u ON c.instructor_id = u.id 
                 WHERE c.instructor_id = ? 
                 GROUP BY c.id", 
                [$instructor_id], 
                "i"
            );
            
            $courses = [];
            if ($courses_result) {
                $courses_set = $courses_result->get_result();
                while ($course = $courses_set->fetch_assoc()) {
                    $courses[] = $course;
                }
                $courses_set->close();
            }
            
            $instructor = $row;
            $instructor['courses'] = $courses;
            
            sendResponse(200, "Instructor details retrieved successfully", $instructor);
        } else {
            sendResponse(404, "Instructor not found");
        }
        $result_set->close();
    } else {
        sendResponse(500, "Failed to retrieve instructor details");
    }
}

function getAllInstructors() {
    $query = "SELECT u.id, u.name, u.email, u.profile_pic, u.created_at, 
             id.specialization, id.qualification, id.experience_years 
             FROM users u 
             LEFT JOIN instructor_details id ON u.id = id.user_id 
             WHERE u.role = 'instructor'
             ORDER BY u.name ASC";
             
    $result = executeQuery($query, [], "");
    
    if ($result) {
        $instructors = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            // Get course count for each instructor
            $count_result = executeQuery(
                "SELECT COUNT(*) as count FROM courses WHERE instructor_id = ?", 
                [$row['id']], 
                "i"
            );
            $count_row = $count_result->get_result()->fetch_assoc();
            $row['course_count'] = $count_row['count'] ?? 0;
            
            $instructors[] = $row;
        }
        $result_set->close();
        
        sendResponse(200, "Instructors list retrieved successfully", $instructors);
    } else {
        sendResponse(500, "Failed to retrieve instructors list");
    }
}

?>
