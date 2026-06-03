<?php
session_start();
header('Content-Type: application/json');
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) ? filter_var($_POST['remember'], FILTER_VALIDATE_BOOLEAN) : false;

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}
if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}

try {
    // Get user from personnel table only (no military_personnel_status join)
    $stmt = $pdo->prepare("
        SELECT * FROM personnel WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }
    
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }
    
    // Store role as INTEGER (0=User, 1=Admin, 2=Super Admin)
    $user_role_int = (int)($user['role'] ?? 0);
    
    // Clear any existing session data
    $_SESSION = array();
    
    // Set session variables
    $_SESSION['user_id'] = $user['personnel_number'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name_en'];
    $_SESSION['user_rank'] = $user['rank'];
    $_SESSION['user_unit'] = $user['unit'];
    $_SESSION['user_role'] = $user_role_int;  // INTEGER: 0, 1, or 2
    $_SESSION['user_role_string'] = $user_role_int == 2 ? 'supervisor' : ($user_role_int == 1 ? 'admin' : 'user');
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Remember me logic
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (86400 * 30);
        $stmt = $pdo->prepare("UPDATE personnel SET remember_token = ? WHERE personnel_number = ?");
        $stmt->execute([$token, $user['personnel_number']]);
        setcookie('remember_token', $token, $expiry, '/', '', false, true);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Login successful!',
        'user' => [
            'name' => $user['full_name_en'],
            'email' => $user['email'],
            'rank' => $user['rank'],
            'role' => $user_role_int,
            'role_text' => $user_role_int == 2 ? 'Super Admin' : ($user_role_int == 1 ? 'Admin' : 'User'),
            'personnel_number' => $user['personnel_number']
        ]
    ]);
    
} catch(PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>