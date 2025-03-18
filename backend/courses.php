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
    
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
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
                $instructor_result = executeQuery("SELECT * FROM instructor_details WHERE user_id=?", [$instructor_id], "i");
                if ($instructor_result) {
                    $instructor_result_set = $instructor_result->get_result();
                    if ($instructor_details = $instructor_result_set->fetch_assoc()) {
                        $courses[$key]['instructor_bio'] = $instructor_details['bio'];
                        $courses[$key]['instructor_qualifications'] = $instructor_details['qualifications'];
                        $courses[$key]['instructor_expertise'] = $instructor_details['expertise_areas'];
                        $courses[$key]['instructor_experience'] = $instructor_details['years_experience'];
                    }
                    $instructor_result_set->close(); // Close the result set
                }
                
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
            $instructor_result = executeQuery("SELECT * FROM instructor_details WHERE user_id=?", [$instructor_id], "i");
            if ($instructor_result) {
                $instructor_result_set = $instructor_result->get_result();
                if ($instructor_details = $instructor_result_set->fetch_assoc()) {
                    $course['instructor_bio'] = $instructor_details['bio'];
                    $course['instructor_qualifications'] = $instructor_details['qualifications'];
                    $course['instructor_expertise'] = $instructor_details['expertise_areas'];
                    $course['instructor_experience'] = $instructor_details['years_experience'];
                }
                $instructor_result_set->close(); // Close the result set
            }
            
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
            $instructor_result = executeQuery("SELECT * FROM instructor_details WHERE user_id=?", [$instructor_id], "i");
            if ($instructor_result) {
                $instructor_result_set = $instructor_result->get_result();
                if ($instructor_details = $instructor_result_set->fetch_assoc()) {
                    $courses[$key]['instructor_bio'] = $instructor_details['bio'];
                    $courses[$key]['instructor_qualifications'] = $instructor_details['qualifications'];
                    $courses[$key]['instructor_expertise'] = $instructor_details['expertise_areas'];
                    $courses[$key]['instructor_experience'] = $instructor_details['years_experience'];
                }
                $instructor_result_set->close(); // Close the result set
            }
            
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
        sendResponse(403, "Permission denied");
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
        sendResponse(200, "Verification status updated successfully");
    } else {
        sendResponse(500, "Failed to update verification status");
    }
}
?>