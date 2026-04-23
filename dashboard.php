<?php
// Include database configuration
require_once('includes/config.php');

// Pagination configuration
$limit = 5; // Number of records per page
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $limit;

// Fetch statistics (no pagination for stats)
// Total Personnel
$stmt = $pdo->query("SELECT COUNT(*) as total FROM personnel");
$totalPersonnel = $stmt->fetch()['total'];

// Active Duty (personnel with current_status = 'Active')
$stmt = $pdo->query("SELECT COUNT(*) as active FROM personnel WHERE current_status = 'Active'");
$activeDuty = $stmt->fetch()['active'];

// On Leave (personnel with current_status = 'Leave')
$stmt = $pdo->query("SELECT COUNT(*) as leave_count FROM personnel WHERE current_status = 'Leave'");
$onLeave = $stmt->fetch()['leave_count'];

// Training personnel
$stmt = $pdo->query("SELECT COUNT(*) as training_count FROM personnel WHERE current_status = 'Training'");
$inTraining = $stmt->fetch()['training_count'];

// Pending Requests (from military_personnel_status where out_time is NULL)
$stmt = $pdo->query("SELECT COUNT(*) as pending FROM military_personnel_status WHERE out_time IS NULL AND record_date = CURDATE()");
$pendingRequests = $stmt->fetch()['pending'];

// Get total count for pagination
$countStmt = $pdo->query("SELECT COUNT(*) as total FROM personnel");
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch personnel list with pagination
$stmt = $pdo->prepare("
    SELECT p.rank, p.full_name_en as name, p.unit, p.current_status as status 
    FROM personnel p 
    ORDER BY 
        CASE p.current_status 
            WHEN 'Active' THEN 1 
            WHEN 'Training' THEN 2 
            WHEN 'Leave' THEN 3 
            ELSE 4 
        END, 
        p.full_name_en 
    LIMIT :offset, :limit
");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$personnelList = $stmt->fetchAll();

$pageTitle = "Dashboard";
$pageSubtitle = "Welcome back, Personnel. Here's your HR overview.";
$activePage = "dashboard";

// Include pagination functions
include('includes/pagination.php');

// Prepare the content
ob_start();
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #1e3c72 0%, #2b4c7c 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1a2a3a;
        line-height: 1.2;
    }
    
    .stat-label {
        font-size: 0.85rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
    }
    
    .data-table table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f8f9fa;
        padding: 1rem 1.5rem;
        text-align: left;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #e9ecef;
    }
    
    .data-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
        color: #212529;
    }
    
    .data-table tr:hover {
        background: #f8f9fa;
    }
    
    .badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #d4edda;
        color: #155724;
    }
    
    .badge.leave {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge.training {
        background: #cce5ff;
        color: #004085;
    }
    
    .badge.other {
        background: #e2e3e5;
        color: #383d41;
    }

    /* Pagination Styles */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .pagination {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .pagination-btn,
    .page-number {
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
    
    .pagination-btn:hover,
    .page-number:hover {
        background: #f8f9fa;
        border-color: #1e3c72;
        color: #1e3c72;
    }
    
    .page-number.active {
        background: #1e3c72;
        border-color: #1e3c72;
        color: white;
    }
    
    .page-dots {
        padding: 0.5rem;
        color: #6c757d;
    }
    
    .pagination-info {
        color: #6c757d;
        font-size: 0.875rem;
    }
    
    /* Serial Number Column Style */
    .serial-number {
        width: 60px;
        text-align: center;
        font-weight: 500;
        color: #6c757d;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .stat-card {
            padding: 1rem;
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            font-size: 1.3rem;
        }
        
        .stat-value {
            font-size: 1.4rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem 1rem;
        }
        
        .pagination-container {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .pagination {
            width: 100%;
            justify-content: center;
        }
        
        .serial-number {
            width: 50px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .pagination-btn,
        .page-number {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
    }
</style>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($totalPersonnel); ?></div>
            <div class="stat-label">Total Personnel</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($activeDuty); ?></div>
            <div class="stat-label">Active Duty</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($inTraining); ?></div>
            <div class="stat-label">In Training</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($onLeave); ?></div>
            <div class="stat-label">On Leave</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($pendingRequests); ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
    </div>
</div>

<div class="data-table">
    <table>
        <thead>
            <tr>
                <th class="serial-number">S.N.</th>
                <th>Rank</th>
                <th>Name</th>
                <th>Unit</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($personnelList) > 0): ?>
                <?php 
                $serialNumber = $offset + 1;
                foreach ($personnelList as $person): 
                ?>
                    <tr>
                        <td class="serial-number"><?php echo $serialNumber++; ?></td>
                        <td><?php echo htmlspecialchars($person['rank'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($person['name']); ?></td>
                        <td><?php echo htmlspecialchars($person['unit'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                            $status = $person['status'] ?? 'Unknown';
                            $badgeClass = '';
                            if ($status === 'Active') {
                                $badgeClass = 'badge';
                            } elseif ($status === 'Leave') {
                                $badgeClass = 'badge leave';
                            } elseif ($status === 'Training') {
                                $badgeClass = 'badge training';
                            } else {
                                $badgeClass = 'badge other';
                            }
                            ?>
                            <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No personnel records found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination-container">
    <?php renderPaginationInfo($offset, $limit, $totalRecords); ?>
    <?php renderPagination($currentPage, $totalPages); ?>
</div>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>