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

/**
 * Simple and effective background removal
 */
function removeBackground($source_path, $output_path) {
    // Check if GD is installed
    if (!extension_loaded('gd')) {
        return false;
    }
    
    // Load image based on type
    $image_info = getimagesize($source_path);
    if (!$image_info) return false;
    
    switch ($image_info[2]) {
        case IMAGETYPE_JPEG:
            $img = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $img = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $img = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    if (!$img) return false;
    
    // Get dimensions
    $width = imagesx($img);
    $height = imagesy($img);
    
    // Create new image with transparency
    $new_img = imagecreatetruecolor($width, $height);
    imagealphablending($new_img, false);
    imagesavealpha($new_img, true);
    
    // Fill with transparent background
    $transparent = imagecolorallocatealpha($new_img, 0, 0, 0, 127);
    imagefill($new_img, 0, 0, $transparent);
    
    // Get background color from top-left corner (usually paper color)
    $bg_rgb = imagecolorat($img, 0, 0);
    $bg_r = ($bg_rgb >> 16) & 0xFF;
    $bg_g = ($bg_rgb >> 8) & 0xFF;
    $bg_b = $bg_rgb & 0xFF;
    
    // Process each pixel
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Calculate color difference
            $diff = abs($r - $bg_r) + abs($g - $bg_g) + abs($b - $bg_b);
            
            // If pixel is similar to background, make transparent
            if ($diff < 50) {
                $color = imagecolorallocatealpha($new_img, $r, $g, $b, 127);
            } else {
                // Keep signature pixel
                $color = imagecolorallocatealpha($new_img, $r, $g, $b, 0);
            }
            
            imagesetpixel($new_img, $x, $y, $color);
        }
    }
    
    // Save as PNG
    $result = imagepng($new_img, $output_path, 9);
    
    // Clean up
    imagedestroy($img);
    imagedestroy($new_img);
    
    return $result;
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
    
    // Max file size 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
        exit;
    }
    
    // Generate unique filename
    $new_filename = $personnel_id . '_signature_' . time() . '.png';
    $upload_path = $upload_dir . $new_filename;
    $db_path = '/uploads/signatures/' . $new_filename;
    
    // Process the image
    $success = false;
    
    if (extension_loaded('gd')) {
        // Try to remove background
        $success = removeBackground($file['tmp_name'], $upload_path);
        
        // If background removal failed, just convert to PNG
        if (!$success) {
            $img = imagecreatefromstring(file_get_contents($file['tmp_name']));
            if ($img) {
                imagepng($img, $upload_path, 9);
                imagedestroy($img);
                $success = true;
            }
        }
    } else {
        // GD not available, just move the file
        $success = move_uploaded_file($file['tmp_name'], $upload_path);
    }
    
    if ($success && file_exists($upload_path)) {
        // Delete old signature if exists
        try {
            $stmt = $pdo->prepare("SELECT signature FROM personnel WHERE personnel_number = ?");
            $stmt->execute([$personnel_id]);
            $old_signature = $stmt->fetchColumn();
            
            if ($old_signature && file_exists($_SERVER['DOCUMENT_ROOT'] . $old_signature)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $old_signature);
            }
        } catch (PDOException $e) {
            // Ignore deletion errors
        }
        
        // Update database
        try {
            $stmt = $pdo->prepare("UPDATE personnel SET signature = ? WHERE personnel_number = ?");
            $stmt->execute([$db_path, $personnel_id]);
            
            echo json_encode(['success' => true, 'message' => 'Signature uploaded successfully', 'path' => $db_path]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload signature. Please try again.']);
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
        
        echo json_encode(['success' => true, 'message' => 'Signature removed successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>