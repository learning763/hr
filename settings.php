<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Settings";
$pageSubtitle = "Manage your preferences and account.";
$activePage = "settings";

// Get current user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Prepare the content
ob_start();
?>

<style>
    .settings-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .settings-section {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .settings-section h3 {
        margin: 0 0 20px 0;
        color: #1a2c3e;
        font-size: 18px;
        font-weight: 600;
        padding-bottom: 12px;
        border-bottom: 2px solid #eef2f6;
    }
    
    .settings-section h3 i {
        margin-right: 10px;
        color: #2c5f4e;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eef2f6;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-weight: 600;
        color: #334155;
        font-size: 14px;
    }
    
    .info-value {
        color: #6c7a8e;
        font-size: 14px;
    }
    
    .btn-change {
        background: #2c5f4e;
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }
    
    .btn-change:hover {
        background: #1e3a32;
        transform: translateY(-1px);
    }
    
    /* Password Modal Styles */
    .password-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s;
    }
    
    .password-modal-content {
        background-color: #fff;
        margin: 15% auto;
        width: 90%;
        max-width: 450px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideDown 0.3s;
    }
    
    .password-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .password-modal-header h3 {
        margin: 0;
        color: #1a2c3e;
        font-size: 18px;
    }
    
    .password-modal-header h3 i {
        margin-right: 10px;
        color: #2c5f4e;
    }
    
    .password-modal-body {
        padding: 24px;
    }
    
    .password-field {
        margin-bottom: 20px;
    }
    
    .password-field label {
        display: block;
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
        font-size: 14px;
    }
    
    .password-field input {
        width: 100%;
        padding: 10px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
    }
    
    .password-field input:focus {
        border-color: #2c5f4e;
        box-shadow: 0 0 0 3px rgba(44, 95, 78, 0.08);
    }
    
    .password-strength {
        margin-top: 8px;
        font-size: 12px;
    }
    
    .strength-weak { color: #c2410c; }
    .strength-medium { color: #f59e0b; }
    .strength-strong { color: #10b981; }
    
    .modal-buttons {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 24px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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
    
    .close-modal {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #9aa9bc;
        transition: 0.2s;
    }
    
    .close-modal:hover {
        color: #c2410c;
    }
    
    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1100;
        animation: slideInRight 0.3s;
    }
    
    .toast-content {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .toast-content i {
        font-size: 20px;
    }
    
    .toast.success .toast-content i {
        color: #10b981;
    }
    
    .toast.error .toast-content i {
        color: #c2410c;
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
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
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
</style>

<div class="settings-container">
    <!-- Change Password Section -->
    <div class="settings-section">
        <h3><i class="fas fa-key"></i> Security Settings</h3>
        <div class="info-row">
            <div class="info-label">Change Password</div>
            <div class="info-value">
                <button class="btn-change" id="changePasswordBtn">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Last Password Change</div>
            <div class="info-value" id="lastPasswordChange">
                <?php
                // Get last password change date if you have that column
                try {
                    $stmt = $pdo->prepare("SELECT password_updated_at FROM personnel WHERE personnel_number = ?");
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch();
                    if ($result && $result['password_updated_at']) {
                        echo date('Y-m-d H:i', strtotime($result['password_updated_at']));
                    } else {
                        echo 'Not recorded';
                    }
                } catch(PDOException $e) {
                    echo 'Not available';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Account Information -->
    <div class="settings-section">
        <h3><i class="fas fa-user-circle"></i> Account Information</h3>
        <div class="info-row">
            <div class="info-label">Personnel Number</div>
            <div class="info-value"><?php echo htmlspecialchars($user_id); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Full Name</div>
            <div class="info-value"><?php echo htmlspecialchars($user_name); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email Address</div>
            <div class="info-value"><?php echo htmlspecialchars($user_email); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Role</div>
            <div class="info-value">
                <?php
                $role = $_SESSION['user_role'] ?? 0;
                switch($role) {
                    case 2:
                        echo '<span class="role-badge" style="background:#8b5cf6; color:white; padding:4px 10px; border-radius:20px; font-size:12px;">Super Admin</span>';
                        break;
                    case 1:
                        echo '<span class="role-badge" style="background:#3b82f6; color:white; padding:4px 10px; border-radius:20px; font-size:12px;">Admin</span>';
                        break;
                    default:
                        echo '<span class="role-badge" style="background:#6b7280; color:white; padding:4px 10px; border-radius:20px; font-size:12px;">User</span>';
                        break;
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Preferences Section -->
    <div class="settings-section">
        <h3><i class="fas fa-sliders-h"></i> Preferences</h3>
        <div class="info-row">
            <div class="info-label">Language</div>
            <div class="info-value">English (Default)</div>
        </div>
        <div class="info-row">
            <div class="info-label">Theme</div>
            <div class="info-value">Light Mode</div>
        </div>
        <div class="info-row">
            <div class="info-label">Notifications</div>
            <div class="info-value">Email alerts enabled</div>
        </div>
        <div class="info-row">
            <div class="info-label">Session Timeout</div>
            <div class="info-value">30 minutes</div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="passwordModal" class="password-modal">
    <div class="password-modal-content">
        <div class="password-modal-header">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="password-modal-body">
            <div id="passwordAlert"></div>
            <div class="password-field">
                <label>Current Password</label>
                <input type="password" id="currentPassword" placeholder="Enter your current password">
            </div>
            <div class="password-field">
                <label>New Password</label>
                <input type="password" id="newPassword" placeholder="Enter new password">
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            <div class="password-field">
                <label>Confirm New Password</label>
                <input type="password" id="confirmPassword" placeholder="Confirm new password">
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" id="cancelPasswordBtn" style="padding: 8px 20px; background: #f1f3f5; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="button" class="btn-submit" id="updatePasswordBtn" style="padding: 8px 20px; background: #1e3a32; color: white; border: none; border-radius: 6px; cursor: pointer;">Update Password</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none;">
    <div class="toast-content">
        <i class="fas fa-check-circle" id="toastIcon"></i>
        <span id="toastMessage"></span>
    </div>
</div>

<script>
// Modal elements
const passwordModal = document.getElementById('passwordModal');
const changePasswordBtn = document.getElementById('changePasswordBtn');
const closeModalBtn = document.querySelector('.close-modal');
const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
const updatePasswordBtn = document.getElementById('updatePasswordBtn');
const currentPassword = document.getElementById('currentPassword');
const newPassword = document.getElementById('newPassword');
const confirmPassword = document.getElementById('confirmPassword');
const passwordStrength = document.getElementById('passwordStrength');
const passwordAlert = document.getElementById('passwordAlert');

// Toast function
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMessage = document.getElementById('toastMessage');
    
    toast.className = `toast ${type}`;
    toastIcon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    toastMessage.textContent = message;
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

// Show alert in modal
function showAlert(message, type = 'error') {
    passwordAlert.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    setTimeout(() => {
        passwordAlert.innerHTML = '';
    }, 3000);
}

// Password strength checker
newPassword.addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    let message = '';
    let className = '';
    
    if (password.length === 0) {
        passwordStrength.innerHTML = '';
        return;
    }
    
    // Length check
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Contains number
    if (/\d/.test(password)) strength++;
    
    // Contains lowercase and uppercase
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    
    // Contains special character
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    if (strength <= 2) {
        message = 'Weak password';
        className = 'strength-weak';
    } else if (strength <= 4) {
        message = 'Medium password';
        className = 'strength-medium';
    } else {
        message = 'Strong password';
        className = 'strength-strong';
    }
    
    passwordStrength.innerHTML = `<span class="${className}">${message}</span>`;
});

// Open modal
changePasswordBtn.onclick = function() {
    passwordModal.style.display = 'block';
    currentPassword.value = '';
    newPassword.value = '';
    confirmPassword.value = '';
    passwordStrength.innerHTML = '';
    passwordAlert.innerHTML = '';
}

// Close modal
function closeModal() {
    passwordModal.style.display = 'none';
}

if (closeModalBtn) {
    closeModalBtn.onclick = closeModal;
}
if (cancelPasswordBtn) {
    cancelPasswordBtn.onclick = closeModal;
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == passwordModal) {
        closeModal();
    }
}

// Update password
updatePasswordBtn.onclick = function() {
    const current = currentPassword.value.trim();
    const newPass = newPassword.value.trim();
    const confirm = confirmPassword.value.trim();
    
    // Validate
    if (!current) {
        showAlert('Please enter your current password');
        return;
    }
    
    if (!newPass) {
        showAlert('Please enter a new password');
        return;
    }
    
    if (newPass.length < 4) {
        showAlert('Password must be at least 4 characters');
        return;
    }
    
    if (newPass !== confirm) {
        showAlert('New passwords do not match');
        return;
    }
    
    if (current === newPass) {
        showAlert('New password cannot be the same as current password');
        return;
    }
    
    // Send AJAX request
    fetch('change_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `current_password=${encodeURIComponent(current)}&new_password=${encodeURIComponent(newPass)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Password changed successfully!', 'success');
            closeModal();
            // Update last password change display if needed
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Error changing password', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error changing password', 'error');
    });
}

// Enter key support
const inputs = [currentPassword, newPassword, confirmPassword];
inputs.forEach(input => {
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            updatePasswordBtn.click();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>