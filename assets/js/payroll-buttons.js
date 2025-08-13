// Event listeners for payroll.php buttons

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing payroll button event listeners');
    
    // Calculate Payroll button
    const calculateButtons = document.querySelectorAll('.calculate-payroll-btn');
    calculateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const periodId = this.getAttribute('data-id');
            const periodName = this.getAttribute('data-name');
            console.log('Opening calculate payroll modal for period:', periodId, periodName);
            
            // Set the pay period ID in the modal form
            document.getElementById('pay_period_id').value = periodId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('calculatePayrollModal'));
            modal.show();
        });
    });
    
    // View Payroll button
    const viewButtons = document.querySelectorAll('.view-payroll-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const payrollId = this.getAttribute('data-id');
            console.log('Viewing payroll:', payrollId);
            viewPayroll(payrollId);
        });
    });
    
    // Approve Payroll button
    const approveButtons = document.querySelectorAll('.approve-payroll-btn');
    approveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const payrollId = this.getAttribute('data-id');
            console.log('Approving payroll:', payrollId);
            
            // Set the payroll ID in the modal form
            document.getElementById('approve_payroll_id').value = payrollId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('approvePayrollModal'));
            modal.show();
        });
    });
    
    // Delete Payroll button
    const deletePayrollButtons = document.querySelectorAll('.delete-payroll-btn');
    deletePayrollButtons.forEach(button => {
        button.addEventListener('click', function() {
            const payrollId = this.getAttribute('data-id');
            console.log('Deleting payroll:', payrollId);
            
            // Set the payroll ID in the modal form
            document.getElementById('delete_payroll_id').value = payrollId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('deletePayrollModal'));
            modal.show();
        });
    });
    
    // Mark Paid button
    const markPaidButtons = document.querySelectorAll('.mark-paid-btn');
    markPaidButtons.forEach(button => {
        button.addEventListener('click', function() {
            const payrollId = this.getAttribute('data-id');
            console.log('Marking payroll as paid:', payrollId);
            
            // Set the payroll ID in the modal form
            document.getElementById('mark_paid_id').value = payrollId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
            modal.show();
        });
    });
    
    // Delete Pay Period button
    const deletePayPeriodButtons = document.querySelectorAll('.delete-pay-period-btn');
    deletePayPeriodButtons.forEach(button => {
        button.addEventListener('click', function() {
            const periodId = this.getAttribute('data-id');
            console.log('Deleting pay period:', periodId);
            
            // Set the period ID in the modal form
            document.getElementById('delete_pay_period_id').value = periodId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('deletePayPeriodModal'));
            modal.show();
        });
    });
    
    // Dropdown initialization is handled by dropdown-init.js
});