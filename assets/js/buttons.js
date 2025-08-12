// Buttons functionality for Payroll System

// Employee management functions
function editEmployee(employee) {
    try {
        // Make sure employee is an object, not a string
        if (typeof employee === 'string') {
            employee = JSON.parse(employee);
        }
        
        document.getElementById('edit_id').value = employee.id;
        document.getElementById('edit_first_name').value = employee.first_name;
        document.getElementById('edit_last_name').value = employee.last_name;
        document.getElementById('edit_email').value = employee.email;
        document.getElementById('edit_phone').value = employee.phone || '';
        document.getElementById('edit_position').value = employee.position;
        document.getElementById('edit_department').value = employee.department;
        document.getElementById('edit_status').value = employee.status;
        document.getElementById('edit_rate_type').value = employee.rate_type;
        
        // If rate type is monthly, multiply daily_rate by 22 to show monthly rate
        if (employee.rate_type === 'monthly') {
            document.getElementById('edit_daily_rate').value = (parseFloat(employee.daily_rate) * 22).toFixed(2);
            document.getElementById('edit_rate_label').textContent = '/ month';
        } else {
            document.getElementById('edit_daily_rate').value = employee.daily_rate;
            document.getElementById('edit_rate_label').textContent = '/ day';
        }
        
        var editModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
        editModal.show();
    } catch (error) {
        console.error('Error in editEmployee function:', error);
        alert('An error occurred while trying to edit this employee. Please try again.');
    }
}

function deleteEmployee(id, name) {
    document.getElementById('delete_employee_id').value = id;
    document.getElementById('delete_employee_name').textContent = name;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteEmployeeModal'));
    deleteModal.show();
}

// Payroll management functions
function viewPayroll(id) {
    window.open('payslip_view.php?id=' + id, '_blank');
}

function approvePayroll(id) {
    if (confirm('Are you sure you want to approve this payroll?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve_payroll">
            <input type="hidden" name="payroll_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deletePayroll(id) {
    if (confirm('Are you sure you want to delete this payroll record? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_payroll">
            <input type="hidden" name="payroll_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deletePayPeriod(id) {
    if (confirm('Are you sure you want to delete this pay period? This will also delete all related payroll records, leave records, and reports. This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_pay_period">
            <input type="hidden" name="pay_period_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function markPaid(id) {
    if (confirm('Mark this payroll as Paid?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="action" value="mark_paid">
            <input type="hidden" name="payroll_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Make sure Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded! Buttons may not work properly.');
    } else {
        console.log('Bootstrap is loaded. Buttons should work properly.');
    }
});