<?php
// update_personnel_status.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'includes/config.php';

ob_clean();
header('Content-Type: application/json');

function sendJsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Only Super Admin can change status
if (!isset($_SESSION['user_role']) || (int)$_SESSION['user_role'] !== 2) {
    sendJsonResponse(false, 'Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

$personnel_number = isset($_POST['personnel_number']) ? trim($_POST['personnel_number']) : '';
$current_status = isset($_POST['current_status']) ? trim($_POST['current_status']) : '';

$allowed_statuses = ['Active', 'Training', 'Mission', 'Leave', 'Retired'];

if (empty($personnel_number)) {
    sendJsonResponse(false, 'Personnel number is required');
}

if (!in_array($current_status, $allowed_statuses, true)) {
    sendJsonResponse(false, 'Invalid status value');
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_number = ?");
    $stmt->execute([$personnel_number]);
    if ($stmt->fetchColumn() == 0) {
        sendJsonResponse(false, 'Personnel not found');
    }

    $stmt = $pdo->prepare("UPDATE personnel SET current_status = ? WHERE personnel_number = ?");
    $result = $stmt->execute([$current_status, $personnel_number]);

    if ($result) {
        sendJsonResponse(true, 'Status updated successfully!');
    } else {
        sendJsonResponse(false, 'Failed to update status');
    }
} catch (PDOException $e) {
    error_log("Update status error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred');
}
