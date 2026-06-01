<?php
// Start session and include database connection
session_start();
require_once 'includes/config.php';

// Get current user's role from session
$current_user_role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;

$pageTitle = "Personnel Directory";
$pageSubtitle = "Complete list of commissioned officers and jawans.";
$activePage = "personnel";

// Check permissions
$isAdmin = ($current_user_role >= 1); // Admin (1) or Super Admin (2)
$isSuperAdmin = ($current_user_role === 2); // Super Admin only

// Pagination configuration
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get search term if any
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search condition
$search_condition = "";
$params = [];

if (!empty($search_term)) {
    $search_condition = "WHERE personnel_number LIKE ? OR full_name_en LIKE ? OR rank LIKE ? OR unit LIKE ? OR email LIKE ? OR province LIKE ? OR district LIKE ? OR municipality LIKE ? OR village_tole LIKE ?";
    $search_param = "%$search_term%";
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param];
}

// Get statistics directly from database
try {
    // Total Personnel
    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel");
    $total_records = $stmt->fetchColumn();
    
    // Active Personnel count
    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel WHERE current_status = 'Active'");
    $active_count = $stmt->fetchColumn();
    
    // Commissioned Officers count (based on rank)
    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel WHERE rank IN ('Captain', 'Major', 'Lieutenant Colonel', 'Colonel', 'Brigadier General', 'Major General', 'Lieutenant General', 'General', 'Lieutenant', 'Second Lieutenant', 'Subedar', 'Lieutenant Subedar')");
    $officer_count = $stmt->fetchColumn();
    if ($officer_count == 0) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM personnel WHERE rank NOT LIKE '%Jawan%' AND rank NOT LIKE '%Soldier%' AND rank NOT LIKE '%Sepoy%' AND rank IS NOT NULL");
        $officer_count = $stmt->fetchColumn();
    }
    
    // Unique Units/Branches count
    $stmt = $pdo->query("SELECT COUNT(DISTINCT unit) FROM personnel WHERE unit IS NOT NULL AND unit != ''");
    $unit_count = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    error_log("Statistics error: " . $e->getMessage());
    $total_records = 0;
    $active_count = 0;
    $officer_count = 0;
    $unit_count = 0;
}

// Get total records for pagination
try {
    if (!empty($search_condition)) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel $search_condition");
        $count_stmt->execute($params);
        $total_filtered_records = $count_stmt->fetchColumn();
    } else {
        $total_filtered_records = $total_records;
    }
    $total_pages = ceil($total_filtered_records / $records_per_page);
    
    // Adjust page if out of range
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
    
    // Fetch personnel with pagination
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
    error_log("Personnel fetch error: " . $e->getMessage());
    $personnel_list = [];
    $total_pages = 0;
}

// Prepare the content
ob_start();
?>

<style>
    /* Statistics Cards - Enhanced UI */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 18px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(44, 95, 78, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #2c5f4e, #4a9b82);
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    
    .stat-icon {
        width: 55px;
        height: 55px;
        background: linear-gradient(135deg, #e8f5f0 0%, #d1e8e0 100%);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: #2c5f4e;
        transition: transform 0.3s;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.05);
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: 800;
        color: #1a2c3e;
        line-height: 1.2;
        letter-spacing: -0.5px;
    }
    
    .stat-label {
        font-size: 13px;
        color: #6c7a8e;
        margin-top: 5px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Search Section */
    .search-section {
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .search-container {
        position: relative;
        flex: 1;
        max-width: 450px;
    }
    
    .search-container form {
        margin: 0;
        width: 100%;
    }
    
    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #9aa9bc;
        font-size: 14px;
        z-index: 1;
    }
    
    .search-input {
        width: 100%;
        padding: 12px 40px 12px 42px;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
        background: white;
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
        background: #f1f3f5;
        border: none;
        cursor: pointer;
        color: #6c7a8e;
        font-size: 12px;
        padding: 0;
        width: 22px;
        height: 22px;
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
    
    /* Buttons */
    .btn-add {
        background: linear-gradient(135deg, #1e3a32 0%, #2c5f4e 100%);
        color: white;
        border: none;
        padding: 11px 24px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(44, 95, 78, 0.3);
        background: linear-gradient(135deg, #14362c 0%, #1e4a3e 100%);
    }
    
    /* Table */
    .data-table {
        background: white;
        border-radius: 16px;
        overflow-x: auto;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #eef2f6;
    }
    
    .data-table table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1300px;
    }
    
    .data-table th {
        text-align: left;
        padding: 16px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        color: #1a2c3e;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #eef2f6;
        font-size: 13px;
        color: #334155;
        vertical-align: middle;
    }
    
    .data-table tr:hover {
        background: #fafcff;
    }
    
    /* Photo Styles */
    .photo-cell {
        text-align: center;
        padding: 8px !important;
        width: 60px;
    }
    
    .profile-preview {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        cursor: pointer;
        border: 2px solid #e2e8f0;
        transition: all 0.2s;
    }
    
    .profile-preview:hover {
        transform: scale(1.1);
        border-color: #2c5f4e;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .avatar-placeholder {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 2px dashed #cbd5e1;
        transition: all 0.2s;
        color: #94a3b8;
        font-size: 20px;
    }
    
    .avatar-placeholder:hover {
        border-color: #2c5f4e;
        background: #e8f5f0;
        color: #2c5f4e;
    }
    
    /* Signature Styles */
    .signature-cell {
        text-align: center;
    }
    
    .signature-preview {
        max-width: 80px;
        max-height: 35px;
        cursor: pointer;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 2px;
        background: white;
        transition: transform 0.2s;
    }
    
    .signature-preview:hover {
        transform: scale(1.3);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        z-index: 10;
        position: relative;
    }
    
    .no-signature {
        color: #9aa9bc;
        font-size: 12px;
    }
    
    /* Badges */
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .badge-active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-leave {
        background: #fef3c7;
        color: #92400e;
    }
    
    .badge-retired {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .badge-training {
        background: #dbeafe;
        color: #1e40af;
    }
    
    /* Role Badges */
    .role-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .role-superadmin {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
    }
    
    .role-admin {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }
    
    .role-user {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 8px;
        transition: all 0.2s;
        font-size: 14px;
    }
    
    .btn-edit {
        color: #2c5f4e;
    }
    
    .btn-edit:hover {
        background: #e8f5f0;
        transform: scale(1.05);
    }
    
    .btn-photo {
        color: #3b82f6;
    }
    
    .btn-photo:hover {
        background: #dbeafe;
        transform: scale(1.05);
    }
    
    .btn-signature {
        color: #8b5cf6;
    }
    
    .btn-signature:hover {
        background: #f3e8ff;
        transform: scale(1.05);
    }
    
    .btn-reset {
        color: #f59e0b;
    }
    
    .btn-reset:hover {
        background: #fef3c7;
        transform: scale(1.05);
    }
    
    .btn-delete {
        color: #c2410c;
    }
    
    .btn-delete:hover {
        background: #fff0ed;
        transform: scale(1.05);
    }
    
    /* Pagination */
    .pagination-container {
        margin-top: 24px;
        padding-top: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .pagination-info {
        color: #6c7a8e;
        font-size: 13px;
    }
    
    .pagination {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .pagination-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        height: 38px;
        padding: 0 12px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #1a2c3e;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .pagination-link:hover {
        background: #f8fafc;
        border-color: #2c5f4e;
        color: #2c5f4e;
        transform: translateY(-1px);
    }
    
    .pagination-link.active {
        background: linear-gradient(135deg, #1e3a32, #2c5f4e);
        border-color: #2c5f4e;
        color: white;
    }
    
    .page-dots {
        padding: 0 5px;
        color: #9aa9bc;
    }
    
    .records-per-page {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #6c7a8e;
    }
    
    .records-per-page select {
        padding: 8px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        background: white;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }
    
    .records-per-page select:focus {
        border-color: #2c5f4e;
        outline: none;
    }
    
    /* Email link */
    .email-link {
        color: #2c5f4e;
        text-decoration: none;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .email-link:hover {
        text-decoration: underline;
        color: #1e4a3e;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .stat-value {
            font-size: 24px;
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            font-size: 20px;
        }
        
        .stat-card {
            padding: 15px;
        }
        
        .search-section {
            flex-direction: column;
            align-items: stretch;
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
        
        .action-buttons {
            flex-direction: column;
        }
        
        .btn-icon {
            width: 32px;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .stat-card {
            margin-bottom: 10px;
        }
    }
</style>

<!-- Statistics Cards - Server Side Generated -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($total_records); ?></div>
            <div class="stat-label">Total Personnel</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($active_count); ?></div>
            <div class="stat-label">Active Personnel</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($officer_count); ?></div>
            <div class="stat-label">Commissioned Officers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-building"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($unit_count); ?></div>
            <div class="stat-label">Units/Branches</div>
        </div>
    </div>
</div>

<!-- Search and Add Personnel Section -->
<div class="search-section">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <form method="GET" action="" id="searchForm" style="flex: 1;">
            <input type="text" name="search" id="searchInput" class="search-input" 
                   placeholder="🔍 Search by name, service no., rank, branch, email or location..." 
                   value="<?php echo htmlspecialchars($search_term); ?>">
            <?php if (!empty($search_term)): ?>
                <button type="button" id="clearSearch" class="clear-search">✕</button>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if ($isSuperAdmin): ?>
        <button class="btn-add" id="addPersonnelBtn">
            <i class="fas fa-user-plus"></i> Add Personnel
        </button>
    <?php endif; ?>
</div>

<!-- Personnel Table -->
<div class="data-table">
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th style="width: 60px;">Photo</th>
                <th>Personnel No.</th>
                <th>Name</th>
                <th>Signature</th>
                <th>Email</th>
                <th>Rank</th>
                <th>Branch</th>
                <th>Location</th>
                <th>Recruitment Date</th>
                <th>Status</th>
                <?php if ($isAdmin): ?>
                    <th>Role</th>
                <?php endif; ?>
                <?php if ($isSuperAdmin): ?>
                    <th style="width: 200px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($personnel_list)): ?>
                <?php $counter = $offset + 1; ?>
                <?php foreach ($personnel_list as $personnel): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td class="photo-cell">
                            <?php if (!empty($personnel['profile_picture_path']) && file_exists($personnel['profile_picture_path'])): ?>
                                <img src="<?php echo htmlspecialchars($personnel['profile_picture_path']); ?>" 
                                     alt="Profile Photo" 
                                     class="profile-preview"
                                     onclick="viewProfilePhoto('<?php echo htmlspecialchars($personnel['profile_picture_path']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')">
                            <?php else: ?>
                                <div class="avatar-placeholder" onclick="editProfilePhoto('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($personnel['personnel_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($personnel['full_name_en']); ?></td>
                        <td class="signature-cell">
                            <?php if (!empty($personnel['signature']) && file_exists($personnel['signature'])): ?>
                                <img src="<?php echo htmlspecialchars($personnel['signature']); ?>" 
                                     alt="Signature" 
                                     class="signature-preview"
                                     onclick="viewSignature('<?php echo htmlspecialchars($personnel['signature']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')">
                            <?php else: ?>
                                <span class="no-signature">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($personnel['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($personnel['email']); ?>" class="email-link">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars(substr($personnel['email'], 0, 20)); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #9aa9bc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($personnel['rank'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($personnel['unit'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                            $location_parts = [];
                            if (!empty($personnel['village_tole'])) $location_parts[] = $personnel['village_tole'];
                            if (!empty($personnel['municipality'])) $location_parts[] = $personnel['municipality'];
                            if (!empty($personnel['district'])) $location_parts[] = $personnel['district'];
                            if (!empty($personnel['province'])) $location_parts[] = $personnel['province'];
                            
                            echo !empty($location_parts) ? htmlspecialchars(implode(', ', $location_parts)) : '—';
                            ?>
                        </td>
                        <td><?php echo $personnel['recruitment_date'] && $personnel['recruitment_date'] != '0000-00-00' ? date('Y-m-d', strtotime($personnel['recruitment_date'])) : 'N/A'; ?></td>
                        <td>
                            <?php
                            $status_class = 'badge-active';
                            $status_lower = strtolower($personnel['current_status'] ?? 'active');
                            if ($status_lower == 'leave') $status_class = 'badge-leave';
                            elseif ($status_lower == 'retired') $status_class = 'badge-retired';
                            elseif ($status_lower == 'training') $status_class = 'badge-training';
                            ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($personnel['current_status'] ?? 'Active'); ?>
                            </span>
                        </td>
                        <?php if ($isAdmin): ?>
                            <td>
                                <?php
                                $role = isset($personnel['role']) ? (int)$personnel['role'] : 0;
                                $roleClass = '';
                                $roleText = '';
                                switch($role) {
                                    case 2: $roleClass = 'role-superadmin'; $roleText = 'Super Admin'; break;
                                    case 1: $roleClass = 'role-admin'; $roleText = 'Admin'; break;
                                    default: $roleClass = 'role-user'; $roleText = 'User'; break;
                                }
                                ?>
                                <span class="role-badge <?php echo $roleClass; ?>">
                                    <?php echo $roleText; ?>
                                </span>
                            </td>
                        <?php endif; ?>
                        <?php if ($isSuperAdmin): ?>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-edit" onclick="editPersonnel('<?php echo htmlspecialchars($personnel['personnel_number']); ?>')" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-photo" onclick="editProfilePhoto('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')" title="Upload/Edit Photo">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                    <button class="btn-icon btn-signature" onclick="editSignature('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>', '<?php echo htmlspecialchars($personnel['signature'] ?? ''); ?>')" title="Upload/Edit Signature">
                                        <i class="fas fa-signature"></i>
                                    </button>
                                    <button class="btn-icon btn-reset" onclick="resetPassword('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($personnel['personnel_number'] !== ($_SESSION['user_id'] ?? '')): ?>
                                        <button class="btn-icon btn-delete" onclick="deletePersonnel('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <?php
                    $colspan = 11;
                    if ($isAdmin) $colspan++;
                    if ($isSuperAdmin) $colspan++;
                    ?>
                    <td colspan="<?php echo $colspan; ?>" style="text-align: center; padding: 60px;">
                        <i class="fas fa-user-slash" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                        <p style="color: #6c7a8e;">No personnel records found.</p>
                        <?php if (!empty($search_term)): ?>
                            <p style="color: #9aa9bc; font-size: 12px; margin-top: 10px;">Try adjusting your search criteria.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination-container">
    <div class="pagination-info">
        <i class="fas fa-info-circle"></i> Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_filtered_records); ?> of <?php echo $total_filtered_records; ?> entries
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
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) echo '<span class="page-dots">...</span>';
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <a href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
               class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($end_page < $total_pages): ?>
            <span class="page-dots">...</span>
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
            <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
            <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
            <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
            <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
        </select>
        <span>entries per page</span>
    </div>
</div>
<?php endif; ?>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none; position: fixed; bottom: 20px; right: 20px; background: #1e3a32; color: white; padding: 12px 20px; border-radius: 10px; font-size: 14px; z-index: 1100; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
    <span id="toastMessage"></span>
</div>

<style>
    .toast {
        animation: slideInRight 0.3s;
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
</style>

<script>
    // DOM Elements
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearch');
    const recordsPerPageSelect = document.getElementById('recordsPerPage');
    const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

    // Toast notification
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
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
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

    // View Signature
    function viewSignature(signaturePath, name) {
        // Create modal if not exists
        let viewModal = document.getElementById('signatureViewModal');
        if (!viewModal) {
            viewModal = document.createElement('div');
            viewModal.id = 'signatureViewModal';
            viewModal.className = 'modal';
            viewModal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <div class="modal-header">
                        <h3 id="signatureViewTitle"><i class="fas fa-signature"></i> Signature</h3>
                        <span class="close-signature-view" style="cursor: pointer; font-size: 28px;">&times;</span>
                    </div>
                    <div class="modal-body" style="text-align: center;">
                        <div id="signatureViewImage"></div>
                        <p id="signatureViewName" style="margin-top: 15px; color: #6c7a8e;"></p>
                    </div>
                </div>
            `;
            document.body.appendChild(viewModal);
            
            const closeBtn = viewModal.querySelector('.close-signature-view');
            closeBtn.onclick = () => viewModal.style.display = 'none';
            window.onclick = (event) => {
                if (event.target == viewModal) viewModal.style.display = 'none';
            };
        }
        
        const viewImage = document.getElementById('signatureViewImage');
        const viewName = document.getElementById('signatureViewName');
        const viewTitle = document.getElementById('signatureViewTitle');
        
        if (viewImage) {
            viewImage.innerHTML = `<img src="${signaturePath}" alt="Signature of ${name}" style="max-width: 280px; max-height: 100px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: white;">`;
        }
        if (viewName) viewName.textContent = name;
        if (viewTitle) viewTitle.innerHTML = `<i class="fas fa-signature"></i> Signature - ${name}`;
        
        viewModal.style.display = 'block';
    }

    // View Profile Photo
    function viewProfilePhoto(photoPath, name) {
        let viewModal = document.getElementById('photoViewModal');
        if (!viewModal) {
            viewModal = document.createElement('div');
            viewModal.id = 'photoViewModal';
            viewModal.className = 'modal';
            viewModal.innerHTML = `
                <div class="modal-content" style="max-width: 450px; text-align: center;">
                    <div class="modal-header">
                        <h3 id="photoViewTitle"><i class="fas fa-user-circle"></i> Profile Photo</h3>
                        <span class="close-photo-view" style="cursor: pointer; font-size: 28px;">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="photoViewImage"></div>
                        <p id="photoViewName" style="margin-top: 15px;"></p>
                    </div>
                </div>
            `;
            document.body.appendChild(viewModal);
            
            const closeBtn = viewModal.querySelector('.close-photo-view');
            closeBtn.onclick = () => viewModal.style.display = 'none';
        }
        
        const viewImage = document.getElementById('photoViewImage');
        const viewName = document.getElementById('photoViewName');
        const viewTitle = document.getElementById('photoViewTitle');
        
        if (viewImage) {
            viewImage.innerHTML = `<img src="${photoPath}" alt="Profile Photo of ${name}" style="width: 200px; height: 200px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; padding: 5px; background: white;">`;
        }
        if (viewName) viewName.textContent = name;
        if (viewTitle) viewTitle.innerHTML = `<i class="fas fa-user-circle"></i> Profile Photo - ${name}`;
        
        viewModal.style.display = 'block';
    }

    <?php if ($isSuperAdmin): ?>
    // Edit Profile Photo
    function editProfilePhoto(serviceNo, name) {
        // Create modal if not exists
        let photoModal = document.getElementById('profilePhotoModal');
        if (!photoModal) {
            photoModal = document.createElement('div');
            photoModal.id = 'profilePhotoModal';
            photoModal.className = 'modal';
            photoModal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3 id="profilePhotoModalTitle"><i class="fas fa-camera"></i> Upload Profile Photo</h3>
                        <span class="close-profile-photo" style="cursor: pointer; font-size: 28px;">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="profilePhotoForm" enctype="multipart/form-data">
                            <input type="hidden" id="photoPersonnelId" name="personnel_id">
                            <input type="hidden" id="existingPhoto" name="existing_photo">
                            <div class="photo-preview-container" style="text-align: center; margin-bottom: 20px;">
                                <div id="currentPhotoPreview" style="margin-bottom: 15px;">
                                    <label style="font-size: 13px; color: #6c7a8e;">Current Photo:</label>
                                    <div id="currentPhotoImage" style="margin-top: 10px;"></div>
                                </div>
                            </div>
                            <div class="input-field" style="margin-bottom: 20px;">
                                <label><i class="fas fa-cloud-upload-alt"></i> Upload New Profile Photo</label>
                                <input type="file" id="profilePhotoFile" name="profile_photo" accept="image/*" style="padding: 8px; width: 100%;">
                                <small style="color: #6c7a8e;">Supported formats: JPG, PNG, GIF. Max size: 5MB.</small>
                            </div>
                            <div class="modal-buttons" style="display: flex; gap: 12px; justify-content: flex-end;">
                                <button type="button" class="btn-cancel" id="cancelPhotoBtn" style="padding: 10px 20px; background: #f1f3f5; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                                <button type="button" class="btn-delete-photo" id="deletePhotoBtn" style="background: #dc2626; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;"><i class="fas fa-trash"></i> Remove Photo</button>
                                <button type="submit" class="btn-submit" style="background: #1e3a32; color: white; padding: 10px 24px; border: none; border-radius: 8px; cursor: pointer;">Upload Photo</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(photoModal);
            
            const closeBtn = photoModal.querySelector('.close-profile-photo');
            closeBtn.onclick = () => photoModal.style.display = 'none';
            
            const cancelBtn = photoModal.querySelector('#cancelPhotoBtn');
            if (cancelBtn) cancelBtn.onclick = () => photoModal.style.display = 'none';
            
            // Handle form submission
            const photoForm = photoModal.querySelector('#profilePhotoForm');
            if (photoForm) {
                photoForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const personnelId = document.getElementById('photoPersonnelId').value;
                    const fileInput = document.getElementById('profilePhotoFile');
                    const file = fileInput.files[0];
                    
                    if (!file) {
                        showToast('Please select a profile photo to upload', 'error');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'upload_profile_photo');
                    formData.append('personnel_id', personnelId);
                    formData.append('profile_photo', file);
                    
                    try {
                        const response = await fetch('upload_profile_photo.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast('Profile photo uploaded successfully!', 'success');
                            photoModal.style.display = 'none';
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(result.message || 'Error uploading profile photo', 'error');
                        }
                    } catch (error) {
                        showToast('Error uploading profile photo', 'error');
                    }
                });
            }
            
            // Delete photo button
            const deletePhotoBtn = photoModal.querySelector('#deletePhotoBtn');
            if (deletePhotoBtn) {
                deletePhotoBtn.addEventListener('click', async function() {
                    const personnelId = document.getElementById('photoPersonnelId').value;
                    if (confirm('Are you sure you want to remove the profile photo?')) {
                        const formData = new FormData();
                        formData.append('action', 'delete_profile_photo');
                        formData.append('personnel_id', personnelId);
                        
                        try {
                            const response = await fetch('upload_profile_photo.php', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            
                            if (result.success) {
                                showToast('Profile photo removed successfully!', 'success');
                                photoModal.style.display = 'none';
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showToast(result.message || 'Error removing profile photo', 'error');
                            }
                        } catch (error) {
                            showToast('Error removing profile photo', 'error');
                        }
                    }
                });
            }
        }
        
        document.getElementById('photoPersonnelId').value = serviceNo;
        const modalTitle = document.getElementById('profilePhotoModalTitle');
        if (modalTitle) modalTitle.innerHTML = `<i class="fas fa-camera"></i> Upload Profile Photo - ${name}`;
        
        // Get current photo
        fetch(`get_personnel.php?id=${serviceNo}`)
            .then(response => response.json())
            .then(data => {
                const previewContainer = document.getElementById('currentPhotoImage');
                if (previewContainer) {
                    if (data.success && data.data.profile_picture_path) {
                        previewContainer.innerHTML = `<img src="${data.data.profile_picture_path}" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0;">`;
                    } else {
                        previewContainer.innerHTML = `<span style="color: #9aa9bc;">No profile photo uploaded yet</span>`;
                    }
                }
                if (document.getElementById('existingPhoto')) {
                    document.getElementById('existingPhoto').value = data.data.profile_picture_path || '';
                }
            });
        
        const fileInput = document.getElementById('profilePhotoFile');
        if (fileInput) fileInput.value = '';
        
        photoModal.style.display = 'block';
    }

    // Edit Signature
    function editSignature(serviceNo, name, currentSignature) {
        let sigModal = document.getElementById('signatureModal');
        if (!sigModal) {
            sigModal = document.createElement('div');
            sigModal.id = 'signatureModal';
            sigModal.className = 'modal';
            sigModal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3 id="signatureModalTitle"><i class="fas fa-signature"></i> Upload Signature</h3>
                        <span class="close-signature" style="cursor: pointer; font-size: 28px;">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="signatureForm" enctype="multipart/form-data">
                            <input type="hidden" id="signaturePersonnelId" name="personnel_id">
                            <input type="hidden" id="existingSignature" name="existing_signature">
                            <div class="signature-preview-container" style="text-align: center; margin-bottom: 20px;">
                                <div id="currentSignaturePreview">
                                    <label style="font-size: 13px; color: #6c7a8e;">Current Signature:</label>
                                    <div id="currentSignatureImage" style="margin-top: 10px;"></div>
                                </div>
                            </div>
                            <div class="input-field" style="margin-bottom: 20px;">
                                <label><i class="fas fa-cloud-upload-alt"></i> Upload New Signature Image</label>
                                <input type="file" id="signatureFile" name="signature" accept="image/*" style="padding: 8px; width: 100%;">
                                <small style="color: #6c7a8e;">Supported formats: JPG, PNG, GIF. Max size: 2MB.</small>
                            </div>
                            <div class="modal-buttons" style="display: flex; gap: 12px; justify-content: flex-end;">
                                <button type="button" class="btn-cancel" id="cancelSignatureBtn" style="padding: 10px 20px; background: #f1f3f5; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                                <button type="button" class="btn-delete-signature" id="deleteSignatureBtn" style="background: #dc2626; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;"><i class="fas fa-trash"></i> Remove Signature</button>
                                <button type="submit" class="btn-submit" style="background: #1e3a32; color: white; padding: 10px 24px; border: none; border-radius: 8px; cursor: pointer;">Upload Signature</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(sigModal);
            
            const closeBtn = sigModal.querySelector('.close-signature');
            closeBtn.onclick = () => sigModal.style.display = 'none';
            
            const cancelBtn = sigModal.querySelector('#cancelSignatureBtn');
            if (cancelBtn) cancelBtn.onclick = () => sigModal.style.display = 'none';
            
            // Handle form submission
            const sigForm = sigModal.querySelector('#signatureForm');
            if (sigForm) {
                sigForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const personnelId = document.getElementById('signaturePersonnelId').value;
                    const fileInput = document.getElementById('signatureFile');
                    const file = fileInput.files[0];
                    
                    if (!file) {
                        showToast('Please select a signature image to upload', 'error');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'upload_signature');
                    formData.append('personnel_id', personnelId);
                    formData.append('signature', file);
                    
                    try {
                        const response = await fetch('upload_signature.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast('Signature uploaded successfully!', 'success');
                            sigModal.style.display = 'none';
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(result.message || 'Error uploading signature', 'error');
                        }
                    } catch (error) {
                        showToast('Error uploading signature', 'error');
                    }
                });
            }
            
            // Delete signature button
            const deleteSigBtn = sigModal.querySelector('#deleteSignatureBtn');
            if (deleteSigBtn) {
                deleteSigBtn.addEventListener('click', async function() {
                    const personnelId = document.getElementById('signaturePersonnelId').value;
                    if (confirm('Are you sure you want to remove the signature?')) {
                        const formData = new FormData();
                        formData.append('action', 'delete_signature');
                        formData.append('personnel_id', personnelId);
                        
                        try {
                            const response = await fetch('upload_signature.php', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            
                            if (result.success) {
                                showToast('Signature removed successfully!', 'success');
                                sigModal.style.display = 'none';
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showToast(result.message || 'Error removing signature', 'error');
                            }
                        } catch (error) {
                            showToast('Error removing signature', 'error');
                        }
                    }
                });
            }
        }
        
        document.getElementById('signaturePersonnelId').value = serviceNo;
        document.getElementById('existingSignature').value = currentSignature || '';
        
        const modalTitle = document.getElementById('signatureModalTitle');
        if (modalTitle) modalTitle.innerHTML = `<i class="fas fa-signature"></i> Upload Signature - ${name}`;
        
        const previewContainer = document.getElementById('currentSignatureImage');
        if (previewContainer) {
            if (currentSignature && currentSignature !== '') {
                previewContainer.innerHTML = `<img src="${currentSignature}" alt="Current Signature" style="max-width: 250px; max-height: 80px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 5px; background: white;">`;
            } else {
                previewContainer.innerHTML = `<span style="color: #9aa9bc;">No signature uploaded yet</span>`;
            }
        }
        
        const fileInput = document.getElementById('signatureFile');
        if (fileInput) fileInput.value = '';
        
        sigModal.style.display = 'block';
    }

    // Reset Password
    function resetPassword(serviceNo, name) {
        if(confirm(`Are you sure you want to reset password for ${name} (${serviceNo})?\n\nPassword will be set to: reset@123`)) {
            fetch('reset_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `personnel_no=${encodeURIComponent(serviceNo)}&password=reset@123`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Password for ${name} has been reset to: reset@123`, 'success');
                } else {
                    showToast(data.message || 'Error resetting password', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error resetting password', 'error');
            });
        }
    }

    // Delete personnel
    function deletePersonnel(serviceNo, name) {
        if(confirm(`Are you sure you want to delete ${name} (${serviceNo})? This action cannot be undone.`)) {
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
                    setTimeout(() => location.reload(), 1000);
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

    // Edit personnel
    function editPersonnel(personnelNumber) {
        // Create modal if not exists
        let editModal = document.getElementById('personnelModal');
        if (!editModal) {
            // Modal creation code here (similar to existing)
            window.location.href = `edit_personnel.php?id=${personnelNumber}`;
            return;
        }
        // ... rest of edit logic
    }

    // Add personnel button
    const addBtn = document.getElementById('addPersonnelBtn');
    if (addBtn) {
        addBtn.onclick = function() {
            window.location.href = 'add_personnel.php';
        }
    }
    <?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>