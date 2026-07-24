<?php
require_once 'includes/config.php';

$role = $_SESSION['user_role'] ?? 0;
$user_id = $_SESSION['user_id'] ?? '';

$user_name = '';
$user_rank = '';

if (!empty($user_id)) {
    $stmt = $pdo->prepare("
        SELECT p.full_name_ne,
               r.rank_unicode
        FROM personnel p
        LEFT JOIN def_rank r ON p.rank = r.rank_code
        WHERE p.personnel_number = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user_name = $user['full_name_ne'];
        $user_rank = $user['rank_unicode'];
    }
}
?>
<aside
    style="width: 224px; min-height: calc(100vh - 54px); background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border-right: none; box-shadow: 2px 0 20px rgba(0,0,0,0.05); padding: 16px 0; flex-shrink: 0; position: sticky; top: 54px;">
    <!-- User Profile Summary -->
    <div style="padding: 0 16px 14px 16px; margin-bottom: 14px; border-bottom: 1px solid #eef2f6;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
            <div
                style="width: 38px; height: 38px; background: linear-gradient(135deg, #10263f 0%, #081b2e 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(16,38,63,0.2); flex-shrink: 0;">
                <i class="fas fa-user-shield" style="font-size: 18px; color: #22d3ee;"></i>
            </div>
            <div style="min-width: 0;">
                <div id="sidebarUserName" style="font-size: 13px; font-weight: 600; color: #10263f; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo htmlspecialchars($user_rank ?: 'N/A'); ?>
                </div>
                <div id="sidebarUserRank" style="font-size: 11px; color: #8a99b0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo htmlspecialchars($user_name ?: 'N/A'); ?>
                </div>
            </div>
        </div>
    </div>

    <ul style="list-style: none; margin: 0; padding: 0 10px;">
        <li style="margin-bottom: 4px;">
            <a href="dashboard.php" class="sidebar-link" data-page="dashboard"
                style="display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-radius: 10px; text-decoration: none; color: #5b6e8c; font-size: 13px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-tachometer-alt" style="width: 17px; font-size: 15px; transition: all 0.3s ease;"></i>
                <span>Dashboard</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #22d3ee; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>
        <li style="margin-bottom: 4px;">
            <a href="personnel.php" class="sidebar-link" data-page="personnel"
                style="display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-radius: 10px; text-decoration: none; color: #5b6e8c; font-size: 13px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-users" style="width: 17px; font-size: 15px; transition: all 0.3s ease;"></i>
                <span>व्यक्तिगत विवरण</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #22d3ee; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>
        <li style="margin-bottom: 4px;">
            <a href="events.php" class="sidebar-link" data-page="events"
                style="display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-radius: 10px; text-decoration: none; color: #5b6e8c; font-size: 13px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-calendar-alt" style="width: 17px; font-size: 15px; transition: all 0.3s ease;"></i>
                <span>कार्यक्रम विवरण</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #22d3ee; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>
        <li style="margin-bottom: 4px;">
            <a href="leave.php" class="sidebar-link" data-page="leave"
                style="display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-radius: 10px; text-decoration: none; color: #5b6e8c; font-size: 13px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-umbrella-beach" style="width: 17px; font-size: 15px; transition: all 0.3s ease;"></i>
                <span>बिदा विवरण</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #22d3ee; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>

        <li style="margin-top: 20px; border-top: 1px solid #eef2f6; padding-top: 12px;"></li>

</aside>

<style>
    /* Sidebar link hover effect - no translation */
    .sidebar-link {
        position: relative;
    }

    .sidebar-link:hover {
        background: #e6f4fa;
        color: #10263f !important;
    }

    .sidebar-link:hover i {
        color: #10263f;
        transform: scale(1.05);
    }

    .sidebar-link:hover .active-indicator {
        height: 60%;
    }

    /* Active link styling */
    .sidebar-link.active {
        background: #e6f4fa;
        color: #10263f;
        font-weight: 600;
    }

    .sidebar-link.active i {
        color: #10263f;
    }

    .sidebar-link.active .active-indicator {
        height: 60%;
    }

    /* Icon animation */
    .sidebar-link i {
        transition: transform 0.3s ease, color 0.3s ease;
        color: #9aa9bc;
    }

    /* Scrollbar styling for sidebar */
    aside::-webkit-scrollbar {
        width: 4px;
    }

    aside::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    aside::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    aside::-webkit-scrollbar-thumb:hover {
        background: #0e7490;
    }
</style>

<script>
    // Set active link based on current page
    function setActiveSidebarLink() {
        const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
        const links = document.querySelectorAll('.sidebar-link');

        links.forEach(link => {
            const page = link.getAttribute('data-page');
            if (page === currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    // Initialize sidebar
    document.addEventListener('DOMContentLoaded', function () {
        setActiveSidebarLink();
    });
</script>