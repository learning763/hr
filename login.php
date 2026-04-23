<?php
header('Content-Type: application/json');
require_once 'includes/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get POST data
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) ? filter_var($_POST['remember'], FILTER_VALIDATE_BOOLEAN) : false;

// Validation
if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}
if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

try {
    // Get user by email
    $stmt = $pdo->prepare("SELECT * FROM personnel WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['personnel_number'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name_en'];
    $_SESSION['user_rank'] = $user['rank'];
    $_SESSION['user_unit'] = $user['unit'];
    $_SESSION['logged_in'] = true;
    
    // Set remember me cookie if checked (30 days)
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (86400 * 30); // 30 days
        
        // Store token in database
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
            'unit' => $user['unit']
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>