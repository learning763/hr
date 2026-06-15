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
    SELECT 
        p.personnel_number,
        p.rank,
        r.rank_unicode,
        p.full_name_ne,
        p.contact,
        p.email
    FROM personnel p
    LEFT JOIN def_rank r ON p.rank = r.rank_code
    WHERE p.contact IS NOT NULL 
       OR p.email IS NOT NULL
    ORDER BY p.personnel_number ASC
");
$contacts = $stmt->fetchAll();

$icon = '<i class="fa-solid fa-house"></i>';
$pageTitle = "<h2>$icon Dashboard</h2>";
$pageSubtitle = "श्री साइबर सुरक्षाा निर्देशनालयको HR System मा स्वागत छ ।";
$activePage = "dashboard";

// Prepare the content
ob_start();
?>

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
                <td><?php echo htmlspecialchars($c['rank_unicode'] ?? 'N/A'); ?></td>
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