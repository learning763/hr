<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode(['authenticated' => true, 'user' => [
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'rank' => $_SESSION['user_rank'],
        'unit' => $_SESSION['user_unit']
    ]]);
} else {
    // Check remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT * FROM personnel WHERE remember_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['personnel_number'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name_en'];
            $_SESSION['user_rank'] = $user['rank'];
            $_SESSION['user_unit'] = $user['unit'];
            $_SESSION['logged_in'] = true;
            
            echo json_encode(['authenticated' => true, 'user' => [
                'name' => $user['full_name_en'],
                'email' => $user['email'],
                'rank' => $user['rank'],
                'unit' => $user['unit']
            ]]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
    } else {
        echo json_encode(['authenticated' => false]);
    }
}
?>