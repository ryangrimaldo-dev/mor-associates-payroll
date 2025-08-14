document.addEventListener('DOMContentLoaded', function() {
    // Force hide all dropdown menus with multiple methods
    const dropdownMenus = document.querySelectorAll('.dropdown-menu');
    dropdownMenus.forEach(menu => {
        menu.style.display = 'none !important';
        menu.style.visibility = 'hidden';
        menu.style.opacity = '0';
        menu.classList.remove('show');
        menu.setAttribute('data-bs-popper', 'none');
    });
    
    // Initialize custom dropdown functionality
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        // Set initial state
        toggle.setAttribute('aria-expanded', 'false');
        
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                const isVisible = menu.style.display === 'block' && menu.style.visibility === 'visible';
                
                // Close all other dropdowns first
                dropdownMenus.forEach(otherMenu => {
                    if (otherMenu !== menu) {
                        otherMenu.style.display = 'none !important';
                        otherMenu.style.visibility = 'hidden';
                        otherMenu.style.opacity = '0';
                        otherMenu.classList.remove('show');
                    }
                });
                
                dropdownToggles.forEach(otherToggle => {
                    if (otherToggle !== this) {
                        otherToggle.setAttribute('aria-expanded', 'false');
                    }
                });
                
                // Toggle current dropdown
                if (isVisible) {
                    // Hide dropdown
                    menu.style.display = 'none !important';
                    menu.style.visibility = 'hidden';
                    menu.style.opacity = '0';
                    menu.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    // Show dropdown
                    menu.style.display = 'block !important';
                    menu.style.visibility = 'visible';
                    menu.style.opacity = '1';
                    menu.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            }
        });
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            dropdownMenus.forEach(menu => {
                menu.style.display = 'none !important';
                menu.style.visibility = 'hidden';
                menu.style.opacity = '0';
                menu.classList.remove('show');
            });
            dropdownToggles.forEach(toggle => {
                toggle.setAttribute('aria-expanded', 'false');
            });
        }
    });
    
    // Ensure dropdown links work properly
    document.querySelectorAll('.dropdown-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            // Allow normal link behavior (don't prevent default)
            console.log('Dropdown link clicked:', this.href);
        });
    });
    
    console.log('Custom dropdown functionality initialized');
});