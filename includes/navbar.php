<nav style="background: linear-gradient(135deg, #1a3c34 0%, #0f2c26 100%); box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 0 32px; height: 70px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.1);">
    <!-- Logo Section -->
    <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
            <i class="fas fa-shield-alt" style="font-size: 22px; color: #ffd700; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"></i>
        </div>
        <div style="font-size: 22px; font-weight: 700; letter-spacing: -0.5px;">
            <span style="color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Cyber</span>
            <span style="color: #ffd700;">HRMS</span>
        </div>
        <div style="height: 30px; width: 1px; background: rgba(255,255,255,0.2); margin-left: 8px;"></div>
        <div style="font-size: 12px; color: rgba(255,255,255,0.7); letter-spacing: 0.5px;">
            <i class="fas fa-flag-checkered" style="margin-right: 6px; font-size: 10px;"></i>
            नेपाली सेना
        </div>
    </div>
    
    <!-- Right Section -->
    <div style="display: flex; align-items: center; gap: 20px;">
        <!-- Date/Time Display -->
        <div style="display: flex; align-items: center; gap: 12px; padding: 6px 14px; background: rgba(255,255,255,0.08); border-radius: 30px; backdrop-filter: blur(10px);">
            <i class="far fa-calendar-alt" style="font-size: 13px; color: #ffd700;"></i>
            <span id="currentDateTime" style="font-size: 13px; color: rgba(255,255,255,0.9); font-weight: 500;"></span>
        </div>
    
        <!-- Logout Button -->
        <div id="logoutBtnNav" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(255,255,255,0.1); border-radius: 30px; cursor: pointer; font-size: 13px; font-weight: 500; color: #ffffff; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.15);" 
             onmouseover="this.style.background='rgba(220, 38, 38, 0.8)'; this.style.borderColor='rgba(255,255,255,0.3)'; this.style.transform='translateY(-1px)'" 
             onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.15)'; this.style.transform='translateY(0)'">
            <i class="fas fa-sign-out-alt" style="font-size: 13px;"></i>
            <span>Logout</span>
        </div>
        
        <!-- User Avatar -->
        <div id="userAvatarNav" style="width: 42px; height: 42px; background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.2); border: 2px solid rgba(255,255,255,0.3);" 
             onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.3)'" 
             onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.2)'">
            <i class="fas fa-user-astronaut" style="font-size: 20px; color: #1a3c34;"></i>
        </div>
    </div>
</nav>

<!-- User Profile Dropdown Menu -->
<div id="userDropdownMenu" style="display: none; position: fixed; top: 75px; right: 32px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 1000; min-width: 280px; overflow: hidden; animation: slideDown 0.2s ease;">
    <div style="padding: 20px; background: linear-gradient(135deg, #1a3c34 0%, #0f2c26 100%); color: white;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 50px; height: 50px; background: #ffd700; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-user-shield" style="font-size: 24px; color: #1a3c34;"></i>
            </div>
            <div>
                <div id="dropdownUserName" style="font-weight: 700; font-size: 16px; margin-bottom: 4px;"></div>
                <div id="dropdownUserRank" style="font-size: 12px; opacity: 0.9;"></div>
            </div>
        </div>
    </div>
    <div style="padding: 12px 0;">
        <div style="padding: 10px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f0;">
            <i class="fas fa-envelope" style="width: 18px; color: #6c7a8e; font-size: 13px;"></i>
            <div>
                <div style="font-size: 11px; color: #9aa9bc; margin-bottom: 2px;">Email</div>
                <div id="dropdownUserEmail" style="font-size: 13px; color: #1a2c3e;"></div>
            </div>
        </div>
        <div style="padding: 10px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f0;">
            <i class="fas fa-building" style="width: 18px; color: #6c7a8e; font-size: 13px;"></i>
            <div>
                <div style="font-size: 11px; color: #9aa9bc; margin-bottom: 2px;">Unit</div>
                <div id="dropdownUserUnit" style="font-size: 13px; color: #1a2c3e;"></div>
            </div>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid #f0f0f0; margin-top: 8px;">
            <div id="dropdownLogoutBtn" style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #fef2f2; border-radius: 10px; cursor: pointer; transition: all 0.2s; color: #dc2626;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                <i class="fas fa-sign-out-alt" style="font-size: 14px;"></i>
                <span style="font-size: 13px; font-weight: 500;">Logout</span>
            </div>
        </div>
    </div>
</div>

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
    
    // Update user avatar with user initial
    function updateUserAvatar() {
        const userName = sessionStorage.getItem('user_name') || localStorage.getItem('user_name');
        const userAvatar = document.getElementById('userAvatarNav');
        
        if (userName && userAvatar) {
            const initial = userName.charAt(0).toUpperCase();
            userAvatar.innerHTML = `<span style="font-size: 18px; font-weight: 700; color: #1a3c34;">${initial}</span>`;
        }
    }
    
    // Show user dropdown
    const userAvatar = document.getElementById('userAvatarNav');
    const userDropdown = document.getElementById('userDropdownMenu');
    
    if (userAvatar) {
        userAvatar.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = userDropdown.style.display === 'block';
            
            // Update dropdown content
            const userName = sessionStorage.getItem('user_name') || localStorage.getItem('user_name') || 'Guest User';
            const userEmail = sessionStorage.getItem('user_email') || localStorage.getItem('user_email') || 'Not specified';
            const userRank = sessionStorage.getItem('user_rank') || localStorage.getItem('user_rank') || 'N/A';
            const userUnit = sessionStorage.getItem('user_unit') || localStorage.getItem('user_unit') || 'N/A';
            
            document.getElementById('dropdownUserName').textContent = userName;
            document.getElementById('dropdownUserEmail').textContent = userEmail;
            document.getElementById('dropdownUserRank').textContent = userRank;
            document.getElementById('dropdownUserUnit').textContent = userUnit;
            
            userDropdown.style.display = isVisible ? 'none' : 'block';
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (userDropdown && !userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.style.display = 'none';
        }
    });
    
    // Logout functionality
    function performLogout() {
        // Show loading state
        const logoutBtn = document.getElementById('logoutBtnNav');
        const dropdownLogoutBtn = document.getElementById('dropdownLogoutBtn');
        const originalContent = logoutBtn ? logoutBtn.innerHTML : '';
        
        if (logoutBtn) {
            logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Logging out...</span>';
            logoutBtn.style.pointerEvents = 'none';
        }
        
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
                // Restore button
                if (logoutBtn) {
                    logoutBtn.innerHTML = originalContent;
                    logoutBtn.style.pointerEvents = 'auto';
                }
                
                if (typeof showToast === 'function') {
                    showToast(result.message || 'Logout failed', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            
            // Restore button
            if (logoutBtn) {
                logoutBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i><span>Logout</span>';
                logoutBtn.style.pointerEvents = 'auto';
            }
            
            if (typeof showToast === 'function') {
                showToast('Error logging out. Please try again.', 'error');
            }
        });
    }
    
    // Attach logout event listeners
    document.getElementById('logoutBtnNav')?.addEventListener('click', performLogout);
    document.getElementById('dropdownLogoutBtn')?.addEventListener('click', function() {
        userDropdown.style.display = 'none';
        performLogout();
    });
    
    // Update avatar on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateUserAvatar();
    });
</script>