<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$personnel_no = $_POST['personnel_no'] ?? '';
$new_password = $_POST['password'] ?? '';

if (empty($personnel_no)) {
    echo json_encode(['success' => false, 'message' => 'Personnel number is required']);
    exit;
}

// Use default password if none provided
if (empty($new_password)) {
    $new_password = 'reset@123';
}

if (strlen($new_password) < 4) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 4 characters']);
    exit;
}

try {
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update the password in personnel table
    $stmt = $pdo->prepare("UPDATE personnel SET password = ? WHERE personnel_number = ?");
    $stmt->execute([$hashed_password, $personnel_no]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully to: ' . $new_password]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Personnel not found']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>