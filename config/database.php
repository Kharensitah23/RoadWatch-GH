<?php
/**
 * RoadWatch GH - Database Configuration
 * Handles database connection for the application
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'roadwatch_gh');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Function to execute queries safely
function executeQuery($query, $params = array()) {
    global $conn;
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return array('success' => false, 'error' => $conn->error);
    }
    
    if (!empty($params)) {
        $types = '';
        $values = array();
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }
        
        $stmt->bind_param($types, ...$values);
    }
    
    if ($stmt->execute()) {
        return array('success' => true, 'stmt' => $stmt);
    } else {
        return array('success' => false, 'error' => $stmt->error);
    }
}

// Function to fetch data from database
function fetchData($query, $params = array()) {
    $result = executeQuery($query, $params);
    
    if ($result['success']) {
        $stmt = $result['stmt'];
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return array();
}

// Function to fetch single row
function fetchRow($query, $params = array()) {
    $result = executeQuery($query, $params);
    
    if ($result['success']) {
        $stmt = $result['stmt'];
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    return null;
}
?>
