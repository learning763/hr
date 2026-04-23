<?php
header('Content-Type: application/json');
require_once 'includes/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get POST data
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$rank = trim($_POST['rank'] ?? '');
$unit = trim($_POST['unit'] ?? '');
$personnel_number = trim($_POST['personnel_number'] ?? '');
$joint_date = $_POST['joint_date'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
$errors = [];

if (empty($full_name)) {
    $errors[] = 'Full name is required';
}
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}
if (empty($rank)) {
    $errors[] = 'Rank is required';
}
if (empty($unit)) {
    $errors[] = 'Branch/Unit is required';
}
if (empty($personnel_number)) {
    $errors[] = 'Personnel number is required';
}
if (empty($joint_date)) {
    $errors[] = 'Commission date is required';
}
if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters';
}
if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Email already registered']);
        exit;
    }
    
    // Check if personnel number already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_number = ?");
    $stmt->execute([$personnel_number]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Personnel number already exists']);
        exit;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new personnel
    $stmt = $pdo->prepare("
        INSERT INTO personnel (personnel_number, full_name_en, rank, unit, email, joint_date, password, current_status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())
    ");
    
    $result = $stmt->execute([
        $personnel_number,
        $full_name,
        $rank,
        $unit,
        $email,
        $joint_date,
        $hashed_password
    ]);
    
    if ($result) {
        // Also add to military_personnel_status table
        $stmt2 = $pdo->prepare("
            INSERT INTO military_personnel_status (personnel_name, rank, status, record_date, remarks) 
            VALUES (?, ?, 'present', CURDATE(), 'New registration')
        ");
        $stmt2->execute([$full_name, $rank]);
        
        echo json_encode(['success' => true, 'message' => 'Registration successful! Please login.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Registration failed. Please try again.']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>