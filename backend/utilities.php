<?php
function executeQuery($query, $params = [], $types = "") {
    global $conn;
    try {
        $stmt = $conn->prepare($query);
        
        if ($stmt === false) {
            // Handle prepare error - usually SQL syntax error
            error_log("SQL Query Prepare Error: " . $conn->error . " - Query: " . $query);
            return false;
        }
        
        if (!empty($params) && !empty($types)) {
            if (!$stmt->bind_param($types, ...$params)) {
                error_log("SQL Bind Param Error: " . $stmt->error . " - Query: " . $query);
                $stmt->close();
                return false;
            }
        }
        
        $result = $stmt->execute();
        
        if ($result === false) {
            // Handle execution error
            error_log("SQL Query Execution Error: " . $stmt->error . " - Query: " . $query);
            $stmt->close();
            return false;
        }
        
        return $stmt;
    } catch (Exception $e) {
        // Handle exceptions like "table doesn't exist"
        error_log("SQL Exception: " . $e->getMessage() . " - Query: " . $query);
        
        // For table doesn't exist errors, handle it more gracefully
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            // Create a temporary empty result set or return an appropriate value
            // depending on what the calling code expects
            return createEmptyResultForMissingTable();
        }
        
        return false;
    }
}

function createEmptyResultForMissingTable() {
    // Create a mock object that behaves similarly to what the calling code expects
    $mock = new class {
        public function get_result() {
            return new class {
                public $num_rows = 0;
                public function fetch_assoc() { return null; }
                public function close() {}
            };
        }
        public function close() {}
    };
    
    return $mock;
}

/**
 * Safely get array value with a default if not set
 * 
 * @param array $array The array to get value from
 * @param string $key The key to get value for
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The value or default
 */
function safeArrayGet($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

function sendResponse($status, $message, $data = [], $responseType = 'json') {
    http_response_code($status);
    
    if ($responseType === 'json') {
        header('Content-Type: application/json');
        echo json_encode(["status" => $status, "message" => $message, "data" => $data]);
    } else if ($responseType === 'html') {
        // For HTML redirects or full page responses
        echo $message; // $message should contain the HTML content
    } else if ($responseType === 'redirect') {
        // For simple redirects
        header("Location: $message");
    }
    exit();
}

function bearerToken() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * Handles file uploads with a standardized approach
 * 
 * @param array $file The $_FILES array element for the uploaded file
 * @param int $user_id The ID of the user uploading the file
 * @param string $subdir The subdirectory within the uploads folder
 * @return string|null The relative path to the file or null on failure
 */
function handleFileUpload($file, $user_id, $subdir = '') {
    // Validate file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Define base directory and relative path
    $base_dir = $_SERVER['DOCUMENT_ROOT'] . '/Pioneer/uploads/';
    $relative_dir = $subdir ? trim($subdir, '/') . '/' : '';
    $upload_dir = $base_dir . $relative_dir;
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_name = $user_id . '_' . time() . '_' . basename($file['name']);
    $target_file = $upload_dir . $file_name;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Return only the relative path for database storage
        return $relative_dir . $file_name;
    }
    
    return null;
}

/**
 * Gets the full URL path for a stored file
 * 
 * @param string $relativePath The relative path stored in the database
 * @return string The full URL path to the file
 */
function getFileUrl($relativePath) {
    if (empty($relativePath)) {
        return null;
    }
    
    // Define base URL for files
    $base_url = '/Pioneer/uploads/';
    
    // Return full URL path
    return $base_url . $relativePath;
}
?>