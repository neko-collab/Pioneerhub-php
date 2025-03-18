<?php
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
            case "addInternship":
                if ($user_role === 'admin' || $user_role === 'employer') {
                    addInternship($data, $user_role, $user_id);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "editInternship":
                if ($user_role === 'admin' || $user_role === 'employer') {
                    editInternship($data, $user_role, $user_id);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "deleteInternship":
                if ($user_role === 'admin' || $user_role === 'employer') {
                    deleteInternship($data, $user_role);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "viewInternship":
                viewInternship($data);
                break;
            case "applyInternship":
                if ($user_role === 'user') {
                    applyInternship($data, $user_id, $user_role);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "internshipApplications":
                if ($user_role === 'admin' || $user_role === 'employer') {
                    internshipApplications($data, $user_role);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "toggleApplicationStatus":
                if ($user_role === 'admin' || $user_role === 'employer') {
                    toggleApplicationStatus($data, $user_role);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            default:
                sendResponse(400, "Invalid action");
        }
    } else {
        sendResponse(400, "Action is required");
    }
} elseif ($request_method === "GET") {
    listInternships();
} elseif ($request_method === "PUT" || $request_method === "PATCH") {
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
    
    if ($user_role === 'admin' || $user_role === 'employer') {
        editInternship($data, $user_role, $user_id);
    } else {
        sendResponse(403, "Permission denied");
    }
} else {
    sendResponse(405, "Method Not Allowed");
}

function addInternship($data, $user_role, $user_id) {
    if (!isset($data["title"], $data["description"], $data['company'], $data['location'], $data['internship_type'])) {
        sendResponse(400, "Title, description, company, location, and internship_type are required");
    }

    $title = $data["title"];
    $description = $data["description"];
    $company = $data["company"];
    $location = $data["location"];
    $internship_type = $data["internship_type"];

    $query = "INSERT INTO internships (title, description, posted_by, company, location, internship_type, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    if (executeQuery($query, [$title, $description, $user_id, $company, $location, $internship_type], "ssisss")) {
        sendResponse(200, "Internship added successfully");
    } else {
        sendResponse(500, "Failed to add internship");
    }
}

function editInternship($data, $user_role, $user_id) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];
    $fields = [];
    $params = [];
    $types = "";

    if (isset($data["title"])) {
        $fields[] = "title=?";
        $params[] = $data["title"];
        $types .= "s";
    }
    if (isset($data["description"])) {
        $fields[] = "description=?";
        $params[] = $data["description"];
        $types .= "s";
    }
    if (isset($data["company"])) {
        $fields[] = "company=?";
        $params[] = $data["company"];
        $types .= "s";
    }
    if (isset($data["location"])) {
        $fields[] = "location=?";
        $params[] = $data["location"];
        $types .= "s";
    }
    if (isset($data["internship_type"])) {
        $fields[] = "internship_type=?";
        $params[] = $data["internship_type"];
        $types .= "s";
    }

    if (empty($fields)) {
        sendResponse(400, "No fields to update");
    }

    $params[] = $id;
    $types .= "i";

    $query = "UPDATE internships SET " . implode(", ", $fields) . " WHERE id=?";
    if (executeQuery($query, $params, $types)) {
        sendResponse(200, "Internship updated successfully");
    } else {
        sendResponse(500, "Failed to update internship");
    }
}

function deleteInternship($data, $user_role) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];

    $query = "DELETE FROM internships WHERE id=?";
    if (executeQuery($query, [$id], "i")) {
        sendResponse(200, "Internship deleted successfully");
    } else {
        sendResponse(500, "Failed to delete internship");
    }
}

function viewInternship($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];

    $result = executeQuery("SELECT * FROM internships WHERE id=?", [$id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            $result_set->close(); // Close the result set
            sendResponse(200, "Internship details", $row);
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Internship not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function listInternships() {
    // with posted_by user information also with employer_details with same 
    $result = executeQuery("SELECT * FROM internships", [], "");
    if ($result) {
        $internships = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            //
            $internships[] = $row;
        }
        $result_set->close(); // Close the result set
        sendResponse(200, "Internships list", $internships);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function applyInternship($data, $user_id, $user_role) {
    if (!isset($data["internship_id"])) {
        sendResponse(400, "Internship ID is required");
    }

    $internship_id = $data["internship_id"];

    $query = "INSERT INTO internship_applications (user_id, internship_id, applied_at, status) VALUES (?, ?, NOW(), 'pending')";
    if (executeQuery($query, [$user_id, $internship_id], "ii")) {
        sendResponse(200, "Internship application successful");
    } else {
        sendResponse(500, "Failed to apply for internship");
    }
}

function internshipApplications($data, $user_role) {
    if (!isset($data["internship_id"])) {
        sendResponse(400, "Internship ID is required");
    }

    $internship_id = $data["internship_id"];

    $result = executeQuery("SELECT users.id, users.name, users.email, internship_applications.status FROM users JOIN internship_applications ON users.id = internship_applications.user_id WHERE internship_applications.internship_id=?", [$internship_id], "i");
    if ($result) {
        $applications = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $applications[] = $row;
        }
        $result_set->close(); // Close the result set
        sendResponse(200, "Internship applications list", $applications);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function toggleApplicationStatus($data, $user_role) {
    if (!isset($data["application_id"], $data["status"])) {
        sendResponse(400, "Application ID and status are required");
    }

    $application_id = $data["application_id"];
    $status = $data["status"];

    $query = "UPDATE internship_applications SET status=? WHERE id=?";
    if (executeQuery($query, [$status, $application_id], "si")) {
        sendResponse(200, "Application status updated successfully");
    } else {
        sendResponse(500, "Failed to update application status");
    }
}


?>