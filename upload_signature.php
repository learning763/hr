<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// Create uploads directory if not exists
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/signatures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($action === 'upload_signature') {
    $personnel_id = isset($_POST['personnel_id']) ? trim($_POST['personnel_id']) : '';
    
    if (empty($personnel_id)) {
        echo json_encode(['success' => false, 'message' => 'Personnel ID is required']);
        exit;
    }
    
    if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid signature image']);
        exit;
    }
    
    $file = $_FILES['signature'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_ext, $allowed_ext)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and GIF files are allowed']);
        exit;
    }
    
    // Max file size 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB']);
        exit;
    }
    
    // Generate unique filename
    $new_filename = $personnel_id . '_signature_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    $db_path = '/uploads/signatures/' . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Delete old signature file if exists
        try {
            $stmt = $pdo->prepare("SELECT signature FROM personnel WHERE personnel_number = ?");
            $stmt->execute([$personnel_id]);
            $old_signature = $stmt->fetchColumn();
            
            if ($old_signature && file_exists($_SERVER['DOCUMENT_ROOT'] . $old_signature)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $old_signature);
            }
        } catch (PDOException $e) {
            // Ignore errors when deleting old file
        }
        
        // Update database
        try {
            $stmt = $pdo->prepare("UPDATE personnel SET signature = ? WHERE personnel_number = ?");
            $stmt->execute([$db_path, $personnel_id]);
            
            // Also update military_personnel_status if exists
            try {
                $stmt2 = $pdo->prepare("UPDATE military_personnel_status SET signature = ? WHERE personnel_number = ?");
                $stmt2->execute([$db_path, $personnel_id]);
            } catch (PDOException $e) {
                // Table might not exist
            }
            
            echo json_encode(['success' => true, 'message' => 'Signature uploaded successfully', 'path' => $db_path]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
    
} elseif ($action === 'delete_signature') {
    $personnel_id = isset($_POST['personnel_id']) ? trim($_POST['personnel_id']) : '';
    
    if (empty($personnel_id)) {
        echo json_encode(['success' => false, 'message' => 'Personnel ID is required']);
        exit;
    }
    
    try {
        // Get current signature path
        $stmt = $pdo->prepare("SELECT signature FROM personnel WHERE personnel_number = ?");
        $stmt->execute([$personnel_id]);
        $signature_path = $stmt->fetchColumn();
        
        // Delete file if exists
        if ($signature_path && file_exists($_SERVER['DOCUMENT_ROOT'] . $signature_path)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $signature_path);
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE personnel SET signature = NULL WHERE personnel_number = ?");
        $stmt->execute([$personnel_id]);
        
        // Also update military_personnel_status if exists
        try {
            $stmt2 = $pdo->prepare("UPDATE military_personnel_status SET signature = NULL WHERE personnel_number = ?");
            $stmt2->execute([$personnel_id]);
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        echo json_encode(['success' => true, 'message' => 'Signature removed successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>