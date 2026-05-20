<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1); // Changed to 1 for debugging
ini_set('log_errors', 1);

include('includes/config.php');
include_once('includes/pagination.php');

$pageTitle = "Leave Management";
$pageSubtitle = "Manage military personnel leave requests, approvals, and tracking";
$activePage = "leave";

// Get user role from session (from personnel table)
$user_role_int = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
$user_role_string = '';
if ($user_role_int === 2) {
    $user_role_string = 'super_admin';
} elseif ($user_role_int === 1) {
    $user_role_string = 'admin';
} else {
    $user_role_string = 'user';
}

// Get current user's personnel ID from session
$current_personnel_id = isset($_SESSION['user_personnel_id']) ? (int)$_SESSION['user_personnel_id'] : 0;

// First, establish database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get current user's personnel ID from personnel table if not set
if ($current_personnel_id == 0 && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM military_personnel_status WHERE personnel_number = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($personnel) {
        $_SESSION['user_personnel_id'] = $personnel['id'];
        $current_personnel_id = $personnel['id'];
    }
}

// For testing - find a valid personnel if still not set
if ($current_personnel_id == 0) {
    $stmt = $pdo->prepare("SELECT id FROM military_personnel_status LIMIT 1");
    $stmt->execute();
    $first = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($first) {
        $_SESSION['user_personnel_id'] = $first['id'];
        $current_personnel_id = $first['id'];
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to get leave balance for a personnel
function getLeaveBalance($pdo, $personnel_id) {
    $stmt = $pdo->prepare("SELECT * FROM leave_balance WHERE personnel_id = ?");
    $stmt->execute([$personnel_id]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$balance) {
        $stmt = $pdo->prepare("INSERT INTO leave_balance (personnel_id, gharpari_bida_days, parba_bida_days, bhaeepari_bida_days) VALUES (?, 15, 12, 10)");
        $stmt->execute([$personnel_id]);
        
        $stmt = $pdo->prepare("SELECT * FROM leave_balance WHERE personnel_id = ?");
        $stmt->execute([$personnel_id]);
        $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return $balance;
}

// Helper function to get all personnel leave balances
function getAllPersonnelBalances($pdo, $page = 1, $per_page = 10, $search = '') {
    $offset = ($page - 1) * $per_page;
    
    $sql = "SELECT mps.id as personnel_id, mps.personnel_name, mps.rank,
                   COALESCE(lb.gharpari_bida_days, 15) as gharpari_bida_days,
                   COALESCE(lb.parba_bida_days, 12) as parba_bida_days,
                   COALESCE(lb.bhaeepari_bida_days, 10) as bhaeepari_bida_days,
                   COALESCE(lb.last_updated, mps.created_at) as last_updated
            FROM military_personnel_status mps
            LEFT JOIN leave_balance lb ON mps.id = lb.personnel_id";
    
    $count_sql = "SELECT COUNT(*) as total FROM military_personnel_status mps";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE mps.personnel_name LIKE ? OR mps.rank LIKE ?";
        $count_sql .= " WHERE mps.personnel_name LIKE ? OR mps.rank LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm];
    }
    
    $sql .= " GROUP BY mps.id";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total / $per_page);
    
    $sql .= " ORDER BY last_updated DESC LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index++, $param);
    }
    $stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $personnel,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ];
}

function updateUserLeaveBalance($pdo, $personnel_id, $gharpari_days, $parba_days, $bhaeepari_days) {
    $stmt = $pdo->prepare("
        UPDATE leave_balance 
        SET gharpari_bida_days = ?, 
            parba_bida_days = ?, 
            bhaeepari_bida_days = ?,
            last_updated = NOW()
        WHERE personnel_id = ?
    ");
    return $stmt->execute([$gharpari_days, $parba_days, $bhaeepari_days, $personnel_id]);
}

function deductLeaveBalance($pdo, $personnel_id, $leave_type, $days_used) {
    $balance = getLeaveBalance($pdo, $personnel_id);
    
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
    return $stmt->execute([$new_days, $personnel_id]);
}

function addBackLeaveBalance($pdo, $personnel_id, $leave_type, $days_used) {
    $balance = getLeaveBalance($pdo, $personnel_id);
    
    $field_map = [
        'gharpari_bida' => 'gharpari_bida_days',
        'parba_bida' => 'parba_bida_days',
        'bhaeepari_bida' => 'bhaeepari_bida_days'
    ];
    
    if (!isset($field_map[$leave_type])) return false;
    
    $field = $field_map[$leave_type];
    $current_days = floatval($balance[$field]);
    $new_days = $current_days + $days_used;
    
    $stmt = $pdo->prepare("UPDATE leave_balance SET $field = ?, last_updated = NOW() WHERE personnel_id = ?");
    return $stmt->execute([$new_days, $personnel_id]);
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
        
        if ($action === 'get_all_personnel_balances') {
            if ($user_role_int < 1) {
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
            $search = $_POST['search'] ?? '';
            
            $result = getAllPersonnelBalances($pdo, $page, $per_page, $search);
            
            echo json_encode([
                'success' => true, 
                'data' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'total_pages' => $result['total_pages'],
                    'total_records' => $result['total'],
                    'per_page' => $result['per_page']
                ]
            ]);
            exit;
        }
        
        if ($action === 'update_user_balance') {
            if ($user_role_int < 1) {
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            
            $personnel_id = intval($_POST['personnel_id'] ?? 0);
            $gharpari_days = floatval($_POST['gharpari_days'] ?? 0);
            $parba_days = floatval($_POST['parba_days'] ?? 0);
            $bhaeepari_days = floatval($_POST['bhaeepari_days'] ?? 0);
            
            if ($personnel_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid personnel ID']);
                exit;
            }
            
            $result = updateUserLeaveBalance($pdo, $personnel_id, $gharpari_days, $parba_days, $bhaeepari_days);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        if ($action === 'get_leave_settings') {
            if ($user_role_int < 1) {
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM leave_settings WHERE id = 1");
            $stmt->execute();
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $settings]);
            exit;
        }
        
        if ($action === 'update_leave_settings') {
            if ($user_role_int < 1) {
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            
            $gharpari_bida = floatval($_POST['gharpari_bida'] ?? 0);
            $parba_bida = floatval($_POST['parba_bida'] ?? 0);
            $bhaeepari_bida = floatval($_POST['bhaeepari_bida'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE leave_settings SET gharpari_bida_default = ?, parba_bida_default = ?, bhaeepari_bida_default = ? WHERE id = 1");
            $result = $stmt->execute([$gharpari_bida, $parba_bida, $bhaeepari_bida]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        if ($action === 'apply_global_settings') {
            if ($user_role_int < 1) {
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM leave_settings WHERE id = 1");
            $stmt->execute();
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                UPDATE leave_balance 
                SET gharpari_bida_days = ?,
                    parba_bida_days = ?,
                    bhaeepari_bida_days = ?,
                    last_updated = NOW()
            ");
            $result = $stmt->execute([
                $settings['gharpari_bida_default'],
                $settings['parba_bida_default'],
                $settings['bhaeepari_bida_default']
            ]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        if ($action === 'get_leave_balance') {
            $personnel_id = intval($_POST['personnel_id'] ?? 0);
            if ($personnel_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid personnel ID']);
                exit;
            }
            $balance = getLeaveBalance($pdo, $personnel_id);
            echo json_encode(['success' => true, 'data' => $balance]);
            exit;
        }
        
        if ($action === 'get_my_balance') {
            $balance = getLeaveBalance($pdo, $current_personnel_id);
            echo json_encode(['success' => true, 'data' => $balance]);
            exit;
        }
        
        if ($action === 'get_all') {
            $filter = $_POST['filter'] ?? 'all';
            $search = $_POST['search'] ?? '';
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 5;
            $offset = ($page - 1) * $per_page;
            
            $sql = "SELECT lr.*, mps.personnel_name, mps.rank,
                           lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days,
                           io.personnel_name as initiating_officer_name,
                           ao.personnel_name as accepting_officer_name
                    FROM leave_requests lr
                    INNER JOIN military_personnel_status mps ON lr.personnel_id = mps.id
                    LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
                    LEFT JOIN military_personnel_status io ON lr.initiating_officer = io.id
                    LEFT JOIN military_personnel_status ao ON lr.accepting_officer = ao.id
                    WHERE 1=1";
            
            $count_sql = "SELECT COUNT(*) as total FROM leave_requests lr WHERE 1=1";
            $params = [];
            
            if ($filter !== 'all') {
                $sql .= " AND lr.status = ?";
                $count_sql .= " AND lr.status = ?";
                $params[] = $filter;
            }
            
            if (!empty($search)) {
                $sql .= " AND (mps.personnel_name LIKE ? OR mps.rank LIKE ? OR lr.reason LIKE ?)";
                $count_sql .= " AND (mps.personnel_name LIKE ? OR mps.rank LIKE ? OR lr.reason LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = ceil($total_records / $per_page);
            
            $sql .= " ORDER BY 
                        CASE lr.status 
                            WHEN 'pending' THEN 1 
                            WHEN 'forwarded' THEN 2 
                            ELSE 3 
                        END, 
                        lr.created_at ASC";
            
            $sql .= " LIMIT ? OFFSET ?";
            
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
        
        if ($action === 'submit_leave') {
            $personnel_id = intval($_POST['personnel_id'] ?? 0);
            $leave_type = trim($_POST['leave_type'] ?? '');
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $initiating_officer = intval($_POST['initiating_officer'] ?? 0);
            $accepting_officer = intval($_POST['accepting_officer'] ?? 0);
            
            if ($personnel_id <= 0 || empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
                echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
                exit;
            }
            
            if ($initiating_officer <= 0 || $accepting_officer <= 0) {
                echo json_encode(['success' => false, 'error' => 'Please select both initiating and accepting officers']);
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
            
            $balance = getLeaveBalance($pdo, $personnel_id);
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
                AND status IN ('pending', 'approved', 'forwarded')
                AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
            ");
            $stmt->execute([$personnel_id, $end_date, $start_date, $end_date, $start_date]);
            $overlap = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($overlap['count'] > 0) {
                echo json_encode(['success' => false, 'error' => 'You already have a leave request for this period']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests 
                (personnel_id, leave_type, start_date, end_date, leave_days, reason, status, created_by, initiating_officer, accepting_officer) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $personnel_id, 
                $leave_type, 
                $start_date, 
                $end_date, 
                $leave_days, 
                $reason, 
                $_SESSION['user_personnel_id'] ?? $personnel_id,
                $initiating_officer,
                $accepting_officer
            ]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to submit leave request']);
            }
            exit;
        }
        
        // Forward leave request to superadmin
        if ($action === 'forward_leave') {
            if ($user_role_int != 1) {
                echo json_encode(['success' => false, 'error' => 'Only admin can forward requests']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $forward_remarks = trim($_POST['forward_remarks'] ?? '');
            
            if (empty($forward_remarks)) {
                echo json_encode(['success' => false, 'error' => 'Forward remarks are required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT status FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Only pending requests can be forwarded']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'forwarded', 
                    forwarded_by = ?, 
                    forwarded_at = NOW(),
                    forward_remarks = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([$current_personnel_id, $forward_remarks, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        if ($action === 'approve_leave') {
            $id = intval($_POST['id'] ?? 0);
            $approver_remarks = trim($_POST['approver_remarks'] ?? '');
            
            $stmt = $pdo->prepare("SELECT personnel_id, leave_type, leave_days, status FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['status'] === 'approved') {
                echo json_encode(['success' => false, 'error' => 'Leave request already approved']);
                exit;
            }
            
            // Admin can approve pending, Superadmin can approve forwarded
            if ($user_role_int == 1 && $leave['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Admin can only approve pending requests']);
                exit;
            }
            
            if ($user_role_int == 2 && $leave['status'] !== 'forwarded') {
                echo json_encode(['success' => false, 'error' => 'Superadmin can only approve forwarded requests']);
                exit;
            }
            
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
                SET status = 'approved', 
                    approved_by = ?, 
                    approved_at = NOW(),
                    approver_remarks = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([$current_personnel_id, $approver_remarks, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        if ($action === 'reject_leave') {
            $id = intval($_POST['id'] ?? 0);
            $rejection_reason = trim($_POST['approver_remarks'] ?? '');
            
            if (empty($rejection_reason)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT status FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            // Admin can reject pending, Superadmin can reject forwarded
            if ($user_role_int == 1 && $leave['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Admin can only reject pending requests']);
                exit;
            }
            
            if ($user_role_int == 2 && $leave['status'] !== 'forwarded') {
                echo json_encode(['success' => false, 'error' => 'Superadmin can only reject forwarded requests']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'rejected', 
                    approved_by = ?, 
                    approved_at = NOW(),
                    approver_remarks = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([$current_personnel_id, $rejection_reason, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        if ($action === 'cancel_leave') {
            $id = intval($_POST['id'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT personnel_id, status, leave_type, leave_days FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            // Only the requester or admin can cancel pending requests
            if ($leave['status'] === 'approved') {
                addBackLeaveBalance($pdo, $leave['personnel_id'], $leave['leave_type'], $leave['leave_days']);
            }
            
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
            $result = $stmt->execute([$id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        if ($action === 'get_stats') {
            $stats = [];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
            $stmt->execute();
            $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'forwarded'");
            $stmt->execute();
            $stats['forwarded'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(leave_days), 0) as total_days FROM leave_requests WHERE status = 'approved' AND MONTH(start_date) = MONTH(CURDATE()) AND YEAR(start_date) = YEAR(CURDATE())");
            $stmt->execute();
            $stats['total_leave_days'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_days'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved' AND DATE(approved_at) = CURDATE()");
            $stmt->execute();
            $stats['approved_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT personnel_id) as count FROM leave_requests WHERE status = 'approved' AND CURDATE() BETWEEN start_date AND end_date");
            $stmt->execute();
            $stats['on_leave_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
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

// Get current user's leave balance for dashboard
$myBalance = getLeaveBalance($pdo, $current_personnel_id);

// Get leave statistics for initial display
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
$stmt->execute();
$pendingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'forwarded'");
$stmt->execute();
$forwardedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved' AND DATE(approved_at) = CURDATE()");
$stmt->execute();
$approvedToday = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT personnel_id) as count FROM leave_requests WHERE status = 'approved' AND CURDATE() BETWEEN start_date AND end_date");
$stmt->execute();
$onLeaveToday = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get officers for dropdown
$officers = [];
try {
    $stmt = $pdo->query("SELECT id, personnel_name, rank FROM military_personnel_status WHERE rank IN ('Officer', 'Major', 'Captain', 'Colonel', 'Lieutenant') OR is_officer = 1 ORDER BY personnel_name");
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If the query fails, try without the is_officer column
    $stmt = $pdo->query("SELECT id, personnel_name, rank FROM military_personnel_status WHERE rank IN ('Officer', 'Major', 'Captain', 'Colonel', 'Lieutenant') ORDER BY personnel_name");
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #1a2c3e;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stats-grid-second {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eef2f6;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .category-card {
            color: white;
            border: none;
        }
        
        .category-card:nth-child(1) { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .category-card:nth-child(2) { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .category-card:nth-child(3) { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 14px;
            margin-top: 6px;
            opacity: 0.9;
        }
        
        .stat-progress {
            width: 100%;
            height: 8px;
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
            margin-top: 12px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: rgba(255,255,255,0.9);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .admin-section {
            background: #f8fafc;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid #eef2f6;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .admin-header h2 {
            font-size: 20px;
            color: #1a2c3e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-settings {
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            border: none;
            background: #1e3a32;
            color: white;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-settings:hover {
            background: #14362c;
            transform: translateY(-1px);
        }
        
        .search-container {
            position: relative;
            max-width: 400px;
        }
        
        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa9bc;
            font-size: 14px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 40px 12px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
        }
        
        .search-input:focus {
            border-color: #2c5f4e;
            box-shadow: 0 0 0 3px rgba(44, 95, 78, 0.1);
        }
        
        .clear-search {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9aa9bc;
            font-size: 14px;
            padding: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .clear-search:hover {
            background: #e2e8f0;
            color: #c2410c;
        }
        
        .filter-btn {
            padding: 8px 18px;
            border: 1.5px solid #e2e8f0;
            background: white;
            border-radius: 30px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .filter-btn:hover {
            background: #f1f5f9;
            border-color: #2c5f4e;
        }
        
        .filter-btn.active {
            background: #1e3a32;
            color: white;
            border-color: #1e3a32;
        }
        
        .btn-add, .btn-export {
            padding: 10px 22px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }
        
        .btn-add {
            background: #1e3a32;
            color: white;
        }
        
        .btn-add:hover {
            background: #14362c;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-export {
            background: #2c5f4e;
            color: white;
            margin-left: 10px;
        }
        
        .btn-export:hover {
            background: #1e4a3a;
            transform: translateY(-1px);
        }
        
        .data-table {
            overflow-x: auto;
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eef2f6;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        
        th {
            text-align: left;
            padding: 14px 12px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #1a2c3e;
            font-size: 13px;
        }
        
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #eef2f6;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-forwarded { background: #e0e7ff; color: #3730a3; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f3f4f6; color: #4b5563; }
        
        .balance-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        .balance-good { background: #d1fae5; color: #065f46; }
        .balance-low { background: #fef3c7; color: #92400e; }
        .balance-critical { background: #fee2e2; color: #991b1b; }
        
        .officer-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .action-btn-small {
            padding: 5px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .edit-btn {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .edit-btn:hover {
            background: #bfdbfe;
            transform: translateY(-1px);
        }
        
        .btn-view { background: #e0e7ff; color: #3730a3; }
        .btn-view:hover { background: #c7d2fe; }
        .btn-approve { background: #d1fae5; color: #065f46; }
        .btn-approve:hover { background: #a7f3d0; }
        .btn-reject { background: #fee2e2; color: #991b1b; }
        .btn-reject:hover { background: #fecaca; }
        .btn-forward { background: #fef3c7; color: #92400e; }
        .btn-forward:hover { background: #fde68a; }
        .btn-cancel-action { background: #f3f4f6; color: #4b5563; }
        .btn-cancel-action:hover { background: #e5e7eb; }
        
        .pagination-container {
            margin-top: 24px;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 1px solid #eef2f6;
        }
        
        .pagination-info {
            color: #6c7a8e;
            font-size: 14px;
        }
        
        .pagination {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .pagination-btn, .page-number {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            color: #495057;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .pagination-btn:hover, .page-number:hover {
            background: #f8f9fa;
            border-color: #1e3c72;
            color: #1e3c72;
        }
        
        .page-number.active {
            background: #1e3a32;
            border-color: #1e3a32;
            color: white;
        }
        
        .records-per-page {
            margin-top: 15px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #6c7a8e;
        }
        
        .records-per-page select {
            padding: 6px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            outline: none;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            width: 90%;
            max-width: 700px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideDown 0.3s;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1a2c3e;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close, .close-action, .close-details, .close-edit, .close-global, .close-forward {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #9aa9bc;
            transition: 0.2s;
        }
        
        .close:hover, .close-action:hover, .close-details:hover, .close-edit:hover, .close-global:hover, .close-forward:hover {
            color: #c2410c;
        }
        
        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .input-field {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .input-field label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        
        .input-field input, .input-field select, .input-field textarea {
            padding: 12px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
            font-family: inherit;
        }
        
        .input-field input:focus, .input-field select:focus, .input-field textarea:focus {
            border-color: #2c5f4e;
            box-shadow: 0 0 0 3px rgba(44, 95, 78, 0.1);
        }
        
        .required-star {
            color: #c2410c;
        }
        
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eef2f6;
        }
        
        .btn-cancel {
            padding: 10px 24px;
            background: #f1f3f5;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s;
        }
        
        .btn-cancel:hover {
            background: #e9ecef;
        }
        
        .btn-submit {
            padding: 10px 28px;
            background: #1e3a32;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }
        
        .btn-submit:hover {
            background: #14362c;
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e3a32;
            color: white;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 14px;
            z-index: 1100;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
            display: none;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .stats-grid, .stats-grid-second {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 15% auto;
                width: 95%;
            }
            
            .search-container {
                max-width: 100%;
            }
            
            .admin-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-settings {
                justify-content: center;
            }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: #1e3a32;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .info-box {
            background: #e0e7ff;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1e40af;
            font-size: 13px;
        }
        
        .officer-section {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }
    </style>
</head>
<body>

<div class="stats-grid">
    <div class="stat-card category-card">
        <div class="stat-icon"><i class="fas fa-home"></i></div>
        <div>
            <div class="stat-value" id="gharpariBalance"><?php echo isset($myBalance['gharpari_bida_days']) ? $myBalance['gharpari_bida_days'] : 15; ?></div>
            <div class="stat-label">🏠 Gharpari Bida (Family Leave)</div>
            <div class="stat-progress"><div class="progress-bar" id="gharpariProgress" style="width: <?php echo min(100, ((isset($myBalance['gharpari_bida_days']) ? $myBalance['gharpari_bida_days'] : 15) / 15) * 100); ?>%;"></div></div>
            <small style="opacity:0.8;">Days remaining this year</small>
        </div>
    </div>
    <div class="stat-card category-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div>
            <div class="stat-value" id="parbaBalance"><?php echo isset($myBalance['parba_bida_days']) ? $myBalance['parba_bida_days'] : 12; ?></div>
            <div class="stat-label">🎉 Parba Bida (Festival Leave)</div>
            <div class="stat-progress"><div class="progress-bar" id="parbaProgress" style="width: <?php echo min(100, ((isset($myBalance['parba_bida_days']) ? $myBalance['parba_bida_days'] : 12) / 12) * 100); ?>%;"></div></div>
            <small style="opacity:0.8;">Days remaining this year</small>
        </div>
    </div>
    <div class="stat-card category-card">
        <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
        <div>
            <div class="stat-value" id="bhaeepariBalance"><?php echo isset($myBalance['bhaeepari_bida_days']) ? $myBalance['bhaeepari_bida_days'] : 10; ?></div>
            <div class="stat-label">🤝 Bhaeepari Bida (Emergency Leave)</div>
            <div class="stat-progress"><div class="progress-bar" id="bhaeepariProgress" style="width: <?php echo min(100, ((isset($myBalance['bhaeepari_bida_days']) ? $myBalance['bhaeepari_bida_days'] : 10) / 10) * 100); ?>%;"></div></div>
            <small style="opacity:0.8;">Days remaining this year</small>
        </div>
    </div>
</div>

<div class="stats-grid-second">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value" id="pendingCount"><?php echo $pendingCount; ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-share"></i></div>
        <div>
            <div class="stat-value" id="forwardedCount"><?php echo $forwardedCount; ?></div>
            <div class="stat-label">Forwarded to Superadmin</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value" id="approvedToday"><?php echo $approvedToday; ?></div>
            <div class="stat-label">Approved Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div>
            <div class="stat-value" id="onLeaveToday"><?php echo $onLeaveToday; ?></div>
            <div class="stat-label">On Leave Today</div>
        </div>
    </div>
</div>

<?php if ($user_role_int >= 1): ?>
<div class="admin-section">
    <div class="admin-header">
        <h2><i class="fas fa-users"></i> Personnel Leave Management</h2>
        <div style="display: flex; gap: 10px;">
            <button class="btn-settings" id="openGlobalSettingsBtn"><i class="fas fa-globe"></i> Set Global Defaults</button>
            <button class="btn-settings" id="applyGlobalBtn"><i class="fas fa-sync-alt"></i> Apply to All</button>
        </div>
    </div>
    
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <span>Search for a personnel to view and edit their leave balance for all three categories.</span>
    </div>
    
    <div class="search-container" style="max-width:100%; margin-bottom:20px;">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="personnelSearch" class="search-input" placeholder="Search personnel by name or rank...">
        <button id="clearPersonnelSearch" class="clear-search">✕</button>
    </div>
    
    <div class="data-table">
        <table id="personnelTable">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Personnel Name</th>
                    <th>Rank</th>
                    <th>🏠 Gharpari Bida</th>
                    <th>🎉 Parba Bida</th>
                    <th>🤝 Bhaeepari Bida</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="personnelTableBody">
                <tr><td colspan="8" style="text-align:center"><div class="loading-spinner"></div> Loading personnel...<\/td><ee
            </tbody>
        </table>
    </div>
    <div id="personnelPaginationContainer" class="pagination-container"></div>
    <div class="records-per-page">
        <label>Show:</label>
        <select id="personnelRecordsPerPage">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <span>entries per page</span>
    </div>
</div>

<div id="editPersonnelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Leave Balance - <span id="editPersonnelName"></span></h3>
            <span class="close-edit">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editPersonnelForm">
                <input type="hidden" id="editPersonnelId">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="info-box" style="background:#dbeafe; margin-bottom:20px;">
                    <i class="fas fa-info-circle"></i> Set the number of days this personnel can take for each leave category.
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-home"></i> Gharpari Bida (Family Leave)</label>
                    <input type="number" id="editGharpari" step="0.5" min="0" max="100" required>
                    <small>Days allowed per year</small>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-calendar-alt"></i> Parba Bida (Festival Leave)</label>
                    <input type="number" id="editParba" step="0.5" min="0" max="100" required>
                    <small>Days allowed per year</small>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-hand-holding-heart"></i> Bhaeepari Bida (Emergency Leave)</label>
                    <input type="number" id="editBhaeepari" step="0.5" min="0" max="100" required>
                    <small>Days allowed per year</small>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="globalSettingsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-globe"></i> Global Default Leave Limits</h3>
            <span class="close-global">&times;</span>
        </div>
        <div class="modal-body">
            <form id="globalSettingsForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="info-box" style="background:#fef3c7; margin-bottom:20px;">
                    <i class="fas fa-exclamation-triangle"></i> These values will be used as defaults for new personnel. Use "Apply to All" to update existing personnel.
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-home"></i> Gharpari Bida (Default)</label>
                    <input type="number" id="globalGharpari" step="0.5" min="0" max="100" required>
                    <small>Default days per year per personnel</small>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-calendar-alt"></i> Parba Bida (Default)</label>
                    <input type="number" id="globalParba" step="0.5" min="0" max="100" required>
                    <small>Default days per year per personnel</small>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-hand-holding-heart"></i> Bhaeepari Bida (Default)</label>
                    <input type="number" id="globalBhaeepari" step="0.5" min="0" max="100" required>
                    <small>Default days per year per personnel</small>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelGlobalBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Search by name, rank, or reason...">
        <button id="clearSearch" class="clear-search">✕</button>
    </div>
    <div>
        <button class="btn-add" id="newLeaveBtn">
            <i class="fas fa-plus-circle"></i> New Leave Request
        </button>
        <?php if ($user_role_int >= 1): ?>
        <button class="btn-export" id="exportBtn">
            <i class="fas fa-download"></i> Export Report
        </button>
        <?php endif; ?>
    </div>
</div>

<div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="filter-btn active" data-filter="all">📋 All Requests</button>
    <button class="filter-btn" data-filter="pending">⏳ Pending</button>
    <button class="filter-btn" data-filter="forwarded">📤 Forwarded</button>
    <button class="filter-btn" data-filter="approved">✅ Approved</button>
    <button class="filter-btn" data-filter="rejected">❌ Rejected</button>
    <button class="filter-btn" data-filter="cancelled">🚫 Cancelled</button>
</div>

<div class="data-table">
    <table id="leaveTable">
        <thead>
            <tr>
                <th style="width: 40px;">S.N.</th>
                <th>Personnel</th>
                <th>Rank</th>
                <th>Leave Type</th>
                <th>Period</th>
                <th>Days</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Balance</th>
                <th>Initiating Officer</th>
                <th>Accepting Officer</th>
                <th>Submitted</th>
                <th style="width: 200px;">Actions</th>
            </tr>
        </thead>
        <tbody id="leaveTableBody">
            <tr><td colspan="13" style="text-align:center"><div class="loading-spinner"></div> Loading leave requests...<\/td><ee
        </tbody>
    </table>
</div>

<div id="paginationContainer" class="pagination-container"></div>
<div class="records-per-page">
    <label>Show:</label>
    <select id="recordsPerPage">
        <option value="5">5</option>
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>
    <span>entries per page</span>
</div>

<div id="leaveModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> New Leave Request</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="leaveForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="input-field">
                    <label><i class="fas fa-user"></i> Personnel <span class="required-star">*</span></label>
                    <select id="personnelId" required>
                        <option value="">Select Personnel</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, personnel_name, rank FROM military_personnel_status ORDER BY personnel_name");
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($user_role_int === 0 && $row['id'] == $current_personnel_id) ? 'selected' : '';
                            echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['rank'] . ' ' . $row['personnel_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-tag"></i> Leave Type <span class="required-star">*</span></label>
                    <select id="leaveType" required>
                        <option value="">Select Type</option>
                        <option value="gharpari_bida">🏠 Gharpari Bida (Family Leave)</option>
                        <option value="parba_bida">🎉 Parba Bida (Festival Leave)</option>
                        <option value="bhaeepari_bida">🤝 Bhaeepari Bida (Emergency Leave)</option>
                    </select>
                </div>
                
                <div class="info-box officer-section">
                    <i class="fas fa-user-tie"></i>
                    <span><strong>Officer Authorization Required</strong> - Please select both initiating and accepting officers</span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input-field">
                        <label><i class="fas fa-user-shield"></i> Initiating Officer <span class="required-star">*</span></label>
                        <select id="initiatingOfficer" required>
                            <option value="">Select Initiating Officer</option>
                            <?php foreach ($officers as $officer): ?>
                                <option value="<?php echo $officer['id']; ?>"><?php echo htmlspecialchars($officer['rank'] . ' ' . $officer['personnel_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Officer who will forward this request</small>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-user-check"></i> Accepting Officer <span class="required-star">*</span></label>
                        <select id="acceptingOfficer" required>
                            <option value="">Select Accepting Officer</option>
                            <?php foreach ($officers as $officer): ?>
                                <option value="<?php echo $officer['id']; ?>"><?php echo htmlspecialchars($officer['rank'] . ' ' . $officer['personnel_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Officer who will approve this request</small>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input-field">
                        <label><i class="fas fa-calendar-day"></i> Start Date <span class="required-star">*</span></label>
                        <input type="date" id="startDate" required>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-calendar-day"></i> End Date <span class="required-star">*</span></label>
                        <input type="date" id="endDate" required>
                    </div>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-sort-numeric-up"></i> Total Days</label>
                    <input type="text" id="totalDays" readonly style="background: #f8fafc;">
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-chart-line"></i> Available Days</label>
                    <input type="text" id="availableBalance" readonly style="background: #e8f5e9; font-weight: bold;">
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-comment"></i> Reason for Leave <span class="required-star">*</span></label>
                    <textarea id="reason" rows="3" placeholder="Please provide detailed reason for leave..." required></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="forwardModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-share"></i> Forward to Superadmin</h3>
            <span class="close-forward">&times;</span>
        </div>
        <div class="modal-body">
            <form id="forwardForm">
                <input type="hidden" id="forwardLeaveId">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="info-box" style="background:#fef3c7; margin-bottom:20px;">
                    <i class="fas fa-info-circle"></i> Forward this leave request to superadmin for final approval.
                </div>
                
                <div class="input-field">
                    <label>Forward Remarks <span class="required-star">*</span></label>
                    <textarea id="forwardRemarks" rows="3" placeholder="Please provide reason for forwarding this request..." required></textarea>
                    <small>Explain why this request needs superadmin approval</small>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelForwardBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Forward Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="actionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="actionModalTitle">Process Leave Request</h3>
            <span class="close-action">&times;</span>
        </div>
        <div class="modal-body">
            <form id="actionForm">
                <input type="hidden" id="actionLeaveId">
                <input type="hidden" id="actionType">
                
                <div class="input-field">
                    <label id="actionLabel">Remarks <span class="required-star">*</span></label>
                    <textarea id="actionRemarks" rows="3" placeholder="Enter remarks..."></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelActionBtn">Cancel</button>
                    <button type="submit" class="btn-submit" id="submitActionBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="detailsModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Leave Request Details</h3>
            <span class="close-details">&times;</span>
        </div>
        <div class="modal-body" id="detailsContent"></div>
    </div>
</div>

<div id="toast" class="toast">
    <span id="toastMessage"></span>
</div>

<script>
    let leaveData = [];
    let currentFilter = 'all';
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    let currentPerPage = 5;
    let currentUserRole = <?php echo $user_role_int; ?>;
    let currentPersonnelId = <?php echo $current_personnel_id; ?>;
    
    let personnelCurrentPage = 1;
    let personnelTotalPages = 1;
    let personnelTotalRecords = 0;
    let personnelPerPage = 10;
    let personnelSearchTerm = '';
    
    async function loadDataFromDatabase(page = 1) {
        try {
            currentPage = page;
            const formData = new FormData();
            formData.append('action', 'get_all');
            formData.append('filter', currentFilter);
            formData.append('search', document.getElementById('searchInput')?.value || '');
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
                leaveData = result.data || [];
                totalPages = result.pagination.total_pages;
                totalRecords = result.pagination.total_records;
                renderLeaveTable();
                renderPaginationUI();
                loadStatistics();
                loadMyBalance();
            } else {
                showToast(result.error || 'Failed to load data', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error loading data', 'error');
        }
    }
    
    async function loadPersonnelBalances(page = 1) {
        if (currentUserRole < 1) return;
        
        try {
            personnelCurrentPage = page;
            personnelSearchTerm = document.getElementById('personnelSearch')?.value || '';
            const formData = new FormData();
            formData.append('action', 'get_all_personnel_balances');
            formData.append('search', personnelSearchTerm);
            formData.append('page', personnelCurrentPage);
            formData.append('per_page', personnelPerPage);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                renderPersonnelTable(result.data);
                personnelTotalPages = result.pagination.total_pages;
                personnelTotalRecords = result.pagination.total_records;
                renderPersonnelPaginationUI();
            }
        } catch (error) {
            console.error('Error loading personnel:', error);
        }
    }
    
    function renderPersonnelPaginationUI() {
        const container = document.getElementById('personnelPaginationContainer');
        if (!container) return;
        
        if (personnelTotalPages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'flex';
        const start = (personnelCurrentPage - 1) * personnelPerPage + 1;
        const end = Math.min(personnelCurrentPage * personnelPerPage, personnelTotalRecords);
        
        let html = `<div class="pagination-info">Showing ${start} to ${end} of ${personnelTotalRecords} personnel</div><div class="pagination">`;
        
        if (personnelCurrentPage > 1) {
            html += `<button onclick="loadPersonnelBalances(1)" class="pagination-btn"><i class="fas fa-angle-double-left"></i></button>`;
            html += `<button onclick="loadPersonnelBalances(${personnelCurrentPage - 1})" class="pagination-btn"><i class="fas fa-angle-left"></i></button>`;
        }
        
        for (let i = Math.max(1, personnelCurrentPage - 2); i <= Math.min(personnelTotalPages, personnelCurrentPage + 2); i++) {
            html += `<button onclick="loadPersonnelBalances(${i})" class="page-number ${i === personnelCurrentPage ? 'active' : ''}">${i}</button>`;
        }
        
        if (personnelCurrentPage < personnelTotalPages) {
            html += `<button onclick="loadPersonnelBalances(${personnelCurrentPage + 1})" class="pagination-btn"><i class="fas fa-angle-right"></i></button>`;
            html += `<button onclick="loadPersonnelBalances(${personnelTotalPages})" class="pagination-btn"><i class="fas fa-angle-double-right"></i></button>`;
        }
        
        html += `</div>`;
        container.innerHTML = html;
    }
    
    function renderPersonnelTable(personnel) {
        const tbody = document.getElementById('personnelTableBody');
        if (!tbody) return;
        
        if (!personnel || personnel.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">No personnel found. Try a different search term.</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        
        personnel.forEach((p, i) => {
            const row = tbody.insertRow();
            const serialNumber = (personnelCurrentPage - 1) * personnelPerPage + i + 1;
            row.innerHTML = `
                <td>${serialNumber}</td>
                <td><strong>${escapeHtml(p.personnel_name)}</strong></td>
                <td>${escapeHtml(p.rank)}</span></td>
                <td><span class="balance-badge" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:6px 14px; border-radius:20px;">${p.gharpari_bida_days} days</span></td>
                <td><span class="balance-badge" style="background:linear-gradient(135deg,#f093fb,#f5576c); color:white; padding:6px 14px; border-radius:20px;">${p.parba_bida_days} days</span></td>
                <td><span class="balance-badge" style="background:linear-gradient(135deg,#4facfe,#00f2fe); color:white; padding:6px 14px; border-radius:20px;">${p.bhaeepari_bida_days} days</span></td>
                <td>${new Date(p.last_updated).toLocaleString()}</td>
                <td><button class="action-btn-small edit-btn" onclick="openEditPersonnel(${p.personnel_id}, '${escapeHtml(p.personnel_name)}', ${p.gharpari_bida_days}, ${p.parba_bida_days}, ${p.bhaeepari_bida_days})"><i class="fas fa-edit"></i> Edit</button></td>
            `;
        });
    }
    
    function renderPaginationUI() {
        const container = document.getElementById('paginationContainer');
        if (!container) return;
        
        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'flex';
        const start = (currentPage - 1) * currentPerPage + 1;
        const end = Math.min(currentPage * currentPerPage, totalRecords);
        
        let html = `<div class="pagination-info">Showing ${start} to ${end} of ${totalRecords} entries</div><div class="pagination">`;
        
        if (currentPage > 1) {
            html += `<button onclick="loadDataFromDatabase(1)" class="pagination-btn"><i class="fas fa-angle-double-left"></i></button>`;
            html += `<button onclick="loadDataFromDatabase(${currentPage - 1})" class="pagination-btn"><i class="fas fa-angle-left"></i></button>`;
        }
        
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            html += `<button onclick="loadDataFromDatabase(${i})" class="page-number ${i === currentPage ? 'active' : ''}">${i}</button>`;
        }
        
        if (currentPage < totalPages) {
            html += `<button onclick="loadDataFromDatabase(${currentPage + 1})" class="pagination-btn"><i class="fas fa-angle-right"></i></button>`;
            html += `<button onclick="loadDataFromDatabase(${totalPages})" class="pagination-btn"><i class="fas fa-angle-double-right"></i></button>`;
        }
        
        html += `</div>`;
        container.innerHTML = html;
    }
    
    function renderLeaveTable() {
        const tbody = document.getElementById('leaveTableBody');
        if (!tbody) return;
        
        if (!leaveData || leaveData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="13" style="text-align:center">No leave requests found</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        
        leaveData.forEach((leave, idx) => {
            let balance = 0;
            if (leave.leave_type === 'gharpari_bida') balance = leave.gharpari_bida_days || 0;
            else if (leave.leave_type === 'parba_bida') balance = leave.parba_bida_days || 0;
            else if (leave.leave_type === 'bhaeepari_bida') balance = leave.bhaeepari_bida_days || 0;
            
            let statusClass = '', statusIcon = '';
            if (leave.status === 'pending') { statusClass = 'status-pending'; statusIcon = '<i class="fas fa-clock"></i>'; }
            else if (leave.status === 'forwarded') { statusClass = 'status-forwarded'; statusIcon = '<i class="fas fa-share"></i>'; }
            else if (leave.status === 'approved') { statusClass = 'status-approved'; statusIcon = '<i class="fas fa-check-circle"></i>'; }
            else if (leave.status === 'rejected') { statusClass = 'status-rejected'; statusIcon = '<i class="fas fa-times-circle"></i>'; }
            else { statusClass = 'status-cancelled'; statusIcon = '<i class="fas fa-ban"></i>'; }
            
            let balanceClass = balance >= 10 ? 'balance-good' : (balance >= 3 ? 'balance-low' : 'balance-critical');
            
            let actionBtns = `<div class="action-buttons">
                <button class="action-btn-small btn-view" onclick="viewDetails(${leave.id})"><i class="fas fa-eye"></i> View</button>`;
            
            if (currentUserRole === 1 && leave.status === 'pending') {
                actionBtns += `<button class="action-btn-small btn-approve" onclick="processLeave(${leave.id}, 'approve')"><i class="fas fa-check"></i> Approve</button>
                              <button class="action-btn-small btn-reject" onclick="processLeave(${leave.id}, 'reject')"><i class="fas fa-times"></i> Reject</button>
                              <button class="action-btn-small btn-forward" onclick="forwardLeave(${leave.id})"><i class="fas fa-share"></i> Forward</button>`;
            }
            
            if (currentUserRole === 2 && leave.status === 'forwarded') {
                actionBtns += `<button class="action-btn-small btn-approve" onclick="processLeave(${leave.id}, 'approve')"><i class="fas fa-check"></i> Approve</button>
                              <button class="action-btn-small btn-reject" onclick="processLeave(${leave.id}, 'reject')"><i class="fas fa-times"></i> Reject</button>`;
            }
            
            if ((currentUserRole === 0 && leave.personnel_id == currentPersonnelId && leave.status === 'pending') || 
                (currentUserRole === 1 && leave.status === 'pending')) {
                actionBtns += `<button class="action-btn-small btn-cancel-action" onclick="cancelLeave(${leave.id})"><i class="fas fa-ban"></i> Cancel</button>`;
            }
            
            actionBtns += `</div>`;
            
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>${idx + 1 + ((currentPage - 1) * currentPerPage)}</td>
                <td><strong>${escapeHtml(leave.personnel_name)}</strong></td>
                <td>${escapeHtml(leave.rank)}</td>
                <td>${leave.leave_type === 'gharpari_bida' ? '🏠 Gharpari Bida' : (leave.leave_type === 'parba_bida' ? '🎉 Parba Bida' : '🤝 Bhaeepari Bida')}</td>
                <td>${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</td>
                <td><strong>${leave.leave_days}</strong> days</td>
                <td>${escapeHtml(leave.reason.substring(0, 40))}${leave.reason.length > 40 ? '...' : ''}</td>
                <td><span class="status-badge ${statusClass}">${statusIcon} ${leave.status.charAt(0).toUpperCase() + leave.status.slice(1)}</span></td>
                <td><span class="balance-badge ${balanceClass}">${balance} days</span></td>
                <td>${leave.initiating_officer_name ? '<span class="officer-badge"><i class="fas fa-user-shield"></i> ' + escapeHtml(leave.initiating_officer_name) + '</span>' : '-'}</td>
                <td>${leave.accepting_officer_name ? '<span class="officer-badge"><i class="fas fa-user-check"></i> ' + escapeHtml(leave.accepting_officer_name) + '</span>' : '-'}</td>
                <td>${new Date(leave.created_at).toLocaleDateString()}</td>
                <td>${actionBtns}</td>
            `;
        });
    }
    
    function openEditPersonnel(id, name, g, p, b) {
        document.getElementById('editPersonnelId').value = id;
        document.getElementById('editPersonnelName').textContent = name;
        document.getElementById('editGharpari').value = g;
        document.getElementById('editParba').value = p;
        document.getElementById('editBhaeepari').value = b;
        document.getElementById('editPersonnelModal').style.display = 'block';
    }
    
    async function loadGlobalSettings() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_leave_settings');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                document.getElementById('globalGharpari').value = result.data.gharpari_bida_default;
                document.getElementById('globalParba').value = result.data.parba_bida_default;
                document.getElementById('globalBhaeepari').value = result.data.bhaeepari_bida_default;
            }
        } catch (e) { console.error(e); }
    }
    
    async function applyGlobalToAll() {
        if (!confirm('⚠️ WARNING: This will overwrite ALL personnel leave balances with the global default values. Are you sure you want to continue?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'apply_global_settings');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Global settings applied to all personnel successfully', 'success');
                loadPersonnelBalances(1);
                loadMyBalance();
            } else {
                showToast(result.error || 'Failed to apply global settings', 'error');
            }
        } catch (e) { showToast('Error applying settings', 'error'); }
    }
    
    async function loadMyBalance() {
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
            
            if (result.success && result.data) {
                document.getElementById('gharpariBalance').textContent = result.data.gharpari_bida_days || 0;
                document.getElementById('parbaBalance').textContent = result.data.parba_bida_days || 0;
                document.getElementById('bhaeepariBalance').textContent = result.data.bhaeepari_bida_days || 0;
                
                const gPercent = Math.min(100, ((result.data.gharpari_bida_days || 0) / 15) * 100);
                const pPercent = Math.min(100, ((result.data.parba_bida_days || 0) / 12) * 100);
                const bPercent = Math.min(100, ((result.data.bhaeepari_bida_days || 0) / 10) * 100);
                
                document.getElementById('gharpariProgress').style.width = gPercent + '%';
                document.getElementById('parbaProgress').style.width = pPercent + '%';
                document.getElementById('bhaeepariProgress').style.width = bPercent + '%';
            }
        } catch (e) { console.error(e); }
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
            
            if (result.success && result.data) {
                document.getElementById('pendingCount').textContent = result.data.pending || 0;
                document.getElementById('forwardedCount').textContent = result.data.forwarded || 0;
                document.getElementById('approvedToday').textContent = result.data.approved_today || 0;
                document.getElementById('onLeaveToday').textContent = result.data.on_leave_today || 0;
            }
        } catch (e) { console.error(e); }
    }
    
    async function getLeaveBalance(personnelId, leaveType) {
        try {
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
            
            if (result.success && result.data) {
                if (leaveType === 'gharpari_bida') return result.data.gharpari_bida_days || 0;
                if (leaveType === 'parba_bida') return result.data.parba_bida_days || 0;
                if (leaveType === 'bhaeepari_bida') return result.data.bhaeepari_bida_days || 0;
            }
            return 0;
        } catch (e) { return 0; }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        toastMessage.textContent = message;
        toast.style.backgroundColor = type === 'success' ? '#1e3a32' : '#dc2626';
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 4000);
    }
    
    function calculateDays() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('totalDays').value = days;
            checkBalanceAndDisplay();
        }
    }
    
    async function checkBalanceAndDisplay() {
        const personnelId = document.getElementById('personnelId').value;
        const leaveType = document.getElementById('leaveType').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (personnelId && leaveType && startDate && endDate) {
            const balance = await getLeaveBalance(personnelId, leaveType);
            const start = new Date(startDate);
            const end = new Date(endDate);
            const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            
            const balanceInput = document.getElementById('availableBalance');
            balanceInput.value = `${balance} days available`;
            
            if (days > balance) {
                balanceInput.style.color = '#dc2626';
                balanceInput.style.fontWeight = 'bold';
                balanceInput.style.background = '#fee2e2';
                showToast(`⚠️ Warning: Requesting ${days} days but only ${balance} days available!`, 'error');
            } else {
                balanceInput.style.color = '#065f46';
                balanceInput.style.fontWeight = 'normal';
                balanceInput.style.background = '#d1fae5';
            }
        }
    }
    
    async function viewDetails(id) {
        const leave = leaveData.find(l => l.id == id);
        if (!leave) return;
        
        let balance = 0;
        if (leave.leave_type === 'gharpari_bida') balance = leave.gharpari_bida_days || 0;
        else if (leave.leave_type === 'parba_bida') balance = leave.parba_bida_days || 0;
        else if (leave.leave_type === 'bhaeepari_bida') balance = leave.bhaeepari_bida_days || 0;
        
        let balanceClass = balance >= 10 ? 'balance-good' : (balance >= 3 ? 'balance-low' : 'balance-critical');
        
        document.getElementById('detailsContent').innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Personnel Name</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${escapeHtml(leave.personnel_name)}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Rank</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${escapeHtml(leave.rank)}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Leave Type</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${leave.leave_type === 'gharpari_bida' ? '🏠 Gharpari Bida' : (leave.leave_type === 'parba_bida' ? '🎉 Parba Bida' : '🤝 Bhaeepari Bida')}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Leave Days</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${leave.leave_days} days</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Remaining Balance</div>
                    <div style="font-size: 15px; font-weight: 600;"><span class="balance-badge ${balanceClass}">${balance} days left</span></div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Period</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Initiating Officer</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${escapeHtml(leave.initiating_officer_name) || '-'}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Accepting Officer</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${escapeHtml(leave.accepting_officer_name) || '-'}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Status</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${leave.status.toUpperCase()}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Submitted On</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${new Date(leave.created_at).toLocaleString()}</div>
                </div>
                ${leave.forwarded_at ? `<div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Forwarded On</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${new Date(leave.forwarded_at).toLocaleString()}</div>
                </div>` : ''}
                ${leave.forward_remarks ? `<div style="padding: 12px; background: #f8fafc; border-radius: 10px; grid-column: span 2;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Forward Remarks</div>
                    <div style="font-size: 14px; color: #1a2c3e; margin-top: 5px;">${escapeHtml(leave.forward_remarks)}</div>
                </div>` : ''}
                ${leave.approved_at ? `<div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Processed On</div>
                    <div style="font-size: 15px; font-weight: 600; color: #1a2c3e;">${new Date(leave.approved_at).toLocaleString()}</div>
                </div>` : ''}
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px; grid-column: span 2;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Reason</div>
                    <div style="font-size: 14px; color: #1a2c3e; margin-top: 5px;">${escapeHtml(leave.reason)}</div>
                </div>
                ${leave.approver_remarks ? `<div style="padding: 12px; background: #f8fafc; border-radius: 10px; grid-column: span 2;">
                    <div style="font-size: 11px; color: #6c7a8e; text-transform: uppercase;">Remarks</div>
                    <div style="font-size: 14px; color: #1a2c3e; margin-top: 5px;">${escapeHtml(leave.approver_remarks)}</div>
                </div>` : ''}
            </div>
        `;
        
        document.getElementById('detailsModal').style.display = 'block';
    }
    
    function processLeave(id, action) {
        document.getElementById('actionLeaveId').value = id;
        document.getElementById('actionType').value = action;
        document.getElementById('actionModalTitle').innerHTML = action === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request';
        document.getElementById('actionLabel').innerHTML = action === 'approve' ? 'Approver Remarks' : 'Rejection Reason <span class="required-star">*</span>';
        document.getElementById('actionRemarks').value = '';
        document.getElementById('actionModal').style.display = 'block';
    }
    
    function forwardLeave(id) {
        document.getElementById('forwardLeaveId').value = id;
        document.getElementById('forwardRemarks').value = '';
        document.getElementById('forwardModal').style.display = 'block';
    }
    
    async function cancelLeave(id) {
        if (!confirm('Are you sure you want to cancel this leave request?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'cancel_leave');
            formData.append('id', id);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Leave request cancelled successfully', 'success');
                loadDataFromDatabase(1);
                loadMyBalance();
            } else {
                showToast(result.error || 'Failed to cancel leave request', 'error');
            }
        } catch (e) { showToast('Error cancelling leave request', 'error'); }
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
        
        loadDataFromDatabase(1);
    }
    
    function searchTable() {
        currentPage = 1;
        loadDataFromDatabase(1);
        const clearBtn = document.getElementById('clearSearch');
        const searchInput = document.getElementById('searchInput');
        if (clearBtn && searchInput) {
            clearBtn.style.display = searchInput.value.trim() !== '' ? 'block' : 'none';
        }
    }
    
    function clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            searchTable();
            searchInput.focus();
        }
    }
    
    // Event Listeners
    document.getElementById('recordsPerPage')?.addEventListener('change', function() {
        currentPerPage = parseInt(this.value);
        currentPage = 1;
        loadDataFromDatabase(1);
    });
    
    document.getElementById('personnelRecordsPerPage')?.addEventListener('change', function() {
        personnelPerPage = parseInt(this.value);
        personnelCurrentPage = 1;
        loadPersonnelBalances(1);
    });
    
    document.getElementById('personnelSearch')?.addEventListener('input', function() {
        personnelCurrentPage = 1;
        loadPersonnelBalances(1);
        const clearBtn = document.getElementById('clearPersonnelSearch');
        if (clearBtn) clearBtn.style.display = this.value.trim() !== '' ? 'block' : 'none';
    });
    
    document.getElementById('clearPersonnelSearch')?.addEventListener('click', function() {
        const searchInput = document.getElementById('personnelSearch');
        if (searchInput) {
            searchInput.value = '';
            loadPersonnelBalances(1);
            searchInput.focus();
            this.style.display = 'none';
        }
    });
    
    document.getElementById('newLeaveBtn')?.addEventListener('click', () => {
        document.getElementById('leaveForm').reset();
        document.getElementById('totalDays').value = '';
        document.getElementById('availableBalance').value = '';
        document.getElementById('leaveModal').style.display = 'block';
    });
    
    document.getElementById('openGlobalSettingsBtn')?.addEventListener('click', () => {
        loadGlobalSettings();
        document.getElementById('globalSettingsModal').style.display = 'block';
    });
    
    document.getElementById('applyGlobalBtn')?.addEventListener('click', applyGlobalToAll);
    
    document.querySelectorAll('.close, .close-action, .close-details, .close-edit, .close-global, .close-forward').forEach(btn => {
        btn.onclick = () => {
            document.getElementById('leaveModal').style.display = 'none';
            document.getElementById('actionModal').style.display = 'none';
            document.getElementById('detailsModal').style.display = 'none';
            document.getElementById('editPersonnelModal').style.display = 'none';
            document.getElementById('globalSettingsModal').style.display = 'none';
            document.getElementById('forwardModal').style.display = 'none';
        };
    });
    
    document.querySelectorAll('#cancelBtn, #cancelActionBtn, #cancelEditBtn, #cancelGlobalBtn, #cancelForwardBtn').forEach(btn => {
        btn.onclick = () => {
            document.getElementById('leaveModal').style.display = 'none';
            document.getElementById('actionModal').style.display = 'none';
            document.getElementById('editPersonnelModal').style.display = 'none';
            document.getElementById('globalSettingsModal').style.display = 'none';
            document.getElementById('forwardModal').style.display = 'none';
        };
    });
    
    window.onclick = (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };
    
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const personnelSelect = document.getElementById('personnelId');
    const leaveTypeSelect = document.getElementById('leaveType');
    
    if (startDateInput) {
        startDateInput.min = new Date().toISOString().split('T')[0];
        startDateInput.addEventListener('change', function() {
            if (endDateInput) endDateInput.min = this.value;
            calculateDays();
        });
    }
    
    if (endDateInput) {
        endDateInput.addEventListener('change', calculateDays);
    }
    
    if (personnelSelect) {
        personnelSelect.addEventListener('change', () => {
            if (leaveTypeSelect && leaveTypeSelect.value) checkBalanceAndDisplay();
        });
    }
    
    if (leaveTypeSelect) {
        leaveTypeSelect.addEventListener('change', () => {
            if (personnelSelect && personnelSelect.value) checkBalanceAndDisplay();
        });
    }
    
    document.getElementById('leaveForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const personnelId = document.getElementById('personnelId').value;
        const leaveType = document.getElementById('leaveType').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const reason = document.getElementById('reason').value;
        const initiatingOfficer = document.getElementById('initiatingOfficer').value;
        const acceptingOfficer = document.getElementById('acceptingOfficer').value;
        
        if (!personnelId || !leaveType || !startDate || !endDate || !reason) {
            showToast('Please fill all required fields', 'error');
            return;
        }
        
        if (!initiatingOfficer || !acceptingOfficer) {
            showToast('Please select both initiating and accepting officers', 'error');
            return;
        }
        
        const balance = await getLeaveBalance(personnelId, leaveType);
        const start = new Date(startDate);
        const end = new Date(endDate);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        
        if (days > balance) {
            showToast(`❌ Insufficient balance! You have ${balance} days available but requested ${days} days.`, 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'submit_leave');
        formData.append('personnel_id', personnelId);
        formData.append('leave_type', leaveType);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);
        formData.append('reason', reason);
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
                showToast('✅ Leave request submitted successfully', 'success');
                document.getElementById('leaveModal').style.display = 'none';
                loadDataFromDatabase(1);
                loadMyBalance();
            } else {
                showToast(result.error || 'Failed to submit leave request', 'error');
            }
        } catch (e) { showToast('Error submitting leave request', 'error'); }
    });
    
    document.getElementById('editPersonnelForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'update_user_balance');
        formData.append('personnel_id', document.getElementById('editPersonnelId').value);
        formData.append('gharpari_days', document.getElementById('editGharpari').value);
        formData.append('parba_days', document.getElementById('editParba').value);
        formData.append('bhaeepari_days', document.getElementById('editBhaeepari').value);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Leave limits updated successfully', 'success');
                document.getElementById('editPersonnelModal').style.display = 'none';
                loadPersonnelBalances(personnelCurrentPage);
                loadMyBalance();
            } else {
                showToast(result.error || 'Failed to update balance', 'error');
            }
        } catch (e) { showToast('Error updating balance', 'error'); }
    });
    
    document.getElementById('globalSettingsForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'update_leave_settings');
        formData.append('gharpari_bida', document.getElementById('globalGharpari').value);
        formData.append('parba_bida', document.getElementById('globalParba').value);
        formData.append('bhaeepari_bida', document.getElementById('globalBhaeepari').value);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Global settings updated successfully', 'success');
                document.getElementById('globalSettingsModal').style.display = 'none';
            } else {
                showToast(result.error || 'Failed to update settings', 'error');
            }
        } catch (e) { showToast('Error updating settings', 'error'); }
    });
    
    document.getElementById('forwardForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('forwardLeaveId').value;
        const remarks = document.getElementById('forwardRemarks').value;
        
        if (!remarks) {
            showToast('Please provide forward remarks', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'forward_leave');
        formData.append('id', id);
        formData.append('forward_remarks', remarks);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Leave request forwarded to superadmin successfully', 'success');
                document.getElementById('forwardModal').style.display = 'none';
                loadDataFromDatabase(1);
            } else {
                showToast(result.error || 'Failed to forward leave request', 'error');
            }
        } catch (e) { showToast('Error forwarding leave request', 'error'); }
    });
    
    document.getElementById('actionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('actionLeaveId').value;
        const action = document.getElementById('actionType').value;
        const remarks = document.getElementById('actionRemarks').value;
        
        if (action === 'reject' && !remarks) {
            showToast('Please provide rejection reason', 'error');
            return;
        }
        
        const endpoint = action === 'approve' ? 'approve_leave' : 'reject_leave';
        
        const formData = new FormData();
        formData.append('action', endpoint);
        formData.append('id', id);
        formData.append('approver_remarks', remarks);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.success) {
                showToast(`✅ Leave ${action}d successfully`, 'success');
                document.getElementById('actionModal').style.display = 'none';
                loadDataFromDatabase(1);
                loadMyBalance();
            } else {
                showToast(result.error || `Failed to ${action} leave request`, 'error');
            }
        } catch (e) { showToast(`Error processing leave request`, 'error'); }
    });
    
    document.getElementById('exportBtn')?.addEventListener('click', function() {
        if (!leaveData || leaveData.length === 0) {
            showToast('No data to export', 'error');
            return;
        }
        
        const rows = [['S.N.', 'Personnel Name', 'Rank', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Reason', 'Status', 'Initiating Officer', 'Accepting Officer', 'Submitted Date', 'Balance Left']];
        
        leaveData.forEach((leave, index) => {
            let balance = 0;
            if (leave.leave_type === 'gharpari_bida') balance = leave.gharpari_bida_days || 0;
            else if (leave.leave_type === 'parba_bida') balance = leave.parba_bida_days || 0;
            else if (leave.leave_type === 'bhaeepari_bida') balance = leave.bhaeepari_bida_days || 0;
            
            rows.push([
                index + 1 + ((currentPage - 1) * currentPerPage),
                leave.personnel_name,
                leave.rank,
                leave.leave_type,
                leave.start_date,
                leave.end_date,
                leave.leave_days,
                leave.reason,
                leave.status,
                leave.initiating_officer_name || '',
                leave.accepting_officer_name || '',
                leave.created_at,
                balance
            ]);
        });
        
        const csvContent = rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `leave_requests_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('✅ Leave data exported successfully', 'success');
    });
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            filterByStatus(this.getAttribute('data-filter'));
        });
    });
    
    document.getElementById('searchInput')?.addEventListener('input', function() {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(searchTable, 500);
        const clearBtn = document.getElementById('clearSearch');
        if (clearBtn) clearBtn.style.display = this.value.trim() !== '' ? 'block' : 'none';
    });
    
    document.getElementById('clearSearch')?.addEventListener('click', clearSearch);
    
    document.addEventListener('DOMContentLoaded', () => {
        loadDataFromDatabase(1);
        if (currentUserRole >= 1) loadPersonnelBalances(1);
        
        setInterval(() => {
            loadDataFromDatabase(currentPage);
            loadMyBalance();
            if (currentUserRole >= 1) loadPersonnelBalances(personnelCurrentPage);
        }, 60000);
    });
</script>

</body>
</html>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>