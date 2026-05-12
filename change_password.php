<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Validate inputs
if (empty($current_password)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

if (strlen($new_password) < 4) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 4 characters']);
    exit;
}

try {
    // Get user's current password from database
    $stmt = $pdo->prepare("SELECT password FROM personnel WHERE personnel_number = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $stmt = $pdo->prepare("UPDATE personnel SET password = ?, password_updated_at = NOW() WHERE personnel_number = ?");
    $stmt->execute([$hashed_password, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>