<?php
session_start();
include('includes/config.php');
include('helper/rank.php');

$pageTitle = "हाजिर विवरण";
$pageSubtitle = "Track attendance, leave, and work status";
$activePage = "attendance";

// Get user role from session
$user_role = isset($_SESSION['user_role']) ? (int) $_SESSION['user_role'] : 0;
$isSuperAdmin = ($user_role === 2);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch personnel for dropdown (only needed for Super Admin)
$personnelList = [];
if ($isSuperAdmin) {
    $personnelList = $pdo->query("
    SELECT
        p.personnel_number,
        p.full_name_en,
        p.rank,
        dr.rank_unicode
    FROM personnel p
    LEFT JOIN def_rank dr
        ON p.rank = dr.rank_code
    ORDER BY p.full_name_en ASC
")->fetchAll(PDO::FETCH_ASSOC);
    // $personnelList = $pdo->query("SELECT personnel_number, full_name_en, rank FROM personnel ORDER BY full_name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'get_records') {
        $date = $_POST['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM military_personnel_status WHERE record_date = ? ORDER BY id DESC");
        $stmt->execute([$date]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // Only Super Admin can add/update/delete
    if ($isSuperAdmin) {
        if ($action === 'add' || $action === 'update') {
            $id = $_POST['id'] ?? 0;
            $data = [
                $_POST['personnel_number'],
                $_POST['personnel_name'],
                $_POST['rank'],
                $_POST['status'],
                $_POST['date'],
                $_POST['inTime'] ?: null,
                $_POST['outTime'] ?: null,
                $_POST['remarks']
            ];

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO military_personnel_status (personnel_number, personnel_name, rank, status, record_date, in_time, out_time, remarks) VALUES (?,?,?,?,?,?,?,?)");
            } else {
                $stmt = $pdo->prepare("UPDATE military_personnel_status SET personnel_number=?, personnel_name=?, rank=?, status=?, record_date=?, in_time=?, out_time=?, remarks=? WHERE id=?");
                $data[] = $id;
            }
            echo json_encode(['success' => $stmt->execute($data)]);
            exit;
        }

        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM military_personnel_status WHERE id=?");
            echo json_encode(['success' => $stmt->execute([$_POST['id']])]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

ob_start();
?>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        background: #f0f2f5;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Search & Action Bar */
    .action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .search-group {
        display: flex;
        gap: 10px;
        flex: 1;
        max-width: 500px;
    }

    .search-box {
        position: relative;
        flex: 1;
    }

    .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    .search-input {
        width: 100%;
        padding: 10px 15px 10px 38px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
    }

    .search-input:focus {
        border-color: #1e3a32;
    }

    .date-filter {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .date-filter label {
        font-size: 13px;
        font-weight: 500;
        color: #475569;
    }

    .date-input {
        padding: 9px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
        cursor: pointer;
    }

    .date-input:focus {
        border-color: #1e3a32;
    }

    /* Buttons */
    .btn {
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: #1e3a32;
        color: white;
    }

    .btn-primary:hover {
        background: #14362c;
    }

    .btn-secondary {
        background: #2c5f4e;
        color: white;
    }

    /* Filter Pills with Counts */
    .filter-pills {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .pill {
        padding: 8px 18px;
        border: 1.5px solid #e2e8f0;
        background: white;
        border-radius: 30px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .pill:hover {
        background: #f1f5f9;
        border-color: #1e3a32;
    }

    .pill.active {
        background: #1e3a32;
        color: white;
        border-color: #1e3a32;
    }

    .pill.active .pill-count {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .pill-count {
        background: #e2e8f0;
        color: #475569;
        border-radius: 20px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 600;
        min-width: 28px;
        text-align: center;
    }

    /* Table - Fixed Layout */
    .data-table {
        background: white;
        border-radius: 12px;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        min-width: 800px;
    }

    th {
        text-align: left;
        padding: 12px 8px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        color: #475569;
        white-space: nowrap;
    }

    td {
        padding: 10px 8px;
        border-bottom: 1px solid #eef2f6;
        font-size: 13px;
        vertical-align: middle;
    }

    tr:hover {
        background: #f8fafc;
    }

    /* Column specific styles */
    .col-sno {
        width: 45px;
        text-align: center;
    }

    .col-personnel {
        width: 110px;
    }

    .col-name {
        width: 130px;
    }

    .col-rank {
        width: 90px;
    }

    .col-status {
        width: 100px;
    }

    .col-time {
        width: 55px;
        text-align: center;
    }

    .col-remarks {
        width: auto;
    }

    .col-actions {
        width: 80px;
        text-align: center;
    }

    /* Text overflow handling */
    .cell-ellipsis {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Remarks cell */
    .remarks-cell {
        word-break: break-word;
        white-space: normal;
        line-height: 1.4;
        cursor: help;
    }

    /* Badges */
    .badge {
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
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

    /* Action Icons */
    .action-icons {
        display: flex;
        gap: 5px;
        justify-content: center;
    }

    .action-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 5px 8px;
        border-radius: 6px;
        font-size: 14px;
    }

    .action-btn.edit {
        color: #1e3a32;
    }

    .action-btn.edit:hover {
        background: #e8f5f0;
    }

    .action-btn.delete {
        color: #dc2626;
    }

    .action-btn.delete:hover {
        background: #fee2e2;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background: white;
        margin: 5% auto;
        width: 90%;
        max-width: 700px;
        border-radius: 16px;
        max-height: 85vh;
        overflow-y: auto;
    }

    .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        background: white;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        color: #1e3a32;
    }

    .modal-close {
        font-size: 24px;
        cursor: pointer;
        color: #9ca3af;
    }

    .modal-close:hover {
        color: #dc2626;
    }

    .modal-body {
        padding: 20px;
    }

    /* Form */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .form-group.full {
        grid-column: span 2;
    }

    .form-group label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }

    .form-control {
        padding: 8px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
    }

    .form-control:focus {
        border-color: #1e3a32;
    }

    .form-control:read-only {
        background: #f8fafc;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-cancel {
        padding: 8px 20px;
        background: #f1f5f9;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    .btn-save {
        padding: 8px 24px;
        background: #1e3a32;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #1e3a32;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        display: none;
        z-index: 1100;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #6c7a8e;
    }

    .readonly-message {
        text-align: center;
        padding: 12px;
        background: #fef3c7;
        color: #92400e;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 13px;
    }

    @media (max-width: 700px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-group.full {
            grid-column: span 1;
        }

        .action-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .search-group {
            max-width: 100%;
        }

        .date-filter {
            justify-content: flex-end;
        }

        .pill {
            padding: 6px 12px;
            font-size: 12px;
        }

        .pill-count {
            padding: 1px 6px;
            font-size: 10px;
        }

        td,
        th {
            padding: 8px 6px;
            font-size: 12px;
        }

        .badge {
            padding: 2px 6px;
            font-size: 10px;
        }

        .col-personnel {
            width: 90px;
        }

        .col-name {
            width: 100px;
        }
    }
</style>

<!-- Read-only warning for non-admin users -->
<?php if (!$isSuperAdmin): ?>
    <div class="readonly-message">
        <i class="fas fa-info-circle"></i> You are in read-only mode. Contact administrator to add or edit attendance
        records.
    </div>
<?php endif; ?>

<!-- Search & Actions -->
<div class="action-bar">
    <div class="search-group">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by name, rank or number...">
        </div>
        <div class="date-filter">
            <label><i class="fas fa-calendar"></i></label>
            <input type="date" id="dateFilter" class="date-input" value="<?php echo date('Y-m-d'); ?>">
        </div>
    </div>
    <?php if ($isSuperAdmin): ?>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-primary" id="addBtn"><i class="fas fa-plus"></i> Add Status</button>
            <button class="btn btn-secondary" id="exportBtn"><i class="fas fa-download"></i> Export</button>
        </div>
    <?php endif; ?>
</div>

<!-- Status Filter Pills with Counts -->
<div class="filter-pills">
    <button class="pill active" data-filter="all">
        📋 सकलदर्जा <span class="pill-count" id="countAll">0</span>
    </button>
    <button class="pill" data-filter="present">
        ✅ हाजिर <span class="pill-count" id="countPresent">0</span>
    </button>
    <button class="pill" data-filter="leave">
        🏖️ बिदा <span class="pill-count" id="countLeave">0</span>
    </button>
    <button class="pill" data-filter="sick">
        🤒 बिरामी <span class="pill-count" id="countSick">0</span>
    </button>
    <button class="pill" data-filter="work">
        💼 तालिम‍(स्वदेश) <span class="pill-count" id="countWork">0</span>
    </button>
    <button class="pill" data-filter="workout">
        ✈️ तालिम‍(बिदेश) <span class="pill-count" id="countWorkout">0</span>
    </button>
    <button class="pill" data-filter="tdy">
        ✈️ शा.से. <span class="pill-count" id="countTdy">0</span>
    </button>

</div>

<!-- Table -->
<div class="data-table">
    <table id="statusTable" style="table-layout: fixed;">
        <colgroup>
            <col class="col-sno">
            <col class="col-personnel">
            <col class="col-name">
            <col class="col-rank">
            <col class="col-status">
            <col class="col-time">
            <col class="col-time">
            <col class="col-remarks">
            <?php if ($isSuperAdmin): ?>
                <col class="col-actions">
            <?php endif; ?>
        </colgroup>
        <thead>
            <tr>
                <th>सि.नं.</th>
                <th>व्य.नं.</th>
                <th>दर्जा</th>
                <th>नामथर</th>
                <th>Status</th>
                <th>IN</th>
                <th>OUT</th>
                <th>कैफियत</th>
                <?php if ($isSuperAdmin): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>
<div id="noResults" class="empty-state" style="display:none">
    <i class="fas fa-info-circle"></i>
    <p>No attendance records for selected date</p>
    <?php if (!$isSuperAdmin): ?>
        <p style="font-size: 12px; margin-top: 10px;">Contact administrator to mark attendance</p>
    <?php else: ?>
        <p style="font-size: 12px; margin-top: 10px;">Click "Add Status" to mark attendance</p>
    <?php endif; ?>
</div>

<!-- Modal (Only for Super Admin) -->
<?php if ($isSuperAdmin): ?>
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-plus"></i> Add Status</h3><span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" id="recordId">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>सैनिक व्यक्ति छान्नुहोस् *</label>
                            <select id="personnelSelect" class="form-control" required>
                                <option value="">-- Select --</option>

                                <?php foreach ($personnelList as $p): ?>
                                    <option value="<?php echo $p['personnel_number']; ?>"
                                        data-name="<?php echo htmlspecialchars($p['full_name_en']); ?>"
                                        data-rank="<?php echo htmlspecialchars($p['rank_unicode']); ?>">

                                        <?php echo htmlspecialchars(
                                            $p['personnel_number'] . ' - ' .
                                            $p['rank_unicode'] . ' ' .
                                            $p['full_name_en']
                                        ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group"><label>दर्जा</label><input type="text" id="rank" class="form-control"
                                readonly></div>
                        <div class="form-group"><label>नामथर</label><input type="text" id="personnelName"
                                class="form-control" readonly></div>
                        <div class="form-group"><label>मिति *</label><input type="date" id="recordDate" class="form-control"
                                required></div>
                        <div class="form-group"><label>Status *</label>
                            <select id="status" class="form-control" required>
                                <option value="">Select Status</option>
                                <option value="present">हाजिर</option>
                                <option value="leave">बिदा</option>
                                <option value="sick">बिरामी</option>
                                <option value="work">तालिम स्वादेश</option>
                                <option value="workout">तालिम विदेश</option>
                                <option value="tdy">शा.से.</option>
                                <!-- <option value="course">Course</option> -->
                            </select>
                        </div>
                        <div class="form-group" id="inTimeGroup" style="display:none"><label>IN Time</label><input
                                type="time" id="inTime" class="form-control"></div>
                        <div class="form-group" id="outTimeGroup" style="display:none"><label>OUT Time</label><input
                                type="time" id="outTime" class="form-control"></div>
                        <div class="form-group full"><label>कैफियत *</label><textarea id="remarks" class="form-control"
                                rows="2" required placeholder="Enter remarks..."></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn-cancel" id="cancelBtn">Cancel</button><button
                            type="submit" class="btn-save">Save</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="toast" class="toast"></div>

<script>
    let allData = [], currentFilter = 'all';
    let currentDate = '<?php echo date('Y-m-d'); ?>';
    const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;

    <?php if ($isSuperAdmin): ?>
        const modal = document.getElementById('modal');
        const addBtn = document.getElementById('addBtn');
        const exportBtn = document.getElementById('exportBtn');
        const closeModal = document.querySelector('.modal-close');
        const cancelBtn = document.getElementById('cancelBtn');
        const form = document.getElementById('statusForm');
        let isEditing = false, editId = null;
    <?php endif; ?>

    // Load data for selected date
    async function loadData() {
        const fd = new FormData();
        fd.append('action', 'get_records');
        fd.append('date', currentDate);
        const res = await fetch(window.location.href, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            allData = data.data;
            updateCounts();
            render();
        }
    }

    // Update count badges
    function updateCounts() {
        const counts = {
            all: allData.length,
            present: 0,
            leave: 0,
            sick: 0,
            work: 0,
            workout: 0,
            tdy: 0,
            course: 0
        };

        allData.forEach(item => {
            switch (item.status) {
                case 'present': counts.present++; break;
                case 'leave': counts.leave++; break;
                case 'sick': counts.sick++; break;
                case 'work': counts.work++; break;
                case 'workout': counts.workout++; break;
                case 'tdy': counts.tdy++; break;
                case 'course': counts.course++; break;
            }
        });

        document.getElementById('countAll').textContent = counts.all;
        document.getElementById('countPresent').textContent = counts.present;
        document.getElementById('countLeave').textContent = counts.leave;
        document.getElementById('countSick').textContent = counts.sick;
        document.getElementById('countWork').textContent = counts.work;
        document.getElementById('countWorkout').textContent = counts.workout;
        document.getElementById('countTdy').textContent = counts.tdy;
        document.getElementById('countCourse').textContent = counts.course;
    }

    // Helper function to clean text (remove line breaks)
    function cleanText(text) {
        if (!text) return '';
        return String(text).replace(/[\n\r]+/g, ' ').trim();
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function render() {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        let filtered = allData;

        if (currentFilter !== 'all') {
            filtered = filtered.filter(p => p.status === currentFilter);
        }

        const term = document.getElementById('searchInput').value.toLowerCase();
        if (term) {
            filtered = filtered.filter(p =>
                (p.personnel_name?.toLowerCase().includes(term)) ||
                (p.rank?.toLowerCase().includes(term)) ||
                (p.personnel_number?.toLowerCase().includes(term))
            );
        }

        if (filtered.length === 0) {
            document.getElementById('noResults').style.display = 'block';
            document.getElementById('statusTable').style.display = 'none';
            return;
        }

        document.getElementById('noResults').style.display = 'none';
        document.getElementById('statusTable').style.display = 'table';

        let idx = 0;
        filtered.forEach(p => {
            const row = tbody.insertRow();
            idx++;

            // Clean and escape all text fields
            const personnelNumber = cleanText(p.personnel_number || '-');
            const personnelName = cleanText(p.personnel_name || '-');
            const personnelRank = cleanText(p.rank || '-');
            const remarks = cleanText(p.remarks || '');

            // Truncate remarks if too long
            const shortRemarks = remarks.length > 65 ? remarks.substring(0, 65) + '...' : remarks;

            const badgeClass = {
                present: 'badge-present', leave: 'badge-leave', sick: 'badge-sick',
                work: 'badge-work', workout: 'badge-workout', tdy: 'badge-tdy', course: 'badge-course'
            }[p.status] || '';
            const badgeText = {
                present: 'Present', leave: 'Leave', sick: 'Sick', work: 'Work',
                workout: 'PT', tdy: 'TDY', course: 'Course'
            }[p.status] || p.status;

            let actionsHtml = '';
            if (isSuperAdmin) {
                actionsHtml = `<div class="action-icons">
                <button class="action-btn edit" onclick='editRecord(${JSON.stringify(p).replace(/'/g, "&#39;")})' title="Edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete" onclick="deleteRecord(${p.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </div>`;
            }

            // Create row with proper styling
            const snoCell = row.insertCell(0);
            const personnelNoCell = row.insertCell(1);
            const nameCell = row.insertCell(2);
            const rankCell = row.insertCell(3);
            const statusCell = row.insertCell(4);
            const inCell = row.insertCell(5);
            const outCell = row.insertCell(6);
            const remarksCell = row.insertCell(7);

            // Set cell contents with proper classes
            snoCell.textContent = idx;
            snoCell.style.textAlign = 'center';

            personnelNoCell.textContent = personnelNumber;
            personnelNoCell.className = 'cell-ellipsis';
            personnelNoCell.title = personnelNumber;

            nameCell.textContent = personnelName;
            nameCell.className = 'cell-ellipsis';
            nameCell.title = personnelName;

            rankCell.textContent = personnelRank;
            rankCell.className = 'cell-ellipsis';

            statusCell.innerHTML = `<span class="badge ${badgeClass}">${badgeText}</span>`;

            inCell.textContent = p.in_time || '-';
            inCell.style.textAlign = 'center';

            outCell.textContent = p.out_time || '-';
            outCell.style.textAlign = 'center';

            remarksCell.textContent = shortRemarks;
            remarksCell.className = 'remarks-cell';
            if (remarks.length > 65) {
                remarksCell.title = remarks;
            }

            if (isSuperAdmin) {
                const actionsCell = row.insertCell(8);
                actionsCell.innerHTML = actionsHtml;
                actionsCell.style.textAlign = 'center';
            }
        });
    }

    function showToast(msg, type = 'success') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.background = type === 'success' ? '#1e3a32' : '#dc2626';
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }

    <?php if ($isSuperAdmin): ?>
    // Personnel select
    const personnelSelect = document.getElementById('personnelSelect');
    if (personnelSelect) {
        personnelSelect.onchange = function () {
            const opt = this.options[this.selectedIndex];
            document.getElementById('personnelName').value = opt.dataset.name || '';
            document.getElementById('rank').value = opt.dataset.rank || '';
        };
    }

    // Set today's date for new records
    const recordDateInput = document.getElementById('recordDate');
    if (recordDateInput) {
        recordDateInput.value = new Date().toISOString().split('T')[0];
    }

    // Toggle time fields
    function toggleTimeFields() {
        const status = document.getElementById('status').value;
        const inGroup = document.getElementById('inTimeGroup');
        const outGroup = document.getElementById('outTimeGroup');
        if (status === 'present' || status === 'work') {
            inGroup.style.display = 'block';
            outGroup.style.display = 'none';
            document.getElementById('outTime').value = '';
        } else if (status === 'workout') {
            inGroup.style.display = 'block';
            outGroup.style.display = 'block';
        } else {
            inGroup.style.display = 'none';
            outGroup.style.display = 'none';
            document.getElementById('inTime').value = '';
            document.getElementById('outTime').value = '';
        }
    }

    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.onchange = toggleTimeFields;
    }

    // Modal handlers
    if (addBtn) {
        addBtn.onclick = () => {
            isEditing = false;
            editId = null;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Status';
            form.reset();
            document.getElementById('recordId').value = '';
            document.getElementById('personnelSelect').value = '';
            document.getElementById('personnelName').value = '';
            document.getElementById('rank').value = '';
            document.getElementById('recordDate').value = currentDate;
            document.getElementById('remarks').value = '';
            modal.style.display = 'block';
        };
    }

    function closeModalFunc() {
        modal.style.display = 'none';
    }
    if (closeModal) closeModal.onclick = closeModalFunc;
    if (cancelBtn) cancelBtn.onclick = closeModalFunc;
    window.onclick = e => { if (e.target === modal) closeModalFunc(); };

    window.editRecord = (p) => {
        isEditing = true;
        editId = p.id;
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Status';
        document.getElementById('recordId').value = p.id;
        document.getElementById('personnelSelect').value = p.personnel_number;
        const changeEvent = new Event('change');
        document.getElementById('personnelSelect').dispatchEvent(changeEvent);
        document.getElementById('recordDate').value = p.record_date;
        document.getElementById('status').value = p.status;
        document.getElementById('inTime').value = p.in_time || '';
        document.getElementById('outTime').value = p.out_time || '';
        document.getElementById('remarks').value = p.remarks;
        toggleTimeFields();
        modal.style.display = 'block';
    };

    window.deleteRecord = async (id) => {
        if (!confirm('Delete this record?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        const res = await fetch(window.location.href, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            showToast('Record deleted');
            loadData();
        } else {
            showToast('Delete failed', 'error');
        }
    };

    // Form submit
    if (form) {
        form.onsubmit = async (e) => {
            e.preventDefault();

            const personnel_number = document.getElementById('personnelSelect').value;
            const personnel_name = document.getElementById('personnelName').value;
            const rank = document.getElementById('rank').value;
            const date = document.getElementById('recordDate').value;
            const status = document.getElementById('status').value;
            const inTime = document.getElementById('inTime').value || '';
            const outTime = document.getElementById('outTime').value || '';
            const remarks = document.getElementById('remarks').value;

            if (!personnel_number || !personnel_name || !rank || !status || !remarks) {
                showToast('Please fill all required fields', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('action', isEditing ? 'update' : 'add');
            fd.append('personnel_number', personnel_number);
            fd.append('personnel_name', personnel_name);
            fd.append('rank', rank);
            fd.append('status', status);
            fd.append('date', date);
            fd.append('inTime', inTime);
            fd.append('outTime', outTime);
            fd.append('remarks', remarks);
            if (isEditing) fd.append('id', editId);

            const res = await fetch(window.location.href, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.success) {
                showToast(isEditing ? 'Updated' : 'Added');
                closeModalFunc();
                if (date === currentDate) {
                    loadData();
                } else {
                    showToast(`Record added for ${date}. Change date filter to view.`);
                }
            } else {
                showToast('Save failed', 'error');
            }
        };
    }

    // Export functionality
    if (exportBtn) {
        exportBtn.onclick = () => {
            const rows = [['#', 'Personnel No.', 'Name', 'Rank', 'Status', 'IN Time', 'OUT Time', 'Remarks']];
            allData.forEach((p, i) => {
                const statusText = { present: 'Present', leave: 'Leave', sick: 'Sick', work: 'Work', workout: 'PT', tdy: 'TDY', course: 'Course' }[p.status] || p.status;
                rows.push([i + 1, p.personnel_number || '-', p.personnel_name, p.rank, statusText, p.in_time || '-', p.out_time || '-', p.remarks]);
            });
            const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `attendance_${currentDate}.csv`;
            a.click();
            URL.revokeObjectURL(blob);
            showToast('Exported successfully');
        };
    }
    <?php else: ?>
    // For non-admin users, disable export
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.style.opacity = '0.6';
        exportBtn.style.cursor = 'not-allowed';
        exportBtn.title = 'Export is only available for administrators';
        exportBtn.onclick = () => {
            showToast('Export is only available for administrators', 'error');
        };
    }
    <?php endif; ?>

    // Date filter change
    const dateFilter = document.getElementById('dateFilter');
    if (dateFilter) {
        dateFilter.onchange = () => {
            currentDate = dateFilter.value;
            loadData();
        };
    }

    // Filters
    document.querySelectorAll('.pill').forEach(btn => {
        btn.onclick = () => {
            document.querySelectorAll('.pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            render();
        };
    });

    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.oninput = () => render();
    }

    // Initial load
    loadData();
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>