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

// Handle API requests
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
            case "addEnquiry":
                addEnquiry($data, $user_id);
                break;
            case "viewEnquiries":
                viewUserEnquiries($user_id);
                break;
            case "viewEnquiry":
                viewEnquiry($data, $user_id);
                break;
            case "listAllEnquiries":
                if ($user_role !== 'admin') {
                    sendResponse(403, "Permission denied");
                }
                listAllEnquiries();
                break;
            case "updateEnquiryStatus":
                if ($user_role !== 'admin') {
                    sendResponse(403, "Permission denied");
                }
                updateEnquiryStatus($data);
                break;
            case "respondToEnquiry":
                if ($user_role !== 'admin') {
                    sendResponse(403, "Permission denied");
                }
                respondToEnquiry($data);
                break;
            default:
                sendResponse(400, "Invalid action");
        }
    } else {
        sendResponse(400, "Action is required");
    }
} elseif ($request_method === "GET") {
    $headers = apache_request_headers();
    
    if (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
        $decoded = validateJWT($token);
        if (!$decoded) {
            sendResponse(401, "Unauthorized");
        }
        $user_role = $decoded->role;
        $user_id = $decoded->user_id;
        
        if (isset($_GET["id"])) {
            viewEnquiry(["id" => $_GET["id"]], $user_id);
        } else {
            viewUserEnquiries($user_id);
        }
    } else {
        sendResponse(401, "Authorization header is required");
    }
} else {
    sendResponse(405, "Method Not Allowed");
}

// Function to add a new enquiry
function addEnquiry($data, $user_id) {
    if (!isset($data["title"], $data["content"], $data["email"])) {
        sendResponse(400, "Title, content, and email are required");
    }

    $title = $data["title"];
    $content = $data["content"];
    $email = $data["email"];
    $phone = isset($data["phone"]) ? $data["phone"] : null;

    $query = "INSERT INTO enquiries (user_id, title, content, email, phone, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    if (executeQuery($query, [$user_id, $title, $content, $email, $phone], "issss")) {
        $enquiry_id = mysqli_insert_id(getConnection());
        
        // Return the newly created enquiry
        $result = executeQuery("SELECT * FROM enquiries WHERE id=?", [$enquiry_id], "i");
        if ($result) {
            $result_set = $result->get_result();
            if ($row = $result_set->fetch_assoc()) {
                $result_set->close();
                sendResponse(201, "Enquiry submitted successfully", $row);
            } else {
                $result_set->close();
                sendResponse(200, "Enquiry submitted successfully");
            }
        } else {
            sendResponse(200, "Enquiry submitted successfully");
        }
    } else {
        sendResponse(500, "Failed to submit enquiry");
    }
}

// Function to view a specific enquiry
function viewEnquiry($data, $user_id) {
    if (!isset($data["id"])) {
        sendResponse(400, "Enquiry ID is required");
    }

    $id = $data["id"];

    // Get enquiry details
    $result = executeQuery("SELECT * FROM enquiries WHERE id=?", [$id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            // Check if user is authorized to view this enquiry
            if ($row['user_id'] != $user_id) {
                $result_set->close();
                sendResponse(403, "Permission denied. You can only view your own enquiries.");
            }
            $result_set->close();
            sendResponse(200, "Enquiry details", $row);
        } else {
            $result_set->close();
            sendResponse(404, "Enquiry not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

// Function to view all enquiries from a user
function viewUserEnquiries($user_id) {
    $result = executeQuery(
        "SELECT * FROM enquiries WHERE user_id=? ORDER BY created_at DESC", 
        [$user_id], 
        "i"
    );
    
    if ($result) {
        $enquiries = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $enquiries[] = $row;
        }
        $result_set->close();
        sendResponse(200, "User enquiries list", $enquiries);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

// Admin function to list all enquiries
function listAllEnquiries() {
    $result = executeQuery(
        "SELECT e.*, u.name as user_name, u.email as user_email 
        FROM enquiries e 
        LEFT JOIN users u ON e.user_id = u.id 
        ORDER BY e.created_at DESC", 
        [], 
        ""
    );
    
    if ($result) {
        $enquiries = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $enquiries[] = $row;
        }
        $result_set->close();
        sendResponse(200, "All enquiries list", $enquiries);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

// Admin function to update enquiry status
function updateEnquiryStatus($data) {
    if (!isset($data["id"], $data["status"])) {
        sendResponse(400, "Enquiry ID and status are required");
    }

    $id = $data["id"];
    $status = $data["status"];
    
    // Validate status
    $valid_statuses = ['pending', 'in_progress', 'resolved', 'closed'];
    if (!in_array($status, $valid_statuses)) {
        sendResponse(400, "Invalid status. Status must be one of: " . implode(", ", $valid_statuses));
    }

    $query = "UPDATE enquiries SET status=? WHERE id=?";
    if (executeQuery($query, [$status, $id], "si")) {
        sendResponse(200, "Enquiry status updated successfully");
    } else {
        sendResponse(500, "Failed to update enquiry status");
    }
}

// Admin function to respond to an enquiry
function respondToEnquiry($data) {
    if (!isset($data["id"], $data["admin_response"])) {
        sendResponse(400, "Enquiry ID and admin response are required");
    }

    $id = $data["id"];
    $admin_response = $data["admin_response"];
    $status = isset($data["status"]) ? $data["status"] : "in_progress";
    
    // Validate status
    $valid_statuses = ['pending', 'in_progress', 'resolved', 'closed'];
    if (!in_array($status, $valid_statuses)) {
        sendResponse(400, "Invalid status. Status must be one of: " . implode(", ", $valid_statuses));
    }

    $query = "UPDATE enquiries SET admin_response=?, status=? WHERE id=?";
    if (executeQuery($query, [$admin_response, $status, $id], "ssi")) {
        sendResponse(200, "Response added successfully");
    } else {
        sendResponse(500, "Failed to add response");
    }
}

function getConnection() {
    global $conn;
    return $conn;
}
?>
