<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
if ($user_role < 1) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

try {
    // Direct query to get all personnel with their leave balances
    $stmt = $pdo->prepare("
        SELECT 
            m.id as personnel_id,
            m.personnel_number,
            m.personnel_name,
            m.rank,
            COALESCE(lb.gharpari_bida_days, 15.0) as gharpari_bida_days,
            COALESCE(lb.parba_bida_days, 12.0) as parba_bida_days,
            COALESCE(lb.bhaeepari_bida_days, 10.0) as bhaeepari_bida_days
        FROM military_personnel_status m
        LEFT JOIN leave_balance lb ON m.id = lb.personnel_id
        GROUP BY m.id
        ORDER BY m.personnel_name ASC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $results]);
    
} catch (PDOException $e) {
    error_log("Get all leave balances error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>