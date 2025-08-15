/**
 * Dark Mode Theme Toggle for MOR Payroll System
 * Provides smooth theme switching with localStorage persistence
 */

class ThemeToggle {
    constructor() {
        this.currentTheme = this.getStoredTheme() || this.getSystemTheme();
        this.init();
    }

    init() {
        this.createToggleButton();
        this.applyTheme(this.currentTheme);
        this.bindEvents();
    }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    getStoredTheme() {
        return localStorage.getItem('mor-payroll-theme');
    }

    setStoredTheme(theme) {
        localStorage.setItem('mor-payroll-theme', theme);
    }

    createToggleButton() {
        const toggleButton = document.createElement('button');
        toggleButton.id = 'theme-toggle';
        toggleButton.className = 'btn btn-ghost theme-toggle-btn';
        toggleButton.setAttribute('aria-label', 'Toggle dark mode');
        toggleButton.innerHTML = this.getToggleIcon(this.currentTheme);

        // Add to navbar
        const navbar = document.querySelector('.navbar-nav');
        if (navbar) {
            const toggleItem = document.createElement('li');
            toggleItem.className = 'nav-item';
            toggleItem.appendChild(toggleButton);
            navbar.appendChild(toggleItem);
        }
    }

    getToggleIcon(theme) {
        if (theme === 'dark') {
            return '<i class="fas fa-sun" title="Switch to light mode"></i>';
        } else {
            return '<i class="fas fa-moon" title="Switch to dark mode"></i>';
        }
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;
        this.setStoredTheme(theme);
        
        // Update toggle button icon
        const toggleButton = document.getElementById('theme-toggle');
        if (toggleButton) {
            toggleButton.innerHTML = this.getToggleIcon(theme);
        }

        // Dispatch custom event for other components
        window.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { theme } 
        }));
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
    }

    bindEvents() {
        // Toggle button click
        document.addEventListener('click', (e) => {
            if (e.target.closest('#theme-toggle')) {
                e.preventDefault();
                this.toggleTheme();
            }
        });

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!this.getStoredTheme()) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Keyboard shortcut (Ctrl/Cmd + Shift + D)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }
}

// Initialize theme toggle when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new ThemeToggle();
});

// Add CSS for theme toggle button
const themeToggleStyles = `
    .theme-toggle-btn {
        padding: var(--space-2) !important;
        margin-left: var(--space-2);
        border-radius: var(--radius-md);
        transition: var(--transition-theme);
        color: var(--text-secondary);
        background: transparent;
        border: 1px solid var(--border-light);
    }

    .theme-toggle-btn:hover {
        color: var(--primary-accent);
        background: var(--primary-50);
        border-color: var(--primary-accent);
        transform: none;
        box-shadow: none;
    }

    .theme-toggle-btn i {
        font-size: var(--text-base);
        transition: var(--transition-theme);
    }

    /* Dark mode specific styles */
    [data-theme="dark"] .theme-toggle-btn {
        border-color: var(--border-light);
        color: var(--text-muted);
    }

    [data-theme="dark"] .theme-toggle-btn:hover {
        color: var(--primary-light);
        background: var(--primary-100);
        border-color: var(--primary-light);
    }

    /* Smooth transitions for theme changes */
    * {
        transition-property: background-color, border-color, color, box-shadow;
        transition-duration: 0.3s;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Prevent transition on page load */
    .no-transition * {
        transition: none !important;
    }
`;

// Inject theme toggle styles
const styleSheet = document.createElement('style');
styleSheet.textContent = themeToggleStyles;
document.head.appendChild(styleSheet);
