<?php
// upload_profile_photo.php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Super Admin can update anyone's photo; everyone else may only update their own
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
$is_super_admin = ((int) $_SESSION['user_role'] === 2);
$target_personnel_id = isset($_POST['personnel_id']) ? trim($_POST['personnel_id']) : '';
if (!$is_super_admin && (string) $target_personnel_id !== (string) $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// Create uploads directory if not exists
$upload_dir = 'uploads/profile_photos/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($action === 'upload_profile_photo') {
    $personnel_id = isset($_POST['personnel_id']) ? trim($_POST['personnel_id']) : '';
    
    if (empty($personnel_id)) {
        echo json_encode(['success' => false, 'message' => 'Personnel ID is required']);
        exit;
    }
    
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'Please select a valid photo';
        if (isset($_FILES['profile_photo']['error'])) {
            switch ($_FILES['profile_photo']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = 'File too large';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = 'No file selected';
                    break;
                default:
                    $error_message = 'Upload error occurred';
            }
        }
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }
    
    $file = $_FILES['profile_photo'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_ext, $allowed_ext)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, and WEBP files are allowed']);
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
        exit;
    }
    
    // Generate unique filename
    $safe_personnel_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $personnel_id);
    $new_filename = $safe_personnel_id . '_photo_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    $db_path = $upload_dir . $new_filename;
    
    // Simple file upload without image processing (to avoid GD issues)
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Delete old photo if exists
        try {
            $stmt = $pdo->prepare("SELECT profile_picture_path FROM personnel WHERE personnel_number = ?");
            $stmt->execute([$personnel_id]);
            $old_photo = $stmt->fetchColumn();
            
            if ($old_photo && file_exists($old_photo) && $old_photo != $db_path) {
                unlink($old_photo);
            }
        } catch (PDOException $e) {
            // Ignore deletion errors
        }
        
        // Update database
        try {
            $stmt = $pdo->prepare("UPDATE personnel SET profile_picture_path = ? WHERE personnel_number = ?");
            $stmt->execute([$db_path, $personnel_id]);
            
            echo json_encode(['success' => true, 'message' => 'Profile photo uploaded successfully!', 'path' => $db_path]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload photo. Please check folder permissions.']);
    }
    
} elseif ($action === 'delete_profile_photo') {
    $personnel_id = isset($_POST['personnel_id']) ? trim($_POST['personnel_id']) : '';
    
    if (empty($personnel_id)) {
        echo json_encode(['success' => false, 'message' => 'Personnel ID is required']);
        exit;
    }
    
    try {
        // Get current photo path
        $stmt = $pdo->prepare("SELECT profile_picture_path FROM personnel WHERE personnel_number = ?");
        $stmt->execute([$personnel_id]);
        $photo_path = $stmt->fetchColumn();
        
        // Delete file if exists
        if ($photo_path && file_exists($photo_path)) {
            unlink($photo_path);
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE personnel SET profile_picture_path = NULL WHERE personnel_number = ?");
        $stmt->execute([$personnel_id]);
        
        echo json_encode(['success' => true, 'message' => 'Profile photo removed successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>