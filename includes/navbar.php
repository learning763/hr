<nav style="background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid #eef2f6;">
    <div style="display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 700; color: #1e3a32;">
        <i class="fas fa-shield-alt" style="font-size: 24px; color: #2c5f4e;"></i>
        <span>Cyber HRMS</span>
    </div>
    <div style="display: flex; align-items: center; gap: 24px;">
        <div id="logoutBtnNav" style="display: flex; align-items: center; gap: 8px; color: #5b6e8c; cursor: pointer; font-size: 14px; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='#c2410c'" onmouseout="this.style.color='#5b6e8c'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </div>
        <div id="userAvatarNav" style="width: 38px; height: 38px; background: #eef2f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#eef2f6'">
            <i class="fas fa-user-circle" style="font-size: 18px; color: #5b6e8c;"></i>
        </div>
    </div>
</nav>

<script>
    // Update user avatar with user initial
    function updateUserAvatar() {
        const userName = sessionStorage.getItem('user_name') || localStorage.getItem('user_name');
        const userAvatar = document.getElementById('userAvatarNav');
        
        if (userName && userAvatar) {
            const initial = userName.charAt(0).toUpperCase();
            userAvatar.innerHTML = `<span style="font-size: 16px; font-weight: 600; color: #1e3a32;">${initial}</span>`;
        }
    }
    
    // Logout functionality
    document.getElementById('logoutBtnNav')?.addEventListener('click', async function() {
        try {
            // Show loading state
            const logoutBtn = document.getElementById('logoutBtnNav');
            const originalContent = logoutBtn.innerHTML;
            logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Logging out...</span>';
            logoutBtn.style.pointerEvents = 'none';
            
            // Call logout.php
            const response = await fetch('logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const result = await response.json();
            
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
                // Restore button
                logoutBtn.innerHTML = originalContent;
                logoutBtn.style.pointerEvents = 'auto';
                
                if (typeof showToast === 'function') {
                    showToast(result.message || 'Logout failed', 'error');
                }
            }
        } catch (error) {
            console.error('Logout error:', error);
            
            // Restore button
            const logoutBtn = document.getElementById('logoutBtnNav');
            if (logoutBtn) {
                logoutBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i><span>Logout</span>';
                logoutBtn.style.pointerEvents = 'auto';
            }
            
            if (typeof showToast === 'function') {
                showToast('Error logging out. Please try again.', 'error');
            }
        }
    });
    
    // User avatar click - show user info dropdown
    document.getElementById('userAvatarNav')?.addEventListener('click', function() {
        const userName = sessionStorage.getItem('user_name') || localStorage.getItem('user_name') || 'User';
        const userEmail = sessionStorage.getItem('user_email') || localStorage.getItem('user_email') || '';
        const userRank = sessionStorage.getItem('user_rank') || localStorage.getItem('user_rank') || '';
        const userUnit = sessionStorage.getItem('user_unit') || localStorage.getItem('user_unit') || '';
        
        // Create a simple alert or use toast
        const message = `${userName}\n${userRank}\n${userUnit}\n${userEmail}`;
        
        if (typeof showToast === 'function') {
            showToast(`👤 ${userName}\n📧 ${userEmail}`, 'success');
        } else {
            alert(`User Information:\nName: ${userName}\nRank: ${userRank}\nUnit: ${userUnit}\nEmail: ${userEmail}`);
        }
    });
    
    // Update avatar on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateUserAvatar();
    });
</script>