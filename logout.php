<?php
session_start();
require_once 'includes/config.php';

// Clear remember token from database if exists
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE personnel SET remember_token = NULL WHERE personnel_number = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Clear session
$_SESSION = array();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>