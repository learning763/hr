<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

include('includes/config.php');
include_once('includes/pagination.php');

$pageTitle = "Leave Management";
$pageSubtitle = "Manage military personnel leave requests, approvals, and tracking";
$activePage = "leave";

// Get user role from session
$user_role_int = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
$user_role_string = '';
if ($user_role_int === 2) {
    $user_role_string = 'super_admin';
} elseif ($user_role_int === 1) {
    $user_role_string = 'admin';
} else {
    $user_role_string = 'user';
}

// Get current user's personnel number from session
$current_personnel_number = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';

// Database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper functions for leave balance
function getLeaveBalance($pdo, $personnel_number) {
    if (empty($personnel_number)) {
        return [
            'gharpari_bida_days' => 15,
            'parba_bida_days' => 12,
            'bhaeepari_bida_days' => 10
        ];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM leave_balance WHERE personnel_id = ?");
    $stmt->execute([$personnel_number]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$balance) {
        $stmt = $pdo->prepare("INSERT INTO leave_balance (personnel_id, gharpari_bida_days, parba_bida_days, bhaeepari_bida_days) VALUES (?, 15, 12, 10)");
        $stmt->execute([$personnel_number]);
        
        $stmt = $pdo->prepare("SELECT * FROM leave_balance WHERE personnel_id = ?");
        $stmt->execute([$personnel_number]);
        $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return $balance;
}

function deductLeaveBalance($pdo, $personnel_number, $leave_type, $days_used) {
    $balance = getLeaveBalance($pdo, $personnel_number);
    
    $field_map = [
        'gharpari_bida' => 'gharpari_bida_days',
        'parba_bida' => 'parba_bida_days',
        'bhaeepari_bida' => 'bhaeepari_bida_days'
    ];
    
    if (!isset($field_map[$leave_type])) return false;
    
    $field = $field_map[$leave_type];
    $current_days = floatval($balance[$field]);
    
    if ($current_days < $days_used) return false;
    
    $new_days = $current_days - $days_used;
    $stmt = $pdo->prepare("UPDATE leave_balance SET $field = ?, last_updated = NOW() WHERE personnel_id = ?");
    return $stmt->execute([$new_days, $personnel_number]);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Security token validation failed']);
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        
        // Get all leave requests
        if ($action === 'get_all') {
            $filter = $_POST['filter'] ?? 'all';
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
            $offset = ($page - 1) * $per_page;
            
            // First, check what columns exist in personnel table
            $stmt = $pdo->query("SHOW COLUMNS FROM personnel");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Determine the correct name column
            $nameColumn = in_array('full_name_en', $columns) ? 'full_name_en' : (in_array('full_name', $columns) ? 'full_name' : 'personnel_number');
            
            // Using personnel table with leave_requests where personnel_id stores personnel_number
            $sql = "SELECT lr.*, 
                           p.{$nameColumn} as personnel_name, 
                           p.rank,
                           p.personnel_number,
                           lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days,
                           io_p.{$nameColumn} as initiating_officer_name,
                           io_p.rank as initiating_officer_rank,
                           ao_p.{$nameColumn} as accepting_officer_name,
                           ao_p.rank as accepting_officer_rank,
                           vo_p.{$nameColumn} as verifying_officer_name,
                           vo_p.rank as verifying_officer_rank,
                           receiver_p.{$nameColumn} as receiver_name,
                           receiver_p.rank as receiver_rank
                    FROM leave_requests lr
                    INNER JOIN personnel p ON lr.personnel_id = p.personnel_number
                    LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
                    LEFT JOIN personnel io_p ON lr.initiating_officer = io_p.personnel_number
                    LEFT JOIN personnel ao_p ON lr.accepting_officer = ao_p.personnel_number
                    LEFT JOIN personnel vo_p ON lr.verifying_officer = vo_p.personnel_number
                    LEFT JOIN personnel receiver_p ON lr.receiver_id = receiver_p.personnel_number
                    WHERE 1=1";
            
            $count_sql = "SELECT COUNT(*) as total FROM leave_requests lr WHERE 1=1";
            $params = [];
            
            if ($filter !== 'all') {
                $sql .= " AND lr.status = ?";
                $count_sql .= " AND lr.status = ?";
                $params[] = $filter;
            }
            
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = ceil($total_records / $per_page);
            
            $sql .= " ORDER BY lr.created_at DESC LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            $param_index = 1;
            foreach ($params as $param) {
                $stmt->bindValue($param_index++, $param);
            }
            $stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
            $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'data' => $leaves,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_records' => $total_records,
                    'per_page' => $per_page
                ]
            ]);
            exit;
        }
        
        // Get pending requests for receiving officer (verifying_officer)
        if ($action === 'get_verifying_pending') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            
            // Get the correct name column
            $stmt = $pdo->query("SHOW COLUMNS FROM personnel");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $nameColumn = in_array('full_name_en', $columns) ? 'full_name_en' : (in_array('full_name', $columns) ? 'full_name' : 'personnel_number');
            
            $sql = "SELECT lr.*, 
                           p.{$nameColumn} as personnel_name, 
                           p.rank,
                           p.personnel_number,
                           lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days,
                           io_p.{$nameColumn} as initiating_officer_name,
                           ao_p.{$nameColumn} as accepting_officer_name,
                           vo_p.{$nameColumn} as verifying_officer_name
                    FROM leave_requests lr
                    INNER JOIN personnel p ON lr.personnel_id = p.personnel_number
                    LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
                    LEFT JOIN personnel io_p ON lr.initiating_officer = io_p.personnel_number
                    LEFT JOIN personnel ao_p ON lr.accepting_officer = ao_p.personnel_number
                    LEFT JOIN personnel vo_p ON lr.verifying_officer = vo_p.personnel_number
                    WHERE lr.verifying_officer = ? 
                    AND (lr.verifying_officer_approved = 0 OR lr.verifying_officer_approved IS NULL)
                    AND lr.status = 'pending'
                    ORDER BY lr.created_at ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_personnel_number]);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $leaves,
                'officer_type' => 'verifying'
            ]);
            exit;
        }
        
        // Get pending requests for initiating officer
        if ($action === 'get_initiating_pending') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            
            $stmt = $pdo->query("SHOW COLUMNS FROM personnel");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $nameColumn = in_array('full_name_en', $columns) ? 'full_name_en' : (in_array('full_name', $columns) ? 'full_name' : 'personnel_number');
            
            $sql = "SELECT lr.*, 
                           p.{$nameColumn} as personnel_name, 
                           p.rank,
                           p.personnel_number,
                           lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days,
                           io_p.{$nameColumn} as initiating_officer_name,
                           ao_p.{$nameColumn} as accepting_officer_name,
                           vo_p.{$nameColumn} as verifying_officer_name
                    FROM leave_requests lr
                    INNER JOIN personnel p ON lr.personnel_id = p.personnel_number
                    LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
                    LEFT JOIN personnel io_p ON lr.initiating_officer = io_p.personnel_number
                    LEFT JOIN personnel ao_p ON lr.accepting_officer = ao_p.personnel_number
                    LEFT JOIN personnel vo_p ON lr.verifying_officer = vo_p.personnel_number
                    WHERE lr.initiating_officer = ? 
                    AND lr.verifying_officer_approved = 1
                    AND (lr.initiating_officer_approved = 0 OR lr.initiating_officer_approved IS NULL)
                    AND lr.status = 'verified'
                    ORDER BY lr.verifying_officer_approved_at ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_personnel_number]);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $leaves,
                'officer_type' => 'initiating'
            ]);
            exit;
        }
        
        // Get pending requests for accepting officer
        if ($action === 'get_accepting_pending') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            
            $stmt = $pdo->query("SHOW COLUMNS FROM personnel");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $nameColumn = in_array('full_name_en', $columns) ? 'full_name_en' : (in_array('full_name', $columns) ? 'full_name' : 'personnel_number');
            
            $sql = "SELECT lr.*, 
                           p.{$nameColumn} as personnel_name, 
                           p.rank,
                           p.personnel_number,
                           lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days,
                           io_p.{$nameColumn} as initiating_officer_name,
                           ao_p.{$nameColumn} as accepting_officer_name,
                           vo_p.{$nameColumn} as verifying_officer_name
                    FROM leave_requests lr
                    INNER JOIN personnel p ON lr.personnel_id = p.personnel_number
                    LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
                    LEFT JOIN personnel io_p ON lr.initiating_officer = io_p.personnel_number
                    LEFT JOIN personnel ao_p ON lr.accepting_officer = ao_p.personnel_number
                    LEFT JOIN personnel vo_p ON lr.verifying_officer = vo_p.personnel_number
                    WHERE lr.accepting_officer = ? 
                    AND lr.verifying_officer_approved = 1
                    AND lr.initiating_officer_approved = 1
                    AND (lr.accepting_officer_approved = 0 OR lr.accepting_officer_approved IS NULL)
                    AND lr.status = 'initiating_approved'
                    ORDER BY lr.initiating_officer_approved_at ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_personnel_number]);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $leaves,
                'officer_type' => 'accepting'
            ]);
            exit;
        }
        
        // Submit leave request
        if ($action === 'submit_leave') {
            $personnel_number = trim($_POST['personnel_id'] ?? '');
            $leave_type = trim($_POST['leave_type'] ?? '');
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $receiving_officer = trim($_POST['receiving_officer'] ?? '');
            $initiating_officer = trim($_POST['initiating_officer'] ?? '');
            $accepting_officer = trim($_POST['accepting_officer'] ?? '');
            
            if (empty($personnel_number) || empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
                echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
                exit;
            }
            
            // Verify personnel exists
            $stmt = $pdo->prepare("SELECT personnel_number FROM personnel WHERE personnel_number = ?");
            $stmt->execute([$personnel_number]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Personnel not found']);
                exit;
            }
            
            if (empty($receiving_officer) || empty($initiating_officer) || empty($accepting_officer)) {
                echo json_encode(['success' => false, 'error' => 'Please select receiving, initiating and accepting officers']);
                exit;
            }
            
            if ($start_date > $end_date) {
                echo json_encode(['success' => false, 'error' => 'End date must be after start date']);
                exit;
            }
            
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $leave_days = $interval->days + 1;
            
            $balance = getLeaveBalance($pdo, $personnel_number);
            $balance_field = '';
            if ($leave_type === 'gharpari_bida') $balance_field = 'gharpari_bida_days';
            elseif ($leave_type === 'parba_bida') $balance_field = 'parba_bida_days';
            elseif ($leave_type === 'bhaeepari_bida') $balance_field = 'bhaeepari_bida_days';
            
            $available_days = floatval($balance[$balance_field]);
            
            if ($available_days < $leave_days) {
                echo json_encode(['success' => false, 'error' => "Insufficient balance! Available: $available_days days, Requested: $leave_days days"]);
                exit;
            }
            
            // Check for overlapping leave requests
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM leave_requests 
                WHERE personnel_id = ? 
                AND status IN ('pending', 'verified', 'initiating_approved', 'approved')
                AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
            ");
            $stmt->execute([$personnel_number, $end_date, $start_date, $end_date, $start_date]);
            $overlap = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($overlap['count'] > 0) {
                echo json_encode(['success' => false, 'error' => 'You already have a leave request for this period']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests 
                (personnel_id, leave_type, start_date, end_date, leave_days, reason, status, created_by, 
                 verifying_officer, initiating_officer, accepting_officer, 
                 verifying_officer_approved, initiating_officer_approved, accepting_officer_approved) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, 0, 0, 0)
            ");
            
            $result = $stmt->execute([
                $personnel_number, 
                $leave_type, 
                $start_date, 
                $end_date, 
                $leave_days, 
                $reason, 
                $current_personnel_number,
                $receiving_officer,
                $initiating_officer,
                $accepting_officer
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully! It will be sent to the receiving officer.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to submit leave request']);
            }
            exit;
        }
        
        // Receiving Officer approves
        if ($action === 'verifying_officer_approve') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            $stmt = $pdo->prepare("
                SELECT verifying_officer, verifying_officer_approved, status, initiating_officer, accepting_officer
                FROM leave_requests WHERE id = ?
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['verifying_officer'] != $current_personnel_number) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to approve this request']);
                exit;
            }
            
            if ($leave['verifying_officer_approved'] == 1) {
                echo json_encode(['success' => false, 'error' => 'Request already approved by receiving officer']);
                exit;
            }
            
            if ($leave['status'] != 'pending') {
                echo json_encode(['success' => false, 'error' => 'Request cannot be approved at this stage']);
                exit;
            }
            
            // Check if receiving officer is also the initiating officer
            $isSameAsInitiating = ($current_personnel_number == $leave['initiating_officer']);
            
            if ($isSameAsInitiating) {
                $stmt = $pdo->prepare("
                    UPDATE leave_requests 
                    SET verifying_officer_approved = 1,
                        verifying_officer_approved_at = NOW(),
                        verifying_officer_remarks = ?,
                        receiver_id = ?,
                        initiating_officer_approved = 1,
                        initiating_officer_approved_at = NOW(),
                        initiating_officer_remarks = CONCAT('Auto-approved by receiving officer (same as initiating): ', ?),
                        status = 'initiating_approved'
                    WHERE id = ?
                ");
                $result = $stmt->execute([$remarks, $current_personnel_number, $remarks, $id]);
                $message = 'Request received and automatically forwarded to accepting officer';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE leave_requests 
                    SET verifying_officer_approved = 1,
                        verifying_officer_approved_at = NOW(),
                        verifying_officer_remarks = ?,
                        receiver_id = ?,
                        status = 'verified'
                    WHERE id = ?
                ");
                $result = $stmt->execute([$remarks, $current_personnel_number, $id]);
                $message = 'Request received and forwarded to initiating officer';
            }
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve request']);
            }
            exit;
        }
        
        // Receiving Officer rejects
        if ($action === 'verifying_officer_reject') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (empty($remarks)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT verifying_officer, status FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['verifying_officer'] != $current_personnel_number) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to reject this request']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'rejected',
                    approver_remarks = ?,
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$remarks, $current_personnel_number, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Initiating Officer approves
        if ($action === 'initiating_officer_approve') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            $stmt = $pdo->prepare("
                SELECT initiating_officer, initiating_officer_approved, status, verifying_officer_approved, accepting_officer
                FROM leave_requests WHERE id = ?
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['initiating_officer'] != $current_personnel_number) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to approve this request']);
                exit;
            }
            
            if ($leave['verifying_officer_approved'] != 1) {
                echo json_encode(['success' => false, 'error' => 'Request must be received by receiving officer first']);
                exit;
            }
            
            if ($leave['initiating_officer_approved'] == 1) {
                echo json_encode(['success' => false, 'error' => 'Request already approved by initiating officer']);
                exit;
            }
            
            if ($leave['status'] != 'verified') {
                echo json_encode(['success' => false, 'error' => 'Request cannot be approved at this stage']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET initiating_officer_approved = 1,
                    initiating_officer_approved_at = NOW(),
                    initiating_officer_remarks = ?,
                    status = 'initiating_approved'
                WHERE id = ?
            ");
            $result = $stmt->execute([$remarks, $id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Request approved and forwarded to accepting officer']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve request']);
            }
            exit;
        }
        
        // Initiating Officer rejects
        if ($action === 'initiating_officer_reject') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (empty($remarks)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT initiating_officer, status FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['initiating_officer'] != $current_personnel_number) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to reject this request']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'rejected',
                    approver_remarks = ?,
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$remarks, $current_personnel_number, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Accepting Officer approves - FINAL APPROVAL
        if ($action === 'accepting_officer_approve') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            $stmt = $pdo->prepare("
                SELECT personnel_id, leave_type, leave_days, accepting_officer, 
                       accepting_officer_approved, initiating_officer_approved, verifying_officer_approved, status 
                FROM leave_requests WHERE id = ?
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['accepting_officer'] != $current_personnel_number) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to approve this request']);
                exit;
            }
            
            if ($leave['verifying_officer_approved'] != 1) {
                echo json_encode(['success' => false, 'error' => 'Request must be received by receiving officer first']);
                exit;
            }
            
            if ($leave['initiating_officer_approved'] != 1) {
                echo json_encode(['success' => false, 'error' => 'Request must be approved by initiating officer first']);
                exit;
            }
            
            if ($leave['accepting_officer_approved'] == 1) {
                echo json_encode(['success' => false, 'error' => 'Request already approved by accepting officer']);
                exit;
            }
            
            if ($leave['status'] != 'initiating_approved') {
                echo json_encode(['success' => false, 'error' => 'Request is not in the correct state for approval']);
                exit;
            }
            
            // Check balance and deduct
            $balance = getLeaveBalance($pdo, $leave['personnel_id']);
            $balance_field = '';
            if ($leave['leave_type'] === 'gharpari_bida') $balance_field = 'gharpari_bida_days';
            elseif ($leave['leave_type'] === 'parba_bida') $balance_field = 'parba_bida_days';
            elseif ($leave['leave_type'] === 'bhaeepari_bida') $balance_field = 'bhaeepari_bida_days';
            
            $available_days = floatval($balance[$balance_field]);
            
            if ($available_days < $leave['leave_days']) {
                echo json_encode(['success' => false, 'error' => "Cannot approve: Insufficient balance! Available: $available_days days"]);
                exit;
            }
            
            $deducted = deductLeaveBalance($pdo, $leave['personnel_id'], $leave['leave_type'], $leave['leave_days']);
            if (!$deducted) {
                echo json_encode(['success' => false, 'error' => 'Failed to update leave balance']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET accepting_officer_approved = 1,
                    accepting_officer_approved_at = NOW(),
                    accepting_officer_remarks = ?,
                    status = 'approved',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$remarks, $current_personnel_number, $id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Leave request FINALLY APPROVED! Leave has been granted.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve request']);
            }
            exit;
        }
        
        // Accepting Officer rejects
        if ($action === 'accepting_officer_reject') {
            if (empty($current_personnel_number)) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (empty($remarks)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT accepting_officer, status FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['accepting_officer'] != $current_personnel_number) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to reject this request']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'rejected',
                    approver_remarks = ?,
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$remarks, $current_personnel_number, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Get statistics
        if ($action === 'get_stats') {
            $stats = [];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE verifying_officer = ? AND (verifying_officer_approved = 0 OR verifying_officer_approved IS NULL) AND status = 'pending'");
            $stmt->execute([$current_personnel_number]);
            $stats['verifying_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE initiating_officer = ? AND verifying_officer_approved = 1 AND (initiating_officer_approved = 0 OR initiating_officer_approved IS NULL) AND status = 'verified'");
            $stmt->execute([$current_personnel_number]);
            $stats['initiating_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE accepting_officer = ? AND initiating_officer_approved = 1 AND (accepting_officer_approved = 0 OR accepting_officer_approved IS NULL) AND status = 'initiating_approved'");
            $stmt->execute([$current_personnel_number]);
            $stats['accepting_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved'");
            $stmt->execute();
            $stats['total_approved'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            exit;
        }
        
        // Get my balance
        if ($action === 'get_my_balance') {
            $balance = getLeaveBalance($pdo, $current_personnel_number);
            echo json_encode(['success' => true, 'data' => $balance]);
            exit;
        }
        
        // Get leave balance for a personnel
        if ($action === 'get_leave_balance') {
            $personnel_number = $_POST['personnel_id'] ?? '';
            if (empty($personnel_number)) {
                echo json_encode(['success' => false, 'error' => 'Invalid personnel ID']);
                exit;
            }
            $balance = getLeaveBalance($pdo, $personnel_number);
            echo json_encode(['success' => true, 'data' => $balance]);
            exit;
        }
        
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
        
    } catch (Exception $e) {
        error_log("Leave.php AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Get current user's leave balance
$myBalance = getLeaveBalance($pdo, $current_personnel_number);

// Get statistics for initial display
$verifyingPending = 0;
$initiatingPending = 0;
$acceptingPending = 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE verifying_officer = ? AND (verifying_officer_approved = 0 OR verifying_officer_approved IS NULL) AND status = 'pending'");
$stmt->execute([$current_personnel_number]);
$verifyingPending = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE initiating_officer = ? AND verifying_officer_approved = 1 AND (initiating_officer_approved = 0 OR initiating_officer_approved IS NULL) AND status = 'verified'");
$stmt->execute([$current_personnel_number]);
$initiatingPending = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE accepting_officer = ? AND initiating_officer_approved = 1 AND (accepting_officer_approved = 0 OR accepting_officer_approved IS NULL) AND status = 'initiating_approved'");
$stmt->execute([$current_personnel_number]);
$acceptingPending = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management System</title>
    <link href="/css/fontawesome.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-verified { background-color: #cce5ff; color: #004085; }
        .status-initiated { background-color: #d4edda; color: #155724; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .btn-process { padding: 4px 12px; font-size: 12px; margin: 2px; border-radius: 4px; cursor: pointer; border: none; }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card.verifying { border-left: 4px solid #ffc107; }
        .stat-card.initiating { border-left: 4px solid #17a2b8; }
        .stat-card.accepting { border-left: 4px solid #28a745; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .officer-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .officer-card { background: white; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .officer-card-header { padding: 15px; font-weight: bold; }
        .officer-card-header.verifying { background: #fff3cd; color: #856404; }
        .officer-card-header.initiating { background: #cce5ff; color: #004085; }
        .officer-card-header.accepting { background: #d4edda; color: #155724; }
        .officer-card-body { padding: 15px; max-height: 400px; overflow-y: auto; }
        .pending-item { border-bottom: 1px solid #eee; padding: 10px; margin-bottom: 10px; }
        .pending-item:last-child { border-bottom: none; }
        .badge-new { background: #dc3545; color: white; border-radius: 20px; padding: 2px 8px; font-size: 11px; margin-left: 10px; }
        .balance-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .balance-card { background: white; border-radius: 10px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .balance-card .days { font-size: 24px; font-weight: bold; color: #007bff; }
        .filter-section { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 5px; }
        .filter-btn { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 20px; cursor: pointer; transition: all 0.2s; }
        .filter-btn:hover { background: #f0f0f0; }
        .filter-btn.active { background: #007bff; color: white; border-color: #007bff; }
        .btn-add { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; font-size: 14px; margin-bottom: 20px; }
        .data-table { background: white; border-radius: 10px; overflow-x: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btn { padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; }
        .btn-approve-action { background: #28a745; color: white; }
        .btn-reject-action { background: #dc3545; color: white; }
        .btn-view-action { background: #17a2b8; color: white; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; width: 90%; max-width: 700px; border-radius: 10px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; }
        .modal-body { padding: 20px; }
        .input-field { margin-bottom: 15px; }
        .input-field label { display: block; margin-bottom: 5px; font-weight: 600; }
        .input-field input, .input-field select, .input-field textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
        .required-star { color: red; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; position: sticky; bottom: 0; background: white; padding-top: 10px; }
        .btn-cancel { background: #6c757d; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; }
        .btn-submit { background: #007bff; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; }
        .toast { position: fixed; bottom: 20px; right: 20px; background: #333; color: white; padding: 12px 20px; border-radius: 5px; display: none; z-index: 1100; }
        .info-box { background: #e7f3ff; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #007bff; }
        .close, .close-action { cursor: pointer; font-size: 24px; line-height: 1; }
        .approval-steps { margin-bottom: 25px; }
        .step-card { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; border-left: 4px solid; }
        .step-card.step-1 { border-left-color: #ffc107; }
        .step-card.step-2 { border-left-color: #17a2b8; }
        .step-card.step-3 { border-left-color: #28a745; }
        .step-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-weight: bold; font-size: 14px; color: white; }
        .step-1 .step-number { background: #ffc107; }
        .step-2 .step-number { background: #17a2b8; }
        .step-3 .step-number { background: #28a745; }
        .step-title { font-weight: 600; font-size: 15px; }
        .step-1 .step-title { color: #856404; }
        .step-2 .step-title { color: #004085; }
        .step-3 .step-title { color: #155724; }
        .step-desc { font-size: 12px; color: #6c757d; margin-bottom: 12px; padding-left: 38px; }
        .step-card select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; background: white; }
        .officer-hint { font-size: 11px; color: #6c757d; margin-top: 5px; padding-left: 38px; }
        .date-group { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .readonly-field { background: #e9ecef; cursor: not-allowed; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; flex-wrap: wrap; }
        .page-btn { padding: 5px 10px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px; }
        .page-btn.active { background: #007bff; color: white; border-color: #007bff; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; }
        .empty-state { text-align: center; padding: 30px; color: #6c757d; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        
        @media (max-width: 768px) {
            .stats-grid, .officer-actions, .balance-cards { grid-template-columns: 1fr; }
            .date-group { grid-template-columns: 1fr; }
            .header-section { flex-direction: column; align-items: stretch; }
            .btn-add { width: 100%; }
            .filter-section { overflow-x: auto; flex-wrap: nowrap; }
            .filter-btn { white-space: nowrap; }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Leave Management System</h2>
    
    <div class="stats-grid">
        <div class="stat-card verifying" onclick="filterByStatus('pending')">
            <div class="stat-value" id="verifyingPending"><?php echo $verifyingPending; ?></div>
            <div>प्राप्त गर्ने (Receiving Officer)</div>
        </div>
        <div class="stat-card initiating" onclick="filterByStatus('verified')">
            <div class="stat-value" id="initiatingPending"><?php echo $initiatingPending; ?></div>
            <div>Initiating Officer</div>
        </div>
        <div class="stat-card accepting" onclick="filterByStatus('initiating_approved')">
            <div class="stat-value" id="acceptingPending"><?php echo $acceptingPending; ?></div>
            <div>Accepting Officer (Final)</div>
        </div>
    </div>

    <div class="officer-actions">
        <div class="officer-card">
            <div class="officer-card-header verifying">
                📋 प्राप्त गर्ने (Receiving Officer)
                <span class="badge-new" id="verifyingBadge"><?php echo $verifyingPending; ?></span>
            </div>
            <div class="officer-card-body" id="verifyingPendingList">
                <div class="loading-spinner"></div> Loading...
            </div>
        </div>
        
        <div class="officer-card">
            <div class="officer-card-header initiating">
                📌 Initiating Officer
                <span class="badge-new" id="initiatingBadge"><?php echo $initiatingPending; ?></span>
            </div>
            <div class="officer-card-body" id="initiatingPendingList">
                <div class="loading-spinner"></div> Loading...
            </div>
        </div>
        
        <div class="officer-card">
            <div class="officer-card-header accepting">
                ✅ Accepting Officer (Final)
                <span class="badge-new" id="acceptingBadge"><?php echo $acceptingPending; ?></span>
            </div>
            <div class="officer-card-body" id="acceptingPendingList">
                <div class="loading-spinner"></div> Loading...
            </div>
        </div>
    </div>

    <div class="balance-cards">
        <div class="balance-card">
            <h4>🏠 Gharpari Bida</h4>
            <div class="days" id="gharpariBalance"><?php echo $myBalance['gharpari_bida_days']; ?></div>
            <small>Days remaining</small>
        </div>
        <div class="balance-card">
            <h4>🎉 Parba Bida</h4>
            <div class="days" id="parbaBalance"><?php echo $myBalance['parba_bida_days']; ?></div>
            <small>Days remaining</small>
        </div>
        <div class="balance-card">
            <h4>🤝 Bhaeepari Bida</h4>
            <div class="days" id="bhaeepariBalance"><?php echo $myBalance['bhaeepari_bida_days']; ?></div>
            <small>Days remaining</small>
        </div>
    </div>

    <div class="header-section">
        <div class="filter-section">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="pending">Pending (Receiving)</button>
            <button class="filter-btn" data-filter="verified">Verified (Initiating)</button>
            <button class="filter-btn" data-filter="initiating_approved">Awaiting Final</button>
            <button class="filter-btn" data-filter="approved">Approved</button>
            <button class="filter-btn" data-filter="rejected">Rejected</button>
        </div>
        <button class="btn-add" id="newLeaveBtn">+ New Leave Request</button>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Personnel</th>
                    <th>Rank</th>
                    <th>Personnel No</th>
                    <th>Leave Type</th>
                    <th>Period</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Receiver</th>
                    <th>Receiving Officer</th>
                    <th>Initiating Officer</th>
                    <th>Accepting Officer</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="leaveTableBody">
                <tr><td colspan="14" style="text-align:center">Loading...<\/td></tr>
            </tbody>
        </table>
    </div>

    <div id="paginationContainer" class="pagination-container"></div>
</div>

<!-- New Leave Request Modal -->
<div id="leaveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> New Leave Request</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="leaveForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div class="input-field">
                        <label><i class="fas fa-user"></i> Personnel <span class="required-star">*</span></label>
                        <select id="personnelId" name="personnel_id" required>
                            <option value="">Select Personnel</option>
                            <?php
                            // Get the correct name column
                            $stmt = $pdo->query("SHOW COLUMNS FROM personnel");
                            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            $nameColumn = in_array('full_name_en', $columns) ? 'full_name_en' : (in_array('full_name', $columns) ? 'full_name' : 'personnel_number');
                            
                            $stmt = $pdo->query("SELECT personnel_number, {$nameColumn} as full_name, rank FROM personnel ORDER BY {$nameColumn}");
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $displayName = !empty($row['full_name']) ? $row['full_name'] : $row['personnel_number'];
                                echo "<option value='" . htmlspecialchars($row['personnel_number']) . "'>" 
                                     . htmlspecialchars($row['rank'] . ' ' . $displayName . ' (' . $row['personnel_number'] . ')') 
                                     . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-tag"></i> Leave Type <span class="required-star">*</span></label>
                        <select id="leaveType" required>
                            <option value="">Select Type</option>
                            <option value="gharpari_bida">🏠 Gharpari Bida (Home Leave)</option>
                            <option value="parba_bida">🎉 Parba Bida (Festival Leave)</option>
                            <option value="bhaeepari_bida">🤝 Bhaeepari Bida (Family/Friends Leave)</option>
                        </select>
                    </div>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> <strong>Three-Level Approval Workflow:</strong><br>
                    1. Receiving Officer receives and verifies the request<br>
                    2. Initiating Officer approves after verification<br>
                    3. Accepting Officer gives final approval
                </div>
                
                <div class="approval-steps">
                    <div class="step-card step-1">
                        <div class="step-header">
                            <span class="step-number">1</span>
                            <span class="step-title">📬 Receiving Officer (प्राप्त गर्ने)</span>
                        </div>
                        <div class="step-desc">This person will receive and verify the request first</div>
                        <select id="receivingOfficer" required>
                            <option value="">Select Personnel</option>
                            <?php
                            $stmt = $pdo->query("SELECT personnel_number, {$nameColumn} as full_name, rank FROM personnel ORDER BY {$nameColumn}");
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $displayName = !empty($row['full_name']) ? $row['full_name'] : $row['personnel_number'];
                                echo "<option value='" . htmlspecialchars($row['personnel_number']) . "'>" 
                                     . htmlspecialchars($row['rank'] . ' ' . $displayName . ' (' . $row['personnel_number'] . ')') 
                                     . "</option>";
                            }
                            ?>
                        </select>
                        <div class="officer-hint"><i class="fas fa-arrow-right"></i> Step 1: Initial verification</div>
                    </div>
                    
                    <div class="step-card step-2">
                        <div class="step-header">
                            <span class="step-number">2</span>
                            <span class="step-title">✍️ Initiating Officer</span>
                        </div>
                        <div class="step-desc">First level approval after receiving officer verification</div>
                        <select id="initiatingOfficer" required>
                            <option value="">Select Personnel</option>
                            <?php
                            $stmt = $pdo->query("SELECT personnel_number, {$nameColumn} as full_name, rank FROM personnel ORDER BY {$nameColumn}");
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $displayName = !empty($row['full_name']) ? $row['full_name'] : $row['personnel_number'];
                                echo "<option value='" . htmlspecialchars($row['personnel_number']) . "'>" 
                                     . htmlspecialchars($row['rank'] . ' ' . $displayName . ' (' . $row['personnel_number'] . ')') 
                                     . "</option>";
                            }
                            ?>
                        </select>
                        <div class="officer-hint"><i class="fas fa-arrow-right"></i> Step 2: First level approval</div>
                    </div>
                    
                    <div class="step-card step-3">
                        <div class="step-header">
                            <span class="step-number">3</span>
                            <span class="step-title">✅ Accepting Officer</span>
                        </div>
                        <div class="step-desc">Final approval authority - grants the leave</div>
                        <select id="acceptingOfficer" required>
                            <option value="">Select Personnel</option>
                            <?php
                            $stmt = $pdo->query("SELECT personnel_number, {$nameColumn} as full_name, rank FROM personnel ORDER BY {$nameColumn}");
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $displayName = !empty($row['full_name']) ? $row['full_name'] : $row['personnel_number'];
                                echo "<option value='" . htmlspecialchars($row['personnel_number']) . "'>" 
                                     . htmlspecialchars($row['rank'] . ' ' . $displayName . ' (' . $row['personnel_number'] . ')') 
                                     . "</option>";
                            }
                            ?>
                        </select>
                        <div class="officer-hint"><i class="fas fa-arrow-right"></i> Step 3: Final approval</div>
                    </div>
                </div>
                
                <div class="date-group">
                    <div class="input-field">
                        <label><i class="fas fa-calendar-alt"></i> Start Date <span class="required-star">*</span></label>
                        <input type="date" id="startDate" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-calendar-alt"></i> End Date <span class="required-star">*</span></label>
                        <input type="date" id="endDate" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="input-field">
                        <label><i class="fas fa-clock"></i> Total Days</label>
                        <input type="text" id="totalDays" class="readonly-field" readonly placeholder="Will be calculated automatically">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-chart-line"></i> Available Balance</label>
                        <input type="text" id="availableBalance" class="readonly-field" readonly placeholder="Select leave type first">
                    </div>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-comment"></i> Reason for Leave <span class="required-star">*</span></label>
                    <textarea id="reason" rows="3" required placeholder="Please provide detailed reason for leave request..."></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelBtn"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div id="actionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="actionModalTitle">Process Request</h3>
            <span class="close-action">&times;</span>
        </div>
        <div class="modal-body">
            <form id="actionForm">
                <input type="hidden" id="actionLeaveId">
                <input type="hidden" id="actionType">
                <input type="hidden" id="actionOfficerType">
                
                <div class="input-field">
                    <label id="actionLabel">Remarks <span class="required-star">*</span></label>
                    <textarea id="actionRemarks" rows="3" placeholder="Enter your remarks here..."></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelActionBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
    let currentFilter = 'all';
    let currentPage = 1;
    let currentPerPage = 10;
    let currentUserPersonnel = '<?php echo htmlspecialchars($current_personnel_number); ?>';

    async function loadVerifyingPending() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_verifying_pending');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            const container = document.getElementById('verifyingPendingList');
            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(leave => {
                    const isSame = leave.verifying_officer == leave.initiating_officer;
                    html += `
                        <div class="pending-item">
                            <strong>📋 From: ${escapeHtml(leave.personnel_name)}</strong> (${escapeHtml(leave.rank)})<br>
                            <small>${leave.leave_type} | ${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()} | ${leave.leave_days} days</small><br>
                            <small>Will forward to: ${escapeHtml(leave.initiating_officer_name || leave.initiating_officer)}</small><br>
                            ${isSame ? '<small style="color:orange">⚠️ Same as Initiating Officer - Will auto-forward to Accepting Officer</small><br>' : ''}
                            <button class="btn-process btn-approve" onclick="openApprovalModal(${leave.id}, 'verifying', 'approve')">✅ Receive & Forward</button>
                            <button class="btn-process btn-reject" onclick="openApprovalModal(${leave.id}, 'verifying', 'reject')">❌ Reject</button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state">📭 No pending requests for receiving officer</div>';
            }
        } catch (error) {
            console.error(error);
            document.getElementById('verifyingPendingList').innerHTML = '<div class="empty-state">Error loading</div>';
        }
    }

    async function loadInitiatingPending() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_initiating_pending');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            const container = document.getElementById('initiatingPendingList');
            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(leave => {
                    html += `
                        <div class="pending-item">
                            <strong>📌 From: ${escapeHtml(leave.personnel_name)}</strong> (${escapeHtml(leave.rank)})<br>
                            <small>${leave.leave_type} | ${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()} | ${leave.leave_days} days</small><br>
                            <small>Received by: ${escapeHtml(leave.verifying_officer_name || leave.verifying_officer)}</small><br>
                            <small>Will forward to: ${escapeHtml(leave.accepting_officer_name || leave.accepting_officer)}</small><br>
                            <button class="btn-process btn-approve" onclick="openApprovalModal(${leave.id}, 'initiating', 'approve')">✅ Approve & Forward</button>
                            <button class="btn-process btn-reject" onclick="openApprovalModal(${leave.id}, 'initiating', 'reject')">❌ Reject</button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state">📭 No pending requests for initiating officer</div>';
            }
        } catch (error) {
            console.error(error);
            document.getElementById('initiatingPendingList').innerHTML = '<div class="empty-state">Error loading</div>';
        }
    }

    async function loadAcceptingPending() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_accepting_pending');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            const container = document.getElementById('acceptingPendingList');
            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(leave => {
                    html += `
                        <div class="pending-item">
                            <strong>✅ From: ${escapeHtml(leave.personnel_name)}</strong> (${escapeHtml(leave.rank)})<br>
                            <small>${leave.leave_type} | ${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()} | ${leave.leave_days} days</small><br>
                            <small>Verified by: ${escapeHtml(leave.verifying_officer_name || leave.verifying_officer)}</small><br>
                            <small>Initiated by: ${escapeHtml(leave.initiating_officer_name || leave.initiating_officer)}</small><br>
                            <button class="btn-process btn-approve" onclick="openApprovalModal(${leave.id}, 'accepting', 'approve')">✅ Final Approve</button>
                            <button class="btn-process btn-reject" onclick="openApprovalModal(${leave.id}, 'accepting', 'reject')">❌ Reject</button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state">📭 No pending requests for final approval</div>';
            }
        } catch (error) {
            console.error(error);
            document.getElementById('acceptingPendingList').innerHTML = '<div class="empty-state">Error loading</div>';
        }
    }

    async function loadLeaveRequests() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_all');
            formData.append('filter', currentFilter);
            formData.append('page', currentPage);
            formData.append('per_page', currentPerPage);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                renderTable(result.data);
                renderPagination(result.pagination);
            } else {
                showToast(result.error || 'Failed to load', 'error');
            }
        } catch (error) {
            console.error(error);
            showToast('Error loading data', 'error');
        }
    }

    function renderTable(leaves) {
        const tbody = document.getElementById('leaveTableBody');
        if (!leaves || leaves.length === 0) {
            tbody.innerHTML = '<tr><td colspan="14" style="text-align:center">No leave requests found</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        leaves.forEach((leave) => {
            let statusClass = '', statusText = '';
            if (leave.status === 'pending') { statusClass = 'status-pending'; statusText = 'Pending (Receiving)'; }
            else if (leave.status === 'verified') { statusClass = 'status-verified'; statusText = 'Verified (Initiating)'; }
            else if (leave.status === 'initiating_approved') { statusClass = 'status-initiated'; statusText = 'Awaiting Final'; }
            else if (leave.status === 'approved') { statusClass = 'status-approved'; statusText = 'Approved'; }
            else if (leave.status === 'rejected') { statusClass = 'status-rejected'; statusText = 'Rejected'; }
            
            let actions = `<div class="action-buttons">
                <button class="action-btn btn-view-action" onclick="viewDetails(${leave.id})">View</button>`;
            
            if (leave.verifying_officer == currentUserPersonnel && leave.verifying_officer_approved == 0 && leave.status === 'pending') {
                actions += `<button class="action-btn btn-approve-action" onclick="openApprovalModal(${leave.id}, 'verifying', 'approve')">Receive</button>
                           <button class="action-btn btn-reject-action" onclick="openApprovalModal(${leave.id}, 'verifying', 'reject')">Reject</button>`;
            }
            
            if (leave.initiating_officer == currentUserPersonnel && leave.verifying_officer_approved == 1 && leave.initiating_officer_approved == 0 && leave.status === 'verified') {
                actions += `<button class="action-btn btn-approve-action" onclick="openApprovalModal(${leave.id}, 'initiating', 'approve')">Approve</button>
                           <button class="action-btn btn-reject-action" onclick="openApprovalModal(${leave.id}, 'initiating', 'reject')">Reject</button>`;
            }
            
            if (leave.accepting_officer == currentUserPersonnel && leave.initiating_officer_approved == 1 && leave.accepting_officer_approved == 0 && leave.status === 'initiating_approved') {
                actions += `<button class="action-btn btn-approve-action" onclick="openApprovalModal(${leave.id}, 'accepting', 'approve')">Final Approve</button>
                           <button class="action-btn btn-reject-action" onclick="openApprovalModal(${leave.id}, 'accepting', 'reject')">Reject</button>`;
            }
            
            actions += `</div>`;
            
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>${leave.id}</td>
                <td>${escapeHtml(leave.personnel_name)}</td>
                <td>${escapeHtml(leave.rank)}</td>
                <td>${escapeHtml(leave.personnel_number)}</td>
                <td>${leave.leave_type}</td>
                <td>${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</td>
                <td>${leave.leave_days}</td>
                <td>${escapeHtml((leave.reason || '').substring(0, 50))}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>${leave.receiver_name || leave.receiver_id || '-'}</td>
                <td>${leave.verifying_officer_name || leave.verifying_officer || '-'}</td>
                <td>${leave.initiating_officer_name || leave.initiating_officer || '-'}</td>
                <td>${leave.accepting_officer_name || leave.accepting_officer || '-'}</td>
                <td>${actions}</td>
            `;
        });
    }

    function renderPagination(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!pagination || pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<div class="pagination">';
        if (pagination.current_page > 1) {
            html += `<button class="page-btn" onclick="loadLeaveRequestsPage(${pagination.current_page - 1})">« Prev</button>`;
        }
        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === 1 || i === pagination.total_pages || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                html += `<button class="page-btn ${i === pagination.current_page ? 'active' : ''}" onclick="loadLeaveRequestsPage(${i})">${i}</button>`;
            } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                html += `<span class="page-dots">...</span>`;
            }
        }
        if (pagination.current_page < pagination.total_pages) {
            html += `<button class="page-btn" onclick="loadLeaveRequestsPage(${pagination.current_page + 1})">Next »</button>`;
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function loadLeaveRequestsPage(page) {
        currentPage = page;
        loadLeaveRequests();
    }

    function openApprovalModal(id, officerType, action) {
        document.getElementById('actionLeaveId').value = id;
        document.getElementById('actionOfficerType').value = officerType;
        document.getElementById('actionType').value = action;
        
        const titles = {
            verifying: action === 'approve' ? 'Receive Leave Request' : 'Reject Leave Request',
            initiating: action === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request',
            accepting: action === 'approve' ? 'Final Approve Leave Request' : 'Reject Leave Request'
        };
        document.getElementById('actionModalTitle').innerHTML = titles[officerType];
        
        const labels = {
            verifying: action === 'approve' ? 'Receiving Remarks (Optional)' : 'Rejection Reason <span class="required-star">*</span>',
            initiating: action === 'approve' ? 'Forwarding Remarks (Optional)' : 'Rejection Reason <span class="required-star">*</span>',
            accepting: action === 'approve' ? 'Approval Remarks (Optional)' : 'Rejection Reason <span class="required-star">*</span>'
        };
        document.getElementById('actionLabel').innerHTML = labels[officerType];
        
        const placeholders = {
            verifying: action === 'approve' ? 'Add any receiving remarks...' : 'Please provide reason for rejection...',
            initiating: action === 'approve' ? 'Add any forwarding remarks...' : 'Please provide reason for rejection...',
            accepting: action === 'approve' ? 'Add any approval remarks...' : 'Please provide reason for rejection...'
        };
        document.getElementById('actionRemarks').placeholder = placeholders[officerType];
        
        document.getElementById('actionRemarks').value = '';
        document.getElementById('actionModal').style.display = 'block';
    }

    async function loadStatistics() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_stats');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('verifyingPending').textContent = result.data.verifying_pending || 0;
                document.getElementById('verifyingBadge').textContent = result.data.verifying_pending || 0;
                document.getElementById('initiatingPending').textContent = result.data.initiating_pending || 0;
                document.getElementById('initiatingBadge').textContent = result.data.initiating_pending || 0;
                document.getElementById('acceptingPending').textContent = result.data.accepting_pending || 0;
                document.getElementById('acceptingBadge').textContent = result.data.accepting_pending || 0;
            }
        } catch (e) { console.error(e); }
    }

    async function loadBalance() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_my_balance');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('gharpariBalance').textContent = result.data.gharpari_bida_days || 0;
                document.getElementById('parbaBalance').textContent = result.data.parba_bida_days || 0;
                document.getElementById('bhaeepariBalance').textContent = result.data.bhaeepari_bida_days || 0;
            }
        } catch (e) { console.error(e); }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.style.backgroundColor = type === 'success' ? '#28a745' : '#dc3545';
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 4000);
    }

    function viewDetails(id) {
        window.open(`generate_leave_pass.php?id=${id}`, '_blank');
    }

    function filterByStatus(status) {
        currentFilter = status;
        currentPage = 1;
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.getAttribute('data-filter') === status) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        loadLeaveRequests();
    }

    // Event Listeners
    document.getElementById('newLeaveBtn')?.addEventListener('click', () => {
        document.getElementById('leaveForm').reset();
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('startDate').min = today;
        document.getElementById('endDate').min = today;
        document.getElementById('leaveModal').style.display = 'block';
    });

    document.querySelectorAll('.close, .close-action').forEach(btn => {
        btn.onclick = () => {
            document.getElementById('leaveModal').style.display = 'none';
            document.getElementById('actionModal').style.display = 'none';
        };
    });

    document.querySelectorAll('#cancelBtn, #cancelActionBtn').forEach(btn => {
        btn.onclick = () => {
            document.getElementById('leaveModal').style.display = 'none';
            document.getElementById('actionModal').style.display = 'none';
        };
    });

    window.onclick = (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };

    document.getElementById('startDate')?.addEventListener('change', calculateDays);
    document.getElementById('endDate')?.addEventListener('change', calculateDays);
    document.getElementById('leaveType')?.addEventListener('change', calculateDays);
    document.getElementById('personnelId')?.addEventListener('change', calculateDays);

    async function calculateDays() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('totalDays').value = days + ' days';
            
            const personnelId = document.getElementById('personnelId').value;
            const leaveType = document.getElementById('leaveType').value;
            if (personnelId && leaveType) {
                const formData = new FormData();
                formData.append('action', 'get_leave_balance');
                formData.append('personnel_id', personnelId);
                formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                let balance = 0;
                if (result.success) {
                    if (leaveType === 'gharpari_bida') balance = result.data.gharpari_bida_days;
                    else if (leaveType === 'parba_bida') balance = result.data.parba_bida_days;
                    else if (leaveType === 'bhaeepari_bida') balance = result.data.bhaeepari_bida_days;
                }
                document.getElementById('availableBalance').value = `${balance} days available`;
                if (days > balance) {
                    showToast(`Warning: Only ${balance} days available!`, 'error');
                }
            }
        }
    }

    document.getElementById('leaveForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Validate that officers are not the same as personnel
        const personnelId = document.getElementById('personnelId').value;
        const receivingOfficer = document.getElementById('receivingOfficer').value;
        const initiatingOfficer = document.getElementById('initiatingOfficer').value;
        const acceptingOfficer = document.getElementById('acceptingOfficer').value;
        
        if (personnelId === receivingOfficer) {
            showToast('Receiving Officer cannot be the same as the personnel requesting leave', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'submit_leave');
        formData.append('personnel_id', personnelId);
        formData.append('leave_type', document.getElementById('leaveType').value);
        formData.append('start_date', document.getElementById('startDate').value);
        formData.append('end_date', document.getElementById('endDate').value);
        formData.append('reason', document.getElementById('reason').value);
        formData.append('receiving_officer', receivingOfficer);
        formData.append('initiating_officer', initiatingOfficer);
        formData.append('accepting_officer', acceptingOfficer);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast(result.message || 'Leave request submitted successfully!');
                document.getElementById('leaveModal').style.display = 'none';
                loadLeaveRequests();
                loadStatistics();
                loadBalance();
                loadVerifyingPending();
                loadInitiatingPending();
                loadAcceptingPending();
            } else {
                showToast(result.error, 'error');
            }
        } catch (error) {
            console.error(error);
            showToast('Error submitting request', 'error');
        }
    });

    document.getElementById('actionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('actionLeaveId').value;
        const officerType = document.getElementById('actionOfficerType').value;
        const action = document.getElementById('actionType').value;
        const remarks = document.getElementById('actionRemarks').value;
        
        if (action === 'reject' && !remarks.trim()) {
            showToast('Remarks required for rejection', 'error');
            return;
        }
        
        const endpoints = {
            verifying: action === 'approve' ? 'verifying_officer_approve' : 'verifying_officer_reject',
            initiating: action === 'approve' ? 'initiating_officer_approve' : 'initiating_officer_reject',
            accepting: action === 'approve' ? 'accepting_officer_approve' : 'accepting_officer_reject'
        };
        
        const successMsgs = {
            verifying: action === 'approve' ? 'Request received and forwarded!' : 'Request rejected!',
            initiating: action === 'approve' ? 'Request approved and forwarded to accepting officer!' : 'Request rejected!',
            accepting: action === 'approve' ? 'Request FINALLY APPROVED! Leave granted.' : 'Request rejected!'
        };
        
        const formData = new FormData();
        formData.append('action', endpoints[officerType]);
        formData.append('id', id);
        formData.append('remarks', remarks);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast(result.message || successMsgs[officerType], 'success');
                document.getElementById('actionModal').style.display = 'none';
                loadLeaveRequests();
                loadStatistics();
                loadBalance();
                loadVerifyingPending();
                loadInitiatingPending();
                loadAcceptingPending();
            } else {
                showToast(result.error, 'error');
            }
        } catch (error) {
            console.error(error);
            showToast('Error processing request', 'error');
        }
    });

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            filterByStatus(this.getAttribute('data-filter'));
        });
    });

    // Refresh data every 30 seconds
    setInterval(() => {
        loadStatistics();
        loadVerifyingPending();
        loadInitiatingPending();
        loadAcceptingPending();
    }, 30000);

    // Initial load
    loadLeaveRequests();
    loadStatistics();
    loadBalance();
    loadVerifyingPending();
    loadInitiatingPending();
    loadAcceptingPending();
    
    // Set min date for date inputs
    const today = new Date().toISOString().split('T')[0];
    if(document.getElementById('startDate')) document.getElementById('startDate').min = today;
    if(document.getElementById('endDate')) document.getElementById('endDate').min = today;
</script>

</body>
</html>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>