<?php
// update_leave_balance.php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
if ($user_role < 1) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

$personnel_id = isset($input['personnel_id']) ? trim($input['personnel_id']) : '';
$gharpari_bida = isset($input['gharpari_bida']) ? (float)$input['gharpari_bida'] : 15.0;
$parba_bida = isset($input['parba_bida']) ? (float)$input['parba_bida'] : 12.0;
$bhaeepari_bida = isset($input['bhaeepari_bida']) ? (float)$input['bhaeepari_bida'] : 10.0;

if (empty($personnel_id)) {
    echo json_encode(['success' => false, 'message' => 'Personnel ID is required']);
    exit;
}

try {
    // Check if personnel exists in personnel table
    $checkPersonnel = $pdo->prepare("SELECT personnel_number FROM personnel WHERE personnel_number = ?");
    $checkPersonnel->execute([$personnel_id]);
    if (!$checkPersonnel->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Personnel not found']);
        exit;
    }
    
    // Check if record exists in leave_balance
    $checkStmt = $pdo->prepare("SELECT id FROM leave_balance WHERE personnel_id = ?");
    $checkStmt->execute([$personnel_id]);
    $exists = $checkStmt->fetch();
    
    if ($exists) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE leave_balance SET gharpari_bida_days = ?, parba_bida_days = ?, bhaeepari_bida_days = ?, last_updated = NOW() WHERE personnel_id = ?");
        $stmt->execute([$gharpari_bida, $parba_bida, $bhaeepari_bida, $personnel_id]);
        $message = 'Leave balance updated successfully';
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO leave_balance (personnel_id, gharpari_bida_days, parba_bida_days, bhaeepari_bida_days, last_updated) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$personnel_id, $gharpari_bida, $parba_bida, $bhaeepari_bida]);
        $message = 'Leave balance created successfully';
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    error_log("Update leave balance error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>