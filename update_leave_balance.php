<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if user is admin or super admin
$user_role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
if ($user_role < 1) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Log the received data for debugging
error_log("Update leave balance received: " . print_r($input, true));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

$personnel_id = isset($input['personnel_id']) ? (int)$input['personnel_id'] : 0;
$gharpari_bida = isset($input['gharpari_bida']) ? (float)$input['gharpari_bida'] : null;
$parba_bida = isset($input['parba_bida']) ? (float)$input['parba_bida'] : null;
$bhaeepari_bida = isset($input['bhaeepari_bida']) ? (float)$input['bhaeepari_bida'] : null;

if ($personnel_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid personnel ID: ' . $personnel_id]);
    exit;
}

try {
    // First, verify the personnel exists in military_personnel_status
    $check_personnel = $pdo->prepare("SELECT id FROM military_personnel_status WHERE id = ?");
    $check_personnel->execute([$personnel_id]);
    
    if ($check_personnel->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Personnel not found in military_personnel_status table']);
        exit;
    }
    
    // Check if record exists in leave_balance
    $check_stmt = $pdo->prepare("SELECT id, gharpari_bida_days, parba_bida_days, bhaeepari_bida_days FROM leave_balance WHERE personnel_id = ?");
    $check_stmt->execute([$personnel_id]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        // Create new record with provided values
        $insert_stmt = $pdo->prepare("
            INSERT INTO leave_balance (personnel_id, gharpari_bida_days, parba_bida_days, bhaeepari_bida_days) 
            VALUES (?, ?, ?, ?)
        ");
        $insert_stmt->execute([
            $personnel_id,
            $gharpari_bida !== null ? $gharpari_bida : 15.0,
            $parba_bida !== null ? $parba_bida : 12.0,
            $bhaeepari_bida !== null ? $bhaeepari_bida : 10.0
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Leave balance created successfully']);
        exit;
    }
    
    // Build update query
    $update_fields = [];
    $params = [];
    
    if ($gharpari_bida !== null) {
        $update_fields[] = "gharpari_bida_days = ?";
        $params[] = $gharpari_bida;
        error_log("Updating gharpari_bida_days to: " . $gharpari_bida);
    }
    
    if ($parba_bida !== null) {
        $update_fields[] = "parba_bida_days = ?";
        $params[] = $parba_bida;
        error_log("Updating parba_bida_days to: " . $parba_bida);
    }
    
    if ($bhaeepari_bida !== null) {
        $update_fields[] = "bhaeepari_bida_days = ?";
        $params[] = $bhaeepari_bida;
        error_log("Updating bhaeepari_bida_days to: " . $bhaeepari_bida);
    }
    
    if (empty($update_fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    $params[] = $personnel_id;
    
    $sql = "UPDATE leave_balance SET " . implode(", ", $update_fields) . " WHERE personnel_id = ?";
    error_log("SQL: " . $sql);
    error_log("Params: " . print_r($params, true));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $row_count = $stmt->rowCount();
    error_log("Rows affected: " . $row_count);
    
    if ($row_count > 0) {
        echo json_encode(['success' => true, 'message' => 'Leave balance updated successfully']);
    } else {
        // Check if values are the same
        echo json_encode(['success' => true, 'message' => 'Leave balance is already up to date']);
    }
    
} catch (PDOException $e) {
    error_log("Update leave balance error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>