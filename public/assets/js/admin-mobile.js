/**
 * Admin Dashboard - Mobile Menu Functionality
 * Handles sidebar toggle for mobile devices
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        
        // Get elements
        const sidebar = document.querySelector('.sidebar');
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (!sidebar || !mobileToggle) {
            console.warn('Admin mobile menu: Required elements not found');
            return;
        }
        
        // Toggle sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            if (overlay) {
                overlay.classList.toggle('active');
            }
            
            // Update button icon
            const icon = mobileToggle.querySelector('i');
            if (icon) {
                if (sidebar.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                } else {
                    icon.className = 'fas fa-bars';
                }
            }
        }
        
        // Close sidebar
        function closeSidebar() {
            sidebar.classList.remove('active');
            if (overlay) {
                overlay.classList.remove('active');
            }
            
            // Reset button icon
            const icon = mobileToggle.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-bars';
            }
        }
        
        // Toggle button click
        mobileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
        
        // Overlay click - close sidebar
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }
        
        // Close sidebar when clicking a menu item (on mobile)
        const menuLinks = sidebar.querySelectorAll('.sidebar-menu a');
        menuLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                // Only close on mobile (screen width < 1024px)
                if (window.innerWidth < 1024) {
                    closeSidebar();
                }
            });
        });
        
        // Close sidebar on window resize to desktop
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            }, 250);
        });
        
        // Prevent body scroll when sidebar is open on mobile
        const body = document.body;
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    if (sidebar.classList.contains('active') && window.innerWidth < 1024) {
                        body.style.overflow = 'hidden';
                    } else {
                        body.style.overflow = '';
                    }
                }
            });
        });
        
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            // ESC key closes sidebar
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
        
        console.log('Admin mobile menu initialized');
    });
    
})();


/**
 * Enhanced Table Scroll Detection for Mobile
 * Hides scroll hint after user interacts with table
 */
document.addEventListener('DOMContentLoaded', function() {
    const tableContainers = document.querySelectorAll('.table-responsive');
    
    tableContainers.forEach(function(container) {
        let scrolled = false;
        
        container.addEventListener('scroll', function() {
            if (!scrolled && this.scrollLeft > 10) {
                scrolled = true;
                this.classList.add('scrolled');
            }
        });
        
        // Also hide hint on touch
        container.addEventListener('touchstart', function() {
            this.classList.add('scrolled');
        });
    });
});


/* =====================================================
   DARK MODE
   ===================================================== */
(function() {
    'use strict';

    function applyDarkMode(enabled) {
        document.body.classList.toggle('dark-mode', enabled);
        const btn = document.getElementById('darkModeToggle');
        if (btn) {
            btn.innerHTML = enabled
                ? '<i class="fas fa-sun"></i> Light Mode'
                : '<i class="fas fa-moon"></i> Dark Mode';
        }
    }

    // Apply saved preference immediately (before DOMContentLoaded to avoid flash)
    const saved = localStorage.getItem('darkMode') === 'true';
    if (saved) document.documentElement.classList.add('dark-mode-pending');

    document.addEventListener('DOMContentLoaded', function() {
        // Remove pending class, apply properly
        document.documentElement.classList.remove('dark-mode-pending');
        applyDarkMode(localStorage.getItem('darkMode') === 'true');

        const btn = document.getElementById('darkModeToggle');
        if (btn) {
            btn.addEventListener('click', function() {
                const isDark = document.body.classList.contains('dark-mode');
                localStorage.setItem('darkMode', !isDark);
                applyDarkMode(!isDark);
            });
        }
    });
})();

/* =====================================================
   TOAST NOTIFICATIONS
   ===================================================== */

/**
 * Show a toast notification
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {number} duration ms (default 3000)
 */
function showToast(message, type, duration) {
    type = type || 'success';
    duration = duration || 3000;

    var icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };

    var container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'toast-notification toast-' + type;
    toast.innerHTML =
        '<i class="fas ' + (icons[type] || icons.info) + ' toast-icon"></i>' +
        '<span class="toast-msg">' + message + '</span>' +
        '<button class="toast-close" aria-label="Close">&times;</button>' +
        '<div class="toast-progress"></div>';

    container.appendChild(toast);

    toast.querySelector('.toast-close').addEventListener('click', function() {
        removeToast(toast);
    });

    var timer = setTimeout(function() { removeToast(toast); }, duration);

    toast.addEventListener('mouseenter', function() { clearTimeout(timer); });
    toast.addEventListener('mouseleave', function() {
        timer = setTimeout(function() { removeToast(toast); }, 1000);
    });
}

function removeToast(toast) {
    toast.classList.add('toast-hide');
    setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
}

/* =====================================================
   LOADING SPINNER HELPERS
   ===================================================== */

function showPageLoader(message) {
    var loader = document.getElementById('page-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'page-loader';
        loader.className = 'page-loader';
        loader.innerHTML = '<div class="spinner-ring"></div><span>' + (message || 'Loading...') + '</span>';
        document.body.appendChild(loader);
    } else {
        loader.classList.remove('hidden');
    }
}

function hidePageLoader() {
    var loader = document.getElementById('page-loader');
    if (loader) loader.classList.add('hidden');
}

function setButtonLoading(btn, loading) {
    if (loading) {
        btn.classList.add('btn-loading');
        btn.dataset.originalText = btn.innerHTML;
        btn.disabled = true;
    } else {
        btn.classList.remove('btn-loading');
        if (btn.dataset.originalText) btn.innerHTML = btn.dataset.originalText;
        btn.disabled = false;
    }
}

/* =====================================================
   AUTO-SHOW TOASTS FROM PHP SESSION FLASH MESSAGES
   ===================================================== */
document.addEventListener('DOMContentLoaded', function() {
    // Look for hidden flash message elements injected by PHP
    var flash = document.getElementById('flash-toast');
    if (flash) {
        var msg  = flash.dataset.message;
        var type = flash.dataset.type || 'success';
        if (msg) showToast(msg, type);
    }
});
