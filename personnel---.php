<?php
// Start session and include database connection
session_start();
require_once 'includes/config.php';

// Get current user's role from session
$current_user_role = isset($_SESSION['user_role']) ? (int) $_SESSION['user_role'] : 0;

$pageTitle = "सकलदर्जाहरुको विवरण";
$pageSubtitle = "";
$activePage = "personnel";

// Check permissions
$isAdmin = ($current_user_role >= 1);
$isSuperAdmin = ($current_user_role === 2);

// Pagination configuration
$records_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
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

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel");
    $total_records = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel WHERE current_status = 'Active'");
    $active_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel WHERE rank < 30");
    $officer_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel WHERE rank BETWEEN 31 AND 37");
    $jco_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM personnel WHERE rank > 37");
    $or_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT unit) FROM personnel WHERE unit IS NOT NULL AND unit != ''");
    $unit_count = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Statistics error: " . $e->getMessage());
    $total_records = 0;
    $active_count = 0;
    $officer_count = 0;
    $jco_count = 0;
    $or_count = 0;
    $unit_count = 0;
}

// Get personnel list
try {
    if (!empty($search_condition)) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel $search_condition");
        $count_stmt->execute($params);
        $total_filtered_records = $count_stmt->fetchColumn();
    } else {
        $total_filtered_records = $total_records;
    }
    $total_pages = ceil($total_filtered_records / $records_per_page);

    if ($page < 1)
        $page = 1;
    if ($page > $total_pages && $total_pages > 0)
        $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;

    $sql = " SELECT p.*, d.rank_unicode, d.rank_name, d.rank_nep
            FROM personnel p
            LEFT JOIN def_rank d ON p.rank = d.rank_code
            $search_condition
            ORDER BY p.personnel_number 
            LIMIT ? OFFSET ?";
    // $sql = "SELECT * FROM personnel $search_condition ORDER BY personnel_number LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);

    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $personnel_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Personnel fetch error: " . $e->getMessage());
    $personnel_list = [];
    $total_pages = 0;
}

ob_start();
?>

<style>
    /* Modal Styles */
    .current-photo-modal {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #2c5f4e;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .photo-placeholder-modal {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e8f5f0 0%, #d1e8e0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        border: 3px solid #2c5f4e;
    }

    .photo-placeholder-modal i {
        font-size: 60px;
        color: #2c5f4e;
    }

    .preview-image-modal {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #0891b2;
        margin-top: 10px;
    }

    .current-signature-modal {
        max-width: 250px;
        max-height: 80px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px;
        background: white;
    }

    .signature-placeholder-modal {
        padding: 20px;
        background: #f8fafc;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        color: #9aa9bc;
        text-align: center;
    }

    .signature-placeholder-modal i {
        font-size: 40px;
        margin-bottom: 10px;
    }

    .preview-signature-modal {
        max-width: 200px;
        max-height: 60px;
        border: 1px solid #0891b2;
        border-radius: 4px;
        padding: 5px;
        margin-top: 10px;
    }

    .btn-danger {
        background: #dc2626;
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-danger:hover {
        background: #b91c1c;
        transform: translateY(-1px);
    }

    .file-input-wrapper {
        position: relative;
        margin-bottom: 20px;
    }

    .file-input-wrapper input[type="file"] {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }

    .file-input-label {
        display: block;
        padding: 12px 20px;
        background: white;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        color: #6c7a8e;
    }

    .file-input-label:hover {
        border-color: #2c5f4e;
        background: #f1f5f9;
    }

    .file-input-label i {
        margin-right: 10px;
    }

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

    .btn-manage-balance {
        background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
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

    .btn-manage-balance:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(8, 145, 178, 0.3);
        background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
    }

    .action-buttons-header {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .modal-content {
        background-color: #fefefe;
        margin: 50px auto;
        border-radius: 16px;
        width: 90%;
        max-width: 550px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideDown 0.3s;
    }

    .modal-content.large {
        max-width: 1200px;
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
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
        border-radius: 16px 16px 0 0;
    }

    .modal-header h3 {
        margin: 0;
        color: #1a2c3e;
        font-size: 20px;
    }

    .modal-header .close {
        font-size: 28px;
        font-weight: bold;
        color: #aaa;
        cursor: pointer;
        transition: 0.2s;
    }

    .modal-header .close:hover {
        color: #c2410c;
    }

    .modal-body {
        padding: 24px;
        max-height: 70vh;
        overflow-y: auto;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }

    .form-group input,
    .form-group select {
        padding: 10px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: #2c5f4e;
        box-shadow: 0 0 0 3px rgba(44, 95, 78, 0.1);
    }

    .modal-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-save {
        background: linear-gradient(135deg, #1e3a32 0%, #2c5f4e 100%);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(44, 95, 78, 0.3);
    }

    .btn-cancel-modal {
        background: #f1f3f5;
        color: #6c7a8e;
        border: 1px solid #e2e8f0;
        padding: 10px 24px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-cancel-modal:hover {
        background: #e2e8f0;
    }

    .personnel-info {
        background: #f8fafc;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
    }

    .personnel-info p {
        margin: 5px 0;
        color: #334155;
    }

    .personnel-info strong {
        color: #1a2c3e;
    }

    .alert {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: none;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

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
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
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
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        z-index: 10;
        position: relative;
    }

    .no-signature {
        color: #9aa9bc;
        font-size: 12px;
    }

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

    /* Leave Balance Table Styles */
    .leave-balance-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .leave-balance-table th {
        background: #f8fafc;
        padding: 14px 12px;
        text-align: left;
        font-weight: 600;
        color: #1a2c3e;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .leave-balance-table td {
        padding: 12px;
        border-bottom: 1px solid #eef2f6;
        font-size: 13px;
        color: #334155;
        vertical-align: middle;
    }

    .leave-balance-table tr:hover {
        background: #fafcff;
    }

    .leave-balance-table .balance-input {
        width: 90px;
        padding: 6px 10px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        transition: all 0.2s;
    }

    .leave-balance-table .balance-input:focus {
        outline: none;
        border-color: #0891b2;
        box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
    }

    .edit-balance-btn,
    .save-balance-btn,
    .cancel-balance-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .edit-balance-btn {
        color: #0891b2;
        background: #f0f9ff;
    }

    .edit-balance-btn:hover {
        background: #cffafe;
        transform: scale(1.02);
    }

    .save-balance-btn {
        color: #2c5f4e;
        background: #e8f5f0;
    }

    .save-balance-btn:hover {
        background: #d1e8e0;
        transform: scale(1.02);
    }

    .cancel-balance-btn {
        color: #c2410c;
        background: #fff0ed;
    }

    .cancel-balance-btn:hover {
        background: #ffe4e0;
        transform: scale(1.02);
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

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(0, 0, 0, .1);
        border-radius: 50%;
        border-top-color: #2c5f4e;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #1e3a32;
        color: white;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 14px;
        z-index: 1100;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideInRight 0.3s;
        display: none;
    }

    .toast.error {
        background: #dc2626 !important;
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

        .modal-content {
            width: 95%;
            margin: 20px auto;
        }

        .action-buttons-header {
            flex-direction: column;
            width: 100%;
        }

        .btn-manage-balance,
        .btn-add {
            width: 100%;
            justify-content: center;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .modal-buttons {
            flex-direction: column;
        }

        .modal-buttons button {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($total_records); ?></div>
            <div class="stat-label">जम्मा नफ्री</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($active_count); ?></div>
            <div class="stat-label">बहालवाला</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-classic fa-solid fa-person-military-pointing fa-fw"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($officer_count); ?></div>
            <div class="stat-label">अधिकृत</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-classic fa-solid fa-person-military-rifle fa-fw"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($jco_count); ?></div>
            <div class="stat-label">पदिक</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-classic fa-solid fa-person-military-rifle fa-fw"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($or_count); ?></div>
            <div class="stat-label">अन्य दर्जा</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-house-user"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($or_count); ?></div>
            <div class="stat-label">अवकास प्राप्त</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-building"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($unit_count); ?></div>
            <div class="stat-label">शाखा</div>
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
        <div class="action-buttons-header">
            <button class="btn-manage-balance" id="manageBalanceBtn">
                <i class="fas fa-chart-line"></i> Manage Leave Balance
            </button>
            <button class="btn-add" id="addPersonnelBtn">
                <i class="fas fa-user-plus"></i> Add Personnel
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Personnel Table -->
<div class="data-table">
    <table class="datatable">
        <thead>
            <tr>
                <th style="width: 30px;text-align:center;">सि.नं.</th>
                <th style="width: 60px;text-align:center;">फोटो</th>
                <th style="text-align:center;">व्य.नं.</th>
                <th style="text-align:center;">दर्जा</th>
                <th style="text-align:center;">नामभर</th>
                <th style="text-align:center;">दस्तखत</th>
                <th style="text-align:center;">Email</th>
                <th style="text-align:center;">शाखा</th>
                <th style="text-align:center;">ठेगाना</th>
                <th style="text-align:center;">भर्ना मिति</th>
                <th style="text-align:center;">बहालवाला/अवकास</th>
                <?php if ($isAdmin): ?>
                    <th style="text-align:center;">नियुक्ति</th>
                <?php endif; ?>
                <?php if ($isSuperAdmin): ?>
                    <th style="width: 200px;text-align:center;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($personnel_list)): ?>
                <?php $counter = $offset + 1; ?>
                <?php foreach ($personnel_list as $personnel): ?>
                    <tr data-personnel-number="<?php echo htmlspecialchars($personnel['personnel_number']); ?>">
                        <td style="text-align:center;"><?php echo $counter++; ?></td>
                        <td class="photo-cell">
                            <?php if (!empty($personnel['profile_picture_path']) && file_exists($personnel['profile_picture_path'])): ?>
                                <img src="<?php echo htmlspecialchars($personnel['profile_picture_path']); ?>" alt="Profile Photo"
                                    class="profile-preview"
                                    onclick="viewProfilePhoto('<?php echo htmlspecialchars($personnel['profile_picture_path']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')">
                            <?php else: ?>
                                <div class="avatar-placeholder"
                                    onclick="openPhotoModal('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>', null)">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($personnel['personnel_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($personnel['rank_unicode'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($personnel['full_name_ne']); ?></td>
                        <td class="signature-cell">
                            <?php if (!empty($personnel['signature']) && file_exists($personnel['signature'])): ?>
                                <img src="<?php echo htmlspecialchars($personnel['signature']); ?>" alt="Signature"
                                    class="signature-preview"
                                    onclick="viewSignature('<?php echo htmlspecialchars($personnel['signature']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')">
                            <?php else: ?>
                                <span class="no-signature">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($personnel['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($personnel['email']); ?>" class="email-link">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars(substr($personnel['email'], 0, 20)); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #9aa9bc;">—</span>
                            <?php endif; ?>
                        </td>

                        <td><?php echo htmlspecialchars($personnel['unit'] ?? 'N/A'); ?></td>
                        <td>
                            <?php
                            $location_parts = [];
                            if (!empty($personnel['village_tole']))
                                $location_parts[] = $personnel['village_tole'];
                            if (!empty($personnel['municipality']))
                                $location_parts[] = $personnel['municipality'];
                            if (!empty($personnel['district']))
                                $location_parts[] = $personnel['district'];
                            if (!empty($personnel['province']))
                                $location_parts[] = $personnel['province'];

                            echo !empty($location_parts) ? htmlspecialchars(implode(', ', $location_parts)) : '—';
                            ?>
                        </td>
                        <td><?php echo $personnel['recruitment_date'] && $personnel['recruitment_date'] != '0000-00-00' ? date('Y-m-d', strtotime($personnel['recruitment_date'])) : 'N/A'; ?>
                        </td>
                        <td>
                            <?php
                            $status_class = 'badge-active';
                            $status_lower = strtolower($personnel['current_status'] ?? 'active');
                            if ($status_lower == 'leave')
                                $status_class = 'badge-leave';
                            elseif ($status_lower == 'retired')
                                $status_class = 'badge-retired';
                            elseif ($status_lower == 'training')
                                $status_class = 'badge-training';
                            ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($personnel['current_status'] ?? 'Active'); ?>
                            </span>
                        </td>
                        <?php if ($isAdmin): ?>
                            <td>
                                <?php
                                $role = isset($personnel['role']) ? (int) $personnel['role'] : 0;
                                $roleClass = '';
                                $roleText = '';
                                switch ($role) {
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
                        <?php endif; ?>
                        <?php if ($isSuperAdmin): ?>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-edit"
                                        onclick="editPersonnel('<?php echo htmlspecialchars($personnel['personnel_number']); ?>')"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-photo"
                                        onclick="openPhotoModal('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>', '<?php echo htmlspecialchars($personnel['profile_picture_path'] ?? ''); ?>')"
                                        title="Upload/Edit Photo">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                    <button class="btn-icon btn-signature"
                                        onclick="openSignatureModal('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>', '<?php echo htmlspecialchars($personnel['signature'] ?? ''); ?>')"
                                        title="Upload/Edit Signature">
                                        <i class="fas fa-signature"></i>
                                    </button>
                                    <button class="btn-icon btn-reset"
                                        onclick="resetPassword('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')"
                                        title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($personnel['personnel_number'] !== ($_SESSION['user_id'] ?? '')): ?>
                                        <button class="btn-icon btn-delete"
                                            onclick="deletePersonnel('<?php echo htmlspecialchars($personnel['personnel_number']); ?>', '<?php echo htmlspecialchars($personnel['full_name_en']); ?>')"
                                            title="Delete">
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
                    if ($isAdmin)
                        $colspan++;
                    if ($isSuperAdmin)
                        $colspan++;
                    ?>
                    <td colspan="<?php echo $colspan; ?>" style="text-align: center; padding: 60px;">
                        <i class="fas fa-user-slash"
                            style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                        <p style="color: #6c7a8e;">No personnel records found.</p>
                        <?php if (!empty($search_term)): ?>
                            <p style="color: #9aa9bc; font-size: 12px; margin-top: 10px;">Try adjusting your search criteria.
                            </p>
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
            <i class="fas fa-info-circle"></i> Showing <?php echo $offset + 1; ?> to
            <?php echo min($offset + $records_per_page, $total_filtered_records); ?> of
            <?php echo $total_filtered_records; ?> entries
        </div>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                    class="pagination-link">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                    class="pagination-link">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1)
                echo '<span class="page-dots">...</span>';

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
                <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                    class="pagination-link">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                    class="pagination-link">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>

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

<!-- Photo Upload Modal -->
<div id="photoUploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="photoModalTitle"><i class="fas fa-camera"></i> Update Profile Photo</h3>
            <span class="close" onclick="closePhotoModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="photoAlert" class="alert" style="display: none;"></div>

            <div style="text-align: center; margin-bottom: 20px;">
                <div id="modalCurrentPhoto"></div>
            </div>

            <div class="personnel-info" id="modalPersonnelInfo"></div>

            <div class="file-input-wrapper">
                <input type="file" id="modalProfilePhoto" accept="image/jpeg,image/png,image/gif,image/webp">
                <label for="modalProfilePhoto" class="file-input-label">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Choose Photo (JPG, PNG, GIF, WEBP - Max 5MB)
                </label>
            </div>

            <div id="modalPreviewContainer" style="text-align: center; margin-top: 15px;"></div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel-modal" onclick="closePhotoModal()">Cancel</button>
                <button type="button" class="btn-save" id="modalUploadBtn" style="display: none;">
                    <i class="fas fa-upload"></i> Upload Photo
                </button>
                <button type="button" class="btn-danger" id="modalDeleteBtn" style="display: none;">
                    <i class="fas fa-trash"></i> Remove Photo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Signature Upload Modal -->
<div id="signatureUploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="signatureModalTitle"><i class="fas fa-signature"></i> Update Signature</h3>
            <span class="close" onclick="closeSignatureModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="signatureAlert" class="alert" style="display: none;"></div>

            <div style="text-align: center; margin-bottom: 20px;">
                <div id="modalCurrentSignature"></div>
            </div>

            <div class="personnel-info" id="modalSignaturePersonnelInfo"></div>

            <div class="file-input-wrapper">
                <input type="file" id="modalSignature" accept="image/jpeg,image/png,image/gif,image/webp">
                <label for="modalSignature" class="file-input-label">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Choose Signature (JPG, PNG, GIF, WEBP - Max 2MB)
                </label>
            </div>

            <div id="modalSignaturePreviewContainer" style="text-align: center; margin-top: 15px;"></div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel-modal" onclick="closeSignatureModal()">Cancel</button>
                <button type="button" class="btn-save" id="modalSignatureUploadBtn" style="display: none;">
                    <i class="fas fa-upload"></i> Upload Signature
                </button>
                <button type="button" class="btn-danger" id="modalSignatureDeleteBtn" style="display: none;">
                    <i class="fas fa-trash"></i> Remove Signature
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Personnel Modal -->
<div id="editPersonnelModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 id="editModalTitle"><i class="fas fa-edit"></i> Edit Personnel</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editPersonnelForm" method="POST">
                <input type="hidden" id="edit_personnel_number" name="personnel_number">

                <div class="form-row">
                    <div class="form-group">
                        <label>Personnel Number *</label>
                        <input type="text" id="edit_personnel_number_display" readonly style="background: #f1f3f5;">
                    </div>
                    <div class="form-group">
                        <label>Full Name (English) *</label>
                        <input type="text" id="edit_full_name_en" name="full_name_en" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name (Nepali)</label>
                        <input type="text" id="edit_full_name_ne" name="full_name_ne">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="edit_email" name="email">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" id="edit_phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Rank *</label>
                        <select id="edit_rank" name="rank" required>
                            <option value="">Select Rank</option>
                            <option value="General">General</option>
                            <option value="Lieutenant General">Lieutenant General</option>
                            <option value="Major General">Major General</option>
                            <option value="Brigadier General">Brigadier General</option>
                            <option value="Colonel">Colonel</option>
                            <option value="Lieutenant Colonel">Lieutenant Colonel</option>
                            <option value="Major">Major</option>
                            <option value="Captain">Captain</option>
                            <option value="Lieutenant">Lieutenant</option>
                            <option value="Second Lieutenant">Second Lieutenant</option>
                            <option value="Subedar">Subedar</option>
                            <option value="Lieutenant Subedar">Lieutenant Subedar</option>
                            <option value="Jawan">Jawan</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Unit/Branch</label>
                        <input type="text" id="edit_unit" name="unit">
                    </div>
                    <div class="form-group">
                        <label>Recruitment Date</label>
                        <input type="date" id="edit_recruitment_date" name="recruitment_date">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Province</label>
                        <select id="edit_province" name="province">
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
                    <div class="form-group">
                        <label>District</label>
                        <input type="text" id="edit_district" name="district">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Municipality</label>
                        <input type="text" id="edit_municipality" name="municipality">
                    </div>
                    <div class="form-group">
                        <label>Village/Tole</label>
                        <input type="text" id="edit_village_tole" name="village_tole">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Current Status</label>
                        <select id="edit_current_status" name="current_status">
                            <option value="Active">Active</option>
                            <option value="Leave">Leave</option>
                            <option value="Training">Training</option>
                            <option value="Retired">Retired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>User Role</label>
                        <select id="edit_role" name="role">
                            <option value="0">User</option>
                            <option value="1">Admin</option>
                            <option value="2">Super Admin</option>
                        </select>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel-modal" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Personnel Modal -->
<div id="addPersonnelModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Personnel</h3>
            <span class="close" onclick="closeAddPersonnelModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="addPersonnelAlert" class="alert" style="display: none;"></div>
            <form id="addPersonnelFormModal">
                <div class="form-row">
                    <div class="form-group">
                        <label>Personnel Number <span class="required-star">*</span></label>
                        <input type="text" id="addServiceNo" name="serviceNo" required
                            placeholder="Enter personnel number (e.g., NA-12345)">
                        <div class="form-hint">Unique personnel number/ID (must be unique)</div>
                    </div>
                    <div class="form-group">
                        <label>Full Name (English) <span class="required-star">*</span></label>
                        <input type="text" id="addFullName" name="fullName" required placeholder="e.g., John Doe">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name (Nepali)</label>
                        <input type="text" id="addFullNameNe" name="fullNameNe" placeholder="e.g., जन डो">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="addEmail" name="email" placeholder="personnel@example.com">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" id="addPhone" name="phone" placeholder="98XXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Rank <span class="required-star">*</span></label>
                        <select id="addRank" name="rank" required>
                            <option value="">Select Rank</option>
                            <option value="General">General</option>
                            <option value="Lieutenant General">Lieutenant General</option>
                            <option value="Major General">Major General</option>
                            <option value="Brigadier General">Brigadier General</option>
                            <option value="Colonel">Colonel</option>
                            <option value="Lieutenant Colonel">Lieutenant Colonel</option>
                            <option value="Major">Major</option>
                            <option value="Captain">Captain</option>
                            <option value="Lieutenant">Lieutenant</option>
                            <option value="Second Lieutenant">Second Lieutenant</option>
                            <option value="Subedar">Subedar</option>
                            <option value="Lieutenant Subedar">Lieutenant Subedar</option>
                            <option value="Jawan">Jawan</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Unit/Branch</label>
                        <input type="text" id="addBranch" name="branch" placeholder="e.g., Infantry, Signals">
                    </div>
                    <div class="form-group">
                        <label>Recruitment Date</label>
                        <input type="date" id="addRecruitmentDate" name="recruitmentDate">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Province</label>
                        <select id="addProvince" name="province">
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
                    <div class="form-group">
                        <label>District</label>
                        <input type="text" id="addDistrict" name="district" placeholder="e.g., Kathmandu">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Municipality</label>
                        <input type="text" id="addMunicipality" name="municipality"
                            placeholder="e.g., Kathmandu Metropolitan City">
                    </div>
                    <div class="form-group">
                        <label>Village/Tole</label>
                        <input type="text" id="addVillageTole" name="villageTole" placeholder="e.g., Baneshwor">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Current Status</label>
                        <select id="addStatus" name="status">
                            <option value="Active">Active</option>
                            <option value="Leave">Leave</option>
                            <option value="Training">Training</option>
                            <option value="Retired">Retired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>User Role</label>
                        <select id="addRole" name="role">
                            <option value="0">User</option>
                            <option value="1">Admin</option>
                            <option value="2">Super Admin</option>
                        </select>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel-modal" onclick="closeAddPersonnelModal()">Cancel</button>
                    <button type="submit" class="btn-save">Add Personnel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Leave Balance Modal -->
<div id="manageBalanceModal" class="modal">
    <div class="modal-content large" style="max-width: 1200px;">
        <div class="modal-header">
            <h3><i class="fas fa-chart-line"></i> Manage Leave Balance</h3>
            <span class="close" onclick="closeManageBalanceModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="search-container" style="margin-bottom: 20px;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="balanceSearchInput" class="search-input"
                    placeholder="🔍 Search personnel by personnel number..." style="padding-left: 42px;">
            </div>
            <div style="overflow-x: auto;">
                <table class="leave-balance-table" id="leaveBalanceTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">S.No</th>
                            <th>Personnel Number</th>
                            <th style="width: 130px;">Gharpari Bida</th>
                            <th style="width: 130px;">Parba Bida</th>
                            <th style="width: 130px;">Bhaeepari Bida</th>
                            <th style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leaveBalanceTableBody">
                        <tr>
                            <td colspan="8" style="text-align:center;padding:40px;">
                                <div class="loading-spinner"></div> Loading leave balances...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast"><span id="toastMessage"></span></div>

<script>
    // Helper function
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        if (!toast || !toastMessage) return;
        toastMessage.textContent = message;
        toast.className = 'toast ' + (type === 'error' ? 'error' : '');
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    // ==================== PHOTO MODAL ====================
    let currentPersonnelId = null, currentPersonnelName = null, currentPersonnelPhoto = null, selectedPhotoFile = null;

    function openPhotoModal(serviceNo, name, currentPhoto) {
        currentPersonnelId = serviceNo;
        currentPersonnelName = name;
        currentPersonnelPhoto = currentPhoto;
        selectedPhotoFile = null;

        document.getElementById('photoModalTitle').innerHTML = `<i class="fas fa-camera"></i> Update Photo - ${escapeHtml(name)}`;
        document.getElementById('modalPersonnelInfo').innerHTML = `<p><strong>Personnel Number:</strong> ${escapeHtml(serviceNo)}</p><p><strong>Name:</strong> ${escapeHtml(name)}</p>`;

        const currentPhotoContainer = document.getElementById('modalCurrentPhoto');
        if (currentPhoto && currentPhoto !== '') {
            const img = new Image();
            img.onload = () => {
                currentPhotoContainer.innerHTML = `<img src="${currentPhoto}?t=${Date.now()}" class="current-photo-modal">`;
                document.getElementById('modalDeleteBtn').style.display = 'inline-flex';
            };
            img.onerror = () => {
                currentPhotoContainer.innerHTML = `<div class="photo-placeholder-modal"><i class="fas fa-user-circle"></i></div>`;
                document.getElementById('modalDeleteBtn').style.display = 'none';
            };
            img.src = currentPhoto;
        } else {
            currentPhotoContainer.innerHTML = `<div class="photo-placeholder-modal"><i class="fas fa-user-circle"></i></div>`;
            document.getElementById('modalDeleteBtn').style.display = 'none';
        }

        document.getElementById('modalPreviewContainer').innerHTML = '';
        document.getElementById('modalProfilePhoto').value = '';
        document.getElementById('modalUploadBtn').style.display = 'none';
        document.getElementById('photoAlert').style.display = 'none';
        document.getElementById('photoUploadModal').style.display = 'block';
    }

    function closePhotoModal() {
        document.getElementById('photoUploadModal').style.display = 'none';
        currentPersonnelId = null;
    }

    function showPhotoAlert(message, type) {
        const alertDiv = document.getElementById('photoAlert');
        alertDiv.innerHTML = `<div style="background:${type === 'success' ? '#d1fae5' : '#fee2e2'};color:${type === 'success' ? '#065f46' : '#991b1b'};padding:12px;border-radius:8px;"><i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i> ${message}</div>`;
        alertDiv.style.display = 'block';
        setTimeout(() => alertDiv.style.display = 'none', 3000);
    }

    document.getElementById('modalProfilePhoto').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            selectedPhotoFile = file;
            const reader = new FileReader();
            reader.onload = (event) => {
                document.getElementById('modalPreviewContainer').innerHTML = `<img src="${event.target.result}" class="preview-image-modal">`;
                document.getElementById('modalUploadBtn').style.display = 'inline-flex';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('modalPreviewContainer').innerHTML = '';
            document.getElementById('modalUploadBtn').style.display = 'none';
            selectedPhotoFile = null;
        }
    });

    document.getElementById('modalUploadBtn').addEventListener('click', async function () {
        if (!selectedPhotoFile) { showPhotoAlert('Please select a photo first', 'error'); return; }

        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Uploading...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'upload_profile_photo');
        formData.append('personnel_id', currentPersonnelId);
        formData.append('profile_photo', selectedPhotoFile);

        try {
            const response = await fetch('upload_profile_photo.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                showPhotoAlert(result.message, 'success');
                document.getElementById('modalCurrentPhoto').innerHTML = `<img src="${result.path}?t=${Date.now()}" class="current-photo-modal">`;
                document.getElementById('modalDeleteBtn').style.display = 'inline-flex';
                const row = document.querySelector(`tr[data-personnel-number="${currentPersonnelId}"]`);
                if (row) {
                    const photoCell = row.querySelector('.photo-cell');
                    photoCell.innerHTML = `<img src="${result.path}?t=${Date.now()}" class="profile-preview" onclick="viewProfilePhoto('${result.path}', '${escapeHtml(currentPersonnelName)}')">`;
                }
                document.getElementById('modalProfilePhoto').value = '';
                document.getElementById('modalPreviewContainer').innerHTML = '';
                btn.style.display = 'none';
                selectedPhotoFile = null;
                setTimeout(() => closePhotoModal(), 1500);
            } else {
                showPhotoAlert(result.message, 'error');
            }
        } catch (error) {
            showPhotoAlert('Error uploading photo', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    document.getElementById('modalDeleteBtn').addEventListener('click', async function () {
        if (!confirm('Remove this photo?')) return;
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Removing...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'delete_profile_photo');
        formData.append('personnel_id', currentPersonnelId);

        try {
            const response = await fetch('upload_profile_photo.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                showPhotoAlert(result.message, 'success');
                document.getElementById('modalCurrentPhoto').innerHTML = `<div class="photo-placeholder-modal"><i class="fas fa-user-circle"></i></div>`;
                btn.style.display = 'none';
                const row = document.querySelector(`tr[data-personnel-number="${currentPersonnelId}"]`);
                if (row) {
                    const photoCell = row.querySelector('.photo-cell');
                    photoCell.innerHTML = `<div class="avatar-placeholder" onclick="openPhotoModal('${currentPersonnelId}', '${escapeHtml(currentPersonnelName)}', null)"><i class="fas fa-user"></i></div>`;
                }
                setTimeout(() => closePhotoModal(), 1500);
            } else {
                showPhotoAlert(result.message, 'error');
            }
        } catch (error) {
            showPhotoAlert('Error removing photo', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // ==================== SIGNATURE MODAL ====================
    let currentSignaturePersonnelId = null, currentSignaturePersonnelName = null, currentSignaturePath = null, selectedSignatureFile = null;

    function openSignatureModal(serviceNo, name, currentSignature) {
        currentSignaturePersonnelId = serviceNo;
        currentSignaturePersonnelName = name;
        currentSignaturePath = currentSignature;
        selectedSignatureFile = null;

        document.getElementById('signatureModalTitle').innerHTML = `<i class="fas fa-signature"></i> Update Signature - ${escapeHtml(name)}`;
        document.getElementById('modalSignaturePersonnelInfo').innerHTML = `<p><strong>Personnel Number:</strong> ${escapeHtml(serviceNo)}</p><p><strong>Name:</strong> ${escapeHtml(name)}</p>`;

        const currentSigContainer = document.getElementById('modalCurrentSignature');
        if (currentSignature && currentSignature !== '') {
            const img = new Image();
            img.onload = () => {
                currentSigContainer.innerHTML = `<img src="${currentSignature}?t=${Date.now()}" class="current-signature-modal">`;
                document.getElementById('modalSignatureDeleteBtn').style.display = 'inline-flex';
            };
            img.onerror = () => {
                currentSigContainer.innerHTML = `<div class="signature-placeholder-modal"><i class="fas fa-signature"></i><p>No signature uploaded</p></div>`;
                document.getElementById('modalSignatureDeleteBtn').style.display = 'none';
            };
            img.src = currentSignature;
        } else {
            currentSigContainer.innerHTML = `<div class="signature-placeholder-modal"><i class="fas fa-signature"></i><p>No signature uploaded</p></div>`;
            document.getElementById('modalSignatureDeleteBtn').style.display = 'none';
        }

        document.getElementById('modalSignaturePreviewContainer').innerHTML = '';
        document.getElementById('modalSignature').value = '';
        document.getElementById('modalSignatureUploadBtn').style.display = 'none';
        document.getElementById('signatureAlert').style.display = 'none';
        document.getElementById('signatureUploadModal').style.display = 'block';
    }

    function closeSignatureModal() {
        document.getElementById('signatureUploadModal').style.display = 'none';
        currentSignaturePersonnelId = null;
    }

    function showSignatureAlert(message, type) {
        const alertDiv = document.getElementById('signatureAlert');
        alertDiv.innerHTML = `<div style="background:${type === 'success' ? '#d1fae5' : '#fee2e2'};color:${type === 'success' ? '#065f46' : '#991b1b'};padding:12px;border-radius:8px;"><i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i> ${message}</div>`;
        alertDiv.style.display = 'block';
        setTimeout(() => alertDiv.style.display = 'none', 3000);
    }

    document.getElementById('modalSignature').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            selectedSignatureFile = file;
            const reader = new FileReader();
            reader.onload = (event) => {
                document.getElementById('modalSignaturePreviewContainer').innerHTML = `<img src="${event.target.result}" class="preview-signature-modal">`;
                document.getElementById('modalSignatureUploadBtn').style.display = 'inline-flex';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('modalSignaturePreviewContainer').innerHTML = '';
            document.getElementById('modalSignatureUploadBtn').style.display = 'none';
            selectedSignatureFile = null;
        }
    });

    document.getElementById('modalSignatureUploadBtn').addEventListener('click', async function () {
        if (!selectedSignatureFile) { showSignatureAlert('Please select a signature image first', 'error'); return; }

        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Uploading...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'upload_signature');
        formData.append('personnel_id', currentSignaturePersonnelId);
        formData.append('signature', selectedSignatureFile);

        try {
            const response = await fetch('upload_signature.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                showSignatureAlert(result.message, 'success');
                document.getElementById('modalCurrentSignature').innerHTML = `<img src="${result.path}?t=${Date.now()}" class="current-signature-modal">`;
                document.getElementById('modalSignatureDeleteBtn').style.display = 'inline-flex';
                const row = document.querySelector(`tr[data-personnel-number="${currentSignaturePersonnelId}"]`);
                if (row) {
                    const sigCell = row.querySelector('.signature-cell');
                    sigCell.innerHTML = `<img src="${result.path}?t=${Date.now()}" class="signature-preview" onclick="viewSignature('${result.path}', '${escapeHtml(currentSignaturePersonnelName)}')">`;
                }
                document.getElementById('modalSignature').value = '';
                document.getElementById('modalSignaturePreviewContainer').innerHTML = '';
                btn.style.display = 'none';
                selectedSignatureFile = null;
                setTimeout(() => closeSignatureModal(), 1500);
            } else {
                showSignatureAlert(result.message, 'error');
            }
        } catch (error) {
            showSignatureAlert('Error uploading signature', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    document.getElementById('modalSignatureDeleteBtn').addEventListener('click', async function () {
        if (!confirm('Remove this signature?')) return;
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Removing...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'delete_signature');
        formData.append('personnel_id', currentSignaturePersonnelId);

        try {
            const response = await fetch('upload_signature.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                showSignatureAlert(result.message, 'success');
                document.getElementById('modalCurrentSignature').innerHTML = `<div class="signature-placeholder-modal"><i class="fas fa-signature"></i><p>No signature uploaded</p></div>`;
                btn.style.display = 'none';
                const row = document.querySelector(`tr[data-personnel-number="${currentSignaturePersonnelId}"]`);
                if (row) {
                    const sigCell = row.querySelector('.signature-cell');
                    sigCell.innerHTML = `<span class="no-signature">—</span>`;
                }
                setTimeout(() => closeSignatureModal(), 1500);
            } else {
                showSignatureAlert(result.message, 'error');
            }
        } catch (error) {
            showSignatureAlert('Error removing signature', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // ==================== OTHER FUNCTIONS ====================
    function viewProfilePhoto(photoPath, name) {
        let modal = document.getElementById('photoViewModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'photoViewModal';
            modal.className = 'modal';
            modal.innerHTML = `<div class="modal-content" style="max-width:450px;text-align:center;"><div class="modal-header"><h3><i class="fas fa-user-circle"></i> Profile Photo</h3><span class="close" style="cursor:pointer;font-size:28px;">&times;</span></div><div class="modal-body"><img src="${photoPath}" style="width:200px;height:200px;border-radius:50%;object-fit:cover;"><p style="margin-top:15px;">${escapeHtml(name)}</p></div></div>`;
            document.body.appendChild(modal);
            modal.querySelector('.close').onclick = () => modal.style.display = 'none';
        }
        modal.style.display = 'block';
    }

    function viewSignature(signaturePath, name) {
        let modal = document.getElementById('signatureViewModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'signatureViewModal';
            modal.className = 'modal';
            modal.innerHTML = `<div class="modal-content" style="max-width:400px;text-align:center;"><div class="modal-header"><h3><i class="fas fa-signature"></i> Signature</h3><span class="close" style="cursor:pointer;font-size:28px;">&times;</span></div><div class="modal-body"><img src="${signaturePath}" style="max-width:100%;max-height:150px;"><p style="margin-top:15px;">${escapeHtml(name)}</p></div></div>`;
            document.body.appendChild(modal);
            modal.querySelector('.close').onclick = () => modal.style.display = 'none';
        }
        modal.style.display = 'block';
    }

    // Close modals on outside click
    window.onclick = function (event) {
        if (event.target === document.getElementById('photoUploadModal')) closePhotoModal();
        if (event.target === document.getElementById('signatureUploadModal')) closeSignatureModal();
        if (event.target === document.getElementById('editPersonnelModal')) closeEditModal();
        if (event.target === document.getElementById('manageBalanceModal')) closeManageBalanceModal();
        if (event.target === document.getElementById('addPersonnelModal')) closeAddPersonnelModal();
    }

    // Add Personnel Modal Functions
    function openAddPersonnelModal() {
        document.getElementById('addPersonnelModal').style.display = 'block';
    }

    function closeAddPersonnelModal() {
        document.getElementById('addPersonnelModal').style.display = 'none';
    }

    // Edit Personnel Modal Functions
    function closeEditModal() {
        document.getElementById('editPersonnelModal').style.display = 'none';
    }

    // Manage Balance Modal Functions
    function closeManageBalanceModal() {
        document.getElementById('manageBalanceModal').style.display = 'none';
    }

    function editPersonnel(personnelNumber) {
        const btn = event.currentTarget;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div>';
        btn.disabled = true;

        fetch(`get_personnel.php?id=${encodeURIComponent(personnelNumber)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const p = data.data;
                    document.getElementById('edit_personnel_number').value = p.personnel_number || '';
                    document.getElementById('edit_personnel_number_display').value = p.personnel_number || '';
                    document.getElementById('edit_full_name_en').value = p.full_name_en || '';
                    document.getElementById('edit_full_name_ne').value = p.full_name_ne || '';
                    document.getElementById('edit_email').value = p.email || '';
                    document.getElementById('edit_phone').value = p.phone || '';
                    document.getElementById('edit_rank').value = p.rank || '';
                    document.getElementById('edit_unit').value = p.unit || '';
                    document.getElementById('edit_recruitment_date').value = p.recruitment_date || '';
                    document.getElementById('edit_province').value = p.province || '';
                    document.getElementById('edit_district').value = p.district || '';
                    document.getElementById('edit_municipality').value = p.municipality || '';
                    document.getElementById('edit_village_tole').value = p.village_tole || '';
                    document.getElementById('edit_current_status').value = p.current_status || 'Active';
                    document.getElementById('edit_role').value = p.role || 0;
                    document.getElementById('editPersonnelModal').style.display = 'block';
                } else {
                    showToast(data.message || 'Error loading data', 'error');
                }
            })
            .catch(error => { showToast('Error loading data', 'error'); })
            .finally(() => { btn.innerHTML = originalHtml; btn.disabled = false; });
    }

    document.getElementById('editPersonnelForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = this.querySelector('.btn-save');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Saving...';
        btn.disabled = true;
        try {
            const res = await fetch('update_personnel.php', { method: 'POST', body: new FormData(this) });
            const result = await res.json();
            if (result.success) {
                showToast(result.message, 'success');
                closeEditModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message || 'Error updating', 'error');
            }
        } catch (error) {
            showToast('Error updating personnel', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // Add Personnel Form Submit
    document.getElementById('addPersonnelFormModal')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitBtn = this.querySelector('.btn-save');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading-spinner"></div> Adding...';
        submitBtn.disabled = true;

        const serviceNo = document.getElementById('addServiceNo').value.trim();
        const fullName = document.getElementById('addFullName').value.trim();
        const rank = document.getElementById('addRank').value;

        if (!serviceNo || !fullName || !rank) {
            showToast('Please fill all required fields', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            return;
        }

        try {
            const response = await fetch('add_personnel_ajax.php', {
                method: 'POST',
                body: new FormData(this)
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message || 'Personnel added successfully!', 'success');
                closeAddPersonnelModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message || 'Error adding personnel', 'error');
            }
        } catch (error) {
            showToast('Error adding personnel', 'error');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });

    <?php if ($isSuperAdmin): ?>
        document.getElementById('manageBalanceBtn')?.addEventListener('click', function () {
            document.getElementById('manageBalanceModal').style.display = 'block';
            loadAllLeaveBalances();
        });
        document.getElementById('addPersonnelBtn')?.addEventListener('click', openAddPersonnelModal);

        function resetPassword(serviceNo, name) {
            if (confirm(`Reset password for ${name} to "reset@123"?`)) {
                fetch('reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `personnel_no=${encodeURIComponent(serviceNo)}&password=reset@123`
                }).then(res => res.json()).then(data => {
                    if (data.success) showToast(`Password reset to: reset@123`, 'success');
                    else showToast(data.message || 'Error', 'error');
                }).catch(() => showToast('Error resetting password', 'error'));
            }
        }

        function deletePersonnel(serviceNo, name) {
            if (confirm(`Delete ${name}? This cannot be undone.`)) {
                fetch('delete_personnel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(serviceNo)}`
                }).then(res => res.json()).then(data => {
                    if (data.success) { showToast('Personnel deleted', 'success'); setTimeout(() => location.reload(), 1000); }
                    else showToast(data.message || 'Error', 'error');
                }).catch(() => showToast('Error deleting', 'error'));
            }
        }
    <?php endif; ?>

    // ==================== MANAGE LEAVE BALANCE ====================
    let allPersonnelData = [];

    function loadAllLeaveBalances() {
        const tbody = document.getElementById('leaveBalanceTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;"><div class="loading-spinner"></div> Loading leave balances...</td></tr>';

        fetch('get_all_leave_balances.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    allPersonnelData = data.data;
                    renderLeaveBalanceTable(allPersonnelData);
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#c2410c;">Failed to load leave balances</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#c2410c;">Error loading leave balances</td></tr>';
            });
    }

    function renderLeaveBalanceTable(data) {
        const tbody = document.getElementById('leaveBalanceTableBody');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;">No leave balance records found</td></tr>';
            return;
        }

        let html = '';
        data.forEach((person, idx) => {
            const pid = person.personnel_id;
            // Format numbers
            const gharpari = person.gharpari_bida_days % 1 === 0 ? person.gharpari_bida_days : person.gharpari_bida_days.toFixed(1);
            const parba = person.parba_bida_days % 1 === 0 ? person.parba_bida_days : person.parba_bida_days.toFixed(1);
            const bhaeepari = person.bhaeepari_bida_days % 1 === 0 ? person.bhaeepari_bida_days : person.bhaeepari_bida_days.toFixed(1);

            html += `
                <tr id="balance-row-${pid}">
                    <td style="width: 50px; text-align: center;">${idx + 1}</td>
                    <td><strong>${escapeHtml(person.service_no || person.personnel_id)}</strong></td>
                    <td style="width: 130px;">
                        <span class="gharpari-display" data-id="${pid}">${gharpari}</span>
                        <input type="number" class="gharpari-input" data-id="${pid}" 
                               value="${person.gharpari_bida_days}" step="0.5" min="0"
                               style="display: none; width: 80px; padding: 5px;" class="balance-input">
                    </td>
                    <td style="width: 130px;">
                        <span class="parba-display" data-id="${pid}">${parba}</span>
                        <input type="number" class="parba-input" data-id="${pid}" 
                               value="${person.parba_bida_days}" step="0.5" min="0"
                               style="display: none; width: 80px; padding: 5px;" class="balance-input">
                    </td>
                    <td style="width: 130px;">
                        <span class="bhaeepari-display" data-id="${pid}">${bhaeepari}</span>
                        <input type="number" class="bhaeepari-input" data-id="${pid}" 
                               value="${person.bhaeepari_bida_days}" step="0.5" min="0"
                               style="display: none; width: 80px; padding: 5px;" class="balance-input">
                    </td>
                    <td style="width: 180px;">
                        <button class="edit-balance-btn" onclick="editBalanceRow('${pid}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="save-balance-btn" onclick="saveBalanceRow('${pid}')" style="display: none;">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button class="cancel-balance-btn" onclick="cancelEditBalanceRow('${pid}')" style="display: none;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function editBalanceRow(personnelId) {
        const gharpariDisplay = document.querySelector(`.gharpari-display[data-id="${personnelId}"]`);
        const gharpariInput = document.querySelector(`.gharpari-input[data-id="${personnelId}"]`);
        const parbaDisplay = document.querySelector(`.parba-display[data-id="${personnelId}"]`);
        const parbaInput = document.querySelector(`.parba-input[data-id="${personnelId}"]`);
        const bhaeepariDisplay = document.querySelector(`.bhaeepari-display[data-id="${personnelId}"]`);
        const bhaeepariInput = document.querySelector(`.bhaeepari-input[data-id="${personnelId}"]`);

        const editBtn = document.querySelector(`.edit-balance-btn[onclick*="${personnelId}"]`);
        const row = editBtn?.closest('tr');
        const saveBtn = row?.querySelector('.save-balance-btn');
        const cancelBtn = row?.querySelector('.cancel-balance-btn');

        if (gharpariDisplay) gharpariDisplay.style.display = 'none';
        if (gharpariInput) gharpariInput.style.display = 'inline-block';
        if (parbaDisplay) parbaDisplay.style.display = 'none';
        if (parbaInput) parbaInput.style.display = 'inline-block';
        if (bhaeepariDisplay) bhaeepariDisplay.style.display = 'none';
        if (bhaeepariInput) bhaeepariInput.style.display = 'inline-block';

        if (editBtn) editBtn.style.display = 'none';
        if (saveBtn) saveBtn.style.display = 'inline-flex';
        if (cancelBtn) cancelBtn.style.display = 'inline-flex';
    }

    function cancelEditBalanceRow(personnelId) {
        const record = allPersonnelData.find(r => r.personnel_id == personnelId);

        const gharpariDisplay = document.querySelector(`.gharpari-display[data-id="${personnelId}"]`);
        const gharpariInput = document.querySelector(`.gharpari-input[data-id="${personnelId}"]`);
        const parbaDisplay = document.querySelector(`.parba-display[data-id="${personnelId}"]`);
        const parbaInput = document.querySelector(`.parba-input[data-id="${personnelId}"]`);
        const bhaeepariDisplay = document.querySelector(`.bhaeepari-display[data-id="${personnelId}"]`);
        const bhaeepariInput = document.querySelector(`.bhaeepari-input[data-id="${personnelId}"]`);

        if (record) {
            if (gharpariInput) gharpariInput.value = record.gharpari_bida_days;
            if (parbaInput) parbaInput.value = record.parba_bida_days;
            if (bhaeepariInput) bhaeepariInput.value = record.bhaeepari_bida_days;
        }

        if (gharpariDisplay) gharpariDisplay.style.display = 'inline';
        if (gharpariInput) gharpariInput.style.display = 'none';
        if (parbaDisplay) parbaDisplay.style.display = 'inline';
        if (parbaInput) parbaInput.style.display = 'none';
        if (bhaeepariDisplay) bhaeepariDisplay.style.display = 'inline';
        if (bhaeepariInput) bhaeepariInput.style.display = 'none';

        const editBtn = document.querySelector(`.edit-balance-btn[onclick*="${personnelId}"]`);
        const row = editBtn?.closest('tr');
        const saveBtn = row?.querySelector('.save-balance-btn');
        const cancelBtn = row?.querySelector('.cancel-balance-btn');

        if (editBtn) editBtn.style.display = 'inline-flex';
        if (saveBtn) saveBtn.style.display = 'none';
        if (cancelBtn) cancelBtn.style.display = 'none';
    }

    function saveBalanceRow(personnelId) {
        const gharpariInput = document.querySelector(`.gharpari-input[data-id="${personnelId}"]`);
        const parbaInput = document.querySelector(`.parba-input[data-id="${personnelId}"]`);
        const bhaeepariInput = document.querySelector(`.bhaeepari-input[data-id="${personnelId}"]`);

        const gharpari = parseFloat(gharpariInput?.value) || 0;
        const parba = parseFloat(parbaInput?.value) || 0;
        const bhaeepari = parseFloat(bhaeepariInput?.value) || 0;

        if (gharpari < 0 || parba < 0 || bhaeepari < 0) {
            showToast('Leave days cannot be negative', 'error');
            return;
        }

        const editBtn = document.querySelector(`.edit-balance-btn[onclick*="${personnelId}"]`);
        const row = editBtn?.closest('tr');
        const saveBtn = row?.querySelector('.save-balance-btn');

        if (saveBtn) {
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<div class="loading-spinner" style="width: 14px; height: 14px;"></div>';
            saveBtn.disabled = true;

            fetch('update_leave_balance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    personnel_id: personnelId,
                    gharpari_bida: gharpari,
                    parba_bida: parba,
                    bhaeepari_bida: bhaeepari
                })
            })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        const gharpariDisplay = document.querySelector(`.gharpari-display[data-id="${personnelId}"]`);
                        const parbaDisplay = document.querySelector(`.parba-display[data-id="${personnelId}"]`);
                        const bhaeepariDisplay = document.querySelector(`.bhaeepari-display[data-id="${personnelId}"]`);

                        const formattedGharpari = gharpari % 1 === 0 ? gharpari : gharpari.toFixed(1);
                        const formattedParba = parba % 1 === 0 ? parba : parba.toFixed(1);
                        const formattedBhaeepari = bhaeepari % 1 === 0 ? bhaeepari : bhaeepari.toFixed(1);

                        if (gharpariDisplay) gharpariDisplay.textContent = formattedGharpari;
                        if (parbaDisplay) parbaDisplay.textContent = formattedParba;
                        if (bhaeepariDisplay) bhaeepariDisplay.textContent = formattedBhaeepari;

                        const record = allPersonnelData.find(r => r.personnel_id == personnelId);
                        if (record) {
                            record.gharpari_bida_days = gharpari;
                            record.parba_bida_days = parba;
                            record.bhaeepari_bida_days = bhaeepari;
                        }

                        cancelEditBalanceRow(personnelId);
                        showToast('Leave balance updated successfully!', 'success');
                    } else {
                        showToast(result.message || 'Error updating leave balance', 'error');
                        cancelEditBalanceRow(personnelId);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error updating leave balance', 'error');
                    cancelEditBalanceRow(personnelId);
                })
                .finally(() => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                });
        }
    }

    // Search functionality for balance modal
    const balanceSearchInput = document.getElementById('balanceSearchInput');
    if (balanceSearchInput) {
        balanceSearchInput.addEventListener('keyup', function () {
            const searchTerm = this.value.toLowerCase().trim();
            if (!searchTerm) {
                renderLeaveBalanceTable(allPersonnelData);
                return;
            }

            const filtered = allPersonnelData.filter(person =>
                (person.personnel_name && person.personnel_name.toLowerCase().includes(searchTerm)) ||
                (person.service_no && person.service_no.toLowerCase().includes(searchTerm)) ||
                (person.rank && person.rank.toLowerCase().includes(searchTerm)) ||
                (person.personnel_id && String(person.personnel_id).toLowerCase().includes(searchTerm))
            );
            renderLeaveBalanceTable(filtered);
        });
    }

    // Search and pagination
    document.getElementById('searchForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const url = new URL(window.location.href);
        const val = document.getElementById('searchInput').value.trim();
        if (val) url.searchParams.set('search', val);
        else url.searchParams.delete('search');
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    });
    document.getElementById('clearSearch')?.addEventListener('click', function () {
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    });
    document.getElementById('recordsPerPage')?.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', this.value);
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    });
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>