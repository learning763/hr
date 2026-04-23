// toast.js - Reusable Toast Notification Component
// Usage: showToast("Your message", "success") or showToast("Error message", "error")

(function() {
    // Create toast container if it doesn't exist
    function createToastContainer() {
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 24px;
                right: 24px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 12px;
            `;
            document.body.appendChild(container);
        }
        return document.getElementById('toast-container');
    }

    // Toast styles
    const toastStyles = `
        .toast-notification {
            background: #1a1a1a;
            border-radius: 40px;
            padding: 12px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideRight 0.25s ease;
            max-width: 340px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .toast-notification i {
            font-size: 18px;
        }
        .toast-notification .message {
            color: white;
            font-size: 13px;
            font-weight: 500;
        }
        .toast-notification.success i {
            color: #ccc;
        }
        .toast-notification.error i {
            color: #ff6b6b;
        }
        .toast-notification.error {
            background: #2c2c2c;
        }
        @keyframes slideRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    `;

    // Add styles to document if not already added
    if (!document.getElementById('toast-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'toast-styles';
        styleSheet.textContent = toastStyles;
        document.head.appendChild(styleSheet);
    }

    // Main showToast function
    window.showToast = function(message, type = 'success') {
        const container = createToastContainer();
        const toast = document.createElement('div');
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `<i class="fas ${icon}"></i><span class="message">${message}</span>`;
        
        container.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.25s ease';
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 250);
        }, 3000);
    };
})();