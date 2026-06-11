<?php
// Include database configuration
require_once('includes/config.php');
// Include footer
require_once('includes/functions.php');

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

// Contact details
$stmt = $pdo->query("
    SELECT personnel_number, rank, full_name_ne, contact, email
    FROM personnel
    WHERE contact IS NOT NULL OR email IS NOT NULL
    ORDER BY personnel_number ASC
");
$contacts = $stmt->fetchAll();

$icon = '<i class="fa-solid fa-house"></i>';
$pageTitle = "<h2>$icon Dashboard</h2>";
$pageSubtitle = "श्री साइबर सुरक्षाा निर्देशनालयको HR System मा स्वागत छ ।";
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
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
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
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s;
    }

    .stats-mini-card:hover {
        transform: translateX(3px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

    .contact-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .contact-table th {
        background: #1a3c34;
        color: white;
        padding: 12px;
        text-align: left;
        font-size: 13px;
    }

    .contact-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #eee;
        font-size: 13px;
        color: #333;
    }

    .contact-table tr:hover {
        background: #f5f7fa;
    }

    /* for blood donation button */
    .blood-group-container {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 12px;
    }

    /* Bigger Button */
    .blood-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;

        min-width: 150px;
        /* ⬆ bigger width */
        padding: 14px 18px;
        /* ⬆ bigger padding */

        border-radius: 14px;
        border: none;
        cursor: pointer;

        background: linear-gradient(135deg, #ff4d4d, #c0392b);
        color: #fff;

        font-weight: 600;
        font-size: 16px;

        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.12);
        transition: all 0.25s ease-in-out;
        position: relative;
        overflow: hidden;
    }

    /* Hover effect */
    .blood-btn:hover {
        transform: translateY(-4px) scale(1.04);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
    }

    /* Text spacing fix */
    .blood-btn span:first-child {
        letter-spacing: 0.5px;
    }

    /* Count badge with SPACE from blood group */
    .blood-btn .count {
        margin-left: 14px;
        /* ✅ SPACE ADDED */
        background: rgba(255, 255, 255, 0.2);
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 13px;
        white-space: nowrap;
    }

    /* Shine effect */
    .blood-btn::before {
        content: "";
        position: absolute;
        top: 0;
        left: -75%;
        width: 50%;
        height: 100%;
        background: rgba(255, 255, 255, 0.15);
        transform: skewX(-25deg);
    }

    .blood-btn:hover::before {
        animation: shine 0.8s ease;
    }

    @keyframes shine {
        0% {
            left: -75%;
        }

        100% {
            left: 125%;
        }
    }
</style>

<!-- Main Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div>
            <div class="stat-value"><?php echo engtouni(number_format($totalPersonnel)); ?></div>
            <div class="stat-label">जम्मा नफ्री</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-value"><?php echo engtouni(number_format($activeDuty)); ?></div>
            <div class="stat-label">हाजिर संख्या</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
        <div>
            <div class="stat-value"><?php echo engtouni(number_format($inTraining)); ?></div>
            <div class="stat-label">तालिम</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
        <div>
            <div class="stat-value"><?php echo engtouni(number_format($onLeave)); ?></div>
            <div class="stat-label">बिदा</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-helmet-un"></i></div>
        <div>
            <div class="stat-value"><?php echo engtouni(number_format($onLeave)); ?></div>
            <div class="stat-label">शान्ति सेना</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="stat-value"><?php echo engtouni(number_format($retired)); ?></div>
            <div class="stat-label">राजिनामा खाली</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?php echo engtouni(number_format($pendingRequests)); ?></div>
            <div class="stat-label">बिदाको निवेदन (Pending)</div>
        </div>
    </div>
</div>

<!-- Gender Distribution -->
<div class="section-title">
    <i class="fas fa-venus-mars"></i> Gender Distribution
</div>
<div class="stats-row" style="width:50%">
    <div class="stats-mini-card">
        <span class="stats-mini-label"><i class="fas fa-mars" style="margin-right:25px;font-size: 32px;"></i><b
                style="font-size:20px;">पुरुष</b></span>
        <span class="stats-mini-value"><?php echo engtouni(number_format($maleCount)); ?></span>
    </div>
    <div class="stats-mini-card">
        <span class="stats-mini-label"><i class="fas fa-venus" style="margin-right:25px;font-size: 32px;"></i> <b
                style="font-size:20px;">महिला</b></span>
        <span class="stats-mini-value"><?php echo engtouni(number_format($femaleCount)); ?></span>
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
        ?>
        <button class="blood-btn" data-group="<?= $bg ?>">
            <span class="group"><?= $bg ?></span>
            <span class="count"><?= $count ?></span>
        </button>

    <?php endforeach; ?>
</div>
<div class="modal fade" id="bloodModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Blood Group Members</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="bloodModalBody">
                Loading...
            </div>

        </div>
    </div>
</div>
<br>

<!-- Contact Details Table -->
<div class="section-title">
    <i class="fas fa-address-book"></i> Telephone Directory
</div>

<table id="contactTable" class="display contact-table" style="text-align:center;">
    <thead>
        <tr>
            <th style="text-align:center;width:20px;">सि.नं.</th>
            <th style="text-align:center;">व्य.नं</th>
            <th style="text-align:center;">दर्जा</th>
            <th style="text-align:center;">नामथर</th>
            <th style="text-align:center;">सम्पर्क नं.</th>
            <th style="text-align:center;">Email</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sn = 1;
        foreach ($contacts as $c):
            ?>
            <tr>
                <td><?php echo engtouni($sn++); ?></td>
                <td><?php echo engtouni($c['personnel_number']); ?></td>
                <td><?php echo htmlspecialchars($c['rank'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($c['full_name_ne'] ?? 'N/A'); ?></td>
                <td><?php echo engtouni($c['contact'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($c['email'] ?? 'N/A'); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
include('includes/layout.php');

?>