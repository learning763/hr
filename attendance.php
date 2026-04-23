<?php
include('includes/config.php');

$pageTitle = "Military Personnel Status Register";
$pageSubtitle = "Track attendance, leave, work status of military personnel with date/time stamps.";
$activePage = "attendance";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Get all personnel
    if ($action === 'get_all') {
        $stmt = $pdo->query("SELECT * FROM military_personnel_status ORDER BY record_date DESC, id DESC");
        $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $personnel]);
        exit;
    }
    
    // Add new personnel
    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $rank = $_POST['rank'] ?? '';
        $status = $_POST['status'] ?? '';
        $date = $_POST['date'] ?? '';
        $inTime = $_POST['inTime'] ?: null;
        $outTime = $_POST['outTime'] ?: null;
        $remarks = $_POST['remarks'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO military_personnel_status (personnel_name, rank, status, record_date, in_time, out_time, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $rank, $status, $date, $inTime, $outTime, $remarks]);
        
        echo json_encode(['success' => $result, 'id' => $pdo->lastInsertId()]);
        exit;
    }
    
    // Update personnel
    if ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $rank = $_POST['rank'] ?? '';
        $status = $_POST['status'] ?? '';
        $date = $_POST['date'] ?? '';
        $inTime = $_POST['inTime'] ?: null;
        $outTime = $_POST['outTime'] ?: null;
        $remarks = $_POST['remarks'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE military_personnel_status SET personnel_name = ?, rank = ?, status = ?, record_date = ?, in_time = ?, out_time = ?, remarks = ? WHERE id = ?");
        $result = $stmt->execute([$name, $rank, $status, $date, $inTime, $outTime, $remarks, $id]);
        
        echo json_encode(['success' => $result]);
        exit;
    }
    
    // Delete personnel
    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM military_personnel_status WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        echo json_encode(['success' => $result]);
        exit;
    }
}

// Prepare the content
ob_start();
?>

<!-- Search and Action Section -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Search by name, rank, status, or remarks...">
        <button id="clearSearch" class="clear-search" style="display: none;">✕</button>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn-add" id="markStatusBtn">
            <i class="fas fa-plus-circle"></i> Add Personnel
        </button>
        <button class="btn-export" id="exportBtn">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
</div>

<!-- Status Filter Buttons - Military Specific -->
<div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="filter-btn active" data-filter="all">📋 All</button>
    <button class="filter-btn" data-filter="present">✅ Present (Duty)</button>
    <button class="filter-btn" data-filter="leave">🏖️ On Leave</button>
    <button class="filter-btn" data-filter="sick">🤒 Sick Report</button>
    <button class="filter-btn" data-filter="work">💼 Work Detail</button>
    <button class="filter-btn" data-filter="workout">🏃‍♂️ Work-Out / PT</button>
    <button class="filter-btn" data-filter="tdy">✈️ TDY/Temporary Duty</button>
    <button class="filter-btn" data-filter="course">📚 Course/Training</button>
</div>

<div class="data-table">
    <table id="statusTable">
        <thead>
            <tr>
                <th style="width: 50px;">S.No.</th>
                <th>Name</th>
                <th>Rank</th>
                <th style="width: 140px;">Status</th>
                <th>Date</th>
                <th>IN Time</th>
                <th>OUT Time</th>
                <th style="min-width: 200px;">Remarks / Reason</th>
                <th style="width: 100px;">Actions</th>
            </tr>
        </thead>
        <tbody id="statusTableBody">
            <!-- Data will be loaded dynamically from database -->
        </tbody>
    </table>
</div>

<!-- No Results Message -->
<div id="noResults" style="display: none; text-align: center; padding: 40px; color: #6c7a8e;">
    <i class="fas fa-search" style="font-size: 48px; margin-bottom: 10px;"></i>
    <p>No personnel records found matching your search criteria.</p>
</div>

<!-- Modal for Add/Edit Personnel -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Personnel</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="statusForm">
                <input type="hidden" id="recordId" value="">
                <div class="form-grid">
                    <div class="input-field">
                        <label><i class="fas fa-user"></i> Personnel Name <span class="required-star">*</span></label>
                        <input type="text" id="personnelName" placeholder="Enter full name with rank" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-star-of-life"></i> Rank <span class="required-star">*</span></label>
                        <input type="text" id="rank" placeholder="e.g., Colonel, Major, Captain" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-calendar-alt"></i> Date <span class="required-star">*</span></label>
                        <input type="date" id="recordDate" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-tag"></i> Status <span class="required-star">*</span></label>
                        <select id="status" required onchange="toggleTimeFields()">
                            <option value="" disabled selected>Select status</option>
                            <option value="present">✅ Present (Duty)</option>
                            <option value="leave">🏖️ On Leave</option>
                            <option value="sick">🤒 Sick Report</option>
                            <option value="work">💼 Work Detail</option>
                            <option value="workout">🏃‍♂️ Work-Out / PT</option>
                            <option value="tdy">✈️ TDY/Temporary Duty</option>
                            <option value="course">📚 Course/Training</option>
                        </select>
                    </div>
                    <div class="input-field" id="inTimeField" style="display: none;">
                        <label><i class="fas fa-clock"></i> IN Time</label>
                        <input type="time" id="inTime">
                    </div>
                    <div class="input-field" id="outTimeField" style="display: none;">
                        <label><i class="fas fa-clock"></i> OUT Time</label>
                        <input type="time" id="outTime">
                    </div>
                    <div class="input-field full-width">
                        <label><i class="fas fa-sticky-note"></i> Remarks / Reason <span class="required-star">*</span></label>
                        <textarea id="remarks" rows="3" placeholder="For Present/Work Detail: Specify duties&#10;For Leave/Sick: Specify leave details and duration&#10;For Work-Out: Specify PT/exercise details&#10;For TDY/Course: Specify location and duration" required></textarea>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none;">
    <span id="toastMessage"></span>
</div>

<!-- Summary Cards -->
<div class="summary-cards" style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-users"></i></div>
        <div class="summary-info">
            <div class="summary-label">Total Personnel</div>
            <div class="summary-value" id="totalPersonnel">0</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-check-circle"></i></div>
        <div class="summary-info">
            <div class="summary-label">Present (Duty)</div>
            <div class="summary-value" id="presentCount">0</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div class="summary-info">
            <div class="summary-label">On Leave</div>
            <div class="summary-value" id="leaveCount">0</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-thermometer-half"></i></div>
        <div class="summary-info">
            <div class="summary-label">Sick Report</div>
            <div class="summary-value" id="sickCount">0</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-briefcase"></i></div>
        <div class="summary-info">
            <div class="summary-label">Work Detail</div>
            <div class="summary-value" id="workCount">0</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-running"></i></div>
        <div class="summary-info">
            <div class="summary-label">Work-Out/PT</div>
            <div class="summary-value" id="workoutCount">0</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-plane"></i></div>
        <div class="summary-info">
            <div class="summary-label">TDY</div>
            <div class="summary-value" id="tdyCount">0</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="summary-info">
            <div class="summary-label">Course/Training</div>
            <div class="summary-value" id="courseCount">0</div>
        </div>
    </div>
</div>

<style>
    /* Search Container Styles */
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
        transition: all 0.2s;
    }
    
    .clear-search:hover {
        background: #e2e8f0;
        color: #c2410c;
    }
    
    /* Filter Buttons */
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
    
    /* Button Styles */
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
    
    /* Action Buttons */
    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        padding: 5px 8px;
        margin: 0 3px;
        border-radius: 4px;
        transition: all 0.2s;
        font-size: 14px;
    }
    
    .edit-btn {
        color: #2c5f4e;
    }
    
    .edit-btn:hover {
        background: #e8f5f0;
        transform: scale(1.1);
    }
    
    .delete-btn {
        color: #dc2626;
    }
    
    .delete-btn:hover {
        background: #fee2e2;
        transform: scale(1.1);
    }
    
    /* Badge Styles - Military Specific */
    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .badge-present {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-leave {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .badge-sick {
        background: #fef3c7;
        color: #92400e;
    }
    
    .badge-work {
        background: #e0e7ff;
        color: #3730a3;
    }
    
    .badge-workout {
        background: #fed7aa;
        color: #9a3412;
    }
    
    .badge-tdy {
        background: #e0f2fe;
        color: #075985;
    }
    
    .badge-course {
        background: #f3e8ff;
        color: #6b21a5;
    }
    
    /* Summary Cards */
    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #eef2f6;
    }
    
    .summary-icon {
        width: 48px;
        height: 48px;
        background: #f0fdf4;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #2c5f4e;
    }
    
    .summary-info {
        flex: 1;
    }
    
    .summary-label {
        font-size: 11px;
        color: #6c7a8e;
        margin-bottom: 4px;
    }
    
    .summary-value {
        font-size: 20px;
        font-weight: 700;
        color: #1a2c3e;
    }
    
    /* Table Styles */
    .data-table {
        overflow-x: auto;
    }
    
    .data-table table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
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
    
    /* Modal Styles */
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
    
    .close {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #9aa9bc;
        transition: 0.2s;
    }
    
    .close:hover {
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
    
    /* Toast Notification */
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
    
    /* Highlight matching text */
    .highlight {
        background-color: #fef3c7;
        font-weight: 600;
    }
    
    @media (max-width: 700px) {
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
        .summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-btn {
            font-size: 11px;
            padding: 6px 12px;
        }
    }
</style>

<script>
    let personnelData = [];
    let currentFilter = 'all';
    
    // Set today's date as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('recordDate').value = today;
    
    // Modal elements
    const modal = document.getElementById('statusModal');
    const markBtn = document.getElementById('markStatusBtn');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.getElementById('cancelBtn');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('statusForm');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearch');
    const tableBody = document.getElementById('statusTableBody');
    const noResultsDiv = document.getElementById('noResults');
    const table = document.getElementById('statusTable');
    
    let isEditing = false;
    let editingId = null;
    
    // Load data from database
    async function loadDataFromDatabase() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_all');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            if (result.success) {
                personnelData = result.data;
                renderTable();
                updateSummary();
            } else {
                showToast('Failed to load data', 'error');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            showToast('Error loading data from database', 'error');
        }
    }
    
    // Status badge mapping
    function getStatusBadge(status) {
        const icons = {
            'present': '<i class="fas fa-check-circle"></i> Present (Duty)',
            'leave': '<i class="fas fa-umbrella-beach"></i> On Leave',
            'sick': '<i class="fas fa-thermometer-half"></i> Sick Report',
            'work': '<i class="fas fa-briefcase"></i> Work Detail',
            'workout': '<i class="fas fa-running"></i> Work-Out / PT',
            'tdy': '<i class="fas fa-plane"></i> TDY/Temporary Duty',
            'course': '<i class="fas fa-graduation-cap"></i> Course/Training'
        };
        
        const badgeClasses = {
            'present': 'badge-present',
            'leave': 'badge-leave',
            'sick': 'badge-sick',
            'work': 'badge-work',
            'workout': 'badge-workout',
            'tdy': 'badge-tdy',
            'course': 'badge-course'
        };
        
        return `<span class="badge ${badgeClasses[status]}">${icons[status] || status}</span>`;
    }
    
    // Render table with data
    function renderTable() {
        tableBody.innerHTML = '';
        let filteredData = personnelData;
        
        // Apply status filter
        if (currentFilter !== 'all') {
            filteredData = personnelData.filter(person => person.status === currentFilter);
        }
        
        // Apply search filter
        const searchTerm = searchInput.value.toLowerCase().trim();
        if (searchTerm) {
            filteredData = filteredData.filter(person => 
                person.personnel_name.toLowerCase().includes(searchTerm) ||
                person.rank.toLowerCase().includes(searchTerm) ||
                getStatusText(person.status).toLowerCase().includes(searchTerm) ||
                person.remarks.toLowerCase().includes(searchTerm)
            );
        }
        
        if (filteredData.length === 0) {
            noResultsDiv.style.display = 'block';
            table.style.display = 'none';
            return;
        }
        
        noResultsDiv.style.display = 'none';
        table.style.display = 'table';
        
        filteredData.forEach((person, index) => {
            const row = tableBody.insertRow();
            const inTimeDisplay = person.in_time ? person.in_time : '-';
            const outTimeDisplay = person.out_time ? person.out_time : '-';
            
            row.setAttribute('data-status', person.status);
            row.setAttribute('data-id', person.id);
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>${person.personnel_name}</td>
                <td>${person.rank}</td>
                <td>${getStatusBadge(person.status)}</td>
                <td>${person.record_date}</td>
                <td>${inTimeDisplay}</td>
                <td>${outTimeDisplay}</td>
                <td>${person.remarks}</td>
                <td>
                    <button class="btn-icon edit-btn" onclick='editPersonnel(${JSON.stringify(person)})'><i class="fas fa-edit"></i></button>
                    <button class="btn-icon delete-btn" onclick="deletePersonnel(${person.id})"><i class="fas fa-trash"></i></button>
                </td>
            `;
            
            // Apply highlight if search term exists
            if (searchTerm) {
                highlightText(row.cells[1], searchTerm);
                highlightText(row.cells[2], searchTerm);
                highlightText(row.cells[7], searchTerm);
            }
        });
    }
    
    function getStatusText(status) {
        const statusMap = {
            'present': 'Present (Duty)',
            'leave': 'On Leave',
            'sick': 'Sick Report',
            'work': 'Work Detail',
            'workout': 'Work-Out / PT',
            'tdy': 'TDY/Temporary Duty',
            'course': 'Course/Training'
        };
        return statusMap[status] || status;
    }
    
    function highlightText(cell, searchTerm) {
        const originalText = cell.textContent;
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        if (originalText.toLowerCase().includes(searchTerm)) {
            cell.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
        }
    }
    
    // Toggle time fields based on status
    function toggleTimeFields() {
        const status = document.getElementById('status').value;
        const inTimeField = document.getElementById('inTimeField');
        const outTimeField = document.getElementById('outTimeField');
        
        if (status === 'present' || status === 'work') {
            inTimeField.style.display = 'block';
            outTimeField.style.display = 'none';
            document.getElementById('outTime').value = '';
        } else if (status === 'workout') {
            inTimeField.style.display = 'block';
            outTimeField.style.display = 'block';
        } else {
            inTimeField.style.display = 'none';
            outTimeField.style.display = 'none';
            document.getElementById('inTime').value = '';
            document.getElementById('outTime').value = '';
        }
    }
    
    // Show toast notification
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        toastMessage.textContent = message;
        toast.style.backgroundColor = type === 'success' ? '#1e3a32' : '#dc2626';
        toast.style.display = 'block';
        
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
    
    // Update summary statistics
    function updateSummary() {
        let total = personnelData.length;
        let presentCount = 0, leaveCount = 0, sickCount = 0, workCount = 0, workoutCount = 0, tdyCount = 0, courseCount = 0;
        
        personnelData.forEach(person => {
            switch(person.status) {
                case 'present': presentCount++; break;
                case 'leave': leaveCount++; break;
                case 'sick': sickCount++; break;
                case 'work': workCount++; break;
                case 'workout': workoutCount++; break;
                case 'tdy': tdyCount++; break;
                case 'course': courseCount++; break;
            }
        });
        
        document.getElementById('totalPersonnel').textContent = total;
        document.getElementById('presentCount').textContent = presentCount;
        document.getElementById('leaveCount').textContent = leaveCount;
        document.getElementById('sickCount').textContent = sickCount;
        document.getElementById('workCount').textContent = workCount;
        document.getElementById('workoutCount').textContent = workoutCount;
        document.getElementById('tdyCount').textContent = tdyCount;
        document.getElementById('courseCount').textContent = courseCount;
    }
    
    // Filter by status
    function filterByStatus(status) {
        currentFilter = status;
        renderTable();
        
        // Update active button style
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.getAttribute('data-filter') === status) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
    
    // Search functionality
    function searchTable() {
        renderTable();
        clearSearchBtn.style.display = searchInput.value.trim() !== '' ? 'block' : 'none';
    }
    
    function clearSearch() {
        searchInput.value = '';
        searchTable();
        searchInput.focus();
    }
    
    // Open modal for adding
    markBtn.onclick = function() {
        isEditing = false;
        editingId = null;
        modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add Personnel';
        form.reset();
        document.getElementById('recordId').value = '';
        document.getElementById('recordDate').value = today;
        document.getElementById('inTimeField').style.display = 'none';
        document.getElementById('outTimeField').style.display = 'none';
        modal.style.display = 'block';
    }
    
    function closeModal() {
        modal.style.display = 'none';
        form.reset();
        isEditing = false;
        editingId = null;
    }
    
    closeBtn.onclick = closeModal;
    cancelBtn.onclick = closeModal;
    
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    
    function editPersonnel(person) {
        isEditing = true;
        editingId = person.id;
        modalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Personnel';
        
        document.getElementById('recordId').value = person.id;
        document.getElementById('personnelName').value = person.personnel_name;
        document.getElementById('rank').value = person.rank;
        document.getElementById('recordDate').value = person.record_date;
        document.getElementById('status').value = person.status;
        document.getElementById('inTime').value = person.in_time || '';
        document.getElementById('outTime').value = person.out_time || '';
        document.getElementById('remarks').value = person.remarks;
        
        toggleTimeFields();
        modal.style.display = 'block';
    }
    
    async function deletePersonnel(id) {
        if (confirm('Are you sure you want to delete this record?')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const result = await response.json();
                if (result.success) {
                    showToast('Record deleted successfully', 'success');
                    await loadDataFromDatabase();
                } else {
                    showToast('Failed to delete record', 'error');
                }
            } catch (error) {
                console.error('Error deleting:', error);
                showToast('Error deleting record', 'error');
            }
        }
    }
    
    // Export to CSV
    document.getElementById('exportBtn').addEventListener('click', function() {
        const rows = [];
        const headers = ['S.No.', 'Name', 'Rank', 'Status', 'Date', 'IN Time', 'OUT Time', 'Remarks'];
        rows.push(headers);
        
        personnelData.forEach((person, index) => {
            const statusText = getStatusText(person.status);
            const inTimeDisplay = person.in_time || '-';
            const outTimeDisplay = person.out_time || '-';
            
            rows.push([
                index + 1,
                person.personnel_name,
                person.rank,
                statusText,
                person.record_date,
                inTimeDisplay,
                outTimeDisplay,
                person.remarks
            ]);
        });
        
        const csvContent = rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `military_status_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        showToast('Personnel data exported successfully', 'success');
    });
    
    // Handle form submission
    form.onsubmit = async function(e) {
        e.preventDefault();
        
        const id = document.getElementById('recordId').value;
        const name = document.getElementById('personnelName').value;
        const rank = document.getElementById('rank').value;
        const date = document.getElementById('recordDate').value;
        const status = document.getElementById('status').value;
        const inTime = document.getElementById('inTime').value || '';
        const outTime = document.getElementById('outTime').value || '';
        const remarks = document.getElementById('remarks').value;
        
        if (!name || !rank || !date || !status || !remarks) {
            showToast('Please fill all required fields', 'error');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', isEditing ? 'update' : 'add');
            formData.append('name', name);
            formData.append('rank', rank);
            formData.append('status', status);
            formData.append('date', date);
            formData.append('inTime', inTime);
            formData.append('outTime', outTime);
            formData.append('remarks', remarks);
            if (isEditing) {
                formData.append('id', id);
            }
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            if (result.success) {
                showToast(isEditing ? 'Record updated successfully' : 'Record added successfully', 'success');
                closeModal();
                await loadDataFromDatabase();
            } else {
                showToast('Failed to save record', 'error');
            }
        } catch (error) {
            console.error('Error saving:', error);
            showToast('Error saving record', 'error');
        }
    }
    
    // Initialize
    loadDataFromDatabase();
    
    // Filter button event listeners
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            filterByStatus(this.getAttribute('data-filter'));
        });
    });
    
    // Event listeners for search
    searchInput.addEventListener('input', searchTable);
    clearSearchBtn.addEventListener('click', clearSearch);
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>