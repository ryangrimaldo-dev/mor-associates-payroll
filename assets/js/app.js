// Payroll System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Auto-calculate payroll fields
    var payrollInputs = document.querySelectorAll('[data-calculate]');
    payrollInputs.forEach(function(input) {
        input.addEventListener('input', calculatePayroll);
    });

    // Currency formatting
    var currencyInputs = document.querySelectorAll('[data-currency]');
    currencyInputs.forEach(function(input) {
        input.addEventListener('blur', formatCurrency);
        input.addEventListener('focus', unformatCurrency);
    });

    // Initialize month/year dropdowns for reports
    var monthSel = document.getElementById('month');
    var yearSel = document.getElementById('year');
    if (monthSel && yearSel) {
        monthSel.addEventListener('change', function() {
            fetchMonthlyReport(monthSel.value, yearSel.value);
        });
        yearSel.addEventListener('change', function() {
            fetchMonthlyReport(monthSel.value, yearSel.value);
        });
        // Initial load
        fetchMonthlyReport(monthSel.value, yearSel.value);
    }

    // Initialize responsive tables
    initializeResponsiveTables();

    // Mobile swipe-to-delete functionality
    initializeSwipeToDelete();
    
    // Enhanced mobile navigation
    initializeMobileNavigation();

    // Date picker initialization
    var dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(function(input) {
        if (!input.value) {
            input.value = new Date().toISOString().split('T')[0];
        }
    });

    // Table row selection
    var tableRows = document.querySelectorAll('.table-hover tbody tr');
    tableRows.forEach(function(row) {
        row.addEventListener('click', function() {
            // Remove active class from all rows
            tableRows.forEach(function(r) {
                r.classList.remove('table-active');
            });
            // Add active class to clicked row
            this.classList.add('table-active');
        });
    });

    // Modal form reset
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            var forms = this.querySelectorAll('form');
            forms.forEach(function(form) {
                form.reset();
                form.classList.remove('was-validated');
            });
        });
    });

    // Search functionality
    var searchInputs = document.querySelectorAll('[data-search]');
    searchInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            var table = document.querySelector(this.dataset.search);
            var rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    // Export functionality
    var exportButtons = document.querySelectorAll('[data-export]');
    exportButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var tableId = this.dataset.export;
            exportTableToCSV(tableId);
        });
    });

    // Print functionality
    var printButtons = document.querySelectorAll('[data-print]');
    printButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var elementId = this.dataset.print;
            printElement(elementId);
        });
    });

    // Mobile table scroll functionality
    initializeMobileTableScroll();
});

// Payroll calculation function
function calculatePayroll() {
    // Safety check to ensure essential elements exist
    if (!document.getElementById('employee_id')) {
        console.warn('Essential payroll elements not found. Skipping calculation.');
        return;
    }
    
    // Helper function to safely get element value
    function getElementValue(id) {
        var element = document.getElementById(id);
        return element ? parseFloat(element.value || 0) : 0;
    }
    
    // Get employee daily rate
    var employeeSelect = document.getElementById('employee_id');
    var dailyRate = 0;
    if (employeeSelect && employeeSelect.selectedIndex >= 0) {
        dailyRate = parseFloat(employeeSelect.options[employeeSelect.selectedIndex].getAttribute('data-rate')) || 0;
    }
    
    var lateMinutes = getElementValue('late_minutes');
    var allowances = getElementValue('allowances');
    var additionalPayment = getElementValue('additional_payment');
    var otherDeductions = getElementValue('other_deductions');
    var loansAdvances = getElementValue('loans_advances');
    var sssLoan = getElementValue('sss_loan');
    var hdmfLoan = getElementValue('hdmf_loan');

    // Calculate late deduction: (late time / 8) / 60 * daily rate
    var lateDeduction = (lateMinutes / 8) / 60 * dailyRate;
    
    // Calculate basic pay from days worked entries
    var basicPay = 0;
    var daysWorkedEntries = document.querySelectorAll('.days-worked-entry');
    daysWorkedEntries.forEach(function(entry) {
        var days = parseFloat(entry.querySelector('.days-worked-count').value) || 0;
        var type = entry.querySelector('.days-worked-type').value;
        
        if (days > 0) {
            var rate = dailyRate;
            switch(type) {
                case 'rest_day':
                    rate = dailyRate * 1.3;
                    break;
                case 'rest_day_special_holiday':
                    rate = dailyRate * 1.5;
                    break;
                case 'regular_holiday_ordinary_day':
                    rate = dailyRate * 2.0;
                    break;
                case 'rest_day_regular_holiday':
                    rate = dailyRate * 2.6;
                    break;
                case 'special_holiday_ordinary_day':
                    rate = dailyRate * 1.3;
                    break;
                default:
                    rate = dailyRate;
            }
            basicPay += days * rate;
        }
    });
    
    basicPay += additionalPayment;
    
    // Calculate overtime pay from overtime entries
    var overtimePay = 0;
    var overtimeEntries = document.querySelectorAll('.overtime-entry');
    overtimeEntries.forEach(function(entry) {
        var hours = parseFloat(entry.querySelector('.overtime-hours').value) || 0;
        var type = entry.querySelector('.overtime-type').value;
        
        if (hours > 0 && type !== '0') {
            var hourlyRate = dailyRate / 8;
            var rate = 0;
            
            switch(type) {
                case 'ordinary_day_ot':
                    rate = hourlyRate * 1.25;
                    break;
                case 'rest_day_special_holiday_ot':
                    rate = hourlyRate * 1.69;
                    break;
                case 'rest_day_and_special_holiday_ot':
                    rate = hourlyRate * 1.95;
                    break;
                case 'regular_holiday_ordinary_day_ot':
                    rate = hourlyRate * 2.6;
                    break;
                case 'rest_day_regular_holiday_ot_special':
                    rate = hourlyRate * 3.38;
                    break;
                case 'night_differential_ordinary_day':
                    rate = hourlyRate * 0.1;
                    break;
                case 'night_differential_rest_day':
                    rate = hourlyRate * 0.13;
                    break;
                default:
                    rate = hourlyRate * 1.25;
            }
            overtimePay += hours * rate;
        }
    });
    
    // Check if automatic deductions should be applied
    var applyDeductions = document.getElementById('apply_deductions') && document.getElementById('apply_deductions').checked;
    
    var sssDeduction, philhealthDeduction, pagibigDeduction, taxDeduction;
    
    if (applyDeductions) {
        // Calculate monthly basic pay for automatic deductions (matches backend logic)
        var monthlyBasicPay = basicPay;
        
        // Use automatic calculation functions (matching backend logic)
        sssDeduction = calculateSSSDeductionJS(monthlyBasicPay);
        philhealthDeduction = calculatePhilHealthDeductionJS(monthlyBasicPay);
        pagibigDeduction = calculatePagIBIGDeductionJS();
        taxDeduction = calculateTaxDeductionJS(monthlyBasicPay);
        
        // Update the form fields with calculated values
        updateFormField('sss_deduction', sssDeduction);
        updateFormField('philhealth_deduction', philhealthDeduction);
        updateFormField('pagibig_deduction', pagibigDeduction);
        updateFormField('tax_deduction', taxDeduction);
    } else {
        // Use manual input values when deductions are not automatically applied
        sssDeduction = getElementValue('sss_deduction');
        philhealthDeduction = getElementValue('philhealth_deduction');
        pagibigDeduction = getElementValue('pagibig_deduction');
        taxDeduction = getElementValue('tax_deduction');
    }
    
    // Calculate total deductions including all components (matches backend logic)
    var totalDeductions = sssDeduction + philhealthDeduction + pagibigDeduction + taxDeduction + otherDeductions + loansAdvances + sssLoan + hdmfLoan + lateDeduction;
    
    // Calculate net pay (matches backend logic)
    var netPay = basicPay + overtimePay + allowances - totalDeductions;
    var thirteenthMonth = basicPay / 12;

    // Update result fields
    updateResultField('basic_pay_result', basicPay);
    updateResultField('overtime_pay_result', overtimePay);
    updateResultField('total_deductions_result', totalDeductions);
    updateResultField('net_pay_result', netPay);
    updateResultField('thirteenth_month_result', thirteenthMonth);
    
    // Update the JSON fields for form submission
    if (typeof collectDaysWorkedEntries === 'function') {
        collectDaysWorkedEntries();
    }
    if (typeof collectOvertimeEntries === 'function') {
        collectOvertimeEntries();
    }
}

// Update result field with formatted currency
function updateResultField(fieldId, value) {
    var field = document.getElementById(fieldId);
    if (field) {
        field.textContent = value.toFixed(2);
    } else {
        console.warn('Element with ID "' + fieldId + '" not found');
    }
}

// Update form field with calculated value
function updateFormField(fieldId, value) {
    var field = document.getElementById(fieldId);
    if (field) {
        field.value = value.toFixed(2);
    }
}

// JavaScript versions of backend calculation functions
function calculateSSSDeductionJS(monthlyBasicPay) {
    if (monthlyBasicPay < 5250) {
        return 250;
    } else if (monthlyBasicPay >= 5250 && monthlyBasicPay < 5750) {
        return 275;
    } else if (monthlyBasicPay >= 5750 && monthlyBasicPay < 6250) {
        return 300;
    } else if (monthlyBasicPay >= 6250 && monthlyBasicPay < 6750) {
        return 325;
    } else if (monthlyBasicPay >= 6750 && monthlyBasicPay < 7250) {
        return 350;
    } else if (monthlyBasicPay >= 7250 && monthlyBasicPay < 7750) {
        return 375;
    } else if (monthlyBasicPay >= 7750 && monthlyBasicPay < 8250) {
        return 400;
    } else if (monthlyBasicPay >= 8250 && monthlyBasicPay < 8750) {
        return 425;
    } else if (monthlyBasicPay >= 8750 && monthlyBasicPay < 9250) {
        return 450;
    } else if (monthlyBasicPay >= 9250 && monthlyBasicPay < 9750) {
        return 475;
    } else if (monthlyBasicPay >= 9750 && monthlyBasicPay < 10250) {
        return 500;
    } else if (monthlyBasicPay >= 10250 && monthlyBasicPay < 10750) {
        return 525;
    } else if (monthlyBasicPay >= 10750 && monthlyBasicPay < 11250) {
        return 550;
    } else if (monthlyBasicPay >= 11250 && monthlyBasicPay < 11750) {
        return 575;
    } else if (monthlyBasicPay >= 11750 && monthlyBasicPay < 12250) {
        return 600;
    } else if (monthlyBasicPay >= 12250 && monthlyBasicPay < 12750) {
        return 625;
    } else if (monthlyBasicPay >= 12750 && monthlyBasicPay < 13250) {
        return 650;
    } else if (monthlyBasicPay >= 13250 && monthlyBasicPay < 13750) {
        return 675;
    } else if (monthlyBasicPay >= 13750 && monthlyBasicPay < 14250) {
        return 700;
    } else if (monthlyBasicPay >= 14250 && monthlyBasicPay < 14750) {
        return 725;
    } else if (monthlyBasicPay >= 14750 && monthlyBasicPay < 15250) {
        return 750;
    } else if (monthlyBasicPay >= 15250 && monthlyBasicPay < 15750) {
        return 775;
    } else if (monthlyBasicPay >= 15750 && monthlyBasicPay < 16250) {
        return 800;
    } else if (monthlyBasicPay >= 16250 && monthlyBasicPay < 16750) {
        return 825;
    } else if (monthlyBasicPay >= 16750 && monthlyBasicPay < 17250) {
        return 850;
    } else if (monthlyBasicPay >= 17250 && monthlyBasicPay < 17750) {
        return 875;
    } else if (monthlyBasicPay >= 17750 && monthlyBasicPay < 18250) {
        return 900;
    } else if (monthlyBasicPay >= 18250 && monthlyBasicPay < 18750) {
        return 925;
    } else if (monthlyBasicPay >= 18750 && monthlyBasicPay < 19250) {
        return 950;
    } else if (monthlyBasicPay >= 19250 && monthlyBasicPay < 19750) {
        return 975;
    } else if (monthlyBasicPay >= 19750 && monthlyBasicPay < 20250) {
        return 1000;
    } else {
        // For amounts 20250 and above, continue adding 25 for each 500 range
        var baseAmount = 20250;
        var baseDeduction = 1000;
        var rangeSize = 500;
        var additionalRanges = Math.floor((monthlyBasicPay - baseAmount) / rangeSize);
        return baseDeduction + (additionalRanges * 25);
    }
}

function calculateTaxDeductionJS(monthlyBasicPay) {
    if (monthlyBasicPay < 10417) {
        return 0;
    } else if (monthlyBasicPay > 10417 && monthlyBasicPay <= 16666) {
        return (0 + (0.15 * monthlyBasicPay));
    } else if (monthlyBasicPay > 16666 && monthlyBasicPay <= 33332) {
        return (937.50 + (0.20 * monthlyBasicPay));
    } else if (monthlyBasicPay > 33332 && monthlyBasicPay <= 83332) {
        return (4270.70 + (0.25 * monthlyBasicPay));
    } else if (monthlyBasicPay > 83332 && monthlyBasicPay <= 333332) {
        return (16770.70 + (0.30 * monthlyBasicPay));
    } else if (monthlyBasicPay >= 333333) {
        return (91770.70 + (0.35 * monthlyBasicPay));
    }
}

function calculatePhilHealthDeductionJS(monthlyBasicPay) {
    // PhilHealth deduction is 5% of basic pay divided by 2
    return (monthlyBasicPay * 0.05) / 2;
}

function calculatePagIBIGDeductionJS() {
    // Pag-IBIG deduction is always 200
    return 200;
}

// Currency formatting
function formatCurrency(event) {
    var input = event.target;
    var value = parseFloat(input.value.replace(/[^\d.-]/g, ''));
    if (!isNaN(value)) {
        input.value = '₱' + value.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function unformatCurrency(event) {
    var input = event.target;
    input.value = input.value.replace(/[^\d.-]/g, '');
}

// Export table to CSV
function exportTableToCSV(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;

    var csv = [];
    var rows = table.querySelectorAll('tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            var text = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }

    var csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
    var encodedUri = encodeURI(csvContent);
    var link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', 'payroll_report.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Print element
function printElement(elementId) {
    var element = document.getElementById(elementId);
    if (!element) return;

    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Print</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<style>@media print { .no-print { display: none !important; } }</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(element.outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}

// Show loading spinner
function showLoading(element) {
    var spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm me-2';
    spinner.setAttribute('role', 'status');
    spinner.setAttribute('aria-hidden', 'true');
    
    element.disabled = true;
    element.insertBefore(spinner, element.firstChild);
}

// Hide loading spinner
function hideLoading(element) {
    var spinner = element.querySelector('.spinner-border');
    if (spinner) {
        spinner.remove();
    }
    element.disabled = false;
}

// Show notification
function showNotification(message, type = 'info') {
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        var alert = new bootstrap.Alert(alertDiv);
        alert.close();
    }, 5000);
}

// Confirm action
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Format number as currency
function formatCurrencyValue(value) {
    return '₱' + parseFloat(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Validate email
function validateEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validate phone number
function validatePhone(phone) {
    var re = /^[\+]?[1-9][\d]{0,15}$/;
    return re.test(phone.replace(/[\s\-\(\)]/g, ''));
}

// Debounce function
function debounce(func, wait) {
    var timeout;
    return function executedFunction() {
        var later = function() {
            clearTimeout(timeout);
            func();
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function
function throttle(func, limit) {
    var inThrottle;
    return function() {
        var args = arguments;
        var context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(function() {
                inThrottle = false;
            }, limit);
        }
    };
}

// Storage utilities removed - not used in the application 

function fetchMonthlyReport(month, year) {
    fetch('reports_ajax.php?action=monthly&month=' + month + '&year=' + year)
        .then(response => response.json())
        .then(data => {
            if (data && typeof data === 'object' && 'total_payroll' in data) {
                document.getElementById('monthly-total-payroll').textContent = data.total_payroll;
                document.getElementById('monthly-total-employees').textContent = data.total_employees;
                document.getElementById('monthly-avg-netpay').textContent = data.avg_net_pay;
                document.getElementById('monthly-total-deductions').textContent = data.total_deductions;
                document.getElementById('monthly-total-overtime').textContent = data.total_overtime;
                document.getElementById('monthly-total-allowances').textContent = data.total_allowances;
                document.getElementById('monthly-report-error').style.display = 'none';
            } else {
                document.getElementById('monthly-report-error').textContent = 'No data found for this month/year.';
                document.getElementById('monthly-report-error').style.display = 'block';
            }
        })
        .catch(err => {
            document.getElementById('monthly-report-error').textContent = 'Error loading report: ' + err;
            document.getElementById('monthly-report-error').style.display = 'block';
        });
}
// Month/year dropdown functionality is now handled in the main DOMContentLoaded listener 

function fetchDepartmentReport(department) {
    var url = 'reports_ajax.php?action=department&department=' + encodeURIComponent(department || '');
    fetch(url)
        .then(response => response.json())
        .then(data => {
            var tbody = document.getElementById('department-report-tbody');
            var errorDiv = document.getElementById('department-report-error');
            tbody.innerHTML = '';
            if (Array.isArray(data) && data.length > 0) {
                data.forEach(function(row) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + row.department + '</td>' +
                                   '<td>' + row.employees + '</td>' +
                                   '<td>₱' + row.total_payroll + '</td>' +
                                   '<td>₱' + row.avg_net_pay + '</td>';
                    tbody.appendChild(tr);
                });
                errorDiv.style.display = 'none';
            } else {
                errorDiv.textContent = 'No data found for this department.';
                errorDiv.style.display = 'block';
            }
        })
        .catch(function(err) {
            var errorDiv = document.getElementById('department-report-error');
            errorDiv.textContent = 'Error loading department report: ' + err;
            errorDiv.style.display = 'block';
        });
}
// Department dropdown event
var deptSelect = document.getElementById('department-ajax');
if (deptSelect) {
    deptSelect.addEventListener('change', function() {
        fetchDepartmentReport(deptSelect.value);
    });
    // Fetch on modal open (Bootstrap 5 event)
    var deptModal = document.getElementById('departmentReportModal');
    if (deptModal) {
        deptModal.addEventListener('shown.bs.modal', function() {
            fetchDepartmentReport(deptSelect.value);
        });
    }
}

// Mobile swipe-to-delete functionality
function initializeSwipeToDelete() {
    if (window.innerWidth <= 768) {
        const swipeItems = document.querySelectorAll('.swipe-item');
        
        swipeItems.forEach(item => {
            let startX = 0;
            let currentX = 0;
            let isDragging = false;
            let hasSwiped = false;
            
            // Touch events
            item.addEventListener('touchstart', handleTouchStart, { passive: true });
            item.addEventListener('touchmove', handleTouchMove, { passive: false });
            item.addEventListener('touchend', handleTouchEnd, { passive: true });
            
            // Mouse events for testing on desktop
            item.addEventListener('mousedown', handleMouseStart);
            item.addEventListener('mousemove', handleMouseMove);
            item.addEventListener('mouseup', handleMouseEnd);
            item.addEventListener('mouseleave', handleMouseEnd);
            
            function handleTouchStart(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
                item.style.transition = 'none';
            }
            
            function handleTouchMove(e) {
                if (!isDragging) return;
                
                e.preventDefault();
                currentX = e.touches[0].clientX;
                const deltaX = currentX - startX;
                
                // Only allow left swipe (negative deltaX)
                if (deltaX < 0 && deltaX > -100) {
                    item.style.transform = `translateX(${deltaX}px)`;
                }
            }
            
            function handleTouchEnd(e) {
                if (!isDragging) return;
                
                isDragging = false;
                item.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                
                const deltaX = currentX - startX;
                
                // If swiped left more than 50px, show delete button
                if (deltaX < -50) {
                    item.classList.add('swiped');
                    item.style.transform = 'translateX(-80px)';
                    hasSwiped = true;
                } else {
                    // Snap back to original position
                    item.classList.remove('swiped');
                    item.style.transform = 'translateX(0)';
                    hasSwiped = false;
                }
                
                startX = 0;
                currentX = 0;
            }
            
            // Mouse events (for desktop testing)
            function handleMouseStart(e) {
                startX = e.clientX;
                isDragging = true;
                item.style.transition = 'none';
                e.preventDefault();
            }
            
            function handleMouseMove(e) {
                if (!isDragging) return;
                
                currentX = e.clientX;
                const deltaX = currentX - startX;
                
                if (deltaX < 0 && deltaX > -100) {
                    item.style.transform = `translateX(${deltaX}px)`;
                }
            }
            
            function handleMouseEnd(e) {
                if (!isDragging) return;
                
                isDragging = false;
                item.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                
                const deltaX = currentX - startX;
                
                if (deltaX < -50) {
                    item.classList.add('swiped');
                    item.style.transform = 'translateX(-80px)';
                    hasSwiped = true;
                } else {
                    item.classList.remove('swiped');
                    item.style.transform = 'translateX(0)';
                    hasSwiped = false;
                }
                
                startX = 0;
                currentX = 0;
            }
            
            // Close swipe when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (!item.contains(e.target) && hasSwiped) {
                    item.classList.remove('swiped');
                    item.style.transform = 'translateX(0)';
                    hasSwiped = false;
                }
            });
        });
    }
}

// Enhanced mobile navigation
function initializeMobileNavigation() {
    const navbar = document.querySelector('.navbar');
    let lastScrollTop = 0;
    
    // Hide/show navbar on scroll (mobile)
    if (window.innerWidth <= 768) {
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                navbar.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop;
        }, { passive: true });
    }
    
    // Add scrolled class to navbar
    window.addEventListener('scroll', function() {
        if (window.scrollY > 10) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }, { passive: true });
    
    // Close mobile menu when clicking outside
    const navbarCollapse = document.querySelector('.navbar-collapse');
    const navbarToggler = document.querySelector('.navbar-toggler');
    
    if (navbarCollapse && navbarToggler) {
        document.addEventListener('click', function(e) {
            if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                if (bsCollapse && navbarCollapse.classList.contains('show')) {
                    bsCollapse.hide();
                }
            }
        });
    }
}

// Utility function to check if device is mobile
function isMobile() {
    return window.innerWidth <= 768;
}

// Responsive utility functions
function handleResize() {
    // Reinitialize mobile features on resize
    if (isMobile()) {
        initializeSwipeToDelete();
    } else {
        // Clean up mobile-specific features on desktop
        const swipeItems = document.querySelectorAll('.swipe-item');
        swipeItems.forEach(item => {
            item.classList.remove('swiped');
            item.style.transform = '';
            item.style.transition = '';
        });
    }
}

// Listen for window resize
window.addEventListener('resize', debounce(handleResize, 250));

// Enhanced table responsiveness
function initializeResponsiveTables() {
    const tables = document.querySelectorAll('.table-responsive');
    
    tables.forEach(table => {
        // Add mobile-friendly table headers
        if (isMobile()) {
            const rows = table.querySelectorAll('tbody tr');
            const headers = table.querySelectorAll('thead th');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index]) {
                        cell.setAttribute('data-label', headers[index].textContent);
                    }
                });
            });
        }
    });
}

// Responsive tables initialization is now handled in the main DOMContentLoaded listener

// Mobile table scroll functionality
function initializeMobileTableScroll() {
    // Only run on mobile devices
    if (window.innerWidth > 768) return;
    
    var tableContainers = document.querySelectorAll('.table-responsive');
    
    tableContainers.forEach(function(container, index) {
        var table = container.querySelector('table');
        if (!table) {
            return;
        }
        
        // Check if this table should be excluded from scroll buttons
        var cardHeader = container.closest('.card');
        if (cardHeader) {
            var headerText = cardHeader.querySelector('.card-header h5');
            if (headerText) {
                var headerContent = headerText.textContent || headerText.innerText;
                if (headerContent.includes('Recent Payroll Activity') || 
                    headerContent.includes('Recent Reports') ||
                    headerContent.includes('System Overview')) {
                    return; // Skip adding buttons but keep native scroll
                }
            }
        }
        
        // Also check for department report modal tables
        var modalParent = container.closest('.modal');
        if (modalParent && modalParent.id === 'departmentReportModal') {
            console.log('Skipping scroll buttons for department report modal table');
            return; // Skip adding buttons but keep native scroll
        }
        
        // Skip if already processed
        if (container.parentNode.classList && container.parentNode.classList.contains('mobile-table-wrapper')) {
            return;
        }
        
        // Create wrapper
        var wrapper = document.createElement('div');
        wrapper.className = 'mobile-table-wrapper';
        wrapper.style.cssText = 'position: relative; margin-bottom: 20px;';
        
        // Insert wrapper
        container.parentNode.insertBefore(wrapper, container);
        wrapper.appendChild(container);
        
        // Create left button
        var leftButton = document.createElement('button');
        leftButton.innerHTML = '<';
        leftButton.type = 'button';
        leftButton.style.cssText = `
            position: absolute;
            left: -20px;
            top: 50%;
            transform: translateY(-50%);
            width: 35px;
            height: 35px;
            background: #01acc1;
            color: white;
            border: none;
            border-radius: 50%;
            z-index: 1000;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        `;
        
        // Create right button
        var rightButton = document.createElement('button');
        rightButton.innerHTML = '>';
        rightButton.type = 'button';
        rightButton.style.cssText = `
            position: absolute;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            width: 35px;
            height: 35px;
            background: #01acc1;
            color: white;
            border: none;
            border-radius: 50%;
            z-index: 1000;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        `;
        
        // Add buttons to wrapper
        wrapper.appendChild(leftButton);
        wrapper.appendChild(rightButton);
        
        // Add click handlers
        leftButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Left button clicked, scrolling left');
            console.log('Before scroll - scrollLeft:', container.scrollLeft);
            
            // Try multiple scroll methods
            if (container.scrollBy) {
                container.scrollBy({ left: -150, behavior: 'smooth' });
            } else {
                container.scrollLeft -= 150;
            }
            
            setTimeout(function() {
                console.log('After scroll - scrollLeft:', container.scrollLeft);
            }, 100);
        });
        
        rightButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Right button clicked, scrolling right');
            console.log('Before scroll - scrollLeft:', container.scrollLeft);
            console.log('Container info:', {
                scrollWidth: container.scrollWidth,
                clientWidth: container.clientWidth,
                scrollLeft: container.scrollLeft
            });
            
            // Try multiple scroll methods
            var originalScrollLeft = container.scrollLeft;
            
            if (container.scrollBy) {
                console.log('Using scrollBy method');
                container.scrollBy({ left: 150, behavior: 'smooth' });
            } else {
                console.log('Using scrollLeft method');
                container.scrollLeft += 150;
            }
            
            setTimeout(function() {
                console.log('After scroll - scrollLeft:', container.scrollLeft);
                
                // If scroll didn't work, try alternative methods
                if (container.scrollLeft === originalScrollLeft) {
                    console.log('Native scroll failed, trying alternative methods');
                    
                    // Method 1: Force scrollLeft with animation disabled
                    container.style.scrollBehavior = 'auto';
                    container.scrollLeft = originalScrollLeft + 150;
                    
                    setTimeout(function() {
                        console.log('After alternative scroll - scrollLeft:', container.scrollLeft);
                        container.style.scrollBehavior = 'smooth';
                    }, 50);
                }
            }, 100);
        });
        
        // Update button states
        function updateButtons() {
            var maxScroll = container.scrollWidth - container.clientWidth;
            var canScrollLeft = container.scrollLeft > 0;
            var canScrollRight = container.scrollLeft < maxScroll - 5;
            
            leftButton.style.opacity = canScrollLeft ? '1' : '0.3';
            rightButton.style.opacity = canScrollRight ? '1' : '0.3';
            
            console.log('Button states updated:', { canScrollLeft, canScrollRight, scrollLeft: container.scrollLeft, maxScroll });
        }
        
        // Listen for scroll events
        container.addEventListener('scroll', updateButtons);
        
        // Initial button state
        setTimeout(function() {
            updateButtons();
            console.log('Initial button state set for container', index);
        }, 200);
        
        console.log('Mobile scroll buttons added to container', index);
    });
}