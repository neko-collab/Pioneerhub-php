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
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        
        if ($result === false) {
            // Handle execution error
            error_log("SQL Query Execution Error: " . $stmt->error . " - Query: " . $query);
            $stmt->close();
            return false;
        }
        
        return $stmt;
    } catch (mysqli_sql_exception $e) {
        // Handle exceptions like "table doesn't exist"
        error_log("SQL Exception: " . $e->getMessage() . " - Query: " . $query);
        
        // For table doesn't exist errors, we'll handle it more gracefully
        if (strpos($e->getMessage(), "doesn't exist") !== false && strpos($query, "instructor_details") !== false) {
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
?>