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

// Build search condition (including location fields)
$search_condition = "";
$params = [];

if (!empty($search_term)) {
    $search_condition = "WHERE personnel_number LIKE ? OR full_name_en LIKE ? OR rank LIKE ? OR unit LIKE ? OR email LIKE ? OR province LIKE ? OR district LIKE ? OR municipality LIKE ? OR village_tole LIKE ?";
    $search_param = "%$search_term%";
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param];
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

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div>
            <div class="stat-value"><?php echo $total_records; ?></div>
            <div class="stat-label">Total Personnel</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-value" id="activeCount">--</div>
            <div class="stat-label">Active Personnel</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div>
            <div class="stat-value" id="officerCount">--</div>
            <div class="stat-label">Commissioned Officers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-building"></i></div>
        <div>
            <div class="stat-value" id="unitCount">--</div>
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
                   placeholder="Search by name, service no., rank, branch, email or location..." 
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
                <th style="width: 50px;">S.No.</th>
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
                    <th style="width: 180px;">Actions</th>
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
                            <?php if (!empty($personnel['profile_picture_path'])): ?>
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
                        <td><?php echo htmlspecialchars($personnel['personnel_number']); ?></td>
                        <td><?php echo htmlspecialchars($personnel['full_name_en']); ?></td>
                        <td class="signature-cell">
                            <?php if (!empty($personnel['signature'])): ?>
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
                                <a href="mailto:<?php echo htmlspecialchars($personnel['email']); ?>" style="color: #2c5f4e; text-decoration: none;">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($personnel['email']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #9aa9bc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($personnel['rank']); ?></td>
                        <td><?php echo htmlspecialchars($personnel['unit']); ?></td>
                        <td>
                            <?php 
                            $location_parts = [];
                            if (!empty($personnel['village_tole'])) $location_parts[] = $personnel['village_tole'];
                            if (!empty($personnel['ward_number'])) $location_parts[] = 'Ward ' . $personnel['ward_number'];
                            if (!empty($personnel['municipality'])) $location_parts[] = $personnel['municipality'];
                            if (!empty($personnel['district'])) $location_parts[] = $personnel['district'];
                            if (!empty($personnel['province'])) $location_parts[] = $personnel['province'];
                            
                            echo !empty($location_parts) ? htmlspecialchars(implode(', ', $location_parts)) : '—';
                            ?>
                        </td>
                        <td><?php echo $personnel['recruitment_date'] ? date('Y-m-d', strtotime($personnel['recruitment_date'])) : 'N/A'; ?></td>
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
                    $colspan = 11; // Increased by 2 for Photo column
                    if ($isAdmin) $colspan++;
                    if ($isSuperAdmin) $colspan++;
                    ?>
                    <td colspan="<?php echo $colspan; ?>" style="text-align: center; padding: 40px;">
                        No personnel records found.
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

<!-- Add/Edit Personnel Modal (Super Admin only) -->
<?php if ($isSuperAdmin): ?>
<div id="personnelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add New Personnel</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="personnelForm" enctype="multipart/form-data">
                <input type="hidden" id="editId" name="editId">
                <div class="form-grid">
                    <div class="input-field">
                        <label><i class="fas fa-id-card"></i> Personnel No. <span class="required-star">*</span></label>
                        <input type="text" id="serviceNo" name="serviceNo" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-user"></i> Full Name <span class="required-star">*</span></label>
                        <input type="text" id="fullName" name="fullName" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-calendar"></i> Date of Birth</label>
                        <input type="date" id="dob" name="dob">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-tint"></i> Blood Group</label>
                        <select id="bloodGroup" name="bloodGroup">
                            <option value="">Select</option>
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
                        <input type="text" id="rank" name="rank" required placeholder="e.g., Captain">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-building"></i> Unit/Branch <span class="required-star">*</span></label>
                        <input type="text" id="branch" name="branch" required placeholder="e.g., Infantry">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-calendar-alt"></i> Recruitment Date <span class="required-star">*</span></label>
                        <input type="date" id="recruitmentDate" name="recruitmentDate" required>
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-flag-checkered"></i> Status</label>
                        <select id="status" name="status">
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
                        <input type="email" id="email" name="email">
                    </div>
                    <div class="input-field">
                        <label><i class="fas fa-phone"></i> Contact</label>
                        <input type="tel" id="contact" name="contact">
                    </div>
                    
                    <!-- Location Fields -->
                    <div class="input-field">
                        <label><i class="fas fa-map-marker-alt"></i> Province</label>
                        <select id="province" name="province">
                            <option value="">Select Province</option>
                            <option value="Province 1">Province 1</option>
                            <option value="Madhesh Province">Madhesh Province</option>
                            <option value="Bagmati Province">Bagmati Province</option>
                            <option value="Gandaki Province">Gandaki Province</option>
                            <option value="Lumbini Province">Lumbini Province</option>
                            <option value="Karnali Province">Karnali Province</option>
                            <option value="Sudurpashchim Province">Sudurpashchim Province</option>
                        </select>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-city"></i> District</label>
                        <input type="text" id="district" name="district" placeholder="e.g., Kathmandu, Chitwan" list="districtList">
                        <datalist id="districtList"></datalist>
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-landmark"></i> Municipality/Rural Municipality</label>
                        <input type="text" id="municipality" name="municipality" placeholder="e.g., Kathmandu Metropolitan City">
                    </div>
                    
                    <div class="input-field">
                        <label><i class="fas fa-hashtag"></i> Ward Number</label>
                        <input type="text" id="wardNumber" name="wardNumber" placeholder="e.g., 1, 2, 3">
                    </div>
                    
                    <div class="input-field full-width">
                        <label><i class="fas fa-home"></i> Village/Tole</label>
                        <input type="text" id="villageTole" name="villageTole" placeholder="e.g., Baneshwor, Thamel">
                    </div>
                    
                    <div class="input-field full-width">
                        <label><i class="fas fa-home"></i> Address</label>
                        <input type="text" id="address" name="address" placeholder="Full address">
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

<!-- Profile Photo Modal -->
<div id="profilePhotoModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="profilePhotoModalTitle"><i class="fas fa-camera"></i> Upload Profile Photo</h3>
            <span class="close-profile-photo">&times;</span>
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
                
                <div class="input-field">
                    <label><i class="fas fa-cloud-upload-alt"></i> Upload New Profile Photo</label>
                    <input type="file" id="profilePhotoFile" name="profile_photo" accept="image/*" style="padding: 8px;">
                    <small style="color: #6c7a8e;">Supported formats: JPG, PNG, GIF. Max size: 5MB. Recommended size: 300x300px.</small>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelPhotoBtn">Cancel</button>
                    <button type="button" class="btn-delete-photo" id="deletePhotoBtn" style="background: #dc2626; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-trash"></i> Remove Photo
                    </button>
                    <button type="submit" class="btn-submit">Upload Photo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Profile Photo View Modal -->
<div id="photoViewModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="photoViewTitle"><i class="fas fa-user-circle"></i> Profile Photo</h3>
            <span class="close-photo-view">&times;</span>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div id="photoViewImage"></div>
            <p id="photoViewName" style="margin-top: 15px; color: #6c7a8e;"></p>
        </div>
    </div>
</div>

<!-- Signature Modal -->
<div id="signatureModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="signatureModalTitle"><i class="fas fa-signature"></i> Upload Signature</h3>
            <span class="close-signature">&times;</span>
        </div>
        <div class="modal-body">
            <form id="signatureForm" enctype="multipart/form-data">
                <input type="hidden" id="signaturePersonnelId" name="personnel_id">
                <input type="hidden" id="existingSignature" name="existing_signature">
                
                <div class="signature-preview-container" style="text-align: center; margin-bottom: 20px;">
                    <div id="currentSignaturePreview" style="margin-bottom: 15px;">
                        <label style="font-size: 13px; color: #6c7a8e;">Current Signature:</label>
                        <div id="currentSignatureImage"></div>
                    </div>
                </div>
                
                <div class="input-field">
                    <label><i class="fas fa-cloud-upload-alt"></i> Upload New Signature Image</label>
                    <input type="file" id="signatureFile" name="signature" accept="image/*" style="padding: 8px;">
                    <small style="color: #6c7a8e;">Supported formats: JPG, PNG, GIF. Max size: 2MB. Recommended size: 300x100px.</small>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelSignatureBtn">Cancel</button>
                    <button type="button" class="btn-delete-signature" id="deleteSignatureBtn" style="background: #dc2626; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-trash"></i> Remove Signature
                    </button>
                    <button type="submit" class="btn-submit">Upload Signature</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Signature View Modal -->
<div id="signatureViewModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 id="signatureViewTitle"><i class="fas fa-signature"></i> Signature</h3>
            <span class="close-signature-view">&times;</span>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div id="signatureViewImage"></div>
            <p id="signatureViewName" style="margin-top: 15px; color: #6c7a8e;"></p>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #eef2f6;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        background: #e8f5f0;
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
    }
    
    .stat-label {
        font-size: 13px;
        color: #6c7a8e;
        margin-top: 4px;
    }
    
    /* Search Section */
    .search-section {
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .search-container {
        position: relative;
        flex: 1;
        max-width: 500px;
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
    }
    
    .clear-search:hover {
        background: #e2e8f0;
        color: #c2410c;
    }
    
    /* Buttons */
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
    }
    
    .btn-add:hover {
        background: #14362c;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    /* Photo Styles */
    .photo-cell {
        text-align: center;
        padding: 8px !important;
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
        transform: scale(1.2);
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
    
    .btn-photo {
        color: #3b82f6;
    }
    
    .btn-photo:hover {
        background: #dbeafe;
    }
    
    .photo-preview-container {
        padding: 15px;
        background: #f8fafc;
        border-radius: 12px;
        text-align: center;
    }
    
    .photo-preview-container img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e2e8f0;
        padding: 3px;
        background: white;
    }
    
    .btn-delete-photo:hover {
        background: #b91c1c;
    }
    
    /* Signature Styles */
    .signature-cell {
        text-align: center;
    }
    
    .signature-preview {
        max-width: 80px;
        max-height: 40px;
        cursor: pointer;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 2px;
        background: white;
        transition: transform 0.2s;
    }
    
    .signature-preview:hover {
        transform: scale(1.5);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        z-index: 10;
        position: relative;
    }
    
    .no-signature {
        color: #9aa9bc;
        font-size: 12px;
    }
    
    .btn-signature {
        color: #8b5cf6;
    }
    
    .btn-signature:hover {
        background: #f3e8ff;
    }
    
    .btn-delete-signature {
        background: #dc2626;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .btn-delete-signature:hover {
        background: #b91c1c;
    }
    
    .signature-preview-container {
        padding: 15px;
        background: #f8fafc;
        border-radius: 12px;
        text-align: center;
    }
    
    .signature-preview-container img {
        max-width: 250px;
        max-height: 80px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 5px;
        background: white;
    }
    
    /* Table */
    .data-table {
        background: white;
        border-radius: 12px;
        overflow-x: auto;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #eef2f6;
    }
    
    .data-table table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1300px;
    }
    
    .data-table th {
        text-align: left;
        padding: 15px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        color: #1a2c3e;
        font-size: 13px;
    }
    
    .data-table td {
        padding: 15px;
        border-bottom: 1px solid #eef2f6;
        font-size: 14px;
    }
    
    /* Badges */
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
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
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 14px;
    }
    
    .btn-edit {
        color: #2c5f4e;
    }
    
    .btn-edit:hover {
        background: #e8f5f0;
    }
    
    .btn-reset {
        color: #f59e0b;
    }
    
    .btn-reset:hover {
        background: #fef3c7;
    }
    
    .btn-delete {
        color: #c2410c;
    }
    
    .btn-delete:hover {
        background: #fff0ed;
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
    
    .page-dots {
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
        cursor: pointer;
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
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s;
    }
    
    .modal-content {
        background-color: white;
        margin: 5% auto;
        width: 90%;
        max-width: 800px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideDown 0.3s;
        max-height: 85vh;
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
    }
    
    .modal-header h3 {
        font-size: 20px;
        color: #1a2c3e;
        margin: 0;
    }
    
    .close, .close-signature, .close-signature-view, .close-profile-photo, .close-photo-view {
        font-size: 28px;
        cursor: pointer;
        color: #9aa9bc;
        transition: 0.2s;
    }
    
    .close:hover, .close-signature:hover, .close-signature-view:hover, .close-profile-photo:hover, .close-photo-view:hover {
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
    
    .input-field input,
    .input-field select {
        padding: 10px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
    }
    
    .input-field input:focus,
    .input-field select:focus {
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
    
    /* Responsive */
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
        .action-buttons {
            flex-direction: column;
        }
        .btn-icon {
            width: 32px;
            text-align: center;
        }
    }
</style>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none;">
    <span id="toastMessage"></span>
</div>

<script>
    // DOM Elements
    const modal = document.getElementById('personnelModal');
    const signatureModal = document.getElementById('signatureModal');
    const signatureViewModal = document.getElementById('signatureViewModal');
    const profilePhotoModal = document.getElementById('profilePhotoModal');
    const photoViewModal = document.getElementById('photoViewModal');
    const addBtn = document.getElementById('addPersonnelBtn');
    const closeBtns = document.querySelectorAll('.close, .close-signature, .close-signature-view, .close-profile-photo, .close-photo-view');
    const cancelBtn = document.getElementById('cancelBtn');
    const cancelSignatureBtn = document.getElementById('cancelSignatureBtn');
    const cancelPhotoBtn = document.getElementById('cancelPhotoBtn');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('personnelForm');
    const signatureForm = document.getElementById('signatureForm');
    const profilePhotoForm = document.getElementById('profilePhotoForm');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearch');
    const recordsPerPageSelect = document.getElementById('recordsPerPage');
    const deleteSignatureBtn = document.getElementById('deleteSignatureBtn');
    const deletePhotoBtn = document.getElementById('deletePhotoBtn');

    let isEditing = false;
    const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

    // Province to Districts mapping
    const provinceDistricts = {
        'Province 1': ['Bhojpur', 'Dhankuta', 'Ilam', 'Jhapa', 'Khotang', 'Morang', 'Okhaldhunga', 'Panchthar', 'Sankhuwasabha', 'Solukhumbu', 'Sunsari', 'Taplejung', 'Tehrathum', 'Udayapur'],
        'Madhesh Province': ['Bara', 'Dhanusha', 'Mahottari', 'Parsa', 'Rautahat', 'Saptari', 'Sarlahi', 'Siraha'],
        'Bagmati Province': ['Bhaktapur', 'Chitwan', 'Dhading', 'Dolakha', 'Kathmandu', 'Kavrepalanchok', 'Lalitpur', 'Makwanpur', 'Nuwakot', 'Ramechhap', 'Rasuwa', 'Sindhuli', 'Sindhupalchok'],
        'Gandaki Province': ['Baglung', 'Gorkha', 'Kaski', 'Lamjung', 'Manang', 'Mustang', 'Myagdi', 'Nawalpur', 'Parbat', 'Syangja', 'Tanahun'],
        'Lumbini Province': ['Arghakhanchi', 'Banke', 'Bardiya', 'Dang', 'Eastern Rukum', 'Gulmi', 'Kapilvastu', 'Parasi', 'Palpa', 'Pyuthan', 'Rolpa', 'Western Rukum'],
        'Karnali Province': ['Dailekh', 'Dolpa', 'Humla', 'Jajarkot', 'Jumla', 'Kalikot', 'Mugu', 'Salyan', 'Surkhet', 'Western Rukum'],
        'Sudurpashchim Province': ['Achham', 'Baitadi', 'Bajhang', 'Bajura', 'Dadeldhura', 'Darchula', 'Doti', 'Kailali', 'Kanchanpur']
    };

    // Load statistics
    async function loadStatistics() {
        try {
            const response = await fetch('get_personnel_stats.php');
            const data = await response.json();
            if (data.success) {
                if (document.getElementById('activeCount')) document.getElementById('activeCount').textContent = data.active_count || 0;
                if (document.getElementById('officerCount')) document.getElementById('officerCount').textContent = data.officer_count || 0;
                if (document.getElementById('unitCount')) document.getElementById('unitCount').textContent = data.unit_count || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

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

    // Update district dropdown based on province selection
    const provinceSelect = document.getElementById('province');
    const districtInput = document.getElementById('district');
    const districtDatalist = document.getElementById('districtList');

    if (provinceSelect) {
        provinceSelect.addEventListener('change', function() {
            const province = this.value;
            if (province && provinceDistricts[province]) {
                districtDatalist.innerHTML = '';
                provinceDistricts[province].forEach(district => {
                    const option = document.createElement('option');
                    option.value = district;
                    districtDatalist.appendChild(option);
                });
                districtInput.placeholder = 'Select or type district';
            } else {
                districtDatalist.innerHTML = '';
                districtInput.placeholder = 'e.g., Kathmandu, Chitwan';
            }
            districtInput.value = '';
        });
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
            window.location.href = window.location.pathname + '?per_page=' + (recordsPerPageSelect?.value || 10);
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

    // View Signature
    function viewSignature(signaturePath, name) {
        const viewImage = document.getElementById('signatureViewImage');
        const viewName = document.getElementById('signatureViewName');
        const viewTitle = document.getElementById('signatureViewTitle');
        
        if (viewImage) {
            viewImage.innerHTML = `<img src="${signaturePath}" alt="Signature of ${name}" style="max-width: 300px; max-height: 120px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: white;">`;
        }
        if (viewName) viewName.textContent = name;
        if (viewTitle) viewTitle.innerHTML = `<i class="fas fa-signature"></i> Signature - ${name}`;
        
        if (signatureViewModal) signatureViewModal.style.display = 'block';
    }

    // View Profile Photo
    function viewProfilePhoto(photoPath, name) {
        const viewImage = document.getElementById('photoViewImage');
        const viewName = document.getElementById('photoViewName');
        const viewTitle = document.getElementById('photoViewTitle');
        
        if (viewImage) {
            viewImage.innerHTML = `<img src="${photoPath}" alt="Profile Photo of ${name}" style="width: 200px; height: 200px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; padding: 5px; background: white;">`;
        }
        if (viewName) viewName.textContent = name;
        if (viewTitle) viewTitle.innerHTML = `<i class="fas fa-user-circle"></i> Profile Photo - ${name}`;
        
        if (photoViewModal) photoViewModal.style.display = 'block';
    }

    <?php if ($isSuperAdmin): ?>
    // Edit Profile Photo
    function editProfilePhoto(serviceNo, name) {
        document.getElementById('photoPersonnelId').value = serviceNo;
        
        const modalTitle = document.getElementById('profilePhotoModalTitle');
        if (modalTitle) modalTitle.innerHTML = `<i class="fas fa-camera"></i> Upload Profile Photo - ${name}`;
        
        // Get current photo path
        fetch(`get_personnel.php?id=${serviceNo}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.profile_picture_path) {
                    const previewContainer = document.getElementById('currentPhotoImage');
                    if (previewContainer) {
                        previewContainer.innerHTML = `<img src="${data.data.profile_picture_path}" alt="Current Photo" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; padding: 3px;">`;
                    }
                    document.getElementById('existingPhoto').value = data.data.profile_picture_path;
                } else {
                    const previewContainer = document.getElementById('currentPhotoImage');
                    if (previewContainer) {
                        previewContainer.innerHTML = `<span style="color: #9aa9bc;">No profile photo uploaded yet</span>`;
                    }
                    document.getElementById('existingPhoto').value = '';
                }
            });
        
        // Reset file input
        const fileInput = document.getElementById('profilePhotoFile');
        if (fileInput) fileInput.value = '';
        
        if (profilePhotoModal) profilePhotoModal.style.display = 'block';
    }

    // Handle profile photo form submission
    if (profilePhotoForm) {
        profilePhotoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const personnelId = document.getElementById('photoPersonnelId').value;
            const fileInput = document.getElementById('profilePhotoFile');
            const file = fileInput.files[0];
            
            if (!file) {
                showToast('Please select a profile photo to upload', 'error');
                return;
            }
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Please upload a valid image file (JPG, PNG, GIF, WEBP)', 'error');
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showToast('File size must be less than 5MB', 'error');
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
                    if (profilePhotoModal) profilePhotoModal.style.display = 'none';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message || 'Error uploading profile photo', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error uploading profile photo', 'error');
            }
        });
    }

    // Delete profile photo
    if (deletePhotoBtn) {
        deletePhotoBtn.addEventListener('click', async function() {
            const personnelId = document.getElementById('photoPersonnelId').value;
            const name = document.getElementById('profilePhotoModalTitle')?.innerText.split('-')[1]?.trim() || '';
            
            if (confirm(`Are you sure you want to remove the profile photo for ${name}?`)) {
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
                        if (profilePhotoModal) profilePhotoModal.style.display = 'none';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(result.message || 'Error removing profile photo', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error removing profile photo', 'error');
                }
            }
        });
    }

    // Edit Signature
    function editSignature(serviceNo, name, currentSignature) {
        document.getElementById('signaturePersonnelId').value = serviceNo;
        document.getElementById('existingSignature').value = currentSignature;
        
        const modalTitle = document.getElementById('signatureModalTitle');
        if (modalTitle) modalTitle.innerHTML = `<i class="fas fa-signature"></i> Upload Signature - ${name}`;
        
        // Show current signature preview
        const previewContainer = document.getElementById('currentSignatureImage');
        if (previewContainer) {
            if (currentSignature && currentSignature !== '') {
                previewContainer.innerHTML = `<img src="${currentSignature}" alt="Current Signature" style="max-width: 250px; max-height: 80px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 5px; background: white;">`;
            } else {
                previewContainer.innerHTML = `<span style="color: #9aa9bc;">No signature uploaded yet</span>`;
            }
        }
        
        // Reset file input
        const fileInput = document.getElementById('signatureFile');
        if (fileInput) fileInput.value = '';
        
        if (signatureModal) signatureModal.style.display = 'block';
    }

    // Handle signature form submission
    if (signatureForm) {
        signatureForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const personnelId = document.getElementById('signaturePersonnelId').value;
            const fileInput = document.getElementById('signatureFile');
            const file = fileInput.files[0];
            
            if (!file) {
                showToast('Please select a signature image to upload', 'error');
                return;
            }
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Please upload a valid image file (JPG, PNG, GIF)', 'error');
                return;
            }
            
            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                showToast('File size must be less than 2MB', 'error');
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
                    if (signatureModal) signatureModal.style.display = 'none';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message || 'Error uploading signature', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error uploading signature', 'error');
            }
        });
    }

    // Delete signature
    if (deleteSignatureBtn) {
        deleteSignatureBtn.addEventListener('click', async function() {
            const personnelId = document.getElementById('signaturePersonnelId').value;
            const name = document.getElementById('signatureModalTitle')?.innerText.split('-')[1]?.trim() || '';
            
            if (confirm(`Are you sure you want to remove the signature for ${name}?`)) {
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
                        if (signatureModal) signatureModal.style.display = 'none';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(result.message || 'Error removing signature', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error removing signature', 'error');
                }
            }
        });
    }

    // Open modal for adding
    if (addBtn) {
        addBtn.onclick = function() {
            isEditing = false;
            modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add New Personnel';
            form.reset();
            document.getElementById('editId').value = '';
            document.getElementById('role').value = '0';
            // Reset district datalist
            if (districtDatalist) districtDatalist.innerHTML = '';
            if (provinceSelect) provinceSelect.value = '';
            if (modal) modal.style.display = 'block';
        }
    }

    // Close modals
    function closeModals() {
        if (modal) modal.style.display = 'none';
        if (signatureModal) signatureModal.style.display = 'none';
        if (signatureViewModal) signatureViewModal.style.display = 'none';
        if (profilePhotoModal) profilePhotoModal.style.display = 'none';
        if (photoViewModal) photoViewModal.style.display = 'none';
        form?.reset();
        isEditing = false;
    }

    closeBtns.forEach(btn => {
        btn.onclick = closeModals;
    });

    if (cancelBtn) cancelBtn.onclick = closeModals;
    if (cancelSignatureBtn) cancelSignatureBtn.onclick = closeModals;
    if (cancelPhotoBtn) cancelPhotoBtn.onclick = closeModals;

    window.onclick = function(event) {
        if (event.target == modal) closeModals();
        if (event.target == signatureModal) closeModals();
        if (event.target == signatureViewModal) closeModals();
        if (event.target == profilePhotoModal) closeModals();
        if (event.target == photoViewModal) closeModals();
    }

    // Edit personnel
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
                    document.getElementById('dob').value = data.data.dob || '';
                    document.getElementById('gender').value = data.data.gender || '';
                    document.getElementById('bloodGroup').value = data.data.blood_group || '';
                    document.getElementById('rank').value = data.data.rank || '';
                    document.getElementById('branch').value = data.data.unit || '';
                    document.getElementById('recruitmentDate').value = data.data.recruitment_date || '';
                    document.getElementById('status').value = data.data.current_status || 'Active';
                    document.getElementById('role').value = data.data.role || 0;
                    document.getElementById('email').value = data.data.email || '';
                    document.getElementById('contact').value = data.data.contact || '';
                    document.getElementById('address').value = data.data.address || '';
                    
                    // Location fields
                    document.getElementById('province').value = data.data.province || '';
                    document.getElementById('district').value = data.data.district || '';
                    document.getElementById('municipality').value = data.data.municipality || '';
                    document.getElementById('wardNumber').value = data.data.ward_number || '';
                    document.getElementById('villageTole').value = data.data.village_tole || '';
                    
                    // Trigger province change to populate districts
                    if (data.data.province) {
                        const provinceChangeEvent = new Event('change');
                        provinceSelect.dispatchEvent(provinceChangeEvent);
                        setTimeout(() => {
                            document.getElementById('district').value = data.data.district || '';
                        }, 100);
                    }
                    
                    if (modal) modal.style.display = 'block';
                } else {
                    showToast('Error loading personnel data', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading personnel data', 'error');
            });
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

    // Handle form submission
    if (form) {
        form.onsubmit = function(e) {
            e.preventDefault();
            
            const serviceNo = document.getElementById('serviceNo').value;
            const fullName = document.getElementById('fullName').value;
            const rank = document.getElementById('rank').value;
            const branch = document.getElementById('branch').value;
            const recruitmentDate = document.getElementById('recruitmentDate').value;
            
            if(!serviceNo || !fullName || !rank || !branch || !recruitmentDate) {
                showToast('Please fill all required fields', 'error');
                return;
            }
            
            const formData = new URLSearchParams();
            formData.append('action', 'save_personnel');
            formData.append('editId', document.getElementById('editId').value);
            formData.append('serviceNo', serviceNo);
            formData.append('fullName', fullName);
            formData.append('dob', document.getElementById('dob').value);
            formData.append('gender', document.getElementById('gender').value);
            formData.append('bloodGroup', document.getElementById('bloodGroup').value);
            formData.append('rank', rank);
            formData.append('branch', branch);
            formData.append('recruitmentDate', recruitmentDate);
            formData.append('status', document.getElementById('status').value);
            formData.append('role', document.getElementById('role').value);
            formData.append('email', document.getElementById('email').value);
            formData.append('contact', document.getElementById('contact').value);
            formData.append('address', document.getElementById('address').value);
            
            // Location fields
            formData.append('province', document.getElementById('province').value);
            formData.append('district', document.getElementById('district').value);
            formData.append('municipality', document.getElementById('municipality').value);
            formData.append('wardNumber', document.getElementById('wardNumber').value);
            formData.append('villageTole', document.getElementById('villageTole').value);
            
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
                    closeModals();
                    setTimeout(() => location.reload(), 1000);
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

    // Load statistics on page load
    loadStatistics();
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>