<?php
// dismiss_profile_alert.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$_SESSION['profile_alert_dismissed'] = true;
echo json_encode(['success' => true]);
