<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is super admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch($action) {
        case 'add_leave_type':
            $leave_name = trim($_POST['leave_name']);
            $available_days = (int)$_POST['available_days'];
            $description = trim($_POST['description']);
            
            if (empty($leave_name)) {
                throw new Exception('Leave name is required');
            }
            
            $stmt = $pdo->prepare("INSERT INTO leave_types (leave_name, available_days, description) VALUES (?, ?, ?)");
            $stmt->execute([$leave_name, $available_days, $description]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'update_leave_days':
            $leave_id = (int)$_POST['leave_id'];
            $available_days = (int)$_POST['available_days'];
            
            if ($available_days < 0) {
                throw new Exception('Available days cannot be negative');
            }
            
            $stmt = $pdo->prepare("UPDATE leave_types SET available_days = ? WHERE id = ?");
            $stmt->execute([$available_days, $leave_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>