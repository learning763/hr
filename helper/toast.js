// Alternative: Light toasts with colored text (more traditional)
const toastStyles = `
    .toast-notification {
        border-radius: 12px;
        padding: 14px 20px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideRight 0.3s ease;
        max-width: 380px;
        min-width: 280px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        border-left: 4px solid;
        background: white;
    }
    .toast-notification i {
        font-size: 20px;
    }
    .toast-notification .message {
        font-size: 14px;
        font-weight: 500;
        line-height: 1.4;
        flex: 1;
        color: #1a2c3e;
    }
    .toast-notification.success {
        border-left-color: #10b981;
    }
    .toast-notification.success i {
        color: #10b981;
    }
    .toast-notification.error {
        border-left-color: #dc2626;
    }
    .toast-notification.error i {
        color: #dc2626;
    }
    .toast-notification.info {
        border-left-color: #3b82f6;
    }
    .toast-notification.info i {
        color: #3b82f6;
    }
    .toast-notification.warning {
        border-left-color: #f59e0b;
    }
    .toast-notification.warning i {
        color: #f59e0b;
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