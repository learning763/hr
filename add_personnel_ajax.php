<?php
// add_personnel_ajax.php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is super admin
if (!isset($_SESSION['user_role']) || (int)$_SESSION['user_role'] !== 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $serviceNo = trim($_POST['serviceNo'] ?? '');
        $fullName = trim($_POST['fullName'] ?? '');
        $fullNameNe = trim($_POST['fullNameNe'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $rank = $_POST['rank'] ?? '';
        $branch = trim($_POST['branch'] ?? '');
        $recruitmentDate = $_POST['recruitmentDate'] ?? null;
        $province = $_POST['province'] ?? '';
        $district = trim($_POST['district'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $villageTole = trim($_POST['villageTole'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $role = (int)($_POST['role'] ?? 0);
        
        // Validate required fields
        if (empty($serviceNo)) {
            echo json_encode(['success' => false, 'message' => 'Personnel number is required']);
            exit;
        }
        
        if (empty($fullName)) {
            echo json_encode(['success' => false, 'message' => 'Full name is required']);
            exit;
        }
        
        if (empty($rank)) {
            echo json_encode(['success' => false, 'message' => 'Rank is required']);
            exit;
        }
        
        // Check if personnel number already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_number = ?");
        $stmt->execute([$serviceNo]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Personnel number already exists! Please use a different number.']);
            exit;
        }
        
        // Check if email already exists (if provided)
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists! Please use a different email address.']);
                exit;
            }
        }
        
        // Insert new personnel
        $sql = "INSERT INTO personnel (personnel_number, full_name_en, full_name_ne, email, phone, rank, unit, recruitment_date, province, district, municipality, village_tole, current_status, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $serviceNo, $fullName, $fullNameNe, $email ?: null, $phone ?: null, $rank, $branch ?: null, 
            $recruitmentDate ?: null, $province ?: null, $district ?: null, $municipality ?: null, $villageTole ?: null, 
            $status, $role
        ]);
        
        if ($result) {
            // Also create leave balance record for the new personnel
            try {
                $stmt2 = $pdo->prepare("INSERT INTO leave_balance (personnel_id, gharpari_bida_days, parba_bida_days, bhaeepari_bida_days) 
                                        VALUES (?, 0, 0, 0)");
                $stmt2->execute([$serviceNo]);
            } catch (PDOException $e) {
                // Leave balance table might not exist, that's okay
                error_log("Leave balance insert error: " . $e->getMessage());
            }
            
            echo json_encode(['success' => true, 'message' => 'Personnel added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add personnel. Please try again.']);
        }
        
    } catch (PDOException $e) {
        error_log("Add personnel error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>