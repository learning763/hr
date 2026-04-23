// helper/common.js

// Set active sidebar link based on data-page attribute
function setActiveSidebarLink() {
    const currentPage = document.body.dataset.page;
    if (currentPage) {
        const activeLink = document.querySelector(`.sidebar-link[data-page="${currentPage}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }
}

// Setup logout functionality
function setupLogout() {
    const logoutBtn = document.getElementById('logoutBtnNav');
    if (logoutBtn) {
        // Remove existing listeners to prevent duplicates
        const newLogoutBtn = logoutBtn.cloneNode(true);
        logoutBtn.parentNode.replaceChild(newLogoutBtn, logoutBtn);
        
        newLogoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Clear all session data
            sessionStorage.clear();
            localStorage.removeItem('hrms_email');
            localStorage.removeItem('hrms_logged_in');
            localStorage.removeItem('rememberMe');
            
            // Show toast message
            if (typeof showToast === 'function') {
                showToast('Logged out successfully', 'success');
            } else {
                console.log('Logged out successfully');
            }
            
            // Redirect to login page after delay
            setTimeout(() => {
                // Try to find the correct login page path
                const currentPath = window.location.pathname;
                
                // If we're in a subdirectory, go back to root
                if (currentPath.includes('/pages/') || currentPath.includes('/admin/')) {
                    window.location.href = '../index.html';
                } 
                // If current file is in root directory
                else {
                    window.location.href = 'index.html';
                }
            }, 1000);
        });
    }
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    setActiveSidebarLink();
    setupLogout();
});