<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

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