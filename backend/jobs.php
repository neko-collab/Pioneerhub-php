<?php
include 'db.php';
include 'jwt.php';
include 'utilities.php';
session_start();

$request_method = $_SERVER["REQUEST_METHOD"];

if ($request_method === "POST") {
    // Check for multipart form data (file uploads)
    if (isset($_POST['action'])) {
        $data = $_POST;
    } else {
        // Regular JSON request
        $data = json_decode(file_get_contents("php://input"), true);
    }
    
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
            case "viewJob":
                viewJob($data);
                break;
            case "applyJob":
                if ($user_role === 'user') {
                    applyJob($data, $user_id, $user_role);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "myApplications":
                if ($user_role === 'user') {
                    myApplications($user_id);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "jobApplications":
                if ($user_role === 'admin' || $user_role === 'employer') {
                    jobApplications($data, $user_role);
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
    listJobs();
} else {
    sendResponse(405, "Method Not Allowed");
}

function viewJob($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];

    $result = executeQuery("SELECT * FROM jobs WHERE id=?", [$id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            $result_set->close();
            sendResponse(200, "Job details", $row);
        } else {
            $result_set->close();
            sendResponse(400, "Job not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function listJobs() {
    $result = executeQuery("SELECT j.*, u.name as employer_name FROM jobs j 
                          LEFT JOIN users u ON j.posted_by = u.id 
                          ORDER BY j.created_at DESC", [], "");
    if ($result) {
        $jobs = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $jobs[] = $row;
        }
        $result_set->close();
        sendResponse(200, "Jobs list", $jobs);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function applyJob($data, $user_id, $user_role) {
    if (!isset($data["job_id"])) {
        sendResponse(400, "Job ID is required");
    }

    $job_id = $data["job_id"];
    $cv_path = null;
    $cover_letter = isset($data["cover_letter"]) ? $data["cover_letter"] : null;
    
    // Check if user already applied
    $checkResult = executeQuery("SELECT id FROM job_applications WHERE user_id = ? AND job_id = ?", 
                              [$user_id, $job_id], "ii");
    if ($checkResult) {
        $check_result_set = $checkResult->get_result();
        if ($check_result_set->num_rows > 0) {
            $check_result_set->close();
            sendResponse(400, "You have already applied for this job");
        }
        $check_result_set->close();
    }
    
    // Handle resume/CV file upload using the utility function
    if (isset($_FILES['cv'])) {
        $cv_path = handleFileUpload($_FILES['cv'], $user_id, 'job_cv');
        if ($cv_path === null && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
            sendResponse(500, "Failed to upload CV file");
        }
    }

    $query = "INSERT INTO job_applications (user_id, job_id, applied_at, status, cv, cover_letter) 
              VALUES (?, ?, NOW(), 'pending', ?, ?)";
    if (executeQuery($query, [$user_id, $job_id, $cv_path, $cover_letter], "iiss")) {
        sendResponse(200, "Job application successful");
    } else {
        sendResponse(500, "Failed to apply for job");
    }
}

function myApplications($user_id) {
    $query = "SELECT ja.id, ja.status, ja.applied_at, ja.cv, ja.cover_letter, 
              j.id as job_id, j.title, j.company, j.location, j.job_type
              FROM job_applications ja 
              JOIN jobs j ON ja.job_id = j.id 
              WHERE ja.user_id = ? 
              ORDER BY ja.applied_at DESC";
    
    $result = executeQuery($query, [$user_id], "i");
    if ($result) {
        $applications = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $applications[] = $row;
        }
        $result_set->close();
        sendResponse(200, "Your job applications", $applications);
    } else {
        sendResponse(500, "Failed to retrieve applications");
    }
}

function jobApplications($data, $user_role) {
    if (!isset($data["job_id"])) {
        sendResponse(400, "Job ID is required");
    }

    $job_id = $data["job_id"];

    $result = executeQuery("SELECT ja.id as application_id, u.id, u.name, u.email, 
                          ja.status, ja.cv, ja.cover_letter, ja.applied_at 
                          FROM users u 
                          JOIN job_applications ja ON u.id = ja.user_id 
                          WHERE ja.job_id = ? 
                          ORDER BY ja.applied_at DESC", 
                          [$job_id], "i");
    if ($result) {
        $applications = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $applications[] = $row;
        }
        $result_set->close();
        sendResponse(200, "Job applications list", $applications);
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

    // Validate status
    $valid_statuses = ['pending', 'reviewed', 'interview', 'selected', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        sendResponse(400, "Invalid status value");
    }

    $query = "UPDATE job_applications SET status=? WHERE id=?";
    if (executeQuery($query, [$status, $application_id], "si")) {
        // If status is 'selected', send email notification
        if ($status === 'selected') {
            // Get applicant info and job details
            $info_result = executeQuery(
                "SELECT u.name, u.email, j.title, j.company 
                 FROM job_applications ja
                 JOIN users u ON ja.user_id = u.id
                 JOIN jobs j ON ja.job_id = j.id
                 WHERE ja.id=?",
                [$application_id],
                "i"
            );
            
            if ($info_result) {
                $info_set = $info_result->get_result();
                if ($info = $info_set->fetch_assoc()) {
                    // Include mailer functions
                    include_once 'mailer.php';
                    
                    // Send email notification
                    sendJobApplicationApproval(
                        $info['email'],
                        $info['name'],
                        $info['title'],
                        $info['company']
                    );
                }
                $info_set->close();
            }
        }
        
        sendResponse(200, "Application status updated successfully");
    } else {
        sendResponse(500, "Failed to update application status");
    }
}
?>
