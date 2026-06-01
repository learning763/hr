<?php
// Include database configuration
require_once('includes/config.php');

// Fetch statistics (no personnel list)
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

// Retired personnel
$stmt = $pdo->query("SELECT COUNT(*) as retired_count FROM personnel WHERE current_status = 'Retired'");
$retired = $stmt->fetch()['retired_count'];

// Pending Requests (from military_personnel_status where out_time is NULL)
$stmt = $pdo->query("SELECT COUNT(*) as pending FROM military_personnel_status WHERE out_time IS NULL AND record_date = CURDATE()");
$pendingRequests = $stmt->fetch()['pending'];

// Gender distribution
$stmt = $pdo->query("SELECT COUNT(*) as male FROM personnel WHERE gender = 'Male'");
$maleCount = $stmt->fetch()['male'];

$stmt = $pdo->query("SELECT COUNT(*) as female FROM personnel WHERE gender = 'Female'");
$femaleCount = $stmt->fetch()['female'];

// Blood group distribution
$stmt = $pdo->query("SELECT blood_group, COUNT(*) as count FROM personnel WHERE blood_group IS NOT NULL GROUP BY blood_group");
$bloodGroups = $stmt->fetchAll();

$pageTitle = "Dashboard";
$pageSubtitle = "Welcome back, Personnel. Here's your HR overview.";
$activePage = "dashboard";

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
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1a2a3a;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stats-mini-card {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s;
    }
    
    .stats-mini-card:hover {
        transform: translateX(3px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .stats-mini-label {
        font-size: 0.85rem;
        color: #6c757d;
        font-weight: 500;
    }
    
    .stats-mini-value {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1e3c72;
    }
    
    .blood-group-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    
    .blood-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        background: #f8f9fa;
        color: #dc2626;
        border: 1px solid #e9ecef;
    }
    
    .blood-badge span {
        font-weight: 700;
        font-size: 1rem;
        margin-right: 0.25rem;
    }
    
    .blood-badge .count {
        color: #6c757d;
        font-weight: 500;
        font-size: 0.75rem;
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
        
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .stats-mini-card {
            padding: 0.75rem;
        }
        
        .stats-mini-value {
            font-size: 1.1rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Main Stats Cards -->
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
        <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($retired); ?></div>
            <div class="stat-label">Retired</div>
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

<!-- Gender Distribution -->
<div class="section-title">
    <i class="fas fa-venus-mars"></i> Gender Distribution
</div>
<div class="stats-row">
    <div class="stats-mini-card">
        <span class="stats-mini-label"><i class="fas fa-mars"></i> Male</span>
        <span class="stats-mini-value"><?php echo number_format($maleCount); ?></span>
    </div>
    <div class="stats-mini-card">
        <span class="stats-mini-label"><i class="fas fa-venus"></i> Female</span>
        <span class="stats-mini-value"><?php echo number_format($femaleCount); ?></span>
    </div>
    <div class="stats-mini-card">
        <span class="stats-mini-label"><i class="fas fa-genderless"></i> Other</span>
        <span class="stats-mini-value"><?php echo number_format($totalPersonnel - $maleCount - $femaleCount); ?></span>
    </div>
</div>

<!-- Blood Group Distribution -->
<div class="section-title">
    <i class="fas fa-tint"></i> Blood Group Distribution
</div>
<div class="blood-group-container">
    <?php 
    $bloodOrder = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    $bloodMap = [];
    foreach ($bloodGroups as $bg) {
        $bloodMap[$bg['blood_group']] = $bg['count'];
    }
    
    foreach ($bloodOrder as $bg):
        $count = $bloodMap[$bg] ?? 0;
        if ($count > 0 || true): // Show all blood types even if 0
    ?>
        <div class="blood-badge">
            <span><?php echo htmlspecialchars($bg); ?></span>
            <span class="count">(<?php echo $count; ?> personnel)</span>
        </div>
    <?php 
        endif;
    endforeach; 
    ?>
</div>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>