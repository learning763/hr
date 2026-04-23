<?php
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include('includes/config.php');
include_once('includes/pagination.php'); // Include pagination functions

$pageTitle = "Leave Management";
$pageSubtitle = "Manage military personnel leave requests, approvals, and tracking";
$activePage = "leave";

// Pagination configuration - DEFAULT 5 ITEMS PER PAGE
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$allowed_per_page = [5, 10, 25, 50, 100];
if (!in_array($records_per_page, $allowed_per_page)) {
    $records_per_page = 5;
}

$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $records_per_page;

// Get filter and search parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Security token validation failed']);
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        
        // Get all leave requests with pagination
        if ($action === 'get_all') {
            $filter = $_POST['filter'] ?? 'all';
            $search = $_POST['search'] ?? '';
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 5;
            $offset = ($page - 1) * $per_page;
            
            // Build the base query
            $sql = "SELECT lr.*, mps.personnel_name, mps.rank 
                    FROM leave_requests lr
                    INNER JOIN military_personnel_status mps ON lr.personnel_id = mps.id
                    WHERE 1=1";
            $count_sql = "SELECT COUNT(*) as total 
                         FROM leave_requests lr
                         INNER JOIN military_personnel_status mps ON lr.personnel_id = mps.id
                         WHERE 1=1";
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
            
            // Get total count for pagination
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = ceil($total_records / $per_page);
            
            // Get paginated results
            $sql .= " ORDER BY lr.created_at DESC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
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
                    'per_page' => $per_page,
                    'offset' => $offset
                ]
            ]);
            exit;
        }
        
        // Submit new leave request
        if ($action === 'submit_leave') {
            $personnel_id = intval($_POST['personnel_id'] ?? 0);
            $leave_type = trim($_POST['leave_type'] ?? '');
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $alternate_officer = trim($_POST['alternate_officer'] ?? '');
            
            // Validate inputs
            if ($personnel_id <= 0 || empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
                echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
                exit;
            }
            
            // Validate dates
            if ($start_date > $end_date) {
                echo json_encode(['success' => false, 'error' => 'End date must be after start date']);
                exit;
            }
            
            // Check for overlapping leave requests
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM leave_requests 
                WHERE personnel_id = ? 
                AND status IN ('pending', 'approved')
                AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
            ");
            $stmt->execute([$personnel_id, $end_date, $start_date, $end_date, $start_date]);
            $overlap = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($overlap['count'] > 0) {
                echo json_encode(['success' => false, 'error' => 'Personnel already has a leave request for this period']);
                exit;
            }
            
            // Calculate leave days
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $leave_days = $interval->days + 1;
            
            // Insert leave request
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests 
                (personnel_id, leave_type, start_date, end_date, leave_days, reason, contact_number, alternate_officer, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $result = $stmt->execute([
                $personnel_id, $leave_type, $start_date, $end_date, $leave_days, 
                $reason, $contact_number, $alternate_officer, $_SESSION['user_id'] ?? 1
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to submit leave request']);
            }
            exit;
        }
        
        // Approve leave request
        if ($action === 'approve_leave') {
            $id = intval($_POST['id'] ?? 0);
            $approver_remarks = trim($_POST['approver_remarks'] ?? '');
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'approved', 
                    approved_by = ?, 
                    approved_at = NOW(),
                    approver_remarks = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([$_SESSION['user_id'] ?? 1, $approver_remarks, $id]);
            
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Reject leave request
        if ($action === 'reject_leave') {
            $id = intval($_POST['id'] ?? 0);
            $rejection_reason = trim($_POST['approver_remarks'] ?? '');
            
            if (empty($rejection_reason)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
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
            $result = $stmt->execute([$_SESSION['user_id'] ?? 1, $rejection_reason, $id]);
            
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Cancel leave request
        if ($action === 'cancel_leave') {
            $id = intval($_POST['id'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            echo json_encode(['success' => $result]);
            exit;
        }
        
        // Get leave statistics
        if ($action === 'get_stats') {
            $stats = [];
            
            // Pending count
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
            $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Approved this month
            $stmt = $pdo->query("
                SELECT COALESCE(SUM(leave_days), 0) as total_days 
                FROM leave_requests 
                WHERE status = 'approved' 
                AND MONTH(start_date) = MONTH(CURDATE()) 
                AND YEAR(start_date) = YEAR(CURDATE())
            ");
            $stats['total_leave_days'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_days'];
            
            // Approved today
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved' AND DATE(approved_at) = CURDATE()");
            $stats['approved_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // On leave today
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT personnel_id) as count 
                FROM leave_requests 
                WHERE status = 'approved' 
                AND CURDATE() BETWEEN start_date AND end_date
            ");
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

// Get leave statistics for display
function getLeaveStatistics($pdo) {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
    $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved' AND DATE(approved_at) = CURDATE()");
    $stats['approved_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT personnel_id) as count 
        FROM leave_requests 
        WHERE status = 'approved' 
        AND CURDATE() BETWEEN start_date AND end_date
    ");
    $stats['on_leave_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}

$leaveStats = getLeaveStatistics($pdo);

// Prepare the content
ob_start();
?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value" id="pendingCount"><?php echo $leaveStats['pending']; ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value" id="approvedToday"><?php echo $leaveStats['approved_today']; ?></div>
            <div class="stat-label">Approved Today</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div>
            <div class="stat-value" id="onLeaveToday"><?php echo $leaveStats['on_leave_today']; ?></div>
            <div class="stat-label">On Leave Today</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div>
            <div class="stat-value" id="monthlyLeave">0</div>
            <div class="stat-label">Leave Days (This Month)</div>
        </div>
    </div>
</div>

<!-- Search and Filter Section -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Search by name, rank, or reason..." value="<?php echo htmlspecialchars($search_term); ?>">
        <?php if (!empty($search_term)): ?>
            <button id="clearSearch" class="clear-search">✕</button>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn-add" id="newLeaveBtn">
            <i class="fas fa-plus-circle"></i> New Leave Request
        </button>
        <button class="btn-export" id="exportBtn">
            <i class="fas fa-download"></i> Export Report
        </button>
    </div>
</div>

<!-- Status Filter Buttons -->
<div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>" data-filter="all">📋 All Requests</button>
    <button class="filter-btn <?php echo $filter == 'pending' ? 'active' : ''; ?>" data-filter="pending">⏳ Pending</button>
    <button class="filter-btn <?php echo $filter == 'approved' ? 'active' : ''; ?>" data-filter="approved">✅ Approved</button>
    <button class="filter-btn <?php echo $filter == 'rejected' ? 'active' : ''; ?>" data-filter="rejected">❌ Rejected</button>
    <button class="filter-btn <?php echo $filter == 'cancelled' ? 'active' : ''; ?>" data-filter="cancelled">🚫 Cancelled</button>
</div>

<!-- Leave Requests Table -->
<div class="data-table">
    <table id="leaveTable">
        <thead>
            <tr>
                <th style="width: 60px;">S.N.</th>
                <th>Personnel</th>
                <th>Rank</th>
                <th>Leave Type</th>
                <th>Period</th>
                <th>Days</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Submitted</th>
                <th style="width: 150px;">Actions</th>
            </tr>
        </thead>
        <tbody id="leaveTableBody">
            <tr>
                <td colspan="10" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin"></i> Loading leave requests...
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination Section -->
<div id="paginationContainer" class="pagination-container" style="display: none;">
    <!-- Pagination will be loaded here via JavaScript -->
</div>

<!-- Records Per Page Selector -->
<div class="records-per-page" style="margin-top: 15px; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
    <label>Show:</label>
    <select id="recordsPerPage">
        <option value="5" <?php echo $records_per_page == 5 ? 'selected' : ''; ?>>5</option>
        <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
        <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
        <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
        <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
    </select>
    <span>entries per page</span>
</div>

<!-- No Results Message -->
<div id="noResults" style="display: none; text-align: center; padding: 40px; color: #6c7a8e;">
    <i class="fas fa-calendar-alt" style="font-size: 48px; margin-bottom: 10px;"></i>
    <p>No leave requests found matching your criteria.</p>
</div>

<!-- Modal for New Leave Request -->
<div id="leaveModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> New Leave Request</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="leaveForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-grid">
                    <div class="input-field full-width">
                        <label><i class="fas fa-user"></i> Personnel Name <span class="required-star">*</span></label>
                        <select id="personnelId" required>
                            <option value="">Select Personnel</option>
                            <?php
                            $stmt = $pdo->query("SELECT DISTINCT id, personnel_name, rank FROM military_personnel_status ORDER BY personnel_name");
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$row['id']}'>{$row['rank']} {$row['personnel_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-tag"></i> Leave Type <span class="required-star">*</span></label>
                        <select id="leaveType" required>
                            <option value="">Select Type</option>
                            <option value="annual">🏖️ Annual Leave</option>
                            <option value="sick">🤒 Sick Leave</option>
                            <option value="casual">🎯 Casual Leave</option>
                            <option value="emergency">🚨 Emergency Leave</option>
                            <option value="study">📚 Study Leave</option>
                        </select>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-calendar-day"></i> Start Date <span class="required-star">*</span></label>
                        <input type="date" id="startDate" required>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-calendar-day"></i> End Date <span class="required-star">*</span></label>
                        <input type="date" id="endDate" required>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-sort-numeric-up"></i> Total Days</label>
                        <input type="text" id="totalDays" readonly style="background: #f8fafc;">
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="tel" id="contactNumber" placeholder="Emergency contact number">
                    </div>
                    
                    <div class="input-field full-width">
                        <label><i class="fas fa-user-friends"></i> Alternate Duty Officer</label>
                        <input type="text" id="alternateOfficer" placeholder="Name of officer handling duties during leave">
                    </div>
                    
                    <div class="input-field full-width">
                        <label><i class="fas fa-comment"></i> Reason for Leave <span class="required-star">*</span></label>
                        <textarea id="reason" rows="3" placeholder="Please provide detailed reason for leave..." required></textarea>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Approve/Reject -->
<div id="actionModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="actionModalTitle">Process Leave Request</h3>
            <span class="close-action">&times;</span>
        </div>
        <div class="modal-body">
            <form id="actionForm">
                <input type="hidden" id="actionLeaveId">
                <input type="hidden" id="actionType">
                
                <div class="input-field full-width">
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

<!-- View Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Leave Request Details</h3>
            <span class="close-details">&times;</span>
        </div>
        <div class="modal-body" id="detailsContent">
            <!-- Details will be loaded here -->
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none;">
    <span id="toastMessage"></span>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #eef2f6;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 54px;
        height: 54px;
        background: #f0fdf4;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #2c5f4e;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #1a2c3e;
        line-height: 1.2;
    }
    
    .stat-label {
        font-size: 13px;
        color: #6c7a8e;
        margin-top: 4px;
    }
    
    .search-container {
        position: relative;
        flex: 1;
        max-width: 400px;
    }
    
    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9aa9bc;
        font-size: 14px;
    }
    
    .search-input {
        width: 100%;
        padding: 10px 35px 10px 38px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
    }
    
    .search-input:focus {
        border-color: #2c5f4e;
        box-shadow: 0 0 0 3px rgba(44, 95, 78, 0.08);
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
        width: 20px;
        height: 20px;
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
        padding: 8px 16px;
        border: 1.5px solid #e2e8f0;
        background: white;
        border-radius: 20px;
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
        padding: 10px 20px;
        border-radius: 8px;
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
    }
    
    .btn-export:hover {
        background: #1e4a3a;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .data-table {
        overflow-x: auto;
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #eef2f6;
    }
    
    .data-table table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .data-table th {
        text-align: left;
        padding: 12px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        color: #1a2c3e;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #eef2f6;
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-approved { background: #d1fae5; color: #065f46; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-cancelled { background: #f3f4f6; color: #4b5563; }
    
    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .action-btn-small {
        padding: 5px 10px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-view { background: #e0e7ff; color: #3730a3; }
    .btn-view:hover { background: #c7d2fe; }
    .btn-approve { background: #d1fae5; color: #065f46; }
    .btn-approve:hover { background: #a7f3d0; }
    .btn-reject { background: #fee2e2; color: #991b1b; }
    .btn-reject:hover { background: #fecaca; }
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
        gap: 5px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .pagination-btn, .page-number {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        color: #495057;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
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
    
    .page-dots {
        padding: 0.5rem;
        color: #6c757d;
    }
    
    .records-per-page {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #6c7a8e;
    }
    
    .records-per-page select {
        padding: 6px 10px;
        border: 1.5px solid #e2e8f0;
        border-radius: 6px;
        background: white;
        font-size: 14px;
        cursor: pointer;
        outline: none;
    }
    
    .records-per-page select:focus {
        border-color: #2c5f4e;
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
    
    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        width: 90%;
        max-width: 700px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideDown 0.3s;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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
    }
    
    .close, .close-action, .close-details {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #9aa9bc;
        transition: 0.2s;
    }
    
    .close:hover, .close-action:hover, .close-details:hover {
        color: #c2410c;
    }
    
    .modal-body {
        padding: 24px;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .input-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .input-field label {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }
    
    .input-field input, .input-field select, .input-field textarea {
        padding: 10px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
        font-family: inherit;
    }
    
    .input-field input:focus, .input-field select:focus, .input-field textarea:focus {
        border-color: #2c5f4e;
        box-shadow: 0 0 0 3px rgba(44, 95, 78, 0.08);
    }
    
    .full-width {
        grid-column: span 2;
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
        padding: 10px 20px;
        background: #f1f3f5;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: 0.2s;
    }
    
    .btn-cancel:hover {
        background: #e9ecef;
    }
    
    .btn-submit {
        padding: 10px 24px;
        background: #1e3a32;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: 0.2s;
    }
    
    .btn-submit:hover {
        background: #14362c;
    }
    
    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .detail-item {
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
    }
    
    .detail-label {
        font-size: 11px;
        color: #6c7a8e;
        margin-bottom: 5px;
    }
    
    .detail-value {
        font-size: 14px;
        font-weight: 600;
        color: #1a2c3e;
    }
    
    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #1e3a32;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 1100;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .full-width {
            grid-column: span 1;
        }
        .modal-content {
            margin: 10% auto;
            width: 95%;
        }
        .search-container {
            max-width: 100%;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .pagination-container {
            flex-direction: column;
            align-items: center;
        }
        .pagination {
            justify-content: center;
        }
    }
</style>

<script>
    let leaveData = [];
    let currentFilter = '<?php echo $filter; ?>';
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    let currentPerPage = <?php echo $records_per_page; ?>;
    
    // Load data from database
    async function loadDataFromDatabase(page = 1) {
        try {
            const searchValue = document.getElementById('searchInput')?.value || '';
            currentPage = page;
            
            const formData = new FormData();
            formData.append('action', 'get_all');
            formData.append('filter', currentFilter);
            formData.append('search', searchValue);
            formData.append('page', currentPage);
            formData.append('per_page', currentPerPage);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                leaveData = result.data || [];
                totalPages = result.pagination.total_pages;
                totalRecords = result.pagination.total_records;
                renderTable();
                renderPaginationUI();
                loadStatistics();
            } else {
                showToast(result.error || 'Failed to load data', 'error');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            showToast('Error loading data from database', 'error');
            renderTable();
        }
    }
    
    // Render pagination UI
    function renderPaginationUI() {
        const paginationContainer = document.getElementById('paginationContainer');
        if (!paginationContainer) return;
        
        if (totalPages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }
        
        paginationContainer.style.display = 'flex';
        
        const offset = (currentPage - 1) * currentPerPage;
        const start = offset + 1;
        const end = Math.min(offset + currentPerPage, totalRecords);
        
        let paginationHtml = `
            <div class="pagination-info">
                Showing ${start} to ${end} of ${totalRecords} entries
            </div>
            <div class="pagination">
        `;
        
        // First page button
        if (currentPage > 1) {
            paginationHtml += `<button onclick="loadDataFromDatabase(1)" class="pagination-btn"><i class="fas fa-angle-double-left"></i> First</button>`;
            paginationHtml += `<button onclick="loadDataFromDatabase(${currentPage - 1})" class="pagination-btn"><i class="fas fa-angle-left"></i> Previous</button>`;
        }
        
        // Page numbers
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            paginationHtml += `<span class="page-dots">...</span>`;
        }
        
        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `<button onclick="loadDataFromDatabase(${i})" class="page-number ${i === currentPage ? 'active' : ''}">${i}</button>`;
        }
        
        if (endPage < totalPages) {
            paginationHtml += `<span class="page-dots">...</span>`;
        }
        
        // Last page button
        if (currentPage < totalPages) {
            paginationHtml += `<button onclick="loadDataFromDatabase(${currentPage + 1})" class="pagination-btn">Next <i class="fas fa-angle-right"></i></button>`;
            paginationHtml += `<button onclick="loadDataFromDatabase(${totalPages})" class="pagination-btn">Last <i class="fas fa-angle-double-right"></i></button>`;
        }
        
        paginationHtml += `</div>`;
        paginationContainer.innerHTML = paginationHtml;
    }
    
    // Load statistics
    async function loadStatistics() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_stats');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success && result.data) {
                const pendingElement = document.getElementById('pendingCount');
                const approvedTodayElement = document.getElementById('approvedToday');
                const onLeaveTodayElement = document.getElementById('onLeaveToday');
                const monthlyElement = document.getElementById('monthlyLeave');
                
                if (pendingElement) pendingElement.textContent = result.data.pending || 0;
                if (approvedTodayElement) approvedTodayElement.textContent = result.data.approved_today || 0;
                if (onLeaveTodayElement) onLeaveTodayElement.textContent = result.data.on_leave_today || 0;
                if (monthlyElement) monthlyElement.textContent = result.data.total_leave_days || 0;
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }
    
    // Get status badge HTML
    function getStatusBadge(status) {
        const badges = {
            'pending': '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>',
            'approved': '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Approved</span>',
            'rejected': '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>',
            'cancelled': '<span class="status-badge status-cancelled"><i class="fas fa-ban"></i> Cancelled</span>'
        };
        return badges[status] || badges['pending'];
    }
    
    // Get leave type icon
    function getLeaveTypeIcon(type) {
        const icons = {
            'annual': '🏖️',
            'sick': '🤒',
            'casual': '🎯',
            'emergency': '🚨',
            'study': '📚'
        };
        return icons[type] || '📅';
    }
    
    // Render table with Serial Number starting from 1
    function renderTable() {
        const tbody = document.getElementById('leaveTableBody');
        const noResults = document.getElementById('noResults');
        
        if (!tbody) return;
        
        if (!leaveData || leaveData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 40px;">No leave requests found</td></tr>';
            if (noResults) noResults.style.display = 'none';
            return;
        }
        
        tbody.innerHTML = '';
        if (noResults) noResults.style.display = 'none';
        
        // Calculate starting serial number based on current page
        const startSerial = ((currentPage - 1) * currentPerPage) + 1;
        
        leaveData.forEach((leave, index) => {
            const row = tbody.insertRow();
            const startDate = new Date(leave.start_date).toLocaleDateString();
            const endDate = new Date(leave.end_date).toLocaleDateString();
            const submittedDate = new Date(leave.created_at).toLocaleDateString();
            const serialNumber = startSerial + index;
            
            row.innerHTML = `
                <td>${serialNumber}</td>
                <td>${escapeHtml(leave.personnel_name)}</td>
                <td>${escapeHtml(leave.rank)}</td>
                <td>${getLeaveTypeIcon(leave.leave_type)} ${leave.leave_type.charAt(0).toUpperCase() + leave.leave_type.slice(1)}</td>
                <td>${startDate} - ${endDate}</td>
                <td>${leave.leave_days}</td>
                <td>${escapeHtml(leave.reason.substring(0, 50))}${leave.reason.length > 50 ? '...' : ''}</td>
                <td>${getStatusBadge(leave.status)}</td>
                <td>${submittedDate}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn-small btn-view" onclick="viewDetails(${leave.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${leave.status === 'pending' ? `
                            <button class="action-btn-small btn-approve" onclick="processLeave(${leave.id}, 'approve')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="action-btn-small btn-reject" onclick="processLeave(${leave.id}, 'reject')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        ` : ''}
                        ${(leave.status === 'pending' || leave.status === 'approved') ? `
                            <button class="action-btn-small btn-cancel-action" onclick="cancelLeave(${leave.id})">
                                <i class="fas fa-ban"></i> Cancel
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
        });
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // View details
    async function viewDetails(id) {
        const leave = leaveData.find(l => l.id == id);
        if (!leave) return;
        
        const detailsContent = document.getElementById('detailsContent');
        if (!detailsContent) return;
        
        detailsContent.innerHTML = `
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Personnel Name</div>
                    <div class="detail-value">${escapeHtml(leave.personnel_name)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Rank</div>
                    <div class="detail-value">${escapeHtml(leave.rank)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Leave Type</div>
                    <div class="detail-value">${getLeaveTypeIcon(leave.leave_type)} ${leave.leave_type.toUpperCase()}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Leave Days</div>
                    <div class="detail-value">${leave.leave_days} days</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Start Date</div>
                    <div class="detail-value">${new Date(leave.start_date).toLocaleDateString()}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">End Date</div>
                    <div class="detail-value">${new Date(leave.end_date).toLocaleDateString()}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">${leave.status.toUpperCase()}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Submitted On</div>
                    <div class="detail-value">${new Date(leave.created_at).toLocaleString()}</div>
                </div>
                ${leave.approved_at ? `
                <div class="detail-item">
                    <div class="detail-label">Processed On</div>
                    <div class="detail-value">${new Date(leave.approved_at).toLocaleString()}</div>
                </div>
                ` : ''}
                <div class="detail-item full-width">
                    <div class="detail-label">Reason</div>
                    <div class="detail-value">${escapeHtml(leave.reason)}</div>
                </div>
                ${leave.contact_number ? `
                <div class="detail-item">
                    <div class="detail-label">Contact Number</div>
                    <div class="detail-value">${escapeHtml(leave.contact_number)}</div>
                </div>
                ` : ''}
                ${leave.alternate_officer ? `
                <div class="detail-item">
                    <div class="detail-label">Alternate Officer</div>
                    <div class="detail-value">${escapeHtml(leave.alternate_officer)}</div>
                </div>
                ` : ''}
                ${leave.approver_remarks ? `
                <div class="detail-item full-width">
                    <div class="detail-label">Approver Remarks</div>
                    <div class="detail-value">${escapeHtml(leave.approver_remarks)}</div>
                </div>
                ` : ''}
            </div>
        `;
        
        const detailsModal = document.getElementById('detailsModal');
        if (detailsModal) detailsModal.style.display = 'block';
    }
    
    // Process leave (approve/reject)
    function processLeave(id, action) {
        const actionLeaveId = document.getElementById('actionLeaveId');
        const actionType = document.getElementById('actionType');
        const actionModalTitle = document.getElementById('actionModalTitle');
        const actionLabel = document.getElementById('actionLabel');
        const actionRemarks = document.getElementById('actionRemarks');
        
        if (actionLeaveId) actionLeaveId.value = id;
        if (actionType) actionType.value = action;
        if (actionModalTitle) actionModalTitle.innerHTML = action === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request';
        if (actionLabel) actionLabel.innerHTML = action === 'approve' ? 'Approver Remarks' : 'Rejection Reason <span class="required-star">*</span>';
        if (actionRemarks) actionRemarks.value = '';
        
        const actionModal = document.getElementById('actionModal');
        if (actionModal) actionModal.style.display = 'block';
    }
    
    // Cancel leave
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
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Leave request cancelled successfully', 'success');
                loadDataFromDatabase(1);
            } else {
                showToast(result.error || 'Failed to cancel leave request', 'error');
            }
        } catch (error) {
            console.error('Error cancelling leave:', error);
            showToast('Error cancelling leave request', 'error');
        }
    }
    
    // Calculate total days
    function calculateDays() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const totalDaysInput = document.getElementById('totalDays');
        
        if (startDate && endDate && totalDaysInput) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            totalDaysInput.value = diffDays;
        }
    }
    
    // Show toast notification
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        
        if (!toast || !toastMessage) return;
        
        toastMessage.textContent = message;
        toast.style.backgroundColor = type === 'success' ? '#1e3a32' : '#dc2626';
        toast.style.display = 'block';
        
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
    
    // Filter by status
    function filterByStatus(status) {
        currentFilter = status;
        currentPage = 1;
        
        const url = new URL(window.location.href);
        url.searchParams.set('filter', status);
        window.history.pushState({}, '', url);
        
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.getAttribute('data-filter') === status) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        loadDataFromDatabase(1);
    }
    
    // Search functionality
    function searchTable() {
        currentPage = 1;
        loadDataFromDatabase(1);
        const clearBtn = document.getElementById('clearSearch');
        const searchInput = document.getElementById('searchInput');
        if (clearBtn && searchInput) {
            clearBtn.style.display = searchInput.value.trim() !== '' ? 'block' : 'none';
        }
        
        const url = new URL(window.location.href);
        if (searchInput.value.trim()) {
            url.searchParams.set('search', searchInput.value.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.history.pushState({}, '', url);
    }
    
    function clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            searchTable();
            searchInput.focus();
        }
    }
    
    // Handle records per page change
    const recordsPerPageSelect = document.getElementById('recordsPerPage');
    if (recordsPerPageSelect) {
        recordsPerPageSelect.addEventListener('change', function() {
            currentPerPage = parseInt(this.value);
            currentPage = 1;
            
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', currentPerPage);
            window.history.pushState({}, '', url);
            
            loadDataFromDatabase(1);
        });
    }
    
    // Modal handlers
    const modal = document.getElementById('leaveModal');
    const actionModal = document.getElementById('actionModal');
    const detailsModal = document.getElementById('detailsModal');
    const newLeaveBtn = document.getElementById('newLeaveBtn');
    
    if (newLeaveBtn) {
        newLeaveBtn.onclick = () => {
            const form = document.getElementById('leaveForm');
            const totalDays = document.getElementById('totalDays');
            
            if (form) form.reset();
            if (totalDays) totalDays.value = '';
            if (modal) modal.style.display = 'block';
        };
    }
    
    document.querySelectorAll('.close, .close-action, .close-details').forEach(btn => {
        btn.onclick = () => {
            if (modal) modal.style.display = 'none';
            if (actionModal) actionModal.style.display = 'none';
            if (detailsModal) detailsModal.style.display = 'none';
        };
    });
    
    document.querySelectorAll('#cancelBtn, #cancelActionBtn').forEach(btn => {
        btn.onclick = () => {
            if (modal) modal.style.display = 'none';
            if (actionModal) actionModal.style.display = 'none';
        };
    });
    
    window.onclick = (event) => {
        if (event.target == modal && modal) modal.style.display = 'none';
        if (event.target == actionModal && actionModal) actionModal.style.display = 'none';
        if (event.target == detailsModal && detailsModal) detailsModal.style.display = 'none';
    };
    
    // Date change listeners
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (startDateInput) {
        startDateInput.addEventListener('change', calculateDays);
        startDateInput.min = new Date().toISOString().split('T')[0];
        startDateInput.addEventListener('change', function() {
            if (endDateInput) {
                endDateInput.min = this.value;
                if (endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                    calculateDays();
                }
            }
        });
    }
    
    if (endDateInput) {
        endDateInput.addEventListener('change', calculateDays);
    }
    
    // Handle leave form submission
    const leaveForm = document.getElementById('leaveForm');
    if (leaveForm) {
        leaveForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const personnelId = document.getElementById('personnelId');
            const leaveType = document.getElementById('leaveType');
            const startDate = document.getElementById('startDate');
            const endDate = document.getElementById('endDate');
            const reason = document.getElementById('reason');
            
            if (!personnelId.value || !leaveType.value || !startDate.value || !endDate.value || !reason.value) {
                showToast('Please fill all required fields', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'submit_leave');
            formData.append('personnel_id', personnelId.value);
            formData.append('leave_type', leaveType.value);
            formData.append('start_date', startDate.value);
            formData.append('end_date', endDate.value);
            formData.append('reason', reason.value);
            formData.append('contact_number', document.getElementById('contactNumber')?.value || '');
            formData.append('alternate_officer', document.getElementById('alternateOfficer')?.value || '');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Leave request submitted successfully', 'success');
                    if (modal) modal.style.display = 'none';
                    loadDataFromDatabase(1);
                } else {
                    showToast(result.error || 'Failed to submit leave request', 'error');
                }
            } catch (error) {
                console.error('Error submitting leave:', error);
                showToast('Error submitting leave request', 'error');
            }
        });
    }
    
    // Handle action form submission (approve/reject)
    const actionForm = document.getElementById('actionForm');
    if (actionForm) {
        actionForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const id = document.getElementById('actionLeaveId')?.value;
            const action = document.getElementById('actionType')?.value;
            const remarks = document.getElementById('actionRemarks')?.value;
            
            if (!id || !action) return;
            
            if (action === 'reject' && !remarks) {
                showToast('Please provide a rejection reason', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', action === 'approve' ? 'approve_leave' : 'reject_leave');
            formData.append('id', id);
            formData.append('approver_remarks', remarks || '');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Leave request ${action}ed successfully`, 'success');
                    if (actionModal) actionModal.style.display = 'none';
                    loadDataFromDatabase(1);
                } else {
                    showToast(result.error || `Failed to ${action} leave request`, 'error');
                }
            } catch (error) {
                console.error(`Error ${action}ing leave:`, error);
                showToast(`Error ${action}ing leave request`, 'error');
            }
        });
    }
    
    // Export to CSV
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            if (!leaveData || leaveData.length === 0) {
                showToast('No data to export', 'error');
                return;
            }
            
            const rows = [['S.N.', 'Personnel Name', 'Rank', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Reason', 'Status', 'Submitted Date']];
            
            const startSerial = ((currentPage - 1) * currentPerPage) + 1;
            leaveData.forEach((leave, index) => {
                rows.push([
                    startSerial + index,
                    leave.personnel_name,
                    leave.rank,
                    leave.leave_type,
                    leave.start_date,
                    leave.end_date,
                    leave.leave_days,
                    leave.reason,
                    leave.status,
                    leave.created_at
                ]);
            });
            
            const csvContent = rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `leave_requests_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('Leave data exported successfully', 'success');
        });
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadDataFromDatabase(1);
        
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                filterByStatus(this.getAttribute('data-filter'));
            });
        });
        
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        
        if (searchInput) searchInput.addEventListener('input', searchTable);
        if (clearSearchBtn) clearSearchBtn.addEventListener('click', clearSearch);
    });
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>