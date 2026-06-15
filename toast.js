class Toast {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
            this.injectStyles();
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    injectStyles() {
        if (document.getElementById('toast-styles')) return;

        const style = document.createElement('style');
        style.id = 'toast-styles';
        style.textContent = `
            .toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 12px;
                pointer-events: none;
            }

            .toast {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 16px;
                padding: 16px 20px;
                min-width: 320px;
                max-width: 420px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12),
                            0 0 1px rgba(0, 0, 0, 0.1);
                display: flex;
                align-items: center;
                gap: 12px;
                pointer-events: all;
                transform: translateX(450px);
                opacity: 0;
                transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
                border: 1px solid rgba(0, 0, 0, 0.08);
                animation: slideInRight 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            }

            .toast.show {
                transform: translateX(0);
                opacity: 1;
            }

            .toast.hide {
                animation: slideOutRight 0.4s cubic-bezier(0.55, 0.085, 0.68, 0.53) forwards;
            }

            @keyframes slideInRight {
                from {
                    transform: translateX(450px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(450px);
                    opacity: 0;
                }
            }

            .toast-icon {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                font-size: 14px;
                animation: iconPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            }

            @keyframes iconPop {
                0% {
                    transform: scale(0);
                    opacity: 0;
                }
                50% {
                    transform: scale(1.2);
                }
                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }

            .toast.success .toast-icon {
                background: #34C759;
                color: white;
            }

            .toast.error .toast-icon {
                background: #FF3B30;
                color: white;
            }

            .toast.warning .toast-icon {
                background: #FF9500;
                color: white;
            }

            .toast.info .toast-icon {
                background: #007AFF;
                color: white;
            }

            .toast-content {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .toast-title {
                font-size: 15px;
                font-weight: 600;
                color: #1d1d1f;
                line-height: 1.3;
            }

            .toast-message {
                font-size: 14px;
                color: #86868b;
                line-height: 1.4;
            }

            .toast-close {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                border: none;
                background: rgba(0, 0, 0, 0.05);
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
                color: #86868b;
                font-size: 18px;
                line-height: 1;
            }

            .toast-close:hover {
                background: rgba(0, 0, 0, 0.1);
                color: #1d1d1f;
                transform: rotate(90deg);
            }

            .toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: currentColor;
                border-radius: 0 0 16px 16px;
                transition: width linear;
            }

            .toast.success .toast-progress {
                color: #34C759;
            }

            .toast.error .toast-progress {
                color: #FF3B30;
            }

            .toast.warning .toast-progress {
                color: #FF9500;
            }

            .toast.info .toast-progress {
                color: #007AFF;
            }

            /* Toast Actions (Botões) */
            .toast-actions {
                display: flex;
                gap: 8px;
                margin-top: 12px;
                width: 100%;
                animation: fadeInUp 0.4s ease 0.2s backwards;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .toast-btn {
                flex: 1;
                padding: 8px 16px;
                border: none;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            .toast-btn-cancel {
                background: rgba(0, 0, 0, 0.06);
                color: #1d1d1f;
            }

            .toast-btn-cancel:hover {
                background: rgba(0, 0, 0, 0.1);
                transform: translateY(-2px);
            }

            .toast-btn-cancel:active {
                transform: translateY(0);
            }

            .toast-btn-confirm {
                background: #007AFF;
                color: white;
            }

            .toast-btn-confirm:hover {
                background: #0051D5;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
            }

            .toast-btn-confirm:active {
                transform: translateY(0);
            }

            .toast.warning .toast-btn-confirm {
                background: #FF9500;
            }

            .toast.warning .toast-btn-confirm:hover {
                background: #E68600;
                box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
            }

            .toast.error .toast-btn-confirm {
                background: #FF3B30;
            }

            .toast.error .toast-btn-confirm:hover {
                background: #E6251A;
                box-shadow: 0 4px 12px rgba(255, 59, 48, 0.3);
            }

            /* Toast com botões não deve ter auto-close */
            .toast.has-actions .toast-close {
                display: none;
            }

            .toast.has-actions {
                min-width: 360px;
            }

            @media (max-width: 640px) {
                .toast-container {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                }

                .toast {
                    min-width: unset;
                    max-width: unset;
                    width: 100%;
                    /* Mantém a mesma animação do desktop */
                    transform: translateX(450px);
                }

                .toast.has-actions {
                    min-width: unset;
                }

                /* Mesmas animações do desktop para mobile */
                @keyframes slideInRight {
                    from {
                        transform: translateX(calc(100vw + 50px));
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }

                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(calc(100vw + 50px));
                        opacity: 0;
                    }
                }

                .toast-btn {
                    padding: 10px 16px;
                    font-size: 14px;
                }

                /* Remove hover effects no mobile */
                .toast-btn-cancel:hover {
                    transform: none;
                }

                .toast-btn-confirm:hover {
                    transform: none;
                    box-shadow: none;
                }
            }

            @keyframes progress {
                from {
                    width: 100%;
                }
                to {
                    width: 0%;
                }
            }
        `;
        document.head.appendChild(style);
    }

    show(options = {}) {
        const {
            type = 'info',
            title = '',
            message = '',
            duration = 4000,
            showProgress = true,
            closable = true,
            confirmButton = false,
            cancelButton = false,
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            onConfirm = null,
            onCancel = null
        } = options;

        const hasActions = confirmButton || cancelButton;
        const toast = document.createElement('div');
        toast.className = `toast ${type}${hasActions ? ' has-actions' : ''}`;

        const icons = {
            success: '✓',
            error: '✕',
            warning: '!',
            info: 'i'
        };

        let actionsHTML = '';
        if (hasActions) {
            actionsHTML = '<div class="toast-actions">';
            if (cancelButton) {
                actionsHTML += `<button class="toast-btn toast-btn-cancel">${cancelText}</button>`;
            }
            if (confirmButton) {
                actionsHTML += `<button class="toast-btn toast-btn-confirm">${confirmText}</button>`;
            }
            actionsHTML += '</div>';
        }

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${title}</div>` : ''}
                ${message ? `<div class="toast-message">${message}</div>` : ''}
                ${actionsHTML}
            </div>
            ${!hasActions && closable ? '<button class="toast-close" aria-label="Fechar">×</button>' : ''}
            ${!hasActions && showProgress ? '<div class="toast-progress"></div>' : ''}
        `;

        this.container.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);

        // Progress bar (só se não tiver botões)
        if (!hasActions && showProgress && duration > 0) {
            const progress = toast.querySelector('.toast-progress');
            if (progress) {
                progress.style.width = '100%';
                progress.style.transition = `width ${duration}ms linear`;
                setTimeout(() => {
                    progress.style.width = '0%';
                }, 10);
            }
        }

        // Auto-close (só se não tiver botões)
        let timeoutId;
        if (!hasActions && duration > 0) {
            timeoutId = setTimeout(() => {
                this.remove(toast);
            }, duration);
        }

        // Close button
        if (!hasActions && closable) {
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn?.addEventListener('click', () => {
                if (timeoutId) clearTimeout(timeoutId);
                this.remove(toast);
            });
        }

        // Action buttons
        if (hasActions) {
            if (cancelButton) {
                const cancelBtn = toast.querySelector('.toast-btn-cancel');
                cancelBtn?.addEventListener('click', () => {
                    this.remove(toast);
                    if (onCancel) onCancel();
                });
            }

            if (confirmButton) {
                const confirmBtn = toast.querySelector('.toast-btn-confirm');
                confirmBtn?.addEventListener('click', () => {
                    this.remove(toast);
                    if (onConfirm) onConfirm();
                });
            }
        }

        return toast;
    }

    remove(toast) {
        toast.classList.remove('show');
        toast.classList.add('hide');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }

    success(title, message, duration) {
        return this.show({ type: 'success', title, message, duration });
    }

    error(title, message, duration) {
        return this.show({ type: 'error', title, message, duration });
    }

    warning(title, message, duration) {
        return this.show({ type: 'warning', title, message, duration });
    }

    info(title, message, duration) {
        return this.show({ type: 'info', title, message, duration });
    }

    confirm(options = {}) {
        const {
            type = 'warning',
            title = 'Confirmar ação?',
            message = '',
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            onConfirm = null,
            onCancel = null
        } = options;

        return this.show({
            type,
            title,
            message,
            confirmButton: true,
            cancelButton: true,
            confirmText,
            cancelText,
            onConfirm,
            onCancel,
            duration: 0,
            showProgress: false,
            closable: false
        });
    }

    clear() {
        const toasts = this.container.querySelectorAll('.toast');
        toasts.forEach(toast => this.remove(toast));
    }
}

const toast = new Toast();

if (typeof window !== 'undefined') {
    window.toast = toast;
}