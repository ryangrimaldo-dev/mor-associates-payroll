document.addEventListener('DOMContentLoaded', function() {
    // Initialize all Bootstrap dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    console.log('Bootstrap dropdowns initialized');
});