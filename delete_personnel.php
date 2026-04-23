<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ['success' => false, 'message' => ''];

try {
    // Check if request method is POST and id is set
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        
        $personnel_id = trim($_POST['id']);
        
        // Validate that personnel_id is not empty
        if (empty($personnel_id)) {
            $response['message'] = 'Personnel ID is required';
            echo json_encode($response);
            exit;
        }
        
        // First, check if the personnel exists
        $check_stmt = $pdo->prepare("SELECT full_name_en FROM personnel WHERE personnel_number = ?");
        $check_stmt->execute([$personnel_id]);
        $personnel = $check_stmt->fetch();
        
        if (!$personnel) {
            $response['message'] = 'Personnel not found';
            echo json_encode($response);
            exit;
        }
        
        // Delete the personnel record
        $delete_stmt = $pdo->prepare("DELETE FROM personnel WHERE personnel_number = ?");
        $result = $delete_stmt->execute([$personnel_id]);
        
        if ($result && $delete_stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = $personnel['full_name_en'] . ' has been deleted successfully';
        } else {
            $response['message'] = 'Failed to delete personnel record';
        }
        
    } else {
        $response['message'] = 'Invalid request method or missing ID';
    }
    
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Delete personnel error: " . $e->getMessage());
}

echo json_encode($response);
?>