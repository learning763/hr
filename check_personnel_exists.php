<?php
// check_personnel_exists.php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_role']) || (int)$_SESSION['user_role'] !== 2) {
    echo json_encode(['exists' => false, 'error' => 'Unauthorized']);
    exit;
}

if (isset($_GET['personnel_no'])) {
    $personnel_no = $_GET['personnel_no'];
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_number = ?");
        $stmt->execute([$personnel_no]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => $count > 0]);
    } catch (PDOException $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['exists' => false, 'error' => 'No personnel number provided']);
}
?>