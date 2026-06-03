<?php
// get_all_leave_balances.php
session_start();
require_once 'includes/config.php';

// Clear any output buffers
ob_clean();
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Unauthorized access');
}

// Check if user is admin or super admin
$user_role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
if ($user_role < 1) {
    sendJsonResponse(false, 'Permission denied');
}

try {
    // Get all records from leave_balance table only
    $query = "SELECT 
                id, 
                personnel_id, 
                gharpari_bida_days, 
                parba_bida_days, 
                bhaeepari_bida_days, 
                last_updated 
              FROM leave_balance 
              ORDER BY id ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJsonResponse(true, 'Success', $results);
    
} catch (PDOException $e) {
    error_log("Get all leave balances error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error: ' . $e->getMessage());
}
?>