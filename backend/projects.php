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
            case "addProject":
                addProject($data, $user_id);
                break;
            case "editProject":
                editProject($data, $user_id);
                break;
            case "deleteProject":
                deleteProject($data, $user_id);
                break;
            case "viewProject":
                viewProject($data);
                break;
            case "requestCollaboration":
                requestCollaboration($data, $user_id);
                break;
            case "listCollaborationRequests":
                listCollaborationRequests($data, $user_id);
                break;
            case "respondToCollaborationRequest":
                respondToCollaborationRequest($data, $user_id);
                break;
            case "listUserProjects":
                listUserProjects($user_id);
                break;
            case "listCollaboratingProjects":
                listCollaboratingProjects($user_id);
                break;
            default:
                sendResponse(400, "Invalid action");
        }
    } else {
        sendResponse(400, "Action is required");
    }
} elseif ($request_method === "GET") {
    if (isset($_GET["id"])) {
        viewProject($_GET);
    } else {
        listProjects();
    }
} elseif ($request_method === "PUT" || $request_method === "PATCH") {
    $data = json_decode(file_get_contents("php://input"), true);
    $headers = apache_request_headers();
    
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $decoded = validateJWT($token);
        if (!$decoded) {
            sendResponse(401, "Unauthorized");
        }
        $user_id = $decoded->user_id;
    } else {
        sendResponse(401, "Authorization header is required");
    }
    
    editProject($data, $user_id);
} elseif ($request_method === "DELETE") {
    $data = json_decode(file_get_contents("php://input"), true);
    $headers = apache_request_headers();
    
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $decoded = validateJWT($token);
        if (!$decoded) {
            sendResponse(401, "Unauthorized");
        }
        $user_id = $decoded->user_id;
    } else {
        sendResponse(401, "Authorization header is required");
    }
    
    if (isset($_GET["id"])) {
        $data["id"] = $_GET["id"];
        deleteProject($data, $user_id);
    } else {
        sendResponse(400, "ID is required");
    }
} else {
    sendResponse(405, "Method Not Allowed");
}

function addProject($data, $user_id) {
    if (!isset($data["title"], $data["description"])) {
        sendResponse(400, "Title and description are required");
    }

    $title = $data["title"];
    $description = $data["description"];

    $query = "INSERT INTO projects (title, description, submitted_by, created_at) VALUES (?, ?, ?, NOW())";
    if (executeQuery($query, [$title, $description, $user_id], "ssi")) {
        $project_id = mysqli_insert_id(getConnection());
        
        // Return the newly created project
        $result = executeQuery("SELECT projects.*, users.name as submitter_name FROM projects JOIN users ON projects.submitted_by = users.id WHERE projects.id=?", [$project_id], "i");
        if ($result) {
            $result_set = $result->get_result();
            if ($row = $result_set->fetch_assoc()) {
                $result_set->close(); // Close the result set
                sendResponse(201, "Project added successfully", $row);
            } else {
                $result_set->close(); // Close the result set
                sendResponse(200, "Project added successfully");
            }
        } else {
            sendResponse(200, "Project added successfully");
        }
    } else {
        sendResponse(500, "Failed to add project");
    }
}

function editProject($data, $user_id) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];
    
    // Check if the user is the owner of the project
    $result = executeQuery("SELECT * FROM projects WHERE id=?", [$id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            if ($row['submitted_by'] != $user_id) {
                $result_set->close(); // Close the result set
                sendResponse(403, "Permission denied. You are not the owner of this project.");
            }
            $result_set->close(); // Close the result set
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Project not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }
    
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

    if (empty($fields)) {
        sendResponse(400, "No fields to update");
    }

    $params[] = $id;
    $types .= "i";

    $query = "UPDATE projects SET " . implode(", ", $fields) . " WHERE id=?";
    if (executeQuery($query, $params, $types)) {
        
        // Return the updated project
        $result = executeQuery("SELECT projects.*, users.name as submitter_name FROM projects JOIN users ON projects.submitted_by = users.id WHERE projects.id=?", [$id], "i");
        if ($result) {
            $result_set = $result->get_result();
            if ($row = $result_set->fetch_assoc()) {
                $result_set->close(); // Close the result set
                sendResponse(200, "Project updated successfully", $row);
            } else {
                $result_set->close(); // Close the result set
                sendResponse(200, "Project updated successfully");
            }
        } else {
            sendResponse(200, "Project updated successfully");
        }
    } else {
        sendResponse(500, "Failed to update project");
    }
}

function deleteProject($data, $user_id) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];
    
    // Check if the user is the owner of the project
    $result = executeQuery("SELECT * FROM projects WHERE id=?", [$id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            if ($row['submitted_by'] != $user_id) {
                $result_set->close(); // Close the result set
                sendResponse(403, "Permission denied. You are not the owner of this project.");
            }
            $result_set->close(); // Close the result set
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Project not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }

    $query = "DELETE FROM projects WHERE id=?";
    if (executeQuery($query, [$id], "i")) {
        sendResponse(200, "Project deleted successfully");
    } else {
        sendResponse(500, "Failed to delete project");
    }
}

function viewProject($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];

    // Get project details with submitter information
    $result = executeQuery("SELECT projects.*, users.name as submitter_name, users.email as submitter_email FROM projects JOIN users ON projects.submitted_by = users.id WHERE projects.id=?", [$id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            $project = $row;
            $result_set->close(); // Close the result set
            
            // Get collaborators for the project
            $collaborators_result = executeQuery(
                "SELECT users.id, users.name, users.email, project_collaborations.status 
                FROM users 
                JOIN project_collaborations ON users.id = project_collaborations.user_id 
                WHERE project_collaborations.project_id=?", 
                [$id], 
                "i"
            );
            
            if ($collaborators_result) {
                $collaborators = [];
                $collaborators_result_set = $collaborators_result->get_result();
                while ($collaborator = $collaborators_result_set->fetch_assoc()) {
                    $collaborators[] = $collaborator;
                }
                $collaborators_result_set->close(); // Close the result set
                $project['collaborators'] = $collaborators;
            }
            
            sendResponse(200, "Project details", $project);
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Project not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function listProjects() {
    // Get all projects with submitter information
    $result = executeQuery(
        "SELECT projects.*, users.name as submitter_name, users.email as submitter_email 
        FROM projects 
        JOIN users ON projects.submitted_by = users.id
        ORDER BY projects.created_at DESC", 
        [], 
        ""
    );
    
    if ($result) {
        $projects = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $projects[] = $row;
        }
        $result_set->close(); // Close the result set
        
        // Get collaborators count for each project
        foreach ($projects as $key => $project) {
            $project_id = $project['id'];
            $collaborators_result = executeQuery(
                "SELECT COUNT(*) as collaborator_count 
                FROM project_collaborations 
                WHERE project_id=? AND status='approved'", 
                [$project_id], 
                "i"
            );
            
            if ($collaborators_result) {
                $collaborators_result_set = $collaborators_result->get_result();
                if ($count = $collaborators_result_set->fetch_assoc()) {
                    $projects[$key]['collaborator_count'] = $count['collaborator_count'];
                }
                $collaborators_result_set->close(); // Close the result set
            }
        }
        
        sendResponse(200, "Projects list", $projects);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function requestCollaboration($data, $user_id) {
    if (!isset($data["project_id"])) {
        sendResponse(400, "Project ID is required");
    }

    $project_id = $data["project_id"];
    
    // Check if the user already has a collaboration request for this project
    $result = executeQuery("SELECT * FROM project_collaborations WHERE project_id=? AND user_id=?", [$project_id, $user_id], "ii");
    if ($result) {
        $result_set = $result->get_result();
        if ($result_set->num_rows > 0) {
            $result_set->close(); // Close the result set
            sendResponse(400, "You have already requested collaboration for this project");
        }
        $result_set->close(); // Close the result set
    }
    
    // Check if the user is the owner of the project
    $result = executeQuery("SELECT * FROM projects WHERE id=?", [$project_id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            if ($row['submitted_by'] == $user_id) {
                $result_set->close(); // Close the result set
                sendResponse(400, "You cannot request collaboration for your own project");
            }
            $result_set->close(); // Close the result set
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Project not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }

    $query = "INSERT INTO project_collaborations (project_id, user_id, status, requested_at) VALUES (?, ?, 'pending', NOW())";
    if (executeQuery($query, [$project_id, $user_id], "ii")) {
        sendResponse(200, "Collaboration request sent successfully");
    } else {
        sendResponse(500, "Failed to send collaboration request");
    }
}

function listCollaborationRequests($data, $user_id) {
    if (!isset($data["project_id"])) {
        sendResponse(400, "Project ID is required");
    }

    $project_id = $data["project_id"];
    
    // Check if the user is the owner of the project
    $result = executeQuery("SELECT * FROM projects WHERE id=?", [$project_id], "i");
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            if ($row['submitted_by'] != $user_id) {
                $result_set->close(); // Close the result set
                sendResponse(403, "Permission denied. You are not the owner of this project.");
            }
            $result_set->close(); // Close the result set
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Project not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }

    $result = executeQuery(
        "SELECT users.id, users.name, users.email, project_collaborations.id as request_id, project_collaborations.status, project_collaborations.requested_at 
        FROM users 
        JOIN project_collaborations ON users.id = project_collaborations.user_id 
        WHERE project_collaborations.project_id=?
        ORDER BY project_collaborations.requested_at DESC", 
        [$project_id], 
        "i"
    );
    
    if ($result) {
        $requests = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $requests[] = $row;
        }
        $result_set->close(); // Close the result set
        sendResponse(200, "Collaboration requests list", $requests);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function respondToCollaborationRequest($data, $user_id) {
    if (!isset($data["request_id"], $data["status"])) {
        sendResponse(400, "Request ID and status are required");
    }

    $request_id = $data["request_id"];
    $status = $data["status"];
    
    if ($status !== 'approved' && $status !== 'rejected') {
        sendResponse(400, "Status must be 'approved' or 'rejected'");
    }
    
    // Get the project ID from the request
    $result = executeQuery(
        "SELECT project_collaborations.project_id, projects.submitted_by 
        FROM project_collaborations 
        JOIN projects ON project_collaborations.project_id = projects.id 
        WHERE project_collaborations.id=?", 
        [$request_id], 
        "i"
    );
    
    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            // Check if the user is the owner of the project
            if ($row['submitted_by'] != $user_id) {
                $result_set->close(); // Close the result set
                sendResponse(403, "Permission denied. You are not the owner of this project.");
            }
            $result_set->close(); // Close the result set
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Collaboration request not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }

    $query = "UPDATE project_collaborations SET status=? WHERE id=?";
    if (executeQuery($query, [$status, $request_id], "si")) {
        sendResponse(200, "Collaboration request " . $status);
    } else {
        sendResponse(500, "Failed to update collaboration request");
    }
}

function listUserProjects($user_id) {
    // Get projects submitted by the user
    $result = executeQuery(
        "SELECT projects.*, users.name as submitter_name, users.email as submitter_email 
        FROM projects 
        JOIN users ON projects.submitted_by = users.id 
        WHERE projects.submitted_by=?
        ORDER BY projects.created_at DESC", 
        [$user_id], 
        "i"
    );
    
    if ($result) {
        $projects = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $projects[] = $row;
        }
        $result_set->close(); // Close the result set
        
        // Get collaborators count for each project
        foreach ($projects as $key => $project) {
            $project_id = $project['id'];
            $collaborators_result = executeQuery(
                "SELECT COUNT(*) as collaborator_count 
                FROM project_collaborations 
                WHERE project_id=? AND status='approved'", 
                [$project_id], 
                "i"
            );
            
            if ($collaborators_result) {
                $collaborators_result_set = $collaborators_result->get_result();
                if ($count = $collaborators_result_set->fetch_assoc()) {
                    $projects[$key]['collaborator_count'] = $count['collaborator_count'];
                }
                $collaborators_result_set->close(); // Close the result set
            }
        }
        
        sendResponse(200, "User projects list", $projects);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function listCollaboratingProjects($user_id) {
    // Get projects where the user is a collaborator
    $result = executeQuery(
        "SELECT projects.*, users.name as submitter_name, users.email as submitter_email, 
         project_collaborations.status as collaboration_status
        FROM projects 
        JOIN users ON projects.submitted_by = users.id 
        JOIN project_collaborations ON projects.id = project_collaborations.project_id 
        WHERE project_collaborations.user_id=?
        ORDER BY projects.created_at DESC", 
        [$user_id], 
        "i"
    );
    
    if ($result) {
        $projects = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
            $projects[] = $row;
        }
        $result_set->close(); // Close the result set
        
        // Get collaborators count for each project
        foreach ($projects as $key => $project) {
            $project_id = $project['id'];
            $collaborators_result = executeQuery(
                "SELECT COUNT(*) as collaborator_count 
                FROM project_collaborations 
                WHERE project_id=? AND status='approved'", 
                [$project_id], 
                "i"
            );
            
            if ($collaborators_result) {
                $collaborators_result_set = $collaborators_result->get_result();
                if ($count = $collaborators_result_set->fetch_assoc()) {
                    $projects[$key]['collaborator_count'] = $count['collaborator_count'];
                }
                $collaborators_result_set->close(); // Close the result set
            }
        }
        
        sendResponse(200, "Collaborating projects list", $projects);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function getConnection() {
    global $conn;
    return $conn;
}
?>