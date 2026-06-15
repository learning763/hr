<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('helper/rank.php');

include('includes/config.php');
include('includes/pagination.php');

$pageTitle = "Personnel Profile";
$pageSubtitle = "View and manage personnel information";
$activePage = "profile";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function to decode JSON trainings
function decodeTrainings($jsonData)
{
    if (empty($jsonData) || $jsonData === 'null' || $jsonData === 'NULL') {
        return [];
    }
    $decoded = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    return is_array($decoded) ? $decoded : [];
}

// Pagination variables
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get search parameter
// $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
// $selected_personnel_number = isset($_GET['personnel_number']) ? $_GET['personnel_number'] : '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_profile_photo') {
        $personnel_id = $_POST['personnel_id'] ?? '';

        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];

            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                exit;
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File too large']);
                exit;
            }

            $upload_dir = 'uploads/profile_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $personnel_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $stmt = $pdo->prepare("SELECT profile_picture_path FROM personnel WHERE personnel_number = ?");
                $stmt->execute([$personnel_id]);
                $existing = $stmt->fetch();

                if ($existing && !empty($existing['profile_picture_path']) && file_exists($existing['profile_picture_path'])) {
                    unlink($existing['profile_picture_path']);
                }

                $update_stmt = $pdo->prepare("UPDATE personnel SET profile_picture_path = ? WHERE personnel_number = ?");
                if ($update_stmt->execute([$filepath, $personnel_id])) {
                    echo json_encode(['success' => true, 'message' => 'Photo uploaded', 'path' => $filepath]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database update failed']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Upload failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
        exit;
    }

    if ($action === 'delete_profile_photo') {
        $personnel_id = $_POST['personnel_id'] ?? '';

        $stmt = $pdo->prepare("SELECT profile_picture_path FROM personnel WHERE personnel_number = ?");
        $stmt->execute([$personnel_id]);
        $existing = $stmt->fetch();

        if ($existing && !empty($existing['profile_picture_path']) && file_exists($existing['profile_picture_path'])) {
            unlink($existing['profile_picture_path']);
        }

        $update_stmt = $pdo->prepare("UPDATE personnel SET profile_picture_path = NULL WHERE personnel_number = ?");
        if ($update_stmt->execute([$personnel_id])) {
            echo json_encode(['success' => true, 'message' => 'Photo removed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Remove failed']);
        }
        exit;
    }

    if ($action === 'update_profile') {
        $personnel_number = $_POST['personnel_number'] ?? '';
        $full_name_ne = trim($_POST['full_name_ne'] ?? '');
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

        // Handle dynamic professional trainings
        $professional_trainings = [];
        if (isset($_POST['professional_trainings']) && is_array($_POST['professional_trainings'])) {
            $trainings = array_values($_POST['professional_trainings']);
            foreach ($trainings as $training) {
                if (isset($training['name']) && !empty(trim($training['name']))) {
                    $professional_trainings[] = [
                        'name' => trim($training['name']),
                        'address' => isset($training['address']) ? trim($training['address']) : '',
                        'year' => isset($training['year']) ? trim($training['year']) : '',
                        'duration' => isset($training['duration']) ? trim($training['duration']) : '',
                        'institution' => isset($training['institution']) ? trim($training['institution']) : ''
                    ];
                }
            }
        }
        $professional_trainings_json = !empty($professional_trainings) ? json_encode($professional_trainings, JSON_UNESCAPED_UNICODE) : null;

        // Handle dynamic foreign trainings
        $foreign_trainings = [];
        if (isset($_POST['foreign_trainings']) && is_array($_POST['foreign_trainings'])) {
            $foreign = array_values($_POST['foreign_trainings']);
            foreach ($foreign as $training) {
                if (isset($training['name']) && !empty(trim($training['name']))) {
                    $foreign_trainings[] = [
                        'name' => trim($training['name']),
                        'country' => isset($training['country']) ? trim($training['country']) : '',
                        'year' => isset($training['year']) ? trim($training['year']) : '',
                        'duration' => isset($training['duration']) ? trim($training['duration']) : '',
                        'institution' => isset($training['institution']) ? trim($training['institution']) : ''
                    ];
                }
            }
        }
        $foreign_trainings_json = !empty($foreign_trainings) ? json_encode($foreign_trainings, JSON_UNESCAPED_UNICODE) : null;

        $current_status = trim($_POST['current_status'] ?? 'Active');

        $sql = "UPDATE personnel 
                SET full_name_ne = ?, full_name_ne = ?, dob = ?, gender = ?, blood_group = ?,
                    `rank` = ?, unit = ?, email = ?, contact = ?, phone = ?, address = ?,
                    religion = ?, military_status = ?, recruitment_date = ?, commission_date = ?,
                    father_name = ?, mother_name = ?, spouse_name = ?, grandfather_name = ?,
                    children_names = ?, family_notes = ?, higher_education = ?, military_trainings = ?,
                    professional_trainings = ?, foreign_trainings = ?, current_status = ?, updated_at = NOW()
                WHERE personnel_number = ?";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $full_name_ne,
            $full_name_ne,
            $dob,
            $gender,
            $blood_group,
            $rank,
            $unit,
            $email,
            $contact,
            $phone,
            $address,
            $religion,
            $military_status,
            $recruitment_date,
            $commission_date,
            $father_name,
            $mother_name,
            $spouse_name,
            $grandfather_name,
            $children_names,
            $family_notes,
            $higher_education,
            $military_trainings,
            $professional_trainings_json,
            $foreign_trainings_json,
            $current_status,
            $personnel_number
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            $error = $stmt->errorInfo();
            echo json_encode(['success' => false, 'error' => 'Database error: ' . ($error[2] ?? 'Unknown error')]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Get total count for personnel
if (!empty($search_term)) {
    // $searchTerm = "%$search_term%";
    // // $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM personnel WHERE personnel_number LIKE ? OR full_name_ne LIKE ? OR rank LIKE ? OR unit LIKE ?");
    // // $countStmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
   

  $stmt = $pdo->prepare("
    SELECT p.*,
           COALESCE(dr.rank_unicode, p.rank) AS rank_unicode
    FROM personnel p
    LEFT JOIN def_rank dr ON p.rank = dr.rank_code
    WHERE p.personnel_number LIKE ?
       OR p.full_name_ne LIKE ?
       OR p.rank LIKE ?
       OR p.unit LIKE ?
    ORDER BY p.full_name_ne
    LIMIT " . (int)$limit . "
    OFFSET " . (int)$offset
);
$stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]); 
 $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total']; 

    // $stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_number LIKE ? OR full_name_ne LIKE ? OR rank LIKE ? OR unit LIKE ? ORDER BY full_name_ne LIMIT " . (int) $limit . " OFFSET " . (int) $offset);
    $stmt = $pdo->prepare("
    SELECT p.*,
           COALESCE(dr.rank_unicode, p.rank) AS rank_unicode
    FROM personnel p
    LEFT JOIN def_rank dr ON p.rank = dr.rank_code
    WHERE p.personnel_number LIKE ? 
       OR p.full_name_ne LIKE ? 
       OR p.rank LIKE ? 
       OR p.unit LIKE ?
    ORDER BY p.full_name_ne
    LIMIT " . (int)$limit . "
    OFFSET " . (int)$offset
);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
} else {
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM personnel");
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // $stmt = $pdo->prepare("SELECT * FROM personnel ORDER BY full_name_ne LIMIT " . (int) $limit . " OFFSET " . (int) $offset);
    $stmt = $pdo->prepare("
    SELECT p.*,
           COALESCE(dr.rank_unicode, p.rank) AS rank_unicode
    FROM personnel p
    LEFT JOIN def_rank dr ON p.rank = dr.rank_code
    ORDER BY p.full_name_ne
    LIMIT " . (int)$limit . "
    OFFSET " . (int)$offset
);
    $stmt->execute();
}

$allPersonnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = ceil($totalRecords / $limit);

// Get selected personnel
$selectedPersonnel = null;
$professional_trainings_list = [];
$foreign_trainings_list = [];

if ($selected_personnel_number) {
    // $stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_number = ?");
    $stmt = $pdo->prepare("
    SELECT p.*,
           COALESCE(dr.rank_unicode, p.rank) AS rank_unicode
    FROM personnel p
    LEFT JOIN def_rank dr ON p.rank = dr.rank_code
    WHERE p.personnel_number = ?
");
    $stmt->execute([$selected_personnel_number]);
    $selectedPersonnel = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedPersonnel) {
        $professional_trainings_list = decodeTrainings($selectedPersonnel['professional_trainings'] ?? '');
        $foreign_trainings_list = decodeTrainings($selectedPersonnel['foreign_trainings'] ?? '');
    }
}

function calculateYearsOfService($join_date)
{
    if (!$join_date || $join_date == '0000-00-00')
        return 'N/A';
    $join = new DateTime($join_date);
    $today = new DateTime();
    $diff = $join->diff($today);
    return $diff->y . ' years ' . $diff->m . ' months';
}

ob_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>सकलदर्जाको प्रोफाइल - नेपाली सेना</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* All your existing styles remain the same */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
        }

        .army-header {
            background: linear-gradient(135deg, #0f2c24 0%, #1a5a4a 100%);
            border-radius: 20px;
            margin-bottom: 30px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .army-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 200%;
            background: rgba(255, 215, 0, 0.05);
            transform: rotate(35deg);
            pointer-events: none;
        }

        .army-header-content {
            display: flex;
            align-items: center;
            gap: 25px;
            position: relative;
            z-index: 1;
        }

        .army-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 215, 0, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: #ffd700;
            border: 2px solid rgba(255, 215, 0, 0.3);
        }

        .army-title h1 {
            color: #ffd700;
            font-size: 28px;
            letter-spacing: 3px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .army-title p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
            letter-spacing: 1px;
        }

        .army-title span {
            color: rgba(255, 215, 0, 0.8);
            font-size: 12px;
            font-weight: 500;
        }

        .personnel-list-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .search-container {
            position: relative;
            max-width: 400px;
            margin-bottom: 25px;
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .search-input:focus {
            border-color: #1a5a4a;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 90, 74, 0.1);
        }

        .clear-search {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #f1f5f9;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #64748b;
            font-size: 12px;
        }

        .personnel-table-container {
            overflow-x: auto;
        }

        .personnel-table {
            width: 100%;
            border-collapse: collapse;
        }

        .personnel-table th {
            text-align: left;
            padding: 15px;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e2e8f0;
        }

        .personnel-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        .personnel-row {
            cursor: pointer;
            transition: all 0.2s;
        }

        .personnel-row:hover {
            background: #f8fafc;
        }

        .personnel-row.active-row {
            background: linear-gradient(90deg, #e8f5e9, transparent);
            border-left: 3px solid #1a5a4a;
        }

        .photo-cell {
            width: 55px;
        }

        .table-profile-img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #1a5a4a;
            cursor: pointer;
        }

        .table-avatar-placeholder {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px dashed #cbd5e1;
            color: #94a3b8;
            font-size: 18px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-leave {
            background: #fed7aa;
            color: #9a3412;
        }

        .status-retired {
            background: #fee2e2;
            color: #991b1b;
        }

        .view-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: #1a5a4a;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .view-profile-btn:hover {
            background: #0f3d32;
            transform: translateY(-1px);
        }

        .download-profile-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 14px;
        }

        .download-profile-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        .premium-profile {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .profile-cover {
            background: linear-gradient(135deg, #1a5a4a, #0f3d32);
            height: 180px;
            position: relative;
        }

        .cover-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.6));
        }

        .profile-avatar-wrapper {
            position: absolute;
            bottom: -50px;
            left: 40px;
        }

        .profile-avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            background: white;
        }

        .profile-avatar-placeholder {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid white;
            cursor: pointer;
            color: #ffd700;
            font-size: 55px;
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #ffd700;
            border: 2px solid white;
            color: #1a5a4a;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .avatar-edit-btn:hover {
            transform: scale(1.1);
        }

        .close-profile {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.5);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.2s;
        }

        .close-profile:hover {
            background: rgba(220, 38, 38, 0.8);
        }

        .profile-info-bar {
            padding: 20px 30px 20px 190px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .profile-name-section h2 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .profile-badges {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .badge-rank,
        .badge-id,
        .badge-unit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #f1f5f9;
            border-radius: 20px;
            font-size: 12px;
            color: #475569;
        }

        .edit-main-btn {
            background: #1a5a4a;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .edit-main-btn:hover {
            background: #0f3d32;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding: 25px 30px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 15px 20px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6d9);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #1a5a4a;
        }

        .stat-info {
            flex: 1;
        }

        .stat-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            display: block;
            margin-top: 4px;
        }

        .profile-tabs {
            display: flex;
            gap: 5px;
            padding: 0 30px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .tab-btn {
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            position: relative;
        }

        .tab-btn:hover {
            color: #1a5a4a;
        }

        .tab-btn.active {
            color: #1a5a4a;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: #1a5a4a;
            border-radius: 3px 3px 0 0;
        }

        .tab-pane {
            display: none;
            padding: 30px;
            animation: fadeIn 0.3s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: 14px;
            transition: all 0.2s;
        }

        .info-item:hover {
            background: #f1f5f9;
            transform: translateX(3px);
        }

        .info-item.full-width {
            grid-column: span 2;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }

        .blood-badge {
            display: inline-block;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .info-card {
            background: #f8fafc;
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .info-card-title {
            background: #f1f5f9;
            padding: 15px 20px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-card-content {
            padding: 20px;
            line-height: 1.6;
            color: #475569;
        }

        .training-table-wrapper {
            overflow-x: auto;
        }

        .training-table {
            width: 100%;
            border-collapse: collapse;
        }

        .training-table th {
            padding: 12px 15px;
            text-align: left;
            background: #f1f5f9;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
        }

        .training-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            color: #334155;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 1000px;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        .modal-header h3 {
            font-size: 20px;
            color: #1e293b;
        }

        .modal-close,
        .modal-close-photo,
        .modal-close-view {
            font-size: 28px;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.2s;
        }

        .modal-close:hover,
        .modal-close-photo:hover,
        .modal-close-view:hover {
            color: #dc2626;
        }

        .modal-body {
            padding: 25px;
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a5a4a;
            margin: 20px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-title:first-of-type {
            margin-top: 0;
        }

        .modal-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #1a5a4a;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 90, 74, 0.1);
        }

        .dynamic-training-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .dynamic-training-item .remove-training {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .dynamic-training-item .remove-training:hover {
            background: #dc2626;
            color: white;
        }

        .add-training-btn {
            background: #1a5a4a;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 10px;
        }

        .add-training-btn:hover {
            background: #0f3d32;
        }

        .training-header {
            font-weight: 600;
            color: #1a5a4a;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-cancel {
            padding: 10px 20px;
            background: #f1f5f9;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-submit {
            padding: 10px 24px;
            background: #1a5a4a;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-delete {
            padding: 10px 20px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .photo-preview {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
        }

        .photo-preview img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1a5a4a;
        }

        .status-badge-lg {
            display: inline-flex;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 24px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #475569;
            margin-bottom: 8px;
        }

        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e293b;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            z-index: 1100;
            display: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }

        .pagination {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }

        .page-btn:hover {
            border-color: #1a5a4a;
            color: #1a5a4a;
        }

        .page-btn.active {
            background: #1a5a4a;
            border-color: #1a5a4a;
            color: white;
        }

        .action-buttons-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .info-item.full-width {
                grid-column: span 1;
            }

            .profile-info-bar {
                padding: 20px;
            }

            .profile-avatar-wrapper {
                left: 20px;
            }

            .profile-avatar,
            .profile-avatar-placeholder {
                width: 90px;
                height: 90px;
            }

            .profile-info-bar {
                padding-left: 130px;
            }

            .profile-tabs {
                overflow-x: auto;
            }

            .modal-form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .action-buttons-group {
                flex-direction: column;
                width: 100%;
            }

            .edit-main-btn,
            .download-profile-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <!-- Nepali Army Header -->
    <div class="army-header">
        <div class="army-header-content">
            <div class="army-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="army-title">
                <h1>NEPALI ARMY</h1>
                <p>DIRECTORATE OF CYBER SECURITY</p>
                <span>STAFF PROFILE FORM</span>
            </div>
        </div>
    </div>

    <!-- Personnel List Section -->
    <div class="personnel-list-section">
        <!-- <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="personnelSearch" class="search-input"
                placeholder="Search by Name, Number, Rank, or Unit..."
                value="<?php //echo htmlspecialchars($search_term); ?>">
            <button id="clearSearch" class="clear-search"
                style="display: <?php //echo !empty($search_term) ? 'flex' : 'none'; ?>;">✕</button>
        </div> -->

        <div class="personnel-table-container">
            <table class="datatable">
                <thead>
                    <tr>
                        <th>सि.नं.</th>
                        <th>फोटो</th>
                        <th>व्य.नं.</th>
                        <th>दर्जा</th>
                        <th>नामथर</th>
                        <th>युनिट</th>
                        <th>बहालवाला/अवकास</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allPersonnel)): ?>
                        <tr class="empty-row">
                            <td colspan="8" style="text-align: center; padding: 40px;">No personnel records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allPersonnel as $index => $person): ?>
                            <tr class="personnel-row <?php echo ($person['personnel_number'] == $selected_personnel_number) ? 'active-row' : ''; ?>"
                                data-personnel-number="<?php echo htmlspecialchars($person['personnel_number']); ?>">
                                <td class="sn-cell"><?php echo $offset + $index + 1; ?></td>
                                <td class="photo-cell">
                                    <?php if (!empty($person['profile_picture_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($person['profile_picture_path']); ?>"
                                            class="table-profile-img"
                                            onclick="event.stopPropagation(); viewProfilePhoto('<?php echo htmlspecialchars($person['profile_picture_path']); ?>', '<?php echo htmlspecialchars($person['full_name_ne']); ?>')">
                                    <?php else: ?>
                                        <div class="table-avatar-placeholder"
                                            onclick="event.stopPropagation(); editProfilePhoto('<?php echo htmlspecialchars($person['personnel_number']); ?>', '<?php echo htmlspecialchars($person['full_name_ne']); ?>')">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="personnel-no"><?php echo htmlspecialchars($person['personnel_number']); ?></td>
                                <td class="rank-cell"><?php echo htmlspecialchars($person['rank_unicode']); ?></td>
                                <td class="name-cell"><?php echo htmlspecialchars($person['full_name_ne']); ?></td>
                                <td><?php echo htmlspecialchars($person['unit']); ?></td>
                                <td><span
                                        class="status-badge status-<?php echo strtolower($person['current_status'] ?? 'active'); ?>"><?php echo htmlspecialchars($person['current_status'] ?? 'Active'); ?></span>
                                </td>
                                <td><a href="?personnel_number=<?php echo urlencode($person['personnel_number']); ?>&page=<?php echo $page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                        class="view-profile-btn"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- <div class="pagination-wrapper">
            <div class="pagination-info">Showing <?php echo $offset + 1; ?> to
                <?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> entries
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($selected_personnel_number) ? '&personnel_number=' . urlencode($selected_personnel_number) : ''; ?>"
                        class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($selected_personnel_number) ? '&personnel_number=' . urlencode($selected_personnel_number) : ''; ?>"
                        class="page-btn"><i class="fas fa-angle-left"></i></a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($selected_personnel_number) ? '&personnel_number=' . urlencode($selected_personnel_number) : ''; ?>"
                        class="page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($selected_personnel_number) ? '&personnel_number=' . urlencode($selected_personnel_number) : ''; ?>"
                        class="page-btn"><i class="fas fa-angle-right"></i></a>
                    <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($selected_personnel_number) ? '&personnel_number=' . urlencode($selected_personnel_number) : ''; ?>"
                        class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        </div> -->
    </div>

    <?php if ($selectedPersonnel): ?>

        <!-- Premium Profile Card -->
        <div class="premium-profile" id="profileContainer">
            <!-- Cover Image Section -->
            <div class="profile-cover">
                <div class="cover-overlay"></div>
                <div class="profile-avatar-wrapper">
                    <?php if (!empty($selectedPersonnel['profile_picture_path'])): ?>
                        <img src="<?php echo htmlspecialchars($selectedPersonnel['profile_picture_path']); ?>"
                            class="profile-avatar" id="mainProfilePhoto"
                            onclick="viewProfilePhoto('<?php echo htmlspecialchars($selectedPersonnel['profile_picture_path']); ?>', '<?php echo htmlspecialchars($selectedPersonnel['full_name_ne']); ?>')">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder" id="mainProfilePhoto"
                            onclick="editProfilePhoto('<?php echo htmlspecialchars($selectedPersonnel['personnel_number']); ?>', '<?php echo htmlspecialchars($selectedPersonnel['full_name_ne']); ?>')">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <button class="avatar-edit-btn"
                        onclick="editProfilePhoto('<?php echo htmlspecialchars($selectedPersonnel['personnel_number']); ?>', '<?php echo htmlspecialchars($selectedPersonnel['full_name_ne']); ?>')">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                <button class="close-profile" id="closeProfileBtn"><i class="fas fa-times"></i></button>
            </div>

            <!-- Profile Info Bar -->
            <div class="profile-info-bar">
                <div class="profile-name-section">
                    <h2><?php echo htmlspecialchars($selectedPersonnel['full_name_ne']); ?></h2>
                    <div class="profile-badges">
                        <span class="badge-rank"><i class="fas fa-star-of-life"></i>
                            <?php echo htmlspecialchars($selectedPersonnel['rank_unicode']); ?></span>
                        <span class="badge-id"><i class="fas fa-id-card"></i>
                            <?php echo htmlspecialchars($selectedPersonnel['personnel_number']); ?></span>
                        <span class="badge-unit"><i class="fas fa-building"></i>
                            <?php echo htmlspecialchars($selectedPersonnel['unit'] ?? 'Corps of Engineers'); ?></span>
                    </div>
                </div>
                <div class="action-buttons-group">
                    <button class="edit-main-btn" id="editProfileBtn"><i class="fas fa-edit"></i> Edit Profile</button>
                    <a href="print_profile.php?personnel_number=<?php echo urlencode($selectedPersonnel['personnel_number']); ?>"
                        class="download-profile-btn" target="_blank">
                        <i class="fas fa-download"></i> Download Profile
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Years of Service</span>
                        <span
                            class="stat-value"><?php echo calculateYearsOfService($selectedPersonnel['recruitment_date'] ?? null); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tint"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Blood Group</span>
                        <span
                            class="stat-value"><?php echo htmlspecialchars($selectedPersonnel['blood_group'] ?? 'AB+'); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Status</span>
                        <span
                            class="stat-value"><?php echo htmlspecialchars($selectedPersonnel['current_status'] ?? 'Active'); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Email</span>
                        <span
                            class="stat-value"><?php echo htmlspecialchars($selectedPersonnel['email'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="personal"><i class="fas fa-user"></i> Personal</button>
                <button class="tab-btn" data-tab="official"><i class="fas fa-briefcase"></i> Official</button>
                <button class="tab-btn" data-tab="education"><i class="fas fa-graduation-cap"></i> Education</button>
                <button class="tab-btn" data-tab="training"><i class="fas fa-chalkboard-teacher"></i> Training</button>
                <button class="tab-btn" data-tab="family"><i class="fas fa-users"></i> Family</button>
            </div>

            <!-- Tab Content: Personal Details -->
            <div class="tab-pane active" id="personal-tab">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-building"></i> Unit</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['unit'] ?? 'Corps of Engineers'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-user-tag"></i> Military Status</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['military_status'] ?? 'Single'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-star-of-life"></i> Rank</div>
                        <div class="info-value"><?php echo htmlspecialchars($selectedPersonnel['rank'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-pray"></i> Religion</div>
                        <div class="info-value"><?php echo htmlspecialchars($selectedPersonnel['religion'] ?? 'Hindu'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar"></i> Date of Birth</div>
                        <div class="info-value">
                            <?php echo $selectedPersonnel['dob'] && $selectedPersonnel['dob'] != '0000-00-00' ? date('F j, Y', strtotime($selectedPersonnel['dob'])) : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-venus-mars"></i> Gender</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['gender'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['phone'] ?? 'Not provided'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-mobile-alt"></i> Mobile</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['contact'] ?? 'Not provided'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['address'] ?? 'Tripurapur, Kathmandu'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-tint"></i> Blood Group</div>
                        <div class="info-value"><span
                                class="blood-badge"><?php echo htmlspecialchars($selectedPersonnel['blood_group'] ?? 'AB+'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Official Details -->
            <div class="tab-pane" id="official-tab">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar-plus"></i> Enrollment Date</div>
                        <div class="info-value">
                            <?php echo $selectedPersonnel['recruitment_date'] && $selectedPersonnel['recruitment_date'] != '0000-00-00' ? date('F j, Y', strtotime($selectedPersonnel['recruitment_date'])) : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar-minus"></i> Commission Date</div>
                        <div class="info-value">
                            <?php echo $selectedPersonnel['commission_date'] && $selectedPersonnel['commission_date'] != '0000-00-00' ? date('F j, Y', strtotime($selectedPersonnel['commission_date'])) : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-hourglass-half"></i> Years of Service</div>
                        <div class="info-value">
                            <?php echo calculateYearsOfService($selectedPersonnel['recruitment_date'] ?? null); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-flag-checkered"></i> Current Status</div>
                        <div class="info-value"><span
                                class="status-badge-lg status-<?php echo strtolower($selectedPersonnel['current_status'] ?? 'active'); ?>"><?php echo htmlspecialchars($selectedPersonnel['current_status'] ?? 'Active'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Education -->
            <div class="tab-pane" id="education-tab">
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-graduation-cap"></i> Academic Qualifications</div>
                    <div class="info-card-content">
                        <?php echo nl2br(htmlspecialchars($selectedPersonnel['higher_education'] ?? 'B.E Computer Engineering')); ?>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Training - With Professional & Foreign Trainings -->
            <div class="tab-pane" id="training-tab">
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-shield-alt"></i> Military Trainings</div>
                    <div class="info-card-content">
                        <?php echo nl2br(htmlspecialchars($selectedPersonnel['military_trainings'] ?? 'Basic Cybersecurity Course')); ?>
                    </div>
                </div>

                <!-- Professional Trainings -->
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-chalkboard-teacher"></i> Professional Trainings</div>
                    <?php if (!empty($professional_trainings_list)): ?>
                        <div class="training-table-wrapper">
                            <table class="training-table">
                                <thead>
                                    <tr>
                                        <th>S.No</th>
                                        <th>Training Name</th>
                                        <th>Location/Address</th>
                                        <th>Year</th>
                                        <th>Duration</th>
                                        <th>Institution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($professional_trainings_list as $index => $training): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($training['name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['address'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['year'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['duration'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['institution'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="info-card-content">No professional trainings recorded.</div>
                    <?php endif; ?>
                </div>

                <!-- Foreign Trainings -->
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-globe"></i> Foreign Trainings</div>
                    <?php if (!empty($foreign_trainings_list)): ?>
                        <div class="training-table-wrapper">
                            <table class="training-table">
                                <thead>
                                    <tr>
                                        <th>S.No</th>
                                        <th>Training Name</th>
                                        <th>Country</th>
                                        <th>Year</th>
                                        <th>Duration</th>
                                        <th>Institution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($foreign_trainings_list as $index => $training): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($training['name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['country'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['year'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['duration'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($training['institution'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="info-card-content">No foreign trainings recorded.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Content: Family -->
            <div class="tab-pane" id="family-tab">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-male"></i> Father's Name</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['father_name'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-female"></i> Mother's Name</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['mother_name'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-user-friends"></i> Spouse's Name</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['spouse_name'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-user"></i> Grandfather's Name</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['grandfather_name'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-child"></i> Children</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($selectedPersonnel['children_names'] ?? 'None'); ?>
                        </div>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label"><i class="fas fa-pen-alt"></i> Family Notes</div>
                        <div class="info-value">
                            <?php echo nl2br(htmlspecialchars($selectedPersonnel['family_notes'] ?? 'No additional information')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Modal -->
        <div id="editProfileModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="editProfileForm">
                        <input type="hidden" name="personnel_number"
                            value="<?php echo htmlspecialchars($selectedPersonnel['personnel_number']); ?>">

                        <!-- Personal Information Section -->
                        <div class="form-section-title"><i class="fas fa-user-circle"></i> Personal Information</div>
                        <div class="modal-form-grid">
                            <div class="form-group"><label>Full Name (English)</label><input type="text" name="full_name_ne"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['full_name_ne'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Full Name (Nepali)</label><input type="text" name="full_name_ne"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['full_name_ne'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Rank</label><input type="text" name="rank"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['rank'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Unit</label><input type="text" name="unit"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['unit'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Date of Birth</label><input type="date" name="dob"
                                    value="<?php echo $selectedPersonnel['dob'] && $selectedPersonnel['dob'] != '0000-00-00' ? $selectedPersonnel['dob'] : ''; ?>">
                            </div>
                            <div class="form-group"><label>Gender</label><select name="gender">
                                    <option value="">Select</option>
                                    <option value="Male" <?php echo ($selectedPersonnel['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($selectedPersonnel['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($selectedPersonnel['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select></div>
                            <div class="form-group"><label>Blood Group</label>
                                <select name="blood_group">
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo ($selectedPersonnel['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                    <option value="B+" <?php echo ($selectedPersonnel['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                    <option value="O+" <?php echo ($selectedPersonnel['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                    <option value="AB+" <?php echo ($selectedPersonnel['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                    <option value="A-" <?php echo ($selectedPersonnel['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                    <option value="B-" <?php echo ($selectedPersonnel['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                    <option value="O-" <?php echo ($selectedPersonnel['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                    <option value="AB-" <?php echo ($selectedPersonnel['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                </select>
                                <!-- <input type="text" name="blood_group" value="<?php echo htmlspecialchars($selectedPersonnel['blood_group'] ?? ''); ?>" placeholder="A+, B+, O+, etc."> -->
                            </div>
                            <div class="form-group"><label>Religion</label><input type="text" name="religion"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['religion'] ?? 'Hindu'); ?>">
                            </div>
                            <div class="form-group"><label>Email</label><input type="email" name="email"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['email'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Contact/Mobile</label><input type="text" name="contact"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['contact'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Phone (Landline)</label><input type="text" name="phone"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['phone'] ?? ''); ?>"></div>
                            <div class="form-group full-width"><label>Address</label><input type="text" name="address"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['address'] ?? ''); ?>"></div>
                        </div>

                        <!-- Official Details Section -->
                        <div class="form-section-title"><i class="fas fa-briefcase"></i> Official Details</div>
                        <div class="modal-form-grid">
                            <div class="form-group"><label>Military Status</label><select name="military_status">
                                    <option value="Single" <?php echo ($selectedPersonnel['military_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo ($selectedPersonnel['military_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                </select></div>
                            <div class="form-group"><label>Recruitment Date</label><input type="date"
                                    name="recruitment_date"
                                    value="<?php echo $selectedPersonnel['recruitment_date'] && $selectedPersonnel['recruitment_date'] != '0000-00-00' ? $selectedPersonnel['recruitment_date'] : ''; ?>">
                            </div>
                            <div class="form-group"><label>Commission Date</label><input type="date" name="commission_date"
                                    value="<?php echo $selectedPersonnel['commission_date'] && $selectedPersonnel['commission_date'] != '0000-00-00' ? $selectedPersonnel['commission_date'] : ''; ?>">
                            </div>
                            <div class="form-group"><label>Current Status</label><select name="current_status">
                                    <option value="Active" <?php echo ($selectedPersonnel['current_status'] ?? '') == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Leave" <?php echo ($selectedPersonnel['current_status'] ?? '') == 'Leave' ? 'selected' : ''; ?>>Leave</option>
                                    <option value="Retired" <?php echo ($selectedPersonnel['current_status'] ?? '') == 'Retired' ? 'selected' : ''; ?>>Retired</option>
                                    <option value="Training" <?php echo ($selectedPersonnel['current_status'] ?? '') == 'Training' ? 'selected' : ''; ?>>Training</option>
                                </select></div>
                        </div>

                        <!-- Family Information Section -->
                        <div class="form-section-title"><i class="fas fa-users"></i> Family Information</div>
                        <div class="modal-form-grid">
                            <div class="form-group"><label>Father's Name</label><input type="text" name="father_name"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['father_name'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Mother's Name</label><input type="text" name="mother_name"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['mother_name'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Spouse's Name</label><input type="text" name="spouse_name"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['spouse_name'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Grandfather's Name</label><input type="text"
                                    name="grandfather_name"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['grandfather_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group full-width"><label>Children Names</label><input type="text"
                                    name="children_names"
                                    value="<?php echo htmlspecialchars($selectedPersonnel['children_names'] ?? ''); ?>"
                                    placeholder="Comma separated names"></div>
                            <div class="form-group full-width"><label>Family Notes</label><textarea name="family_notes"
                                    rows="2"><?php echo htmlspecialchars($selectedPersonnel['family_notes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Education Section -->
                        <div class="form-section-title"><i class="fas fa-graduation-cap"></i> Education & Training</div>
                        <div class="modal-form-grid">
                            <div class="form-group full-width"><label>Academic Qualifications</label><textarea
                                    name="higher_education"
                                    rows="3"><?php echo htmlspecialchars($selectedPersonnel['higher_education'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group full-width"><label>Military Trainings</label><textarea
                                    name="military_trainings"
                                    rows="3"><?php echo htmlspecialchars($selectedPersonnel['military_trainings'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Dynamic Professional Trainings Section -->
                        <div class="form-section-title"><i class="fas fa-chalkboard-teacher"></i> Professional Trainings
                        </div>
                        <div id="professionalTrainingsContainer">
                            <?php
                            if (!empty($professional_trainings_list)) {
                                foreach ($professional_trainings_list as $index => $training):
                                    ?>
                                    <div class="dynamic-training-item" data-index="<?php echo $index; ?>">
                                        <button type="button" class="remove-training" onclick="removeTrainingItem(this)"><i
                                                class="fas fa-trash"></i></button>
                                        <div class="training-header">Training #<?php echo $index + 1; ?></div>
                                        <div class="modal-form-grid">
                                            <div class="form-group full-width"><label>Training Name <span
                                                        style="color: red;">*</span></label><input type="text"
                                                    name="professional_trainings[<?php echo $index; ?>][name]"
                                                    value="<?php echo htmlspecialchars($training['name'] ?? ''); ?>"
                                                    placeholder="e.g., Project Management Professional"></div>
                                            <div class="form-group"><label>Location/Address</label><input type="text"
                                                    name="professional_trainings[<?php echo $index; ?>][address]"
                                                    value="<?php echo htmlspecialchars($training['address'] ?? ''); ?>"
                                                    placeholder="Location"></div>
                                            <div class="form-group"><label>Year</label><input type="text"
                                                    name="professional_trainings[<?php echo $index; ?>][year]"
                                                    value="<?php echo htmlspecialchars($training['year'] ?? ''); ?>"
                                                    placeholder="2023"></div>
                                            <div class="form-group"><label>Duration</label><input type="text"
                                                    name="professional_trainings[<?php echo $index; ?>][duration]"
                                                    value="<?php echo htmlspecialchars($training['duration'] ?? ''); ?>"
                                                    placeholder="e.g., 3 months"></div>
                                            <div class="form-group"><label>Institution</label><input type="text"
                                                    name="professional_trainings[<?php echo $index; ?>][institution]"
                                                    value="<?php echo htmlspecialchars($training['institution'] ?? ''); ?>"
                                                    placeholder="Institution name"></div>
                                        </div>
                                    </div>
                                    <?php
                                endforeach;
                            } else { ?>
                                <div class="dynamic-training-item" data-index="0">
                                    <button type="button" class="remove-training" onclick="removeTrainingItem(this)"
                                        style="display: none;"><i class="fas fa-trash"></i></button>
                                    <div class="training-header">Training #1</div>
                                    <div class="modal-form-grid">
                                        <div class="form-group full-width"><label>Training Name <span
                                                    style="color: red;">*</span></label><input type="text"
                                                name="professional_trainings[0][name]"
                                                placeholder="e.g., Project Management Professional"></div>
                                        <div class="form-group"><label>Location/Address</label><input type="text"
                                                name="professional_trainings[0][address]" placeholder="Location"></div>
                                        <div class="form-group"><label>Year</label><input type="text"
                                                name="professional_trainings[0][year]" placeholder="2023"></div>
                                        <div class="form-group"><label>Duration</label><input type="text"
                                                name="professional_trainings[0][duration]" placeholder="e.g., 3 months"></div>
                                        <div class="form-group"><label>Institution</label><input type="text"
                                                name="professional_trainings[0][institution]" placeholder="Institution name">
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <button type="button" class="add-training-btn" onclick="addProfessionalTraining()"><i
                                class="fas fa-plus"></i> Add Professional Training</button>

                        <!-- Dynamic Foreign Trainings Section -->
                        <div class="form-section-title"><i class="fas fa-globe"></i> Foreign Trainings</div>
                        <div id="foreignTrainingsContainer">
                            <?php
                            if (!empty($foreign_trainings_list)) {
                                foreach ($foreign_trainings_list as $index => $training):
                                    ?>
                                    <div class="dynamic-training-item" data-index="<?php echo $index; ?>">
                                        <button type="button" class="remove-training" onclick="removeForeignTrainingItem(this)"><i
                                                class="fas fa-trash"></i></button>
                                        <div class="training-header">Foreign Training #<?php echo $index + 1; ?></div>
                                        <div class="modal-form-grid">
                                            <div class="form-group full-width"><label>Training Name <span
                                                        style="color: red;">*</span></label><input type="text"
                                                    name="foreign_trainings[<?php echo $index; ?>][name]"
                                                    value="<?php echo htmlspecialchars($training['name'] ?? ''); ?>"
                                                    placeholder="e.g., Advanced Cyber Security Course"></div>
                                            <div class="form-group"><label>Country</label><input type="text"
                                                    name="foreign_trainings[<?php echo $index; ?>][country]"
                                                    value="<?php echo htmlspecialchars($training['country'] ?? ''); ?>"
                                                    placeholder="Country name"></div>
                                            <div class="form-group"><label>Year</label><input type="text"
                                                    name="foreign_trainings[<?php echo $index; ?>][year]"
                                                    value="<?php echo htmlspecialchars($training['year'] ?? ''); ?>"
                                                    placeholder="2023"></div>
                                            <div class="form-group"><label>Duration</label><input type="text"
                                                    name="foreign_trainings[<?php echo $index; ?>][duration]"
                                                    value="<?php echo htmlspecialchars($training['duration'] ?? ''); ?>"
                                                    placeholder="e.g., 2 weeks"></div>
                                            <div class="form-group"><label>Institution</label><input type="text"
                                                    name="foreign_trainings[<?php echo $index; ?>][institution]"
                                                    value="<?php echo htmlspecialchars($training['institution'] ?? ''); ?>"
                                                    placeholder="Institution name"></div>
                                        </div>
                                    </div>
                                    <?php
                                endforeach;
                            } else { ?>
                                <div class="dynamic-training-item" data-index="0">
                                    <button type="button" class="remove-training" onclick="removeForeignTrainingItem(this)"
                                        style="display: none;"><i class="fas fa-trash"></i></button>
                                    <div class="training-header">Foreign Training #1</div>
                                    <div class="modal-form-grid">
                                        <div class="form-group full-width"><label>Training Name <span
                                                    style="color: red;">*</span></label><input type="text"
                                                name="foreign_trainings[0][name]"
                                                placeholder="e.g., Advanced Cyber Security Course"></div>
                                        <div class="form-group"><label>Country</label><input type="text"
                                                name="foreign_trainings[0][country]" placeholder="Country name"></div>
                                        <div class="form-group"><label>Year</label><input type="text"
                                                name="foreign_trainings[0][year]" placeholder="2023"></div>
                                        <div class="form-group"><label>Duration</label><input type="text"
                                                name="foreign_trainings[0][duration]" placeholder="e.g., 2 weeks"></div>
                                        <div class="form-group"><label>Institution</label><input type="text"
                                                name="foreign_trainings[0][institution]" placeholder="Institution name"></div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <button type="button" class="add-training-btn" onclick="addForeignTraining()"><i
                                class="fas fa-plus"></i> Add Foreign Training</button>

                        <div class="modal-buttons">
                            <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                            <button type="submit" class="btn-submit">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Photo Upload Modal -->
        <div id="photoUploadModal" class="modal">
            <div class="modal-content" style="max-width: 450px;">
                <div class="modal-header">
                    <h3><i class="fas fa-camera"></i> Upload Photo</h3><span class="modal-close-photo">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="photoUploadForm" enctype="multipart/form-data">
                        <input type="hidden" id="photoPersonnelId" name="personnel_id">
                        <div class="photo-preview" id="currentPhotoDisplay"></div>
                        <div class="form-group"><label>Select Photo</label><input type="file" id="profilePhotoFile"
                                name="profile_photo" accept="image/*"></div>
                        <div class="modal-buttons"><button type="button" class="btn-cancel"
                                id="cancelPhotoBtn">Cancel</button><button type="button" class="btn-delete"
                                id="deletePhotoBtn"><i class="fas fa-trash"></i> Remove</button><button type="submit"
                                class="btn-submit">Upload</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Photo View Modal -->
        <div id="photoViewModal" class="modal">
            <div class="modal-content" style="max-width: 450px; text-align: center;">
                <div class="modal-header">
                    <h3 id="photoViewTitle">Profile Photo</h3><span class="modal-close-view">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="photoViewImage"></div>
                    <p id="photoViewName" style="margin-top: 15px;"></p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="empty-state"><i class="fas fa-user-circle"></i>
            <h3>Select a Personnel</h3>
            <p>Please select a personnel from the table above to view their profile.</p>
        </div>
    <?php endif; ?>

    <div id="toast" class="toast"></div>

    <script>
        // Counters for dynamic training items
        let professionalTrainingCounter = <?php echo !empty($professional_trainings_list) ? count($professional_trainings_list) : 1; ?>;
        let foreignTrainingCounter = <?php echo !empty($foreign_trainings_list) ? count($foreign_trainings_list) : 1; ?>;

        const searchInput = document.getElementById('personnelSearch');
        const clearSearchBtn = document.getElementById('clearSearch');
        const closeProfileBtn = document.getElementById('closeProfileBtn');

        if (closeProfileBtn) {
            closeProfileBtn.addEventListener('click', () => {
                const url = new URL(window.location.href);
                url.searchParams.delete('personnel_number');
                window.location.href = url.toString();
            });
        }

        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const term = this.value.trim();
                if (clearSearchBtn) clearSearchBtn.style.display = term ? 'flex' : 'none';
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const url = new URL(window.location.href);
                    if (term) url.searchParams.set('search', term);
                    else url.searchParams.delete('search');
                    url.searchParams.delete('page');
                    window.location.href = url.toString();
                }, 500);
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                const url = new URL(window.location.href);
                url.searchParams.delete('search');
                window.location.href = url.toString();
            });
        }

        document.querySelectorAll('.personnel-row').forEach(row => {
            row.addEventListener('click', function (e) {
                if (e.target.closest('.view-profile-btn')) return;
                if (e.target.closest('.table-profile-img')) return;
                if (e.target.closest('.table-avatar-placeholder')) return;
                const num = this.dataset.personnelNumber;
                if (num) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('personnel_number', num);
                    window.location.href = url.toString();
                }
            });
        });

        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabPanes = document.querySelectorAll('.tab-pane');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                tabBtns.forEach(b => b.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });

        const editModal = document.getElementById('editProfileModal');
        const editBtn = document.getElementById('editProfileBtn');
        const closeModal = document.querySelectorAll('.modal-close, .modal-close-photo, .modal-close-view');
        const cancelEdit = document.getElementById('cancelEditBtn');
        const editForm = document.getElementById('editProfileForm');

        if (editBtn) editBtn.onclick = () => editModal.style.display = 'flex';

        function closeModals() {
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
        }

        closeModal.forEach(btn => btn.onclick = closeModals);
        if (cancelEdit) cancelEdit.onclick = closeModals;
        window.onclick = (e) => { if (e.target.classList.contains('modal')) closeModals(); };

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.style.backgroundColor = type === 'success' ? '#1a5a4a' : '#dc2626';
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 3000);
        }

        // Professional Training Functions
        function addProfessionalTraining() {
            const container = document.getElementById('professionalTrainingsContainer');
            const newIndex = professionalTrainingCounter++;
            const trainingDiv = document.createElement('div');
            trainingDiv.className = 'dynamic-training-item';
            trainingDiv.setAttribute('data-index', newIndex);
            trainingDiv.innerHTML = `
            <button type="button" class="remove-training" onclick="removeTrainingItem(this)"><i class="fas fa-trash"></i></button>
            <div class="training-header">Training #${newIndex + 1}</div>
            <div class="modal-form-grid">
                <div class="form-group full-width"><label>Training Name <span style="color: red;">*</span></label><input type="text" name="professional_trainings[${newIndex}][name]" placeholder="e.g., Project Management Professional"></div>
                <div class="form-group"><label>Location/Address</label><input type="text" name="professional_trainings[${newIndex}][address]" placeholder="Location"></div>
                <div class="form-group"><label>Year</label><input type="text" name="professional_trainings[${newIndex}][year]" placeholder="2023"></div>
                <div class="form-group"><label>Duration</label><input type="text" name="professional_trainings[${newIndex}][duration]" placeholder="e.g., 3 months"></div>
                <div class="form-group"><label>Institution</label><input type="text" name="professional_trainings[${newIndex}][institution]" placeholder="Institution name"></div>
            </div>
        `;
            container.appendChild(trainingDiv);
            updateProfessionalTrainingHeaders();
        }

        function removeTrainingItem(button) {
            const item = button.closest('.dynamic-training-item');
            item.remove();
            updateProfessionalTrainingHeaders();
        }

        function updateProfessionalTrainingHeaders() {
            const container = document.getElementById('professionalTrainingsContainer');
            const items = container.querySelectorAll('.dynamic-training-item');
            items.forEach((item, idx) => {
                const header = item.querySelector('.training-header');
                if (header) header.textContent = `Training #${idx + 1}`;
                const inputs = item.querySelectorAll('input');
                inputs.forEach(input => {
                    const oldName = input.getAttribute('name');
                    if (oldName && oldName.includes('professional_trainings')) {
                        const match = oldName.match(/\[(\d+)\]\[(\w+)\]/);
                        if (match) {
                            const fieldName = match[2];
                            input.setAttribute('name', `professional_trainings[${idx}][${fieldName}]`);
                        }
                    }
                });
            });
        }

        // Foreign Training Functions
        function addForeignTraining() {
            const container = document.getElementById('foreignTrainingsContainer');
            const newIndex = foreignTrainingCounter++;
            const trainingDiv = document.createElement('div');
            trainingDiv.className = 'dynamic-training-item';
            trainingDiv.setAttribute('data-index', newIndex);
            trainingDiv.innerHTML = `
            <button type="button" class="remove-training" onclick="removeForeignTrainingItem(this)"><i class="fas fa-trash"></i></button>
            <div class="training-header">Foreign Training #${newIndex + 1}</div>
            <div class="modal-form-grid">
                <div class="form-group full-width"><label>Training Name <span style="color: red;">*</span></label><input type="text" name="foreign_trainings[${newIndex}][name]" placeholder="e.g., Advanced Cyber Security Course"></div>
                <div class="form-group"><label>Country</label><input type="text" name="foreign_trainings[${newIndex}][country]" placeholder="Country name"></div>
                <div class="form-group"><label>Year</label><input type="text" name="foreign_trainings[${newIndex}][year]" placeholder="2023"></div>
                <div class="form-group"><label>Duration</label><input type="text" name="foreign_trainings[${newIndex}][duration]" placeholder="e.g., 2 weeks"></div>
                <div class="form-group"><label>Institution</label><input type="text" name="foreign_trainings[${newIndex}][institution]" placeholder="Institution name"></div>
            </div>
        `;
            container.appendChild(trainingDiv);
            updateForeignTrainingHeaders();
        }

        function removeForeignTrainingItem(button) {
            const item = button.closest('.dynamic-training-item');
            item.remove();
            updateForeignTrainingHeaders();
        }

        function updateForeignTrainingHeaders() {
            const container = document.getElementById('foreignTrainingsContainer');
            const items = container.querySelectorAll('.dynamic-training-item');
            items.forEach((item, idx) => {
                const header = item.querySelector('.training-header');
                if (header) header.textContent = `Foreign Training #${idx + 1}`;
                const inputs = item.querySelectorAll('input');
                inputs.forEach(input => {
                    const oldName = input.getAttribute('name');
                    if (oldName && oldName.includes('foreign_trainings')) {
                        const match = oldName.match(/\[(\d+)\]\[(\w+)\]/);
                        if (match) {
                            const fieldName = match[2];
                            input.setAttribute('name', `foreign_trainings[${idx}][${fieldName}]`);
                        }
                    }
                });
            });
        }

        window.addProfessionalTraining = addProfessionalTraining;
        window.addForeignTraining = addForeignTraining;
        window.removeTrainingItem = removeTrainingItem;
        window.removeForeignTrainingItem = removeForeignTrainingItem;

        function editProfilePhoto(id, name) {
            document.getElementById('photoPersonnelId').value = id;
            document.getElementById('photoUploadModal').style.display = 'flex';
            fetch(`?personnel_number=${id}`).then(r => r.text()).then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const src = doc.querySelector('.profile-avatar')?.src || '';
                const display = document.getElementById('currentPhotoDisplay');
                if (display) display.innerHTML = src ? `<img src="${src}">` : '<span>No photo</span>';
            });
            document.getElementById('profilePhotoFile').value = '';
        }

        function viewProfilePhoto(path, name) {
            document.getElementById('photoViewImage').innerHTML = `<img src="${path}" style="width:200px;height:200px;border-radius:50%;object-fit:cover;border:3px solid #1a5a4a;">`;
            document.getElementById('photoViewName').textContent = name;
            document.getElementById('photoViewModal').style.display = 'flex';
        }

        const photoForm = document.getElementById('photoUploadForm');
        const cancelPhoto = document.getElementById('cancelPhotoBtn');
        const deletePhoto = document.getElementById('deletePhotoBtn');

        if (cancelPhoto) cancelPhoto.onclick = closeModals;

        if (photoForm) {
            photoForm.onsubmit = async (e) => {
                e.preventDefault();
                const id = document.getElementById('photoPersonnelId').value;
                const file = document.getElementById('profilePhotoFile').files[0];
                if (!file) return showToast('Select a photo', 'error');
                const fd = new FormData();
                fd.append('action', 'upload_profile_photo');
                fd.append('personnel_id', id);
                fd.append('profile_photo', file);
                const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) { showToast('Photo uploaded'); setTimeout(() => location.reload(), 1000); }
                else showToast(data.message, 'error');
            };
        }

        if (deletePhoto) {
            deletePhoto.onclick = async () => {
                if (!confirm('Remove photo?')) return;
                const id = document.getElementById('photoPersonnelId').value;
                const fd = new FormData();
                fd.append('action', 'delete_profile_photo');
                fd.append('personnel_id', id);
                const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) { showToast('Photo removed'); setTimeout(() => location.reload(), 1000); }
                else showToast(data.message, 'error');
            };
        }

        if (editForm) {
            editForm.onsubmit = async (e) => {
                e.preventDefault();
                const fd = new FormData(editForm);
                fd.append('action', 'update_profile');

                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Profile updated successfully');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.error || 'Update failed', 'error');
                }
            };
        }

        window.editProfilePhoto = editProfilePhoto;
        window.viewProfilePhoto = viewProfilePhoto;
    </script>

</body>

</html>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>