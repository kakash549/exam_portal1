<?php
// Start session
session_start();

// Include database connection
include '../includes/db.php';

// Prevent CLI execution
if (php_sapi_name() === 'cli') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'This script is intended to be run via a web server, not the CLI. Use a POST request with JSON data.']);
    exit;
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Check user authentication and role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get raw POST data
$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

// Validate input data
$type = isset($data['type']) ? filter_var($data['type'], FILTER_SANITIZE_STRING) : 'unknown';
$exam_id = isset($data['exam_id']) ? (int) $data['exam_id'] : null;
$user_id = $_SESSION['user_id'] ?? null;

if ($exam_id === null || $user_id === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Exam ID and User ID are required']);
    exit;
}

// Log errors to a file for debugging (optional, adjust path)
$logFile = '../logs/proctoring_errors.log';
function logError($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Prepare and execute SQL statement
$stmt = $conn->prepare("INSERT INTO proctoring_logs (user_id, exam_id, violation_type, logged_at) VALUES (?, ?, ?, NOW())");
if ($stmt === false) {
    $error = 'Database preparation failed: ' . $conn->error;
    logError($error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $error]);
    exit;
}

$stmt->bind_param("iis", $user_id, $exam_id, $type);
if (!$stmt->execute()) {
    $error = 'Database execution failed: ' . $stmt->error;
    logError($error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $error]);
    exit;
}

$stmt->close();
http_response_code(200); // OK
echo json_encode(['success' => true, 'message' => 'Violation logged successfully']);
?>