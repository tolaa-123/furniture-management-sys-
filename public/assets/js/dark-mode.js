// Modern Dark Mode Implementation
class DarkModeManager {
    constructor() {
        this.storageKey = 'darkMode';
        this.darkModeToggle = null;
        this.init();
    }

    init() {
        this.createToggle();
        this.loadDarkMode();
        this.setupEventListeners();
    }

    createToggle() {
        // Check for existing dark mode toggle in sidebar
        this.darkModeToggle = document.querySelector('#darkModeToggle');
        
        // If no existing toggle, create one (for non-dashboard pages)
        if (!this.darkModeToggle) {
            this.darkModeToggle = document.createElement('button');
            this.darkModeToggle.className = 'dark-mode-toggle';
            this.darkModeToggle.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
            this.darkModeToggle.setAttribute('aria-label', 'Toggle dark mode');
            
            // Add to body
            document.body.appendChild(this.darkModeToggle);
        }
    }

    setupEventListeners() {
        // Toggle button click
        this.darkModeToggle.addEventListener('click', () => {
            this.toggleDarkMode();
        });

        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (this.getStoredDarkMode() === null) {
                    this.setDarkMode(e.matches);
                }
            });
        }
    }

    toggleDarkMode() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        this.setDarkMode(!isDark);
    }

    setDarkMode(isDark) {
        const root = document.documentElement;
        
        if (isDark) {
            root.setAttribute('data-theme', 'dark');
            this.darkModeToggle.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
            this.darkModeToggle.style.background = '#f59e0b';
        } else {
            root.removeAttribute('data-theme');
            this.darkModeToggle.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
            this.darkModeToggle.style.background = '#3b82f6';
        }

        // Save preference
        localStorage.setItem(this.storageKey, isDark ? 'true' : 'false');

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('darkModeChanged', { detail: { isDark } }));
    }

    loadDarkMode() {
        const stored = this.getStoredDarkMode();
        
        if (stored !== null) {
            this.setDarkMode(stored);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.setDarkMode(true);
        }
    }

    getStoredDarkMode() {
        const stored = localStorage.getItem(this.storageKey);
        return stored === null ? null : stored === 'true';
    }

    // Utility method to check if dark mode is active
    isDarkMode() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }
}

// Initialize dark mode when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.darkModeManager = new DarkModeManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DarkModeManager;
}
