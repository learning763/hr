<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is super admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $personnel_id = $_POST['personnel_id'] ?? '';
    
    if (empty($personnel_id)) {
        echo json_encode(['success' => false, 'message' => 'Personnel ID is required']);
        exit;
    }
    
    if ($action === 'upload_profile_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
            
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload JPG, PNG, GIF, or WEBP.']);
                exit;
            }
            
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB.']);
                exit;
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profile_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $personnel_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Get existing photo to delete
                $stmt = $pdo->prepare("SELECT profile_picture_path FROM personnel WHERE personnel_number = ?");
                $stmt->execute([$personnel_id]);
                $existing = $stmt->fetch();
                
                if ($existing && !empty($existing['profile_picture_path']) && file_exists($existing['profile_picture_path'])) {
                    unlink($existing['profile_picture_path']);
                }
                
                // Update database
                $update_stmt = $pdo->prepare("UPDATE personnel SET profile_picture_path = ? WHERE personnel_number = ?");
                if ($update_stmt->execute([$filepath, $personnel_id])) {
                    echo json_encode(['success' => true, 'message' => 'Profile photo uploaded successfully', 'path' => $filepath]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database update failed']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        }
    } elseif ($action === 'delete_profile_photo') {
        // Get existing photo to delete
        $stmt = $pdo->prepare("SELECT profile_picture_path FROM personnel WHERE personnel_number = ?");
        $stmt->execute([$personnel_id]);
        $existing = $stmt->fetch();
        
        if ($existing && !empty($existing['profile_picture_path']) && file_exists($existing['profile_picture_path'])) {
            unlink($existing['profile_picture_path']);
        }
        
        // Update database
        $update_stmt = $pdo->prepare("UPDATE personnel SET profile_picture_path = NULL WHERE personnel_number = ?");
        if ($update_stmt->execute([$personnel_id])) {
            echo json_encode(['success' => true, 'message' => 'Profile photo removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove profile photo']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>