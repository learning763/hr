<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Super Admin can look up anyone; everyone else may only fetch their own record
$acting_user_role = isset($_SESSION['user_role']) ? (int) $_SESSION['user_role'] : -1;
$acting_user_id = $_SESSION['user_id'] ?? '';
if ($acting_user_role < 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
if ($acting_user_role !== 2 && isset($_GET['id']) && (string) $_GET['id'] !== (string) $acting_user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_number = ?");
        $stmt->execute([$_GET['id']]);
        $personnel = $stmt->fetch();
        
        if ($personnel) {
            echo json_encode(['success' => true, 'data' => $personnel]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Personnel not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}
?>



