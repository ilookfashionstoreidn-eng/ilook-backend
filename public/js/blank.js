document.addEventListener("DOMContentLoaded", () => {
    // -------------------------------------------------------------------------
    // 1. Toast Notification System
    // -------------------------------------------------------------------------
    const createToastContainer = () => {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            Object.assign(container.style, {
                position: 'fixed',
                bottom: '32px',
                right: '32px',
                display: 'flex',
                flexDirection: 'column',
                gap: '12px',
                zIndex: '9999',
                pointerEvents: 'none'
            });
            document.body.appendChild(container);
        }
        return container;
    };

    const showToast = (message, type = 'info') => {
        const container = createToastContainer();
        const toast = document.createElement('div');
        
        // Setup icons and colors based on type using Phosphor icons
        const config = {
            info: { bg: '#fff', text: '#0f172a', icon: '<i class="ph-fill ph-info" style="color:#3b82f6; font-size:20px;"></i>', border: '#3b82f6' },
            success: { bg: '#fff', text: '#0f172a', icon: '<i class="ph-fill ph-check-circle" style="color:#10b981; font-size:20px;"></i>', border: '#10b981' },
            warning: { bg: '#fff', text: '#0f172a', icon: '<i class="ph-fill ph-warning-circle" style="color:#f59e0b; font-size:20px;"></i>', border: '#f59e0b' }
        }[type] || config.info;

        Object.assign(toast.style, {
            background: config.bg,
            color: config.text,
            padding: '14px 20px',
            borderRadius: '12px',
            boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)',
            display: 'flex',
            alignItems: 'center',
            gap: '12px',
            transform: 'translateX(120%) scale(0.9)',
            transition: 'all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
            opacity: '0',
            fontWeight: '600',
            fontSize: '14px',
            borderLeft: `4px solid ${config.border}`,
            pointerEvents: 'auto'
        });

        toast.innerHTML = `<div style="display:flex; align-items:center;">${config.icon}</div> <span>${message}</span>`;
        container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.style.transform = 'translateX(0) scale(1)';
                toast.style.opacity = '1';
            });
        });

        // Animate out and remove
        setTimeout(() => {
            toast.style.transform = 'translateX(120%) scale(0.9)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    };

    // -------------------------------------------------------------------------
    // 2. Ripple Effect for Buttons
    // -------------------------------------------------------------------------
    const createRipple = (event) => {
        const button = event.currentTarget;
        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.getBoundingClientRect().left - radius}px`;
        circle.style.top = `${event.clientY - button.getBoundingClientRect().top - radius}px`;
        circle.classList.add("ripple");

        const existingRipple = button.querySelector(".ripple");
        if (existingRipple) {
            existingRipple.remove();
        }

        button.appendChild(circle);
    };

    // Inject ripple styles dynamically
    const style = document.createElement('style');
    style.innerHTML = `
        .menu-item, .status, .top-action, .status-option {
            position: relative;
            overflow: hidden;
        }
        span.ripple {
            position: absolute;
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            background-color: rgba(79, 70, 229, 0.2);
            pointer-events: none;
        }
        .status-option span.ripple {
            background-color: rgba(0, 0, 0, 0.05);
        }
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        .status-menu {
            transition: opacity 0.2s ease, transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-origin: top left;
            opacity: 0;
            transform: scale(0.95) translateY(-5px);
            pointer-events: none;
            display: block !important;
        }
        .status-menu.open {
            opacity: 1;
            transform: scale(1) translateY(0);
            pointer-events: auto;
        }
        .blank-label span {
            transition: all 0.3s ease;
        }
    `;
    document.head.appendChild(style);

    // -------------------------------------------------------------------------
    // 3. UI Interactions
    // -------------------------------------------------------------------------
    const menuItems = document.querySelectorAll(".menu-item");
    const blankLabel = document.getElementById("blankLabel");
    const statusButton = document.getElementById("statusButton");
    const statusMenu = document.getElementById("statusMenu");
    const statusOptions = document.querySelectorAll(".status-option");
    const searchAction = document.getElementById("searchAction");
    const alertAction = document.getElementById("alertAction");

    const updateLabel = (text, iconClassStr) => {
        if (!blankLabel) return;
        
        const textSpan = blankLabel.querySelector('span');
        const iconElement = blankLabel.querySelector('i');
        
        if (textSpan) {
            textSpan.style.opacity = '0';
            textSpan.style.transform = 'translateY(5px)';
            
            if (iconElement) {
                iconElement.style.opacity = '0';
                iconElement.style.transform = 'scale(0.8)';
            }

            setTimeout(() => {
                textSpan.textContent = text;
                
                if (iconElement && iconClassStr) {
                    iconElement.className = iconClassStr + " blank-icon";
                }

                textSpan.style.opacity = '1';
                textSpan.style.transform = 'translateY(0)';
                
                if (iconElement) {
                    iconElement.style.opacity = '1';
                    iconElement.style.transform = 'scale(1)';
                }
            }, 300);
        }
    };

    menuItems.forEach((item) => {
        item.addEventListener("click", function (e) {
            if (this.classList.contains('danger')) return; // Ignore logout
            e.preventDefault();
            createRipple(e);
            
            menuItems.forEach(node => node.classList.remove("active"));
            this.classList.add("active");

            const labelText = this.querySelector('span') ? this.querySelector('span').textContent.trim() : "View";
            const iconElement = this.querySelector('i');
            const iconClass = iconElement ? iconElement.className : 'ph-duotone ph-rocket-launch';
            
            // Transform the icon to be duotone for the big label
            const duotoneClass = iconClass.replace('ph ', 'ph-duotone ').replace('-fill', '');

            updateLabel(`${labelText} Overview`, duotoneClass);
            showToast(`Navigated to ${labelText}`, 'info');
        });
    });

    if (statusButton && statusMenu) {
        statusButton.addEventListener("click", function (event) {
            event.stopPropagation();
            createRipple(event);
            const isOpen = statusMenu.classList.contains("open");
            
            // Toggle chevron rotation
            const chevron = this.querySelector('.ph-caret-down');
            if (chevron) {
                chevron.style.transition = 'transform 0.3s ease';
                chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
            }
            
            if (isOpen) {
                statusMenu.classList.remove("open");
            } else {
                statusMenu.classList.add("open");
            }
        });
    }

    statusOptions.forEach((option) => {
        option.addEventListener("click", function (e) {
            createRipple(e);
            const nextStatus = this.getAttribute("data-status") || "Status";
            const nextColor = this.getAttribute("data-color") || "#3b82f6";
            
            if (statusButton) {
                // Add tiny bounce to button
                statusButton.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    statusButton.style.transform = 'scale(1)';
                    statusButton.innerHTML = `
                        <span class="status-dot" style="background:${nextColor}"></span>
                        ${nextStatus}
                        <i class="ph ph-caret-down" style="font-size: 14px; color: var(--text-muted)"></i>
                    `;
                }, 150);
            }
            
            const toastType = nextStatus === 'Delivered' ? 'success' : 
                              nextStatus === 'In Transit' ? 'info' : 'warning';
                              
            showToast(`Shipment status updated to ${nextStatus}`, toastType);
            
            if (statusMenu) {
                statusMenu.classList.remove("open");
                const chevron = statusButton?.querySelector('.ph-caret-down');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
        });
    });

    if (searchAction) {
        searchAction.addEventListener("click", function (e) {
            createRipple(e);
            showToast("Search modal feature is coming soon", 'info');
        });
    }

    if (alertAction) {
        alertAction.addEventListener("click", function (e) {
            createRipple(e);
            showToast("You have 3 new notifications", 'warning');
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener("click", function (event) {
        if (!statusMenu || !statusButton) return;
        if (!statusMenu.contains(event.target) && !statusButton.contains(event.target)) {
            statusMenu.classList.remove("open");
            const chevron = statusButton.querySelector('.ph-caret-down');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    });
});
