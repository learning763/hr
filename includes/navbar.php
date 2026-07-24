<?php
require_once 'includes/config.php';

$nav_user_id = $_SESSION['user_id'] ?? '';
$nav_user_name = $_SESSION['user_name'] ?? 'Guest User';
$nav_user_email = $_SESSION['user_email'] ?? '';
$nav_user_rank = '';
$nav_user_unit = '';
$nav_show_complete_profile = false;

if (!empty($nav_user_id)) {
    $stmt = $pdo->prepare("
        SELECT p.unit, r.rank_name, p.registered_via,
               p.dob, p.gender, p.blood_group, p.address, p.contact, p.phone, p.religion,
               p.military_status, p.recruitment_date, p.commission_date,
               p.father_name, p.mother_name, p.spouse_name, p.grandfather_name,
               p.children_names, p.family_notes, p.higher_education, p.military_trainings
        FROM personnel p
        LEFT JOIN def_rank r ON p.rank = r.rank_code
        WHERE p.personnel_number = ?
        LIMIT 1
    ");
    $stmt->execute([$nav_user_id]);
    $nav_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($nav_user) {
        $nav_user_rank = $nav_user['rank_name'] ?: '';
        $nav_user_unit = $nav_user['unit'] ?: '';

        // Every scalar field on the "Edit Profile" form (profile.php) — keep this in sync
        // with that form so the alert only clears once ALL of those fields are filled in.
        $nav_profile_required_fields = [
            'dob', 'gender', 'blood_group', 'address', 'contact', 'phone', 'religion',
            'military_status', 'recruitment_date', 'commission_date',
            'father_name', 'mother_name', 'spouse_name', 'grandfather_name',
            'children_names', 'family_notes', 'higher_education', 'military_trainings'
        ];
        $nav_profile_incomplete = false;
        foreach ($nav_profile_required_fields as $field) {
            if (empty($nav_user[$field]) || $nav_user[$field] === '0000-00-00') {
                $nav_profile_incomplete = true;
                break;
            }
        }

        $nav_show_complete_profile = ($nav_user['registered_via'] === 'signup') && $nav_profile_incomplete;
    }
}

// Tracked in the PHP session (not browser storage) so it's guaranteed to reset on every
// fresh login (login.php clears $_SESSION on success) instead of lingering across logins
// if the tab was never cleanly logged out of.
$nav_needs_profile_alert = $nav_show_complete_profile && empty($_SESSION['profile_alert_dismissed']);
?>
<nav
    style="background: linear-gradient(135deg, #10263f 0%, #081b2e 100%); box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 0 22px; height: 54px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.1);">
    <!-- Logo Section -->
    <div style="display: flex; align-items: center; gap: 10px;">
        <div
            style="width: 32px; height: 32px; background: rgba(255,255,255,0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
            <i class="fas fa-shield-alt"
                style="font-size: 17px; color: #22d3ee; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"></i>
        </div>
        <div style="font-size: 17px; font-weight: 700; letter-spacing: -0.5px;">
            <span style="color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Cyber</span>
            <span style="color: #22d3ee;">HRMS</span>
        </div>
        <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.3); margin-left: 6px;"></div>
        <div style="font-size: 13px; color: rgba(255,255,255,0.85); letter-spacing: 0.2px;">
            श्री साइबर सुरक्षा निर्देशनालय
        </div>
    </div>

    <!-- Right Section -->
    <div style="display: flex; align-items: center; gap: 14px;">
        <!-- Date/Time Display -->
        <div
            style="display: flex; align-items: center; gap: 8px; padding: 5px 12px; background: rgba(255,255,255,0.08); border-radius: 30px; backdrop-filter: blur(10px);">
            <i class="far fa-calendar-alt" style="font-size: 12px; color: #22d3ee;"></i>
            <span id="currentDateTime" style="font-size: 12px; color: rgba(255,255,255,0.9); font-weight: 500;"></span>
        </div>

        <!-- User Avatar -->
        <div id="userAvatarNav"
            style="width: 34px; height: 34px; background: linear-gradient(135deg, #22d3ee 0%, #67e8f9 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.2); border: 2px solid rgba(255,255,255,0.3);"
            onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.3)'"
            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.2)'">
            <span style="font-size: 15px; font-weight: 700; color: #10263f;"><?php echo htmlspecialchars(strtoupper(substr($nav_user_name, 0, 1))); ?></span>
        </div>
    </div>
</nav>

<!-- User Profile Dropdown Menu -->
<div id="userDropdownMenu"
    style="display: none; position: fixed; top: 60px; right: 22px; background: #ffffff; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 1000; min-width: 260px; overflow: hidden; animation: slideDown 0.2s ease;">
    <div style="padding: 16px; background: linear-gradient(135deg, #10263f 0%, #081b2e 100%); color: white;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div
                style="width: 42px; height: 42px; background: #22d3ee; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-user-shield" style="font-size: 20px; color: #10263f;"></i>
            </div>
            <div>
                <div id="dropdownUserName" style="font-weight: 700; font-size: 14px; margin-bottom: 3px;"><?php echo htmlspecialchars($nav_user_name); ?></div>
                <div id="dropdownUserRank" style="font-size: 11px; opacity: 0.9;"><?php echo htmlspecialchars($nav_user_rank ?: 'N/A'); ?></div>
            </div>
        </div>
    </div>
    <div style="padding: 8px 0;">
        <div
            style="padding: 9px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f0f0f0;">
            <i class="fas fa-envelope" style="width: 16px; color: #6c7a8e; font-size: 12px;"></i>
            <div>
                <div style="font-size: 10px; color: #9aa9bc; margin-bottom: 2px;">Email</div>
                <div id="dropdownUserEmail" style="font-size: 12px; color: #1a2c3e;"><?php echo htmlspecialchars($nav_user_email ?: 'Not specified'); ?></div>
            </div>
        </div>
        <div
            style="padding: 9px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f0f0f0;">
            <i class="fas fa-building" style="width: 16px; color: #6c7a8e; font-size: 12px;"></i>
            <div>
                <div style="font-size: 10px; color: #9aa9bc; margin-bottom: 2px;">Unit</div>
                <div id="dropdownUserUnit" style="font-size: 12px; color: #1a2c3e;"><?php echo htmlspecialchars($nav_user_unit ?: 'N/A'); ?></div>
            </div>
        </div>
        <div style="padding: 10px 16px; border-top: 1px solid #f0f0f0; margin-top: 6px;">
            <div id="dropdownLogoutBtn"
                style="display: flex; align-items: center; gap: 8px; padding: 7px 10px; background: #fef2f2; border-radius: 10px; cursor: pointer; transition: all 0.2s; color: #dc2626;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                <i class="fas fa-sign-out-alt" style="font-size: 13px;"></i>
                <span style="font-size: 12px; font-weight: 500;">Logout</span>
            </div>
        </div>
    </div>
</div>

<?php if ($nav_show_complete_profile): ?>
<!-- Complete Profile Alert Modal -->
<div id="completeProfileModal"
    style="display: <?php echo $nav_needs_profile_alert ? 'flex' : 'none'; ?>; position: fixed; inset: 0; background: rgba(16,38,63,0.6); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 420px; width: 90%; padding: 28px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div
            style="width: 56px; height: 56px; margin: 0 auto 16px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-user-edit" style="font-size: 24px; color: #b45309;"></i>
        </div>
        <h3 style="margin: 0 0 8px; font-size: 17px; color: #1a2c3e;">Complete Your Profile</h3>
        <p style="margin: 0 0 22px; font-size: 13px; color: #6c7a8e; line-height: 1.5;">
            Some of your personal details are still missing. Please complete your profile so your records are accurate.
        </p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="dismissCompleteProfileBtn"
                style="padding: 9px 18px; border: none; border-radius: 8px; background: #f1f5f9; color: #334155; font-size: 13px; font-weight: 600; cursor: pointer;">
                Later
            </button>
            <a href="profile.php?personnel_number=<?php echo urlencode($nav_user_id); ?>&edit=1"
                style="padding: 9px 18px; border-radius: 8px; background: #10263f; color: #fff; font-size: 13px; font-weight: 600; text-decoration: none;">
                Complete Profile
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    // Update date and time
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        const dateTimeString = now.toLocaleDateString('en-US', options);
        const dateTimeElement = document.getElementById('currentDateTime');
        if (dateTimeElement) {
            dateTimeElement.textContent = dateTimeString;
        }
    }

    updateDateTime();
    setInterval(updateDateTime, 60000);

    // Complete-profile alert (signup users with missing profile fields).
    // Visibility and dismissal are both tracked server-side in the PHP session, so the
    // alert reliably reappears on every fresh login instead of depending on browser storage.
    document.getElementById('dismissCompleteProfileBtn')?.addEventListener('click', function () {
        const modal = document.getElementById('completeProfileModal');
        fetch('dismiss_profile_alert.php', { method: 'POST' })
            .finally(() => { if (modal) modal.style.display = 'none'; });
    });

    // Show user dropdown (name/rank/email/unit are rendered server-side in the HTML above)
    const userAvatar = document.getElementById('userAvatarNav');
    const userDropdown = document.getElementById('userDropdownMenu');

    if (userAvatar) {
        userAvatar.addEventListener('click', function (e) {
            e.stopPropagation();
            const isVisible = userDropdown.style.display === 'block';
            userDropdown.style.display = isVisible ? 'none' : 'block';
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (userDropdown && !userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.style.display = 'none';
        }
    });

    // Logout functionality
    function performLogout() {
        // Call logout.php
        fetch('logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Clear all storage
                    sessionStorage.clear();
                    localStorage.clear();

                    // Show success message
                    if (typeof showToast === 'function') {
                        showToast('Logged out successfully!', 'success');
                    }

                    // Redirect to login page
                    setTimeout(() => {
                        window.location.href = 'index.html';
                    }, 1000);
                } else {
                    if (typeof showToast === 'function') {
                        showToast(result.message || 'Logout failed', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Logout error:', error);

                if (typeof showToast === 'function') {
                    showToast('Error logging out. Please try again.', 'error');
                }
            });
    }

    // Attach logout event listener
    document.getElementById('dropdownLogoutBtn')?.addEventListener('click', function () {
        userDropdown.style.display = 'none';
        performLogout();
    });
</script>