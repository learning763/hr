<?php
// update_personnel.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1);

session_start();
require_once 'includes/config.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Check if user is super admin
if (!isset($_SESSION['user_role']) || (int)$_SESSION['user_role'] !== 2) {
    sendJsonResponse(false, 'Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

try {
    // Get form data
    $personnel_number = isset($_POST['personnel_number']) ? trim($_POST['personnel_number']) : '';
    $full_name_en = isset($_POST['full_name_en']) ? trim($_POST['full_name_en']) : '';
    $full_name_ne = isset($_POST['full_name_ne']) ? trim($_POST['full_name_ne']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $rank = isset($_POST['rank']) ? $_POST['rank'] : '';
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $recruitment_date = isset($_POST['recruitment_date']) && !empty($_POST['recruitment_date']) ? $_POST['recruitment_date'] : null;
    $province = isset($_POST['province']) ? $_POST['province'] : '';
    $district = isset($_POST['district']) ? trim($_POST['district']) : '';
    $municipality = isset($_POST['municipality']) ? trim($_POST['municipality']) : '';
    $village_tole = isset($_POST['village_tole']) ? trim($_POST['village_tole']) : '';
    $current_status = isset($_POST['current_status']) ? $_POST['current_status'] : 'Active';
    $role = isset($_POST['role']) ? (int)$_POST['role'] : 0;
    
    // Validate required fields
    if (empty($personnel_number)) {
        sendJsonResponse(false, 'Personnel number is required');
    }
    
    if (empty($full_name_en)) {
        sendJsonResponse(false, 'Full name is required');
    }
    
    if (empty($rank)) {
        sendJsonResponse(false, 'Rank is required');
    }
    
    // Check if personnel exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_number = ?");
    $stmt->execute([$personnel_number]);
    if ($stmt->fetchColumn() == 0) {
        sendJsonResponse(false, 'Personnel not found');
    }
    
    // Check if email already exists for another personnel
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE email = ? AND personnel_number != ?");
        $stmt->execute([$email, $personnel_number]);
        if ($stmt->fetchColumn() > 0) {
            sendJsonResponse(false, 'Email already exists for another personnel');
        }
    }
    
    // Build update query dynamically
    $sql = "UPDATE personnel SET 
                full_name_en = :full_name_en,
                full_name_ne = :full_name_ne,
                email = :email,
                phone = :phone,
                rank = :rank,
                unit = :unit,
                recruitment_date = :recruitment_date,
                province = :province,
                district = :district,
                municipality = :municipality,
                village_tole = :village_tole,
                current_status = :current_status,
                role = :role
            WHERE personnel_number = :personnel_number";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':full_name_en' => $full_name_en,
        ':full_name_ne' => !empty($full_name_ne) ? $full_name_ne : null,
        ':email' => !empty($email) ? $email : null,
        ':phone' => !empty($phone) ? $phone : null,
        ':rank' => $rank,
        ':unit' => !empty($unit) ? $unit : null,
        ':recruitment_date' => $recruitment_date,
        ':province' => !empty($province) ? $province : null,
        ':district' => !empty($district) ? $district : null,
        ':municipality' => !empty($municipality) ? $municipality : null,
        ':village_tole' => !empty($village_tole) ? $village_tole : null,
        ':current_status' => $current_status,
        ':role' => $role,
        ':personnel_number' => $personnel_number
    ]);
    
    if ($result) {
        sendJsonResponse(true, 'Personnel updated successfully!');
    } else {
        sendJsonResponse(false, 'Failed to update personnel');
    }
    
} catch (PDOException $e) {
    error_log("Update personnel error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred');
} catch (Exception $e) {
    error_log("Update personnel error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred');
}
?>