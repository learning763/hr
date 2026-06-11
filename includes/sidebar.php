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
    style="width: 280px; min-height: calc(100vh - 70px); background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border-right: none; box-shadow: 2px 0 20px rgba(0,0,0,0.05); padding: 24px 0; flex-shrink: 0; position: sticky; top: 70px;">
    <!-- User Profile Summary -->
    <div style="padding: 0 20px 20px 20px; margin-bottom: 20px; border-bottom: 1px solid #eef2f6;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <div
                style="width: 48px; height: 48px; background: linear-gradient(135deg, #1a3c34 0%, #0f2c26 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(26,60,52,0.2);">
                <i class="fas fa-user-shield" style="font-size: 24px; color: #ffd700;"></i>
            </div>
            <div id="sidebarUserName">
                <?php echo htmlspecialchars($user_rank ?: 'N/A'); ?>
            </div>

            <div id="sidebarUserRank">
                <?php echo htmlspecialchars($user_name ?: 'N/A'); ?>
            </div>
        </div>
    </div>

    <ul style="list-style: none; margin: 0; padding: 0 12px;">
        <li style="margin-bottom: 4px;">
            <a href="dashboard.php" class="sidebar-link" data-page="dashboard"
                style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #5b6e8c; font-size: 14px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-tachometer-alt" style="width: 20px; font-size: 18px; transition: all 0.3s ease;"></i>
                <span>Dashboard</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #ffd700; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>
        <li style="margin-bottom: 4px;">
            <a href="personnel.php" class="sidebar-link" data-page="personnel"
                style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #5b6e8c; font-size: 14px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-users" style="width: 20px; font-size: 18px; transition: all 0.3s ease;"></i>
                <span>व्यक्तिगत विवरण</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #ffd700; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>
        <li style="margin-bottom: 4px;">
            <a href="attendance.php" class="sidebar-link" data-page="attendance"
                style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #1a3c34; font-size: 14px; font-weight: 600; position: relative; overflow: hidden; background: #f0f7f4;">
                <i class="fas fa-clock"
                    style="width: 20px; font-size: 18px; color: #1a3c34; transition: all 0.3s ease;"></i>
                <span>हाजिर विवरण</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; background: #ffd700; border-radius: 0 2px 2px 0;">
                </div>
            </a>
        </li>
        <li style="margin-bottom: 4px;">
            <a href="events.php" class="sidebar-link" data-page="events"
                style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #5b6e8c; font-size: 14px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-calendar-alt" style="width: 20px; font-size: 18px; transition: all 0.3s ease;"></i>
                <span>कार्यक्रम विवरण</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #ffd700; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>
        <li style="margin-bottom: 4px;">
            <a href="leave.php" class="sidebar-link" data-page="leave"
                style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #5b6e8c; font-size: 14px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">
                <i class="fas fa-umbrella-beach" style="width: 20px; font-size: 18px; transition: all 0.3s ease;"></i>
                <span>बिदा विवरण</span>
                <div class="active-indicator"
                    style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #ffd700; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                </div>
            </a>
        </li>

        <?php if ($role == 1 || $role == 2): ?>
            <li style="margin-bottom: 4px;">
                <a href="profile.php" class="sidebar-link" data-page="profile"
                    style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #5b6e8c; font-size: 14px; font-weight: 500; position: relative; overflow: hidden; transition: all 0.3s ease;">

                    <i class="fas fa-user-circle" style="width: 20px; font-size: 18px; transition: all 0.3s ease;"></i>
                    <span>प्रोफाइल</span>

                    <div class="active-indicator"
                        style="position: absolute; left: 0; top: 50%; width: 3px; height: 0; background: #ffd700; border-radius: 0 2px 2px 0; transition: height 0.3s ease;">
                    </div>
                </a>
            </li>
        <?php endif; ?>
        <li style="margin-top: 20px; border-top: 1px solid #eef2f6; padding-top: 12px;"></li>

</aside>

<style>
    /* Sidebar link hover effect - no translation */
    .sidebar-link {
        position: relative;
    }

    .sidebar-link:hover {
        background: #f0f7f4;
        color: #1a3c34 !important;
    }

    .sidebar-link:hover i {
        color: #1a3c34;
        transform: scale(1.05);
    }

    .sidebar-link:hover .active-indicator {
        height: 60%;
    }

    /* Active link styling */
    .sidebar-link.active {
        background: #f0f7f4;
        color: #1a3c34;
        font-weight: 600;
    }

    .sidebar-link.active i {
        color: #1a3c34;
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
        background: #2c5f4e;
    }
</style>

<script>
    // Update sidebar user information
    function updateSidebarUserInfo() {
        // const userName = sessionStorage.getItem('user_name') || localStorage.getItem('user_name');
        // const userRank = sessionStorage.getItem('user_rank') || localStorage.getItem('user_rank');
        const userUnit = sessionStorage.getItem('user_unit') || localStorage.getItem('user_unit');

        const sidebarUserName = document.getElementById('sidebarUserName');
        const sidebarUserRank = document.getElementById('sidebarUserRank');
        const sidebarUserUnit = document.getElementById('sidebarUserUnit');

        if (sidebarUserName && userName) {
            sidebarUserName.textContent = userName;
        }

        if (sidebarUserRank && userRank) {
            sidebarUserRank.textContent = userRank;
        }

        if (sidebarUserUnit && userUnit) {
            sidebarUserUnit.textContent = userUnit;
        } else if (sidebarUserUnit) {
            sidebarUserUnit.textContent = 'Nepal Army';
        }
    }

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
        updateSidebarUserInfo();
        setActiveSidebarLink();
    });
</script>