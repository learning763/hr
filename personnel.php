<?php
// Start session and include database connection
session_start();
require_once 'includes/config.php';

// Get current user's role from session
$current_user_role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;

$pageTitle = "Personnel Directory";
$pageSubtitle = "Complete list of commissioned officers and jawans.";
$activePage = "personnel";

// Pagination configuration
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get search term if any
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = "";
$params = [];

if (!empty($search_term)) {
    $search_condition = "WHERE personnel_number LIKE ? OR full_name_en LIKE ? OR rank LIKE ? OR unit LIKE ?";
    $search_param = "%$search_term%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

// Get total records for pagination
try {
    if (!empty($search_condition)) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel $search_condition");
        $count_stmt->execute($params);
    } else {
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM personnel");
    }
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Adjust page if out of range
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    
    // Fetch personnel with pagination - including role column
    $sql = "SELECT * FROM personnel $search_condition ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $personnel_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
    $personnel_list = [];
    $total_pages = 0;
}

// Prepare the content
ob_start();
?>

<!-- Search and Add Personnel Section -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <form method="GET" action="" id="searchForm" style="flex: 1;">
            <input type="text" name="search" id="searchInput" class="search-input" 
                   placeholder="Search by name, service no., rank or branch..." 
                   value="<?php echo htmlspecialchars($search_term); ?>">
            <?php if (!empty($search_term)): ?>
                <button type="button" id="clearSearch" class="clear-search">✕</button>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Only show Add Personnel button for Super Admin -->
    <?php if ($current_user_role == 2): ?>
        <button class="btn-add" id="addPersonnelBtn">
            <i class="fas fa-user-plus"></i> Add Personnel
        </button>
    <?php endif; ?>
</div>

<div class="data-table">
    <table id="personnelTable">
        <thead>
            <tr>
                <th style="width: 50px;">S.No.</th>
                <th>Personnel No.</th>
                <th>Name</th>
                <th>Rank</th>
                <th>Branch</th>
                <th>Recruitment Date</th>
                <th>Status</th>
                <?php if ($current_user_role == 2): ?>
                    <th>Role</th>
                    <th style="width: 100px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="personnelTableBody">
            <?php if (!empty($personnel_list)): ?>
                <?php $counter = $offset + 1; ?>
                <?php foreach ($personnel_list as $personnel): ?>
                    <tr data-id="<?php echo htmlspecialchars($personnel['personnel_number']); ?>">
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($personnel['personnel_number']); ?></td>
                        <td><?php echo htmlspecialchars($personnel['full_name_en']); ?></td>
                        <td><?php echo htmlspecialchars($personnel['rank']); ?></td>
                        <td><?php echo htmlspecialchars($personnel['unit']); ?></td>
                        <td><?php echo $personnel['recruitment_date'] ? date('Y-m-d', strtotime($personnel['recruitment_date'])) : 'N/A'; ?></td>
                        <td>
                            <span class="badge <?php echo strtolower($personnel['current_status']) == 'active' ? '' : 'leave'; ?>">
                                <?php echo htmlspecialchars($personnel['current_status']); ?>
                            </span>
                        </td>
                        <?php if ($current_user_role == 2): ?>
                            <td>
                                <?php
                                $role = isset($personnel['role']) ? (int)$personnel['role'] : 0;
                                $roleClass = '';
                                $roleText = '';
                                
                                switch($role) {
                                    case 2:
                                        $roleClass = 'role-superadmin';
                                        $roleText = 'Super Admin';
                                        break;
                                    case 1:
                                        $roleClass = 'role-admin';
                                        $roleText = 'Admin';
                                        break;
                                    default:
                                        $roleClass = 'role-user';
                                        $roleText = 'User';
                                        break;
                                }
                                ?>
                                <span class="role-badge <?php echo $roleClass; ?>">
                                    <?php echo $roleText; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-icon edit-btn" onclick="editPersonnel('<?php echo htmlspecialchars($personnel['personnel_number']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon delete-btn" onclick="deletePersonnel('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <?php
                    $colspan = 7; // Base columns
                    if ($current_user_role == 2) $colspan += 2; // Add Role and Actions columns
                    ?>
                    <td colspan="<?php echo $colspan; ?>" style="text-align: center; padding: 40px;">No personnel records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Section -->
<?php if ($total_pages > 1): ?>
<div class="pagination-container">
    <div class="pagination-info">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
    </div>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-link">
                <i class="fas fa-angle-double-left"></i>
            </a>
            <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-link">
                <i class="fas fa-angle-left"></i>
            </a>
        <?php endif; ?>
        
        <?php
        // Calculate page range to display
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) {
            echo '<span class="pagination-dots">...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <a href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
               class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($end_page < $total_pages): ?>
            <span class="pagination-dots">...</span>
        <?php endif; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-link">
                <i class="fas fa-angle-right"></i>
            </a>
            <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-link">
                <i class="fas fa-angle-double-right"></i>
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Records per page selector -->
    <div class="records-per-page">
        <label>Show:</label>
        <select id="recordsPerPage">
            <option value="7" <?php echo $records_per_page == 7 ? 'selected' : ''; ?>>7</option>
            <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
            <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
            <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
            <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
        </select>
        <span>entries per page</span>
    </div>
</div>
<?php endif; ?>

<!-- Modal for Add/Edit Personnel - Only show for Super Admin -->
<?php if ($current_user_role == 2): ?>
<div id="personnelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add New Personnel</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="personnelForm">
                <input type="hidden" id="editId" name="editId">
                <div class="form-grid">
                    <div class="input-field">
                        <label><i class="fas fa-id-card"></i> Personnel No. <span class="required-star">*</span></label>
                        <input type="text" id="serviceNo" name="serviceNo" placeholder="e.g., 7997" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-user"></i> Full Name (English) <span class="required-star">*</span></label>
                        <input type="text" id="fullName" name="fullName" placeholder="e.g., Parbhojimal" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-user"></i> Full Name (Nepali)</label>
                        <input type="text" id="fullNameNe" name="fullNameNe" placeholder="e.g., पर्भोजीमाल">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-calendar"></i> Date of Birth</label>
                        <input type="date" id="dob" name="dob">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-tint"></i> Blood Group</label>
                        <select id="bloodGroup" name="bloodGroup">
                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-star-of-life"></i> Rank <span class="required-star">*</span></label>
                        <select id="rank" name="rank" required>
                            <option value="">Select rank</option>
                            <option>General</option>
                            <option>Lieutenant General</option>
                            <option>Major General</option>
                            <option>Brigadier</option>
                            <option>Colonel</option>
                            <option>Lieutenant Colonel</option>
                            <option>Major</option>
                            <option>Captain</option>
                            <option>Lieutenant</option>
                            <option>Subedar Major</option>
                            <option>Subedar</option>
                            <option>Naib Subedar</option>
                            <option>Havildar</option>
                            <option>Naik</option>
                            <option>Lance Naik</option>
                            <option>Sepoy</option>
                        </select>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-gun"></i> Unit/Branch <span class="required-star">*</span></label>
                        <select id="branch" name="branch" required>
                            <option value="">Select branch</option>
                            <option>Infantry</option>
                            <option>Armoured Corps</option>
                            <option>Artillery</option>
                            <option>Corps of Engineers</option>
                            <option>Signals</option>
                            <option>Army Aviation</option>
                            <option>Intelligence</option>
                            <option>Medical Corps</option>
                            <option>Ordnance</option>
                            <option>EME</option>
                            <option>Education Corps</option>
                            <option>Cyber Security Directorate</option>
                        </select>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-calendar-alt"></i> Recruitment Date <span class="required-star">*</span></label>
                        <input type="date" id="recruitmentDate" name="recruitmentDate" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-calendar-check"></i> Commission Date</label>
                        <input type="date" id="commissionDate" name="commissionDate">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-flag-checkered"></i> Status <span class="required-star">*</span></label>
                        <select id="status" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Leave">Leave</option>
                            <option value="Retired">Retired</option>
                            <option value="Training">Training</option>
                        </select>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-user-shield"></i> User Role</label>
                        <select id="role" name="role">
                            <option value="0">User</option>
                            <option value="1">Admin</option>
                            <option value="2">Super Admin</option>
                        </select>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" placeholder="official@nepalarmy.mil.np">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="tel" id="contact" name="contact" placeholder="98XXXXXXXX">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-phone-alt"></i> Phone (Alternate)</label>
                        <input type="tel" id="phone" name="phone" placeholder="01-XXXXXXX">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-home"></i> Address</label>
                        <input type="text" id="address" name="address" placeholder="Tripurapur, Kathmandu">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-pray"></i> Religion</label>
                        <input type="text" id="religion" name="religion" placeholder="Hindu">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-heart"></i> Military Status</label>
                        <select id="militaryStatus" name="militaryStatus">
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                        </select>
                    </div>
                    <div class="input-field full-width">
                        <label><i class="fas fa-graduation-cap"></i> Academic Qualifications</label>
                        <textarea id="education" name="education" rows="2" placeholder="e.g., B.E Computer Engineering"></textarea>
                    </div>
                    <div class="input-field full-width">
                        <label><i class="fas fa-chalkboard-user"></i> Military Trainings</label>
                        <textarea id="militaryTrainings" name="militaryTrainings" rows="2" placeholder="e.g., Basic Cybersecurity O (7) MCTE Mhow Indore MP"></textarea>
                    </div>
                    <div class="input-field full-width">
                        <label><i class="fas fa-chalkboard-user"></i> Professional Training</label>
                        <textarea id="training" name="training" rows="2" placeholder="e.g., Professional training details"></textarea>
                    </div>
                    <div class="input-field full-width">
                        <label><i class="fas fa-globe"></i> Foreign Training</label>
                        <textarea id="foreignTraining" name="foreignTraining" rows="2" placeholder="e.g., Basic Cybersecurity O (7) MCTE Mhow Indore MP"></textarea>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Save Personnel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none;">
    <div class="toast-content">
        <i class="fas fa-check-circle" id="toastIcon"></i>
        <span id="toastMessage"></span>
    </div>
</div>

<style>
    /* Search Container Styles */
    .search-container {
        position: relative;
        flex: 1;
        max-width: 400px;
    }
    
    .search-container form {
        margin: 0;
        width: 100%;
    }
    
    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9aa9bc;
        font-size: 14px;
        z-index: 1;
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
    
    /* Button Styles */
    .btn-add {
        background: #1e3a32;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }
    
    .btn-add:hover {
        background: #14362c;
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
        color: #c2410c;
    }
    
    .delete-btn:hover {
        background: #fff0ed;
        transform: scale(1.1);
    }
    
    /* Role Badge Styles */
    .role-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .role-superadmin {
        background: #8b5cf6;
        color: white;
    }
    
    .role-admin {
        background: #3b82f6;
        color: white;
    }
    
    .role-user {
        background: #6b7280;
        color: white;
    }
    
    /* Table Styles */
    .data-table {
        overflow-x: auto;
    }
    
    .data-table table {
        width: 100%;
        border-collapse: collapse;
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
    
    .badge {
        display: inline-block;
        padding: 4px 10px;
        background: #10b981;
        color: white;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge.leave {
        background: #f59e0b;
    }
    
    /* Pagination Styles */
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
    
    .pagination-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #1a2c3e;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .pagination-link:hover {
        background: #f8fafc;
        border-color: #2c5f4e;
        color: #2c5f4e;
    }
    
    .pagination-link.active {
        background: #1e3a32;
        border-color: #1e3a32;
        color: white;
    }
    
    .pagination-dots {
        padding: 0 5px;
        color: #9aa9bc;
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
        margin: 3% auto;
        width: 90%;
        max-width: 900px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideDown 0.3s;
        max-height: 90vh;
        overflow-y: auto;
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
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
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
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1100;
        animation: slideInRight 0.3s;
    }
    
    .toast-content {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .toast-content i {
        font-size: 20px;
    }
    
    .toast.success .toast-content i {
        color: #10b981;
    }
    
    .toast.error .toast-content i {
        color: #c2410c;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
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
// Modal elements
const modal = document.getElementById('personnelModal');
const addBtn = document.getElementById('addPersonnelBtn');
const closeBtn = document.querySelector('.close');
const cancelBtn = document.getElementById('cancelBtn');
const modalTitle = document.getElementById('modalTitle');
const form = document.getElementById('personnelForm');
const searchInput = document.getElementById('searchInput');
const clearSearchBtn = document.getElementById('clearSearch');
const recordsPerPageSelect = document.getElementById('recordsPerPage');

let isEditing = false;

// Get current user role from PHP
const currentUserRole = <?php echo $current_user_role; ?>;

// Toast function
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMessage = document.getElementById('toastMessage');
    
    toast.className = `toast ${type}`;
    toastIcon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    toastMessage.textContent = message;
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

// Handle records per page change
if (recordsPerPageSelect) {
    recordsPerPageSelect.addEventListener('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', this.value);
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    });
}

// Clear search
if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', function() {
        window.location.href = window.location.pathname;
    });
}

// Handle search form submission
const searchForm = document.getElementById('searchForm');
if (searchForm) {
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const searchValue = searchInput.value.trim();
        const url = new URL(window.location.href);
        if (searchValue) {
            url.searchParams.set('search', searchValue);
        } else {
            url.searchParams.delete('search');
        }
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    });
}

// Only initialize modal functions if user is Super Admin
<?php if ($current_user_role == 2): ?>
// Open modal for adding
if (addBtn) {
    addBtn.onclick = function() {
        isEditing = false;
        modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add New Personnel';
        form.reset();
        document.getElementById('editId').value = '';
        document.getElementById('role').value = '0';
        modal.style.display = 'block';
    }
}

// Close modal
function closeModal() {
    modal.style.display = 'none';
    form.reset();
    isEditing = false;
}

if (closeBtn) {
    closeBtn.onclick = closeModal;
}
if (cancelBtn) {
    cancelBtn.onclick = closeModal;
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == modal) {
        closeModal();
    }
}

// Edit personnel function
function editPersonnel(personnelNumber) {
    fetch(`get_personnel.php?id=${personnelNumber}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                isEditing = true;
                modalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Personnel';
                
                document.getElementById('editId').value = data.data.personnel_number;
                document.getElementById('serviceNo').value = data.data.personnel_number;
                document.getElementById('fullName').value = data.data.full_name_en || '';
                document.getElementById('fullNameNe').value = data.data.full_name_ne || '';
                document.getElementById('dob').value = data.data.dob || '';
                document.getElementById('gender').value = data.data.gender || '';
                document.getElementById('bloodGroup').value = data.data.blood_group || '';
                document.getElementById('rank').value = data.data.rank || '';
                document.getElementById('branch').value = data.data.unit || '';
                document.getElementById('recruitmentDate').value = data.data.recruitment_date || '';
                document.getElementById('commissionDate').value = data.data.commission_date || '';
                document.getElementById('status').value = data.data.current_status || 'Active';
                document.getElementById('role').value = data.data.role || 0;
                document.getElementById('email').value = data.data.email || '';
                document.getElementById('contact').value = data.data.contact || '';
                document.getElementById('phone').value = data.data.phone || '';
                document.getElementById('address').value = data.data.address || '';
                document.getElementById('religion').value = data.data.religion || '';
                document.getElementById('militaryStatus').value = data.data.military_status || 'Single';
                document.getElementById('education').value = data.data.higher_education || '';
                document.getElementById('militaryTrainings').value = data.data.military_trainings || '';
                document.getElementById('training').value = data.data.training || '';
                document.getElementById('foreignTraining').value = data.data.foreign_training || '';
                
                modal.style.display = 'block';
            } else {
                showToast('Error loading personnel data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading personnel data', 'error');
        });
}

// Delete personnel function
function deletePersonnel(serviceNo, name) {
    if(confirm(`Are you sure you want to delete ${name} (${serviceNo})?`)) {
        fetch('delete_personnel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${encodeURIComponent(serviceNo)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Personnel ${name} deleted successfully`, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Error deleting personnel', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting personnel', 'error');
        });
    }
}

// Handle form submission
if (form) {
    form.onsubmit = function(e) {
        e.preventDefault();
        
        const editId = document.getElementById('editId').value;
        const serviceNo = document.getElementById('serviceNo').value;
        const fullName = document.getElementById('fullName').value;
        const fullNameNe = document.getElementById('fullNameNe').value;
        const dob = document.getElementById('dob').value;
        const gender = document.getElementById('gender').value;
        const bloodGroup = document.getElementById('bloodGroup').value;
        const rank = document.getElementById('rank').value;
        const branch = document.getElementById('branch').value;
        const recruitmentDate = document.getElementById('recruitmentDate').value;
        const commissionDate = document.getElementById('commissionDate').value;
        const status = document.getElementById('status').value;
        const role = document.getElementById('role').value;
        const email = document.getElementById('email').value;
        const contact = document.getElementById('contact').value;
        const phone = document.getElementById('phone').value;
        const address = document.getElementById('address').value;
        const religion = document.getElementById('religion').value;
        const militaryStatus = document.getElementById('militaryStatus').value;
        const education = document.getElementById('education').value;
        const militaryTrainings = document.getElementById('militaryTrainings').value;
        const training = document.getElementById('training').value;
        const foreignTraining = document.getElementById('foreignTraining').value;
        
        if(!serviceNo || !fullName || !rank || !branch || !recruitmentDate || !status) {
            showToast('Please fill all required fields', 'error');
            return;
        }
        
        const formData = new URLSearchParams();
        formData.append('editId', editId);
        formData.append('serviceNo', serviceNo);
        formData.append('fullName', fullName);
        formData.append('fullNameNe', fullNameNe);
        formData.append('dob', dob);
        formData.append('gender', gender);
        formData.append('bloodGroup', bloodGroup);
        formData.append('rank', rank);
        formData.append('branch', branch);
        formData.append('recruitmentDate', recruitmentDate);
        formData.append('commissionDate', commissionDate);
        formData.append('status', status);
        formData.append('role', role);
        formData.append('email', email);
        formData.append('contact', contact);
        formData.append('phone', phone);
        formData.append('address', address);
        formData.append('religion', religion);
        formData.append('militaryStatus', militaryStatus);
        formData.append('education', education);
        formData.append('militaryTrainings', militaryTrainings);
        formData.append('training', training);
        formData.append('foreignTraining', foreignTraining);
        
        fetch('save_personnel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeModal();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Error saving personnel', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error saving personnel', 'error');
        });
    }
}
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>