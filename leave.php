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

// Get current user's personnel ID from session
$current_personnel_id = isset($_SESSION['user_personnel_id']) ? (int)$_SESSION['user_personnel_id'] : 0;

// Database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get current user's personnel ID from personnel table
if ($current_personnel_id == 0 && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM military_personnel_status WHERE personnel_number = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($personnel) {
        $_SESSION['user_personnel_id'] = $personnel['id'];
        $current_personnel_id = $personnel['id'];
    }
}

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

// Helper functions
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
            $search = $_POST['search'] ?? '';
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 5;
            $offset = ($page - 1) * $per_page;
            
            $sql = "SELECT lr.*, 
                           mps.personnel_name, mps.rank,
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
        
        // Get pending requests for initiating officer
        if ($action === 'get_initiating_pending') {
            $sql = "SELECT lr.*, 
                           mps.personnel_name, mps.rank,
                           lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days,
                           io.personnel_name as initiating_officer_name,
                           ao.personnel_name as accepting_officer_name
                    FROM leave_requests lr
                    INNER JOIN military_personnel_status mps ON lr.personnel_id = mps.id
                    LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
                    LEFT JOIN military_personnel_status io ON lr.initiating_officer = io.id
                    LEFT JOIN military_personnel_status ao ON lr.accepting_officer = ao.id
                    WHERE lr.initiating_officer = ? 
                    AND (lr.initiating_officer_approved = 0 OR lr.initiating_officer_approved IS NULL)
                    AND lr.status = 'pending'
                    ORDER BY lr.created_at ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_personnel_id]);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $leaves,
                'officer_type' => 'initiating'
            ]);
            exit;
        }
        
        // Get pending requests for accepting officer - FIXED QUERY
        if ($action === 'get_accepting_pending') {
            $sql = "SELECT lr.*, 
                           mps.personnel_name, mps.rank,
                           lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days,
                           io.personnel_name as initiating_officer_name,
                           ao.personnel_name as accepting_officer_name
                    FROM leave_requests lr
                    INNER JOIN military_personnel_status mps ON lr.personnel_id = mps.id
                    LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
                    LEFT JOIN military_personnel_status io ON lr.initiating_officer = io.id
                    LEFT JOIN military_personnel_status ao ON lr.accepting_officer = ao.id
                    WHERE lr.accepting_officer = ? 
                    AND lr.initiating_officer_approved = 1 
                    AND (lr.accepting_officer_approved = 0 OR lr.accepting_officer_approved IS NULL)
                    AND lr.status = 'initiating_approved'
                    ORDER BY lr.initiating_officer_approved_at ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_personnel_id]);
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
                AND status IN ('pending', 'initiating_approved', 'approved')
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
                (personnel_id, leave_type, start_date, end_date, leave_days, reason, status, created_by, 
                 initiating_officer, accepting_officer, initiating_officer_approved, accepting_officer_approved) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, 0, 0)
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
        
        // Initiating Officer approves (first level approval)
        if ($action === 'initiating_officer_approve') {
            if ($current_personnel_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            $stmt = $pdo->prepare("
                SELECT initiating_officer, initiating_officer_approved, status, accepting_officer
                FROM leave_requests WHERE id = ?
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['initiating_officer'] != $current_personnel_id) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to approve this request']);
                exit;
            }
            
            if ($leave['initiating_officer_approved'] == 1) {
                echo json_encode(['success' => false, 'error' => 'Request already approved by initiating officer']);
                exit;
            }
            
            if ($leave['status'] != 'pending') {
                echo json_encode(['success' => false, 'error' => 'Request cannot be approved at this stage']);
                exit;
            }
            
            // Update the request - set status to 'initiating_approved'
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET initiating_officer_approved = 1,
                    initiating_officer_approved_by = ?,
                    initiating_officer_approved_at = NOW(),
                    initiating_officer_remarks = ?,
                    status = 'initiating_approved'
                WHERE id = ?
            ");
            $result = $stmt->execute([$current_personnel_id, $remarks, $id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Request forwarded to accepting officer']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve request']);
            }
            exit;
        }
        
        // Initiating Officer rejects
        if ($action === 'initiating_officer_reject') {
            if ($current_personnel_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (empty($remarks)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT initiating_officer, status 
                FROM leave_requests WHERE id = ?
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['initiating_officer'] != $current_personnel_id) {
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
            $result = $stmt->execute([$remarks, $current_personnel_id, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Accepting Officer approves and finalizes (second level approval)
        if ($action === 'accepting_officer_approve') {
            if ($current_personnel_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            $stmt = $pdo->prepare("
                SELECT personnel_id, leave_type, leave_days, accepting_officer, 
                       accepting_officer_approved, initiating_officer_approved, status 
                FROM leave_requests WHERE id = ?
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['accepting_officer'] != $current_personnel_id) {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to approve this request']);
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
                    accepting_officer_approved_by = ?,
                    accepting_officer_approved_at = NOW(),
                    accepting_officer_remarks = ?,
                    status = 'approved',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$current_personnel_id, $remarks, $current_personnel_id, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Accepting Officer rejects
        if ($action === 'accepting_officer_reject') {
            if ($current_personnel_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (empty($remarks)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT accepting_officer, status 
                FROM leave_requests WHERE id = ?
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found']);
                exit;
            }
            
            if ($leave['accepting_officer'] != $current_personnel_id) {
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
            $result = $stmt->execute([$remarks, $current_personnel_id, $id]);
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Get statistics
        if ($action === 'get_stats') {
            $stats = [];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE initiating_officer = ? AND (initiating_officer_approved = 0 OR initiating_officer_approved IS NULL) AND status = 'pending'");
            $stmt->execute([$current_personnel_id]);
            $stats['initiating_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE accepting_officer = ? AND initiating_officer_approved = 1 AND (accepting_officer_approved = 0 OR accepting_officer_approved IS NULL) AND status = 'initiating_approved'");
            $stmt->execute([$current_personnel_id]);
            $stats['accepting_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved'");
            $stmt->execute();
            $stats['total_approved'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            exit;
        }
        
        // Get my balance
        if ($action === 'get_my_balance') {
            $balance = getLeaveBalance($pdo, $current_personnel_id);
            echo json_encode(['success' => true, 'data' => $balance]);
            exit;
        }
        
        // Get leave balance for a personnel
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
        
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
        
    } catch (Exception $e) {
        error_log("Leave.php AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Get current user's leave balance
$myBalance = getLeaveBalance($pdo, $current_personnel_id);

// Get statistics for initial display
$initiatingPending = 0;
$acceptingPending = 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE initiating_officer = ? AND (initiating_officer_approved = 0 OR initiating_officer_approved IS NULL) AND status = 'pending'");
$stmt->execute([$current_personnel_id]);
$initiatingPending = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE accepting_officer = ? AND initiating_officer_approved = 1 AND (accepting_officer_approved = 0 OR accepting_officer_approved IS NULL) AND status = 'initiating_approved'");
$stmt->execute([$current_personnel_id]);
$acceptingPending = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get officers for dropdown
$officers = [];
try {
    $stmt = $pdo->query("SELECT id, personnel_name, rank FROM military_personnel_status WHERE rank IN ('Officer', 'Major', 'Captain', 'Colonel', 'Lieutenant') OR is_officer = 1 ORDER BY personnel_name");
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If is_officer column doesn't exist, just use rank filter
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
    <title>Leave Management System - Two Level Approval</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #1a2c3e; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: all 0.3s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
        .stat-card.initiating { background: linear-gradient(135deg, #92400e, #b45309); color: white; }
        .stat-card.accepting { background: linear-gradient(135deg, #1e40af, #1e3a8a); color: white; }
        .stat-card.approved { background: linear-gradient(135deg, #065f46, #047857); color: white; }
        .stat-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .stat-value { font-size: 32px; font-weight: 800; line-height: 1.2; }
        .stat-label { font-size: 13px; margin-top: 5px; opacity: 0.9; }
        
        .balance-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .balance-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid; }
        .balance-card:nth-child(1) { border-left-color: #667eea; }
        .balance-card:nth-child(2) { border-left-color: #f093fb; }
        .balance-card:nth-child(3) { border-left-color: #4facfe; }
        .balance-card h4 { font-size: 14px; color: #6c7a8e; margin-bottom: 10px; }
        .balance-card .days { font-size: 32px; font-weight: 700; color: #1a2c3e; }
        .balance-card .progress-bar { height: 8px; background: #e2e8f0; border-radius: 4px; margin-top: 12px; overflow: hidden; }
        .balance-card .progress-fill { height: 100%; border-radius: 4px; transition: width 0.5s; }
        .balance-card:nth-child(1) .progress-fill { background: #667eea; }
        .balance-card:nth-child(2) .progress-fill { background: #f093fb; }
        .balance-card:nth-child(3) .progress-fill { background: #4facfe; }
        
        .workflow-steps { display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; background: white; padding: 20px; border-radius: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .workflow-step { text-align: center; flex: 1; min-width: 150px; }
        .step-icon { width: 50px; height: 50px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 20px; color: #6c7a8e; }
        .workflow-step.active .step-icon { background: #1e3a32; color: white; }
        .workflow-step.completed .step-icon { background: #065f46; color: white; }
        .step-label { font-size: 12px; font-weight: 600; color: #6c7a8e; }
        .workflow-step.active .step-label { color: #1e3a32; }
        .workflow-step.completed .step-label { color: #065f46; }
        .step-arrow { font-size: 24px; color: #cbd5e1; }
        
        .officer-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .officer-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .officer-card-header { padding: 15px 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .officer-card-header.initiating { background: #fef3c7; color: #92400e; border-bottom: 2px solid #fde68a; }
        .officer-card-header.accepting { background: #e0e7ff; color: #3730a3; border-bottom: 2px solid #c7d2fe; }
        .officer-card-body { padding: 15px 20px; max-height: 400px; overflow-y: auto; }
        .pending-item { padding: 12px; border-bottom: 1px solid #eef2f6; }
        .pending-item:last-child { border-bottom: none; }
        .pending-item-title { font-weight: 600; margin-bottom: 5px; }
        .pending-item-details { font-size: 12px; color: #6c7a8e; display: flex; gap: 15px; flex-wrap: wrap; }
        .btn-process { margin-top: 8px; padding: 5px 12px; background: #1e3a32; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; margin-right: 8px; }
        .btn-process:hover { background: #14362c; }
        .btn-process-reject { background: #dc2626; }
        .btn-process-reject:hover { background: #b91c1c; }
        .empty-state { text-align: center; padding: 30px; color: #6c7a8e; }
        
        .filter-section { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-btn { padding: 8px 20px; border: 1.5px solid #e2e8f0; background: white; border-radius: 30px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .filter-btn:hover { background: #f1f5f9; border-color: #2c5f4e; }
        .filter-btn.active { background: #1e3a32; color: white; border-color: #1e3a32; }
        
        .search-section { display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
        .search-bar { position: relative; flex: 1; max-width: 350px; }
        .search-bar input { width: 100%; padding: 12px 40px 12px 42px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 14px; outline: none; }
        .search-bar input:focus { border-color: #2c5f4e; }
        .search-bar i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9aa9bc; }
        .clear-search { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9aa9bc; }
        
        .btn-add { padding: 10px 22px; background: #1e3a32; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add:hover { background: #14362c; }
        
        .data-table { overflow-x: auto; background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th { text-align: left; padding: 14px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-weight: 600; font-size: 13px; }
        td { padding: 14px 12px; border-bottom: 1px solid #eef2f6; font-size: 13px; }
        
        .status-badge { padding: 5px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-initiated { background: #e0e7ff; color: #3730a3; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        .badge-officer { background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-btn { padding: 5px 12px; border: none; border-radius: 8px; cursor: pointer; font-size: 11px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-approve { background: #d1fae5; color: #065f46; }
        .btn-approve:hover { background: #a7f3d0; }
        .btn-reject { background: #fee2e2; color: #991b1b; }
        .btn-reject:hover { background: #fecaca; }
        .btn-view { background: #e0e7ff; color: #3730a3; }
        .btn-view:hover { background: #c7d2fe; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; width: 90%; max-width: 600px; border-radius: 24px; animation: slideDown 0.3s; }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #eef2f6; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .close { font-size: 28px; cursor: pointer; color: #9aa9bc; }
        .modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
        
        .input-field { margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px; }
        .input-field label { font-size: 13px; font-weight: 600; color: #334155; }
        .input-field input, .input-field select, .input-field textarea { padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 14px; outline: none; }
        .input-field input:focus, .input-field select:focus, .input-field textarea:focus { border-color: #2c5f4e; }
        .required-star { color: #c2410c; }
        
        .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; padding-top: 20px; border-top: 1px solid #eef2f6; }
        .btn-cancel { padding: 10px 24px; background: #f1f3f5; border: none; border-radius: 10px; cursor: pointer; }
        .btn-submit { padding: 10px 28px; background: #1e3a32; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; }
        
        .pagination-container { margin-top: 20px; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-top: 1px solid #eef2f6; }
        .pagination { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .page-btn { padding: 8px 14px; background: white; border: 1px solid #dee2e6; border-radius: 10px; cursor: pointer; font-size: 13px; }
        .page-btn.active { background: #1e3a32; color: white; border-color: #1e3a32; }
        
        .records-per-page { text-align: right; margin-top: 15px; display: flex; justify-content: flex-end; align-items: center; gap: 10px; font-size: 13px; }
        .records-per-page select { padding: 6px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; cursor: pointer; }
        
        .toast { position: fixed; bottom: 30px; right: 30px; background: #1e3a32; color: white; padding: 14px 24px; border-radius: 12px; font-size: 14px; display: none; z-index: 1100; }
        .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid #e2e8f0; border-top-color: #1e3a32; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .info-box { background: #e0e7ff; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .officer-section { background: #f0fdf4; border: 1px solid #bbf7d0; }
        
        .badge-new { background: #ef4444; color: white; border-radius: 10px; padding: 2px 8px; font-size: 11px; margin-left: 8px; }
        
        @media (max-width: 768px) { .stats-grid, .balance-cards, .officer-actions { grid-template-columns: 1fr; } .modal-content { margin: 15% auto; width: 95%; } .workflow-steps { flex-direction: column; } .step-arrow { transform: rotate(90deg); } }
    </style>
</head>
<body>

<div class="container">
    <!-- Workflow Steps -->
    <div class="workflow-steps">
        <div class="workflow-step" id="stepInitiating">
            <div class="step-icon"><i class="fas fa-user-shield"></i></div>
            <div class="step-label">1. Initiating Officer Review & Forward</div>
        </div>
        <div class="step-arrow"><i class="fas fa-arrow-right"></i></div>
        <div class="workflow-step" id="stepAccepting">
            <div class="step-icon"><i class="fas fa-user-check"></i></div>
            <div class="step-label">2. Accepting Officer Final Approval</div>
        </div>
        <div class="step-arrow"><i class="fas fa-arrow-right"></i></div>
        <div class="workflow-step" id="stepComplete">
            <div class="step-icon"><i class="fas fa-check-circle"></i></div>
            <div class="step-label">3. Leave Approved & Granted</div>
        </div>
    </div>

    <!-- Statistics Dashboard -->
    <div class="stats-grid">
        <div class="stat-card initiating" onclick="filterByStatus('pending')">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div>
                <div class="stat-value" id="initiatingPending"><?php echo $initiatingPending; ?></div>
                <div class="stat-label">Pending for Initiating Officer</div>
            </div>
        </div>
        <div class="stat-card accepting" onclick="filterByStatus('initiating_approved')">
            <div class="stat-icon"><i class="fas fa-share"></i></div>
            <div>
                <div class="stat-value" id="acceptingPending"><?php echo $acceptingPending; ?></div>
                <div class="stat-label">Pending for Accepting Officer</div>
            </div>
        </div>
        <div class="stat-card approved" onclick="filterByStatus('approved')">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-value" id="approvedCount">0</div>
                <div class="stat-label">Approved Leaves</div>
            </div>
        </div>
    </div>

    <!-- Officer Action Cards -->
    <div class="officer-actions">
        <div class="officer-card">
            <div class="officer-card-header initiating">
                <i class="fas fa-user-shield"></i> 
                <span>Initiating Officer - Pending Approvals</span>
                <span class="badge-new" id="initiatingBadge"><?php echo $initiatingPending; ?></span>
            </div>
            <div class="officer-card-body" id="initiatingPendingList">
                <div class="loading-spinner" style="margin: 20px auto; display: block;"></div>
            </div>
        </div>
        
        <div class="officer-card">
            <div class="officer-card-header accepting">
                <i class="fas fa-user-check"></i> 
                <span>Accepting Officer - Pending Final Approval</span>
                <span class="badge-new" id="acceptingBadge"><?php echo $acceptingPending; ?></span>
            </div>
            <div class="officer-card-body" id="acceptingPendingList">
                <div class="loading-spinner" style="margin: 20px auto; display: block;"></div>
            </div>
        </div>
    </div>

    <!-- Leave Balance Cards -->
    <div class="balance-cards">
        <div class="balance-card">
            <h4><i class="fas fa-home"></i> 🏠 Gharpari Bida</h4>
            <div class="days" id="gharpariBalance"><?php echo $myBalance['gharpari_bida_days']; ?></div>
            <div class="progress-bar"><div class="progress-fill" id="gharpariProgress" style="width: <?php echo min(100, ($myBalance['gharpari_bida_days'] / 15) * 100); ?>%;"></div></div>
            <small>Days remaining</small>
        </div>
        <div class="balance-card">
            <h4><i class="fas fa-calendar-alt"></i> 🎉 Parba Bida</h4>
            <div class="days" id="parbaBalance"><?php echo $myBalance['parba_bida_days']; ?></div>
            <div class="progress-bar"><div class="progress-fill" id="parbaProgress" style="width: <?php echo min(100, ($myBalance['parba_bida_days'] / 12) * 100); ?>%;"></div></div>
            <small>Days remaining</small>
        </div>
        <div class="balance-card">
            <h4><i class="fas fa-hand-holding-heart"></i> 🤝 Bhaeepari Bida</h4>
            <div class="days" id="bhaeepariBalance"><?php echo $myBalance['bhaeepari_bida_days']; ?></div>
            <div class="progress-bar"><div class="progress-fill" id="bhaeepariProgress" style="width: <?php echo min(100, ($myBalance['bhaeepari_bida_days'] / 10) * 100); ?>%;"></div></div>
            <small>Days remaining</small>
        </div>
    </div>

    <!-- Search and Actions -->
    <div class="search-section">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by name, rank, or reason...">
            <button class="clear-search" id="clearSearch">✕</button>
        </div>
        <button class="btn-add" id="newLeaveBtn"><i class="fas fa-plus-circle"></i> New Leave Request</button>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-section">
        <button class="filter-btn active" data-filter="all">📋 All Requests</button>
        <button class="filter-btn" data-filter="pending">⏳ Pending (Initiating Officer)</button>
        <button class="filter-btn" data-filter="initiating_approved">📤 Awaiting Accepting Officer</button>
        <button class="filter-btn" data-filter="approved">✅ Approved</button>
        <button class="filter-btn" data-filter="rejected">❌ Rejected</button>
    </div>

    <!-- Leave Requests Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>S.N.</th>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="leaveTableBody">
                <tr><td colspan="13" style="text-align:center"><div class="loading-spinner"></div> Loading leave requests...<\/td><\/tr>
            </tbody>
        </table>
    </div>

    <div id="paginationContainer" class="pagination-container"></div>
    <div class="records-per-page">
        <label>Show:</label>
        <select id="recordsPerPage">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
        <span>entries per page</span>
    </div>
</div>

<!-- New Leave Request Modal -->
<div id="leaveModal" class="modal">
    <div class="modal-content">
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
                    <span><strong>Two-Level Approval Required</strong> - Select both officers for approval workflow</span>
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
                        <small>Level 1: Reviews and forwards to accepting officer</small>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-user-check"></i> Accepting Officer <span class="required-star">*</span></label>
                        <select id="acceptingOfficer" required>
                            <option value="">Select Accepting Officer</option>
                            <?php foreach ($officers as $officer): ?>
                                <option value="<?php echo $officer['id']; ?>"><?php echo htmlspecialchars($officer['rank'] . ' ' . $officer['personnel_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Level 2: Final approval and authorization</small>
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

<!-- Action Modal for Approval/Rejection -->
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
                <input type="hidden" id="actionOfficerType">
                
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

<!-- Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Leave Request Details</h3>
            <span class="close-details">&times;</span>
        </div>
        <div class="modal-body" id="detailsContent"></div>
    </div>
</div>

<div id="toast" class="toast"><span id="toastMessage"></span></div>

<script>
    let leaveData = [];
    let currentFilter = 'all';
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    let currentPerPage = 5;
    let currentUserRole = <?php echo $user_role_int; ?>;
    let currentPersonnelId = <?php echo $current_personnel_id; ?>;
    
    // Load initiating officer pending requests
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
                            <div class="pending-item-title">${escapeHtml(leave.personnel_name)} (${escapeHtml(leave.rank)})</div>
                            <div class="pending-item-details">
                                <span>${leave.leave_type === 'gharpari_bida' ? '🏠 Gharpari' : (leave.leave_type === 'parba_bida' ? '🎉 Parba' : '🤝 Bhaeepari')}</span>
                                <span>📅 ${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</span>
                                <span>📆 ${leave.leave_days} days</span>
                            </div>
                            <div class="pending-item-details" style="font-size: 11px; color: #6c7a8e; margin-top: 5px;">
                                Accepting Officer: ${escapeHtml(leave.accepting_officer_name) || '-'}
                            </div>
                            <button class="btn-process" onclick="openApprovalModal(${leave.id}, 'initiating', 'approve')"><i class="fas fa-check"></i> Approve & Forward</button>
                            <button class="btn-process btn-process-reject" onclick="openApprovalModal(${leave.id}, 'initiating', 'reject')"><i class="fas fa-times"></i> Reject</button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i><br>No pending requests for approval</div>';
            }
        } catch (error) {
            console.error('Error loading initiating pending:', error);
            document.getElementById('initiatingPendingList').innerHTML = '<div class="empty-state">Error loading requests</div>';
        }
    }
    
    // Load accepting officer pending requests
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
                            <div class="pending-item-title">${escapeHtml(leave.personnel_name)} (${escapeHtml(leave.rank)})</div>
                            <div class="pending-item-details">
                                <span>${leave.leave_type === 'gharpari_bida' ? '🏠 Gharpari' : (leave.leave_type === 'parba_bida' ? '🎉 Parba' : '🤝 Bhaeepari')}</span>
                                <span>📅 ${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</span>
                                <span>📆 ${leave.leave_days} days</span>
                            </div>
                            <div class="pending-item-details" style="font-size: 11px; color: #6c7a8e; margin-top: 5px;">
                                Initiated by: ${escapeHtml(leave.initiating_officer_name) || '-'}
                                ${leave.initiating_officer_approved_at ? `<br>Forwarded on: ${new Date(leave.initiating_officer_approved_at).toLocaleString()}` : ''}
                            </div>
                            <button class="btn-process" onclick="openApprovalModal(${leave.id}, 'accepting', 'approve')"><i class="fas fa-check-circle"></i> Final Approve</button>
                            <button class="btn-process btn-process-reject" onclick="openApprovalModal(${leave.id}, 'accepting', 'reject')"><i class="fas fa-times"></i> Reject</button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-clock"></i><br>No pending requests for final approval</div>';
            }
        } catch (error) {
            console.error('Error loading accepting pending:', error);
            document.getElementById('acceptingPendingList').innerHTML = '<div class="empty-state">Error loading requests</div>';
        }
    }
    
    function openApprovalModal(id, officerType, action) {
        document.getElementById('actionLeaveId').value = id;
        document.getElementById('actionOfficerType').value = officerType;
        document.getElementById('actionType').value = action;
        
        if (officerType === 'initiating') {
            document.getElementById('actionModalTitle').innerHTML = action === 'approve' ? 'Approve as Initiating Officer' : 'Reject as Initiating Officer';
            document.getElementById('actionLabel').innerHTML = action === 'approve' ? 'Forwarding Remarks (Optional)' : 'Rejection Reason <span class="required-star">*</span>';
        } else {
            document.getElementById('actionModalTitle').innerHTML = action === 'approve' ? 'Final Approve as Accepting Officer' : 'Reject as Accepting Officer';
            document.getElementById('actionLabel').innerHTML = action === 'approve' ? 'Approval Remarks (Optional)' : 'Rejection Reason <span class="required-star">*</span>';
        }
        
        document.getElementById('actionRemarks').value = '';
        document.getElementById('actionModal').style.display = 'block';
    }
    
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
    
    function renderLeaveTable() {
        const tbody = document.getElementById('leaveTableBody');
        if (!tbody) return;
        
        if (!leaveData || leaveData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="13" style="text-align:center">No leave requests found</span></td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        
        leaveData.forEach((leave, idx) => {
            let balance = 0;
            if (leave.leave_type === 'gharpari_bida') balance = leave.gharpari_bida_days || 0;
            else if (leave.leave_type === 'parba_bida') balance = leave.parba_bida_days || 0;
            else if (leave.leave_type === 'bhaeepari_bida') balance = leave.bhaeepari_bida_days || 0;
            
            let statusClass = '', statusText = '';
            if (leave.status === 'pending') { statusClass = 'status-pending'; statusText = 'Pending (Initiating Officer)'; }
            else if (leave.status === 'initiating_approved') { statusClass = 'status-initiated'; statusText = 'Awaiting Accepting Officer'; }
            else if (leave.status === 'approved') { statusClass = 'status-approved'; statusText = 'Approved'; }
            else if (leave.status === 'rejected') { statusClass = 'status-rejected'; statusText = 'Rejected'; }
            else { statusClass = 'status-pending'; statusText = leave.status; }
            
            let actionBtns = `<div class="action-buttons">
                <button class="action-btn btn-view" onclick="viewDetails(${leave.id})"><i class="fas fa-eye"></i> View</button>`;
            
            if (leave.initiating_officer == currentPersonnelId && leave.initiating_officer_approved == 0 && leave.status === 'pending') {
                actionBtns += `<button class="action-btn btn-approve" onclick="openApprovalModal(${leave.id}, 'initiating', 'approve')"><i class="fas fa-check"></i> Approve & Forward</button>
                              <button class="action-btn btn-reject" onclick="openApprovalModal(${leave.id}, 'initiating', 'reject')"><i class="fas fa-times"></i> Reject</button>`;
            }
            
            if (leave.accepting_officer == currentPersonnelId && leave.initiating_officer_approved == 1 && leave.accepting_officer_approved == 0 && leave.status === 'initiating_approved') {
                actionBtns += `<button class="action-btn btn-approve" onclick="openApprovalModal(${leave.id}, 'accepting', 'approve')"><i class="fas fa-check-circle"></i> Final Approve</button>
                              <button class="action-btn btn-reject" onclick="openApprovalModal(${leave.id}, 'accepting', 'reject')"><i class="fas fa-times"></i> Reject</button>`;
            }
            
            actionBtns += `</div>`;
            
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>${idx + 1 + ((currentPage - 1) * currentPerPage)}</span></td>
                <td><strong>${escapeHtml(leave.personnel_name)}</strong></td>
                <td>${escapeHtml(leave.rank)}</span></td>
                <td>${leave.leave_type === 'gharpari_bida' ? '🏠 Gharpari Bida' : (leave.leave_type === 'parba_bida' ? '🎉 Parba Bida' : '🤝 Bhaeepari Bida')}</td>
                <td>${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</td>
                <td><strong>${leave.leave_days}</strong> days</span></td>
                <td>${escapeHtml(leave.reason.substring(0, 40))}${leave.reason.length > 40 ? '...' : ''}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>${balance} days</span></td>
                <td>${leave.initiating_officer_name ? `<span class="badge-officer"><i class="fas fa-user-shield"></i> ${escapeHtml(leave.initiating_officer_name)}</span>` : '-'}</td>
                <td>${leave.accepting_officer_name ? `<span class="badge-officer"><i class="fas fa-user-check"></i> ${escapeHtml(leave.accepting_officer_name)}</span>` : '-'}</td>
                <td>${new Date(leave.created_at).toLocaleDateString()}</td>
                <td>${actionBtns}</td>
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
            html += `<button onclick="loadDataFromDatabase(1)" class="page-btn"><i class="fas fa-angle-double-left"></i></button>`;
            html += `<button onclick="loadDataFromDatabase(${currentPage - 1})" class="page-btn"><i class="fas fa-angle-left"></i></button>`;
        }
        
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            html += `<button onclick="loadDataFromDatabase(${i})" class="page-btn ${i === currentPage ? 'active' : ''}">${i}</button>`;
        }
        
        if (currentPage < totalPages) {
            html += `<button onclick="loadDataFromDatabase(${currentPage + 1})" class="page-btn"><i class="fas fa-angle-right"></i></button>`;
            html += `<button onclick="loadDataFromDatabase(${totalPages})" class="page-btn"><i class="fas fa-angle-double-right"></i></button>`;
        }
        
        html += `</div>`;
        container.innerHTML = html;
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
                document.getElementById('initiatingPending').textContent = result.data.initiating_pending || 0;
                document.getElementById('initiatingBadge').textContent = result.data.initiating_pending || 0;
                document.getElementById('acceptingPending').textContent = result.data.accepting_pending || 0;
                document.getElementById('acceptingBadge').textContent = result.data.accepting_pending || 0;
                document.getElementById('approvedCount').textContent = result.data.total_approved || 0;
            }
        } catch (e) { console.error(e); }
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
    
    async function viewDetails(id) {
        const leave = leaveData.find(l => l.id == id);
        if (!leave) return;
        
        let balance = 0;
        if (leave.leave_type === 'gharpari_bida') balance = leave.gharpari_bida_days || 0;
        else if (leave.leave_type === 'parba_bida') balance = leave.parba_bida_days || 0;
        else if (leave.leave_type === 'bhaeepari_bida') balance = leave.bhaeepari_bida_days || 0;
        
        let initiatingStatus = leave.initiating_officer_approved == 1 ? '✓ Approved' : '⏳ Pending';
        let acceptingStatus = leave.accepting_officer_approved == 1 ? '✓ Approved' : '⏳ Pending';
        
        document.getElementById('detailsContent').innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Personnel</div>
                    <div style="font-size: 15px; font-weight: 600;">${escapeHtml(leave.personnel_name)} (${escapeHtml(leave.rank)})</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Leave Type</div>
                    <div style="font-size: 15px; font-weight: 600;">${leave.leave_type === 'gharpari_bida' ? '🏠 Gharpari Bida' : (leave.leave_type === 'parba_bida' ? '🎉 Parba Bida' : '🤝 Bhaeepari Bida')}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Period</div>
                    <div style="font-size: 14px; font-weight: 600;">${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Total Days</div>
                    <div style="font-size: 15px; font-weight: 600;">${leave.leave_days} days</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Remaining Balance</div>
                    <div style="font-size: 15px; font-weight: 600;">${balance} days</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Status</div>
                    <div style="font-size: 14px; font-weight: 600;">${leave.status.toUpperCase()}</div>
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Initiating Officer</div>
                    <div style="font-size: 14px;">${escapeHtml(leave.initiating_officer_name) || '-'}</div>
                    <small style="color: ${leave.initiating_officer_approved == 1 ? '#065f46' : '#92400e'}">Status: ${initiatingStatus}</small>
                    ${leave.initiating_officer_approved_at ? `<div><small>Approved on: ${new Date(leave.initiating_officer_approved_at).toLocaleString()}</small></div>` : ''}
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 11px; color: #6c7a8e;">Accepting Officer</div>
                    <div style="font-size: 14px;">${escapeHtml(leave.accepting_officer_name) || '-'}</div>
                    <small style="color: ${leave.accepting_officer_approved == 1 ? '#065f46' : '#92400e'}">Status: ${acceptingStatus}</small>
                    ${leave.accepting_officer_approved_at ? `<div><small>Approved on: ${new Date(leave.accepting_officer_approved_at).toLocaleString()}</small></div>` : ''}
                </div>
                <div style="padding: 12px; background: #f8fafc; border-radius: 10px; grid-column: span 2;">
                    <div style="font-size: 11px; color: #6c7a8e;">Reason</div>
                    <div style="font-size: 14px; margin-top: 5px;">${escapeHtml(leave.reason)}</div>
                </div>
                ${leave.initiating_officer_remarks ? `<div style="padding: 12px; background: #fef3c7; border-radius: 10px; grid-column: span 2;">
                    <div style="font-size: 11px; color: #92400e;">Initiating Officer Remarks</div>
                    <div style="font-size: 13px; margin-top: 5px;">${escapeHtml(leave.initiating_officer_remarks)}</div>
                </div>` : ''}
                ${leave.accepting_officer_remarks ? `<div style="padding: 12px; background: #d1fae5; border-radius: 10px; grid-column: span 2;">
                    <div style="font-size: 11px; color: #065f46;">Accepting Officer Remarks</div>
                    <div style="font-size: 13px; margin-top: 5px;">${escapeHtml(leave.accepting_officer_remarks)}</div>
                </div>` : ''}
            </div>
        `;
        
        document.getElementById('detailsModal').style.display = 'block';
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
                balanceInput.style.background = '#fee2e2';
                showToast(`⚠️ Warning: Requesting ${days} days but only ${balance} days available!`, 'error');
            } else {
                balanceInput.style.color = '#065f46';
                balanceInput.style.background = '#d1fae5';
            }
        }
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
    
    document.getElementById('newLeaveBtn')?.addEventListener('click', () => {
        document.getElementById('leaveForm').reset();
        document.getElementById('totalDays').value = '';
        document.getElementById('availableBalance').value = '';
        document.getElementById('leaveModal').style.display = 'block';
    });
    
    document.querySelectorAll('.close, .close-action, .close-details').forEach(btn => {
        btn.onclick = () => {
            document.getElementById('leaveModal').style.display = 'none';
            document.getElementById('actionModal').style.display = 'none';
            document.getElementById('detailsModal').style.display = 'none';
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
                showToast('✅ Leave request submitted successfully. Awaiting initiating officer approval.', 'success');
                document.getElementById('leaveModal').style.display = 'none';
                loadDataFromDatabase(1);
                loadMyBalance();
                loadStatistics();
                loadInitiatingPending();
                loadAcceptingPending();
            } else {
                showToast(result.error || 'Failed to submit leave request', 'error');
            }
        } catch (e) { showToast('Error submitting leave request', 'error'); }
    });
    
    document.getElementById('actionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('actionLeaveId').value;
        const officerType = document.getElementById('actionOfficerType').value;
        const action = document.getElementById('actionType').value;
        const remarks = document.getElementById('actionRemarks').value;
        
        if (action === 'reject' && !remarks) {
            showToast('Please provide remarks', 'error');
            return;
        }
        
        let endpoint = '';
        let successMessage = '';
        
        if (officerType === 'initiating') {
            if (action === 'approve') {
                endpoint = 'initiating_officer_approve';
                successMessage = '✅ Leave request approved and forwarded to accepting officer!';
            } else {
                endpoint = 'initiating_officer_reject';
                successMessage = '❌ Leave request rejected.';
            }
        } else {
            if (action === 'approve') {
                endpoint = 'accepting_officer_approve';
                successMessage = '✅ Leave request FINALLY APPROVED! Leave has been granted.';
            } else {
                endpoint = 'accepting_officer_reject';
                successMessage = '❌ Leave request rejected by accepting officer.';
            }
        }
        
        const formData = new FormData();
        formData.append('action', endpoint);
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
                showToast(successMessage, 'success');
                document.getElementById('actionModal').style.display = 'none';
                loadDataFromDatabase(currentPage);
                loadMyBalance();
                loadStatistics();
                loadInitiatingPending();
                loadAcceptingPending();
            } else {
                showToast(result.error || 'Failed to process request', 'error');
            }
        } catch (e) { showToast('Error processing request', 'error'); }
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
        loadStatistics();
        loadMyBalance();
        loadInitiatingPending();
        loadAcceptingPending();
        
        // Refresh every 15 seconds
        setInterval(() => {
            loadDataFromDatabase(currentPage);
            loadMyBalance();
            loadStatistics();
            loadInitiatingPending();
            loadAcceptingPending();
        }, 15000);
    });
</script>

</body>
</html>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>