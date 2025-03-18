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
    } else {
        sendResponse(401, "Authorization header is required");
    }
    
    if (isset($data["action"])) {
        switch ($data["action"]) {
            case "addInfo":
                if ($user_role === 'admin') {
                    addInfo($data);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "editInfo":
                if ($user_role === 'admin') {
                    editInfo($data);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "deleteInfo":
                if ($user_role === 'admin') {
                    deleteInfo($data);
                } else {
                    sendResponse(403, "Permission denied");
                }
                break;
            case "viewInfo":
                viewInfo($data);
                break;
            default:
                sendResponse(400, "Invalid action");
        }
    } else {
        sendResponse(400, "Action is required");
    }
} elseif ($request_method === "GET") {
    listInfo();
} else {
    sendResponse(405, "Method Not Allowed");
}

function addInfo($data) {
    if (!isset($data["name"], $data["email"], $data["address"], $data["phone"], $data["website"], $data["description"])) {
        sendResponse(400, "Name, email, address, phone, website, and description are required");
    }

    $name = $data["name"];
    $email = $data["email"];
    $logo = isset($data["logo"]) ? $data["logo"] : null;
    $address = $data["address"];
    $phone = $data["phone"];
    $website = $data["website"];
    $description = $data["description"];

    $query = "INSERT INTO pioneerhub_info (name, email, logo, address, phone, website, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    if (executeQuery($query, [$name, $email, $logo, $address, $phone, $website, $description], "sssssss")) {
        sendResponse(200, "Info added successfully");
    } else {
        sendResponse(500, "Failed to add info");
    }
}

function editInfo($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];
    $fields = [];
    $params = [];
    $types = "";

    if (isset($data["name"])) {
        $fields[] = "name=?";
        $params[] = $data["name"];
        $types .= "s";
    }
    if (isset($data["email"])) {
        $fields[] = "email=?";
        $params[] = $data["email"];
        $types .= "s";
    }
    if (isset($data["logo"])) {
        $fields[] = "logo=?";
        $params[] = $data["logo"];
        $types .= "s";
    }
    if (isset($data["address"])) {
        $fields[] = "address=?";
        $params[] = $data["address"];
        $types .= "s";
    }
    if (isset($data["phone"])) {
        $fields[] = "phone=?";
        $params[] = $data["phone"];
        $types .= "s";
    }
    if (isset($data["website"])) {
        $fields[] = "website=?";
        $params[] = $data["website"];
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

    $query = "UPDATE pioneerhub_info SET " . implode(", ", $fields) . " WHERE id=?";
    if (executeQuery($query, $params, $types)) {
        sendResponse(200, "Info updated successfully");
    } else {
        sendResponse(500, "Failed to update info");
    }
}

function deleteInfo($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];

    $query = "DELETE FROM pioneerhub_info WHERE id=?";
    if (executeQuery($query, [$id], "i")) {
        sendResponse(200, "Info deleted successfully");
    } else {
        sendResponse(500, "Failed to delete info");
    }
}

function viewInfo($data) {
    if (!isset($data["id"])) {
        sendResponse(400, "ID is required");
    }

    $id = $data["id"];
    $result = executeQuery("SELECT * FROM pioneerhub_info WHERE id=?", [$id], "i");

    if ($result) {
        $result_set = $result->get_result();
        if ($row = $result_set->fetch_assoc()) {
            $result_set->close(); // Close the result set
            sendResponse(200, "Info details", $row);
        } else {
            $result_set->close(); // Close the result set
            sendResponse(400, "Info not found");
        }
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

function listInfo() {
    $result = executeQuery("SELECT * FROM pioneerhub_info LIMIT 1", [], "");
    if ($result) {
        $info = [];
        $result_set = $result->get_result();
        while ($row = $result_set->fetch_assoc()) {
             // get the total number of courses, internships, and projects, 
            // and add them to the $row array

            $row["courses"] = 0;
            $row["internships"] = 0;
            $row["projects"] = 0;

            $result = executeQuery("SELECT COUNT(*) AS total FROM courses", [], "");
            if ($result) {
                $row["courses"] = $result->get_result()->fetch_assoc()["total"];
            }

            $result = executeQuery("SELECT COUNT(*) AS total FROM internships", [], "");
            if ($result) {
                $row["internships"] = $result->get_result()->fetch_assoc()["total"];
            }

            $result = executeQuery("SELECT COUNT(*) AS total FROM projects", [], "");
            if ($result) {
                $row["projects"] = $result->get_result()->fetch_assoc()["total"];
            }

            $result = executeQuery("SELECT COUNT(*) AS total FROM users WHERE role='instructor'", [], "");
            if ($result) {
                $row["instructors"] = $result->get_result()->fetch_assoc()["total"];
            }

            // add the $row array to the $info array
            $info[] = $row;
        }
            $result_set->close(); // Close the result set

            sendResponse(200, "Info list", $info);
    } else {
        sendResponse(500, "Failed to execute query");
    }
}

?>