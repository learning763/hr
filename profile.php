<?php
session_start();
include ('helper/rank.php');

// // Check if user is logged in
// if (!isset($_SESSION['user_id']) && !isset($_SESSION['personnel_number'])) {
//     header('Location: login.php');
//     exit;
// }

include('includes/config.php');
include('includes/pagination.php');

$pageTitle = "Personnel Profile";
$pageSubtitle = "View and manage personnel information";
$activePage = "profile";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Pagination variables - ensure they are integers
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get search parameter
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_personnel_number = isset($_GET['personnel_number']) ? $_GET['personnel_number'] : '';

// Handle AJAX requests for searching and pagination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Search personnel by number or name with pagination
    if ($action === 'search_personnel') {
        $search = trim($_POST['search'] ?? '');
        $current_page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $items_per_page = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $offset_ajax = ($current_page - 1) * $items_per_page;
        
        if (empty($search)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a search term']);
            exit;
        }
        
        // Count total records
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM personnel 
            WHERE personnel_number LIKE :search1 OR full_name_en LIKE :search2
        ");
        $searchTerm = "%$search%";
        $countStmt->bindParam(':search1', $searchTerm, PDO::PARAM_STR);
        $countStmt->bindParam(':search2', $searchTerm, PDO::PARAM_STR);
        $countStmt->execute();
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $items_per_page);
        
        // Get paginated results - Use named parameters consistently
        $stmt = $pdo->prepare("
            SELECT * FROM personnel 
            WHERE personnel_number LIKE :search1 OR full_name_en LIKE :search2
            ORDER BY full_name_en
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindParam(':search1', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':search2', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset_ajax, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'data' => $results,
            'total' => $totalRecords,
            'current_page' => $current_page,
            'total_pages' => $totalPages
        ]);
        exit;
    }
    
    // Get personnel list with pagination
    if ($action === 'get_personnel_list') {
        $current_page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $items_per_page = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $offset_ajax = ($current_page - 1) * $items_per_page;
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        
        if (!empty($search)) {
            // Search with pagination
            $searchTerm = "%$search%";
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) as total FROM personnel 
                WHERE personnel_number LIKE :search1 OR full_name_en LIKE :search2 OR rank LIKE :search3 OR unit LIKE :search4
            ");
            $countStmt->bindParam(':search1', $searchTerm, PDO::PARAM_STR);
            $countStmt->bindParam(':search2', $searchTerm, PDO::PARAM_STR);
            $countStmt->bindParam(':search3', $searchTerm, PDO::PARAM_STR);
            $countStmt->bindParam(':search4', $searchTerm, PDO::PARAM_STR);
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $pdo->prepare("
                SELECT * FROM personnel 
                WHERE personnel_number LIKE :search1 OR full_name_en LIKE :search2 OR rank LIKE :search3 OR unit LIKE :search4
                ORDER BY full_name_en
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindParam(':search1', $searchTerm, PDO::PARAM_STR);
            $stmt->bindParam(':search2', $searchTerm, PDO::PARAM_STR);
            $stmt->bindParam(':search3', $searchTerm, PDO::PARAM_STR);
            $stmt->bindParam(':search4', $searchTerm, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset_ajax, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // Get total records
            $countStmt = $pdo->query("SELECT COUNT(*) as total FROM personnel");
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get personnel with pagination
            $stmt = $pdo->prepare("
                SELECT * FROM personnel 
                ORDER BY full_name_en
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset_ajax, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalPages = ceil($totalRecords / $items_per_page);
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'total' => $totalRecords,
            'current_page' => $current_page,
            'total_pages' => $totalPages,
            'search' => $search
        ]);
        exit;
    }
    
    // Update personnel profile
    if ($action === 'update_profile') {
        $personnel_number = $_POST['personnel_number'] ?? '';
        $full_name_en = trim($_POST['full_name_en'] ?? '');
        $full_name_ne = trim($_POST['full_name_ne'] ?? '');
        $dob = $_POST['dob'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $blood_group = $_POST['blood_group'] ?? null;
        $rank = trim($_POST['rank'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $religion = trim($_POST['religion'] ?? 'Hindu');
        $military_status = trim($_POST['military_status'] ?? 'Single');
        $recruitment_date = $_POST['recruitment_date'] ?? null;
        $commission_date = $_POST['commission_date'] ?? null;
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $spouse_name = trim($_POST['spouse_name'] ?? '');
        $grandfather_name = trim($_POST['grandfather_name'] ?? '');
        $children_names = trim($_POST['children_names'] ?? '');
        $family_notes = trim($_POST['family_notes'] ?? '');
        $higher_education = trim($_POST['higher_education'] ?? '');
        $military_trainings = trim($_POST['military_trainings'] ?? '');
        $training = trim($_POST['training'] ?? '');
        $training_address = trim($_POST['training_address'] ?? '');
        $training1 = trim($_POST['training1'] ?? '');
        $training1_address = trim($_POST['training1_address'] ?? '');
        $training2 = trim($_POST['training2'] ?? '');
        $training2_address = trim($_POST['training2_address'] ?? '');
        $training3 = trim($_POST['training3'] ?? '');
        $training4 = trim($_POST['training4'] ?? '');
        $training5 = trim($_POST['training5'] ?? '');
        $foreign_training = trim($_POST['foreign_training'] ?? '');
        $current_status = trim($_POST['current_status'] ?? 'Active');
        
        $stmt = $pdo->prepare("
            UPDATE personnel 
            SET full_name_en = ?, full_name_ne = ?, dob = ?, gender = ?, blood_group = ?,
                `rank` = ?, unit = ?, email = ?, contact = ?, phone = ?, address = ?,
                religion = ?, military_status = ?, recruitment_date = ?, commission_date = ?,
                father_name = ?, mother_name = ?, spouse_name = ?, grandfather_name = ?,
                children_names = ?, family_notes = ?, higher_education = ?, military_trainings = ?,
                training = ?, training_address = ?, training1 = ?, training1_address = ?, 
                training2 = ?, training2_address = ?, training3 = ?, training4 = ?, 
                training5 = ?, foreign_training = ?, current_status = ?, updated_at = NOW()
            WHERE personnel_number = ?
        ");
        
        $result = $stmt->execute([
            $full_name_en, $full_name_ne, $dob, $gender, $blood_group,
            $rank, $unit, $email, $contact, $phone, $address,
            $religion, $military_status, $recruitment_date, $commission_date,
            $father_name, $mother_name, $spouse_name, $grandfather_name,
            $children_names, $family_notes, $higher_education, $military_trainings,
            $training, $training_address, $training1, $training1_address,
            $training2, $training2_address, $training3, $training4,
            $training5, $foreign_training, $current_status, $personnel_number
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Get total count for personnel - Use named parameters consistently
if (!empty($search_term)) {
    $searchTerm = "%$search_term%";
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM personnel 
        WHERE personnel_number LIKE :search1 OR full_name_en LIKE :search2 OR rank LIKE :search3 OR unit LIKE :search4
    ");
    $countStmt->bindParam(':search1', $searchTerm, PDO::PARAM_STR);
    $countStmt->bindParam(':search2', $searchTerm, PDO::PARAM_STR);
    $countStmt->bindParam(':search3', $searchTerm, PDO::PARAM_STR);
    $countStmt->bindParam(':search4', $searchTerm, PDO::PARAM_STR);
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT * FROM personnel 
        WHERE personnel_number LIKE :search1 OR full_name_en LIKE :search2 OR rank LIKE :search3 OR unit LIKE :search4
        ORDER BY full_name_en
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':search1', $searchTerm, PDO::PARAM_STR);
    $stmt->bindParam(':search2', $searchTerm, PDO::PARAM_STR);
    $stmt->bindParam(':search3', $searchTerm, PDO::PARAM_STR);
    $stmt->bindParam(':search4', $searchTerm, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
} else {
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM personnel");
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT * FROM personnel 
        ORDER BY full_name_en
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
}

$allPersonnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = ceil($totalRecords / $limit);

// Get the selected personnel data
$selectedPersonnel = null;
if ($selected_personnel_number) {
    $stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_number = ?");
    $stmt->execute([$selected_personnel_number]);
    $selectedPersonnel = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Calculate years of service function
function calculateYearsOfService($join_date) {
    if (!$join_date) return 'N/A';
    $join = new DateTime($join_date);
    $today = new DateTime();
    $diff = $join->diff($today);
    return $diff->y . ' years ' . $diff->m . ' months';
}

ob_start();
?>

<!-- Nepali Army Header -->
<div class="army-header">
    <div class="army-logo">
        <i class="fas fa-shield-alt"></i>
    </div>
    <div class="army-title">
        <h2>NEPALI ARMY</h2>
        <h4>DIRECTORATE OF CYBER SECURITY</h4>
        <h5>STAFF PROFILE FORM</h5>
    </div>
</div>

<!-- Personnel List Section -->
<div class="personnel-list-section">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="personnelSearch" class="search-input" 
               placeholder="Search Personnel by Name, Number, Rank, or Unit..." 
               value="<?php echo htmlspecialchars($search_term); ?>">
        <button id="clearSearch" class="clear-search" style="display: <?php echo !empty($search_term) ? 'block' : 'none'; ?>;">✕</button>
    </div>

    <!-- Personnel Table -->
    <div class="personnel-table-container">
        <table class="personnel-table">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Personnel No.</th>
                    <th>Rank</th>
                    <th>Full Name</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="personnelTableBody">
                <?php if (empty($allPersonnel)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No personnel records found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($allPersonnel as $index => $person): ?>
                <tr class="personnel-row <?php echo ($person['personnel_number'] == $selected_personnel_number) ? 'active-row' : ''; ?>" 
                    data-personnel-number="<?php echo htmlspecialchars($person['personnel_number']); ?>">
                    <td><?php echo $offset + $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($person['personnel_number']); ?></td>
                    <td><?php echo htmlspecialchars($person['rank']); ?></td>
                    <td><?php echo htmlspecialchars($person['full_name_en']); ?></td>
                    <td><?php echo htmlspecialchars($person['unit']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($person['current_status'] ?? 'active'); ?>">
                            <?php echo htmlspecialchars($person['current_status'] ?? 'Active'); ?>
                        </span>
                    </td>
                    <td>
                        <a href="?personnel_number=<?php echo urlencode($person['personnel_number']); ?>&page=<?php echo $page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="view-profile-btn">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="pagination-wrapper">
        <?php
        // Render pagination info
        renderPaginationInfo($offset, $limit, $totalRecords);
        
        // Build base URL for pagination
        $baseUrl = '?';
        if (!empty($search_term)) {
            $baseUrl .= 'search=' . urlencode($search_term) . '&';
        }
        if (!empty($selected_personnel_number)) {
            $baseUrl .= 'personnel_number=' . urlencode($selected_personnel_number) . '&';
        }
        
        // Render pagination
        renderPagination($page, $totalPages, $baseUrl);
        ?>
    </div>
</div>

<?php if ($selectedPersonnel): ?>

<!-- Profile Content -->
<div class="profile-container" id="profileContainer">
    <div class="profile-header">
        <h3><i class="fas fa-user-circle"></i> Personnel Profile</h3>
        <button class="close-profile-btn" id="closeProfileBtn">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <!-- 1. Personal Details Section -->
    <div class="profile-section">
        <div class="section-title">
            <h3>1. Personal Details</h3>
            <button class="edit-profile-btn" id="editProfileBtn">
                <i class="fas fa-edit"></i> Edit Profile
            </button>
        </div>
        
        <div class="personal-details-grid">
            <div class="detail-row">
                <div class="detail-label">Unit:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['unit'] ?? 'Cyber Security Directorate'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Military Status:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['military_status'] ?? 'Single'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Per. No.:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['personnel_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Children:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['children_names'] ?? '-'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Rank:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['rank'] ?? 'N/A'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Religion:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['religion'] ?? 'Hindu'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['full_name_en'] ?? 'N/A'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Phone:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['phone'] ?? '-'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">DOB:</div>
                <div class="detail-value"><?php echo $selectedPersonnel['dob'] ? date('Y/m/d', strtotime($selectedPersonnel['dob'])) : 'N/A'; ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Mobile:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['contact'] ?? 'N/A'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Address:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['address'] ?? 'Tripurapur, Kathmandu'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">E-Mail:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['email'] ?? '-'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Blood Group:</div>
                <div class="detail-value"><?php echo htmlspecialchars($selectedPersonnel['blood_group'] ?? 'AB +'); ?></div>
            </div>
        </div>
    </div>

    <!-- 2. Official Details Section -->
    <div class="profile-section">
        <div class="section-title">
            <h3>2. Official Details</h3>
        </div>
        <div class="official-details">
            <div class="detail-row">
                <div class="detail-label">a) Enrollment Date:</div>
                <div class="detail-value"><?php echo $selectedPersonnel['recruitment_date'] ? date('Y/m/d', strtotime($selectedPersonnel['recruitment_date'])) : 'N/A'; ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">b) Departure Date:</div>
                <div class="detail-value"><?php echo $selectedPersonnel['commission_date'] ? date('Y/m/d', strtotime($selectedPersonnel['commission_date'])) : 'N/A'; ?></div>
            </div>
        </div>
    </div>

    <!-- 3. Academic Qualifications Section -->
    <div class="profile-section">
        <div class="section-title">
            <h3>3. Academic Qualifications:</h3>
        </div>
        <div class="academic-qualification">
            <div class="detail-value academic-value"><?php echo nl2br(htmlspecialchars($selectedPersonnel['higher_education'] ?? 'B.E Computer Engg')); ?></div>
        </div>
    </div>

    <!-- 4. Military Trainings Section -->
    <div class="profile-section">
        <div class="section-title">
            <h3>4. Military Trainings:</h3>
        </div>
        <div class="military-trainings">
            <div class="detail-value training-value"><?php echo nl2br(htmlspecialchars($selectedPersonnel['military_trainings'] ?? 'Basic Cybersecurity O (7) MCTE Mhow Indore MP')); ?></div>
        </div>
    </div>

    <!-- 5. Professional Trainings Section -->
    <div class="profile-section">
        <div class="section-title">
            <h3>5. Professional Trainings:</h3>
        </div>
        <table class="trainings-table">
            <thead>
                <tr>
                    <th>S.n</th>
                    <th>Trainings</th>
                    <th>Country/Address</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>a.</td>
                    <td><?php echo htmlspecialchars($selectedPersonnel['training'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($selectedPersonnel['training_address'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>b.</td>
                    <td><?php echo htmlspecialchars($selectedPersonnel['training1'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($selectedPersonnel['training1_address'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>c.</td>
                    <td><?php echo htmlspecialchars($selectedPersonnel['training2'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($selectedPersonnel['training2_address'] ?? '-'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 6. Foreign Training Section -->
    <div class="profile-section">
        <div class="section-title">
            <h3>6. Foreign Training</h3>
        </div>
        <div class="foreign-training">
            <div class="detail-value"><?php echo nl2br(htmlspecialchars($selectedPersonnel['foreign_training'] ?? 'Basic Cybersecurity O (7) MCTE Mhow Indore MP')); ?></div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="editProfileModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Profile - <?php echo htmlspecialchars($selectedPersonnel['full_name_en']); ?></h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editProfileForm">
                <input type="hidden" name="personnel_number" value="<?php echo htmlspecialchars($selectedPersonnel['personnel_number']); ?>">
                
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="personal">Personal</button>
                    <button type="button" class="tab-btn" data-tab="official">Official</button>
                    <button type="button" class="tab-btn" data-tab="education">Education & Training</button>
                    <button type="button" class="tab-btn" data-tab="family">Family</button>
                </div>
                
                <!-- Personal Tab -->
                <div class="tab-content active" id="personal-tab">
                    <div class="form-grid">
                        <div class="input-field">
                            <label>Unit</label>
                            <input type="text" id="editUnit" name="unit" value="<?php echo htmlspecialchars($selectedPersonnel['unit'] ?? 'Cyber Security Directorate'); ?>">
                        </div>
                        <div class="input-field">
                            <label>Military Status</label>
                            <select id="editMilitaryStatus" name="military_status">
                                <option value="Single" <?php echo ($selectedPersonnel['military_status'] ?? 'Single') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($selectedPersonnel['military_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                            </select>
                        </div>
                        <div class="input-field">
                            <label>Personnel Number</label>
                            <input type="text" id="editPersonnelNumber" value="<?php echo htmlspecialchars($selectedPersonnel['personnel_number']); ?>" readonly>
                        </div>
                        <div class="input-field">
                            <label>Children</label>
                            <input type="text" id="editChildrenNames" name="children_names" value="<?php echo htmlspecialchars($selectedPersonnel['children_names'] ?? ''); ?>" placeholder="Children names">
                        </div>
                        <div class="input-field">
                            <label>Rank</label>
                            <select id="editRank" name="rank" required>
                                <option value="">Select Rank</option>
                                <?php foreach ($nepal_army_ranks as $rank): ?>
                                    <option value="<?php echo htmlspecialchars($rank); ?>" 
                                        <?php echo (isset($selectedPersonnel['rank']) && $selectedPersonnel['rank'] == $rank) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rank); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-field">
                            <label>Religion</label>
                            <input type="text" id="editReligion" name="religion" value="<?php echo htmlspecialchars($selectedPersonnel['religion'] ?? 'Hindu'); ?>">
                        </div>
                        <div class="input-field full-width">
                            <label>Full Name (English)</label>
                            <input type="text" id="editFullNameEn" name="full_name_en" value="<?php echo htmlspecialchars($selectedPersonnel['full_name_en'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Full Name (Nepali)</label>
                            <input type="text" id="editFullNameNe" name="full_name_ne" value="<?php echo htmlspecialchars($selectedPersonnel['full_name_ne'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Phone</label>
                            <input type="text" id="editPhone" name="phone" value="<?php echo htmlspecialchars($selectedPersonnel['phone'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Date of Birth</label>
                            <input type="date" id="editDob" name="dob" value="<?php echo $selectedPersonnel['dob'] ?? ''; ?>">
                        </div>
                        <div class="input-field">
                            <label>Gender</label>
                            <select id="editGender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($selectedPersonnel['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($selectedPersonnel['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($selectedPersonnel['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="input-field">
                            <label>Mobile</label>
                            <input type="text" id="editContact" name="contact" value="<?php echo htmlspecialchars($selectedPersonnel['contact'] ?? ''); ?>">
                        </div>
                        <div class="input-field full-width">
                            <label>Address</label>
                            <input type="text" id="editAddress" name="address" value="<?php echo htmlspecialchars($selectedPersonnel['address'] ?? 'Tripurapur, Kathmandu'); ?>">
                        </div>
                        <div class="input-field">
                            <label>E-Mail</label>
                            <input type="email" id="editEmail" name="email" value="<?php echo htmlspecialchars($selectedPersonnel['email'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Blood Group</label>
                            <select id="editBloodGroup" name="blood_group">
                                <option value="">Select Blood Group</option>
                                <?php
                                $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                                foreach ($bloodGroups as $bg) {
                                    $selected = ($selectedPersonnel['blood_group'] ?? '') == $bg ? 'selected' : '';
                                    echo "<option value='$bg' $selected>$bg</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="input-field">
                            <label>Current Status</label>
                            <select id="editCurrentStatus" name="current_status">
                                <option value="Active" <?php echo ($selectedPersonnel['current_status'] ?? 'Active') == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Leave" <?php echo ($selectedPersonnel['current_status'] ?? '') == 'Leave' ? 'selected' : ''; ?>>Leave</option>
                                <option value="Retired" <?php echo ($selectedPersonnel['current_status'] ?? '') == 'Retired' ? 'selected' : ''; ?>>Retired</option>
                                <option value="Training" <?php echo ($selectedPersonnel['current_status'] ?? '') == 'Training' ? 'selected' : ''; ?>>Training</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Official Tab -->
                <div class="tab-content" id="official-tab">
                    <div class="form-grid">
                        <div class="input-field">
                            <label>Enrollment Date</label>
                            <input type="date" id="editRecruitmentDate" name="recruitment_date" value="<?php echo $selectedPersonnel['recruitment_date'] ?? ''; ?>">
                        </div>
                        <div class="input-field">
                            <label>Departure Date</label>
                            <input type="date" id="editCommissionDate" name="commission_date" value="<?php echo $selectedPersonnel['commission_date'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Education & Training Tab -->
                <div class="tab-content" id="education-tab">
                    <div class="form-grid">
                        <div class="input-field full-width">
                            <label>Academic Qualifications</label>
                            <textarea id="editHigherEducation" name="higher_education" rows="2"><?php echo htmlspecialchars($selectedPersonnel['higher_education'] ?? 'B.E Computer Engg'); ?></textarea>
                        </div>
                        <div class="input-field full-width">
                            <label>Military Trainings</label>
                            <textarea id="editMilitaryTrainings" name="military_trainings" rows="3"><?php echo htmlspecialchars($selectedPersonnel['military_trainings'] ?? ''); ?></textarea>
                        </div>
                        <div class="input-field">
                            <label>Professional Training a.</label>
                            <input type="text" id="editTraining" name="training" value="<?php echo htmlspecialchars($selectedPersonnel['training'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Country/Address</label>
                            <input type="text" id="editTrainingAddress" name="training_address" value="<?php echo htmlspecialchars($selectedPersonnel['training_address'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Professional Training b.</label>
                            <input type="text" id="editTraining1" name="training1" value="<?php echo htmlspecialchars($selectedPersonnel['training1'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Country/Address</label>
                            <input type="text" id="editTraining1Address" name="training1_address" value="<?php echo htmlspecialchars($selectedPersonnel['training1_address'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Professional Training c.</label>
                            <input type="text" id="editTraining2" name="training2" value="<?php echo htmlspecialchars($selectedPersonnel['training2'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Country/Address</label>
                            <input type="text" id="editTraining2Address" name="training2_address" value="<?php echo htmlspecialchars($selectedPersonnel['training2_address'] ?? ''); ?>">
                        </div>
                        <div class="input-field full-width">
                            <label>Foreign Training</label>
                            <textarea id="editForeignTraining" name="foreign_training" rows="2"><?php echo htmlspecialchars($selectedPersonnel['foreign_training'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Family Tab -->
                <div class="tab-content" id="family-tab">
                    <div class="form-grid">
                        <div class="input-field">
                            <label>Father's Name</label>
                            <input type="text" id="editFatherName" name="father_name" value="<?php echo htmlspecialchars($selectedPersonnel['father_name'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Mother's Name</label>
                            <input type="text" id="editMotherName" name="mother_name" value="<?php echo htmlspecialchars($selectedPersonnel['mother_name'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Spouse's Name</label>
                            <input type="text" id="editSpouseName" name="spouse_name" value="<?php echo htmlspecialchars($selectedPersonnel['spouse_name'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label>Grandfather's Name</label>
                            <input type="text" id="editGrandfatherName" name="grandfather_name" value="<?php echo htmlspecialchars($selectedPersonnel['grandfather_name'] ?? ''); ?>">
                        </div>
                        <div class="input-field full-width">
                            <label>Family Notes</label>
                            <textarea id="editFamilyNotes" name="family_notes" rows="2"><?php echo htmlspecialchars($selectedPersonnel['family_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- No Personnel Selected -->
<div class="no-personnel-message" id="noPersonnelMessage">
    <i class="fas fa-user-circle" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px;"></i>
    <h3>Select a Personnel</h3>
    <p>Please select a personnel from the table above to view their profile.</p>
</div>
<?php endif; ?>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none;">
    <span id="toastMessage"></span>
</div>

<style>
    /* Army Header Styles */
    .army-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: linear-gradient(135deg, #1a3a2f 0%, #2c5f4e 100%);
        border-radius: 12px;
        color: #ffd700;
    }
    
    .army-logo {
        font-size: 48px;
        margin-bottom: 10px;
    }
    
    .army-title h2 {
        margin: 0;
        font-size: 24px;
        letter-spacing: 2px;
    }
    
    .army-title h4 {
        margin: 5px 0;
        font-size: 16px;
        font-weight: normal;
    }
    
    .army-title h5 {
        margin: 0;
        font-size: 14px;
        font-weight: normal;
        color: #e0e0e0;
    }
    
    /* Personnel List Section */
    .personnel-list-section {
        background: white;
        border-radius: 12px;
        margin-bottom: 30px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .search-container {
        position: relative;
        max-width: 500px;
        margin-bottom: 20px;
    }
    
    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
    }
    
    .search-input {
        width: 100%;
        padding: 10px 35px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .search-input:focus {
        border-color: #2c5f4e;
        outline: none;
    }
    
    .clear-search {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #999;
        font-size: 16px;
    }
    
    .clear-search:hover {
        color: #333;
    }
    
    .personnel-table-container {
        overflow-x: auto;
        margin-bottom: 20px;
    }
    
    .personnel-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    
    .personnel-table th,
    .personnel-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .personnel-table th {
        background: #f5f5f5;
        font-weight: 600;
        color: #333;
    }
    
    .personnel-table tbody tr:hover {
        background: #f9f9f9;
        cursor: pointer;
    }
    
    .personnel-row.active-row {
        background: #e8f5e9;
        border-left: 3px solid #2c5f4e;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-leave {
        background: #fff3cd;
        color: #856404;
    }
    
    .status-retired {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-training {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .view-profile-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        background: #2c5f4e;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 12px;
        transition: background 0.2s;
    }
    
    .view-profile-btn:hover {
        background: #1a3a2f;
    }
    
    /* Pagination Styles */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }
    
    .pagination-info {
        font-size: 14px;
        color: #666;
    }
    
    .pagination {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .pagination a, .pagination-btn, .page-number {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 5px;
        color: #333;
        text-decoration: none;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .pagination a:hover, .pagination-btn:hover, .page-number:hover {
        background: #f0f0f0;
        border-color: #2c5f4e;
    }
    
    .page-number.active {
        background: #2c5f4e;
        border-color: #2c5f4e;
        color: white;
    }
    
    .page-dots {
        padding: 6px 8px;
        color: #999;
    }
    
    /* Profile Container */
    .profile-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        position: relative;
    }
    
    .profile-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #2c5f4e;
    }
    
    .profile-header h3 {
        margin: 0;
        color: #1a3a2f;
        font-size: 20px;
    }
    
    .close-profile-btn {
        background: #dc2626;
        color: white;
        border: none;
        padding: 6px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
    }
    
    .close-profile-btn:hover {
        background: #b91c1c;
    }
    
    /* Section Styles */
    .profile-section {
        margin-bottom: 30px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .section-title {
        background: #f5f5f5;
        padding: 12px 20px;
        border-bottom: 2px solid #2c5f4e;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .section-title h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #1a3a2f;
    }
    
    /* Personal Details Grid */
    .personal-details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0;
    }
    
    .detail-row {
        display: flex;
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .detail-row:nth-child(odd) {
        border-right: 1px solid #f0f0f0;
    }
    
    .detail-label {
        width: 130px;
        font-weight: 600;
        color: #555;
        font-size: 13px;
    }
    
    .detail-value {
        flex: 1;
        color: #333;
        font-size: 13px;
    }
    
    /* Official Details */
    .official-details {
        padding: 10px 0;
    }
    
    .official-details .detail-row {
        border-bottom: 1px solid #f0f0f0;
    }
    
    /* Academic Qualification */
    .academic-qualification {
        padding: 15px;
    }
    
    .academic-value {
        font-weight: 500;
        color: #2c5f4e;
    }
    
    /* Military Trainings */
    .military-trainings {
        padding: 15px;
    }
    
    .training-value {
        margin-bottom: 8px;
    }
    
    /* Trainings Table */
    .trainings-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .trainings-table th,
    .trainings-table td {
        padding: 10px 12px;
        text-align: left;
        border: 1px solid #e0e0e0;
    }
    
    .trainings-table th {
        background: #f5f5f5;
        font-weight: 600;
        font-size: 13px;
    }
    
    .trainings-table td {
        font-size: 13px;
    }
    
    /* Foreign Training */
    .foreign-training {
        padding: 15px;
    }
    
    /* Edit Button */
    .edit-profile-btn {
        background: #2c5f4e;
        color: white;
        border: none;
        padding: 6px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
    }
    
    .edit-profile-btn:hover {
        background: #1a3a2f;
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
    }
    
    .modal-content {
        background-color: white;
        margin: 3% auto;
        border-radius: 12px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f5f5f5;
        border-radius: 12px 12px 0 0;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 18px;
    }
    
    .close {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #999;
    }
    
    .close:hover {
        color: #333;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .form-tabs {
        display: flex;
        gap: 5px;
        border-bottom: 1px solid #e0e0e0;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .tab-btn {
        padding: 8px 20px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 14px;
        color: #666;
        transition: all 0.2s;
    }
    
    .tab-btn:hover {
        color: #2c5f4e;
    }
    
    .tab-btn.active {
        color: #2c5f4e;
        border-bottom: 2px solid #2c5f4e;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .input-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .input-field label {
        font-size: 12px;
        font-weight: 600;
        color: #555;
    }
    
    .input-field input,
    .input-field select,
    .input-field textarea {
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 13px;
        font-family: inherit;
    }
    
    .input-field input:focus,
    .input-field select:focus,
    .input-field textarea:focus {
        border-color: #2c5f4e;
        outline: none;
    }
    
    .full-width {
        grid-column: span 2;
    }
    
    .modal-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }
    
    .btn-cancel {
        padding: 8px 20px;
        background: #f0f0f0;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .btn-submit {
        padding: 8px 25px;
        background: #2c5f4e;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .btn-submit:hover {
        background: #1a3a2f;
    }
    
    /* No Personnel Message */
    .no-personnel-message {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 12px;
    }
    
    /* Toast */
    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #333;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        z-index: 1100;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .personal-details-grid {
            grid-template-columns: 1fr;
        }
        
        .detail-row:nth-child(odd) {
            border-right: none;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .full-width {
            grid-column: span 1;
        }
        
        .trainings-table {
            font-size: 12px;
        }
        
        .trainings-table th,
        .trainings-table td {
            padding: 6px 8px;
        }
        
        .personnel-table th,
        .personnel-table td {
            padding: 8px 10px;
            font-size: 12px;
        }
        
        .pagination-wrapper {
            flex-direction: column;
            align-items: center;
        }
    }
</style>

<script>
    let searchTimeout;
    const searchInput = document.getElementById('personnelSearch');
    const personnelTableBody = document.getElementById('personnelTableBody');
    const clearSearchBtn = document.getElementById('clearSearch');
    const closeProfileBtn = document.getElementById('closeProfileBtn');
    const profileContainer = document.getElementById('profileContainer');
    const noPersonnelMessage = document.getElementById('noPersonnelMessage');
    
    // Close profile functionality
    if (closeProfileBtn) {
        closeProfileBtn.addEventListener('click', function() {
            // Remove personnel_number from URL and reload
            const url = new URL(window.location.href);
            url.searchParams.delete('personnel_number');
            window.location.href = url.toString();
        });
    }
    
    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            if (clearSearchBtn) clearSearchBtn.style.display = searchTerm !== '' ? 'block' : 'none';
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearchAndReload(searchTerm);
            }, 500);
        });
    }
    
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            performSearchAndReload('');
            this.style.display = 'none';
            if (searchInput) searchInput.focus();
        });
    }
    
    function performSearchAndReload(searchTerm) {
        // Reload the page with search parameter
        const url = new URL(window.location.href);
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
            url.searchParams.delete('page'); // Reset to page 1 on new search
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }
    
    // Make table rows clickable
    document.querySelectorAll('.personnel-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't navigate if clicking on the view button
            if (e.target.closest('.view-profile-btn')) return;
            
            const personnelNumber = this.getAttribute('data-personnel-number');
            if (personnelNumber) {
                const url = new URL(window.location.href);
                url.searchParams.set('personnel_number', personnelNumber);
                window.location.href = url.toString();
            }
        });
    });
    
    // Modal elements
    const editModal = document.getElementById('editProfileModal');
    const editBtn = document.getElementById('editProfileBtn');
    const closeBtn = editModal?.querySelector('.close');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const editForm = document.getElementById('editProfileForm');
    
    if (editBtn) {
        editBtn.onclick = () => { if (editModal) editModal.style.display = 'block'; };
    }
    
    function closeModal() {
        if (editModal) editModal.style.display = 'none';
    }
    
    if (closeBtn) closeBtn.onclick = closeModal;
    if (cancelBtn) cancelBtn.onclick = closeModal;
    
    window.onclick = function(event) {
        if (event.target == editModal) closeModal();
    };
    
    // Tab functionality
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                const activeTab = document.getElementById(`${tabId}-tab`);
                if (activeTab) activeTab.classList.add('active');
            });
        });
    }
    
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        if (!toast || !toastMessage) return;
        toastMessage.textContent = message;
        toast.style.backgroundColor = type === 'success' ? '#2c5f4e' : '#dc2626';
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }
    
    // Handle form submission
    if (editForm) {
        editForm.onsubmit = async function(e) {
            e.preventDefault();
            
            const formData = new FormData(editForm);
            formData.append('action', 'update_profile');
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast(result.error || 'Failed to update profile', 'error');
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                showToast('Error updating profile', 'error');
            }
        };
    }
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>