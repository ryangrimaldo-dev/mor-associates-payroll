<?php
require_once 'config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/auth.php';

requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Automatic deduction calculation functions
function calculateSSSDeduction($monthly_basic_pay) {
    // SSS deduction based on monthly basic pay ranges
    if ($monthly_basic_pay < 5250) {
        return 250;
    } elseif ($monthly_basic_pay >= 5250 && $monthly_basic_pay < 5750) {
        return 275;
    } elseif ($monthly_basic_pay >= 5750 && $monthly_basic_pay < 6250) {
        return 300;
    } elseif ($monthly_basic_pay >= 6250 && $monthly_basic_pay < 6750) {
        return 325;
    } elseif ($monthly_basic_pay >= 6750 && $monthly_basic_pay < 7250) {
        return 350;
    } elseif ($monthly_basic_pay >= 7250 && $monthly_basic_pay < 7750) {
        return 375;
    } elseif ($monthly_basic_pay >= 7750 && $monthly_basic_pay < 8250) {
        return 400;
    } elseif ($monthly_basic_pay >= 8250 && $monthly_basic_pay < 8750) {
        return 425;
    } elseif ($monthly_basic_pay >= 8750 && $monthly_basic_pay < 9250) {
        return 450;
    } elseif ($monthly_basic_pay >= 9250 && $monthly_basic_pay < 9750) {
        return 475;
    } elseif ($monthly_basic_pay >= 9750 && $monthly_basic_pay < 10250) {
        return 500;
    } elseif ($monthly_basic_pay >= 10250 && $monthly_basic_pay < 10750) {
        return 525;
    } elseif ($monthly_basic_pay >= 10750 && $monthly_basic_pay < 11250) {
        return 550;
    } elseif ($monthly_basic_pay >= 11250 && $monthly_basic_pay < 11750) {
        return 575;
    } elseif ($monthly_basic_pay >= 11750 && $monthly_basic_pay < 12250) {
        return 600;
    } elseif ($monthly_basic_pay >= 12250 && $monthly_basic_pay < 12750) {
        return 625;
    } elseif ($monthly_basic_pay >= 12750 && $monthly_basic_pay < 13250) {
        return 650;
    } elseif ($monthly_basic_pay >= 13250 && $monthly_basic_pay < 13750) {
        return 675;
    } elseif ($monthly_basic_pay >= 13750 && $monthly_basic_pay < 14250) {
        return 700;
    } elseif ($monthly_basic_pay >= 14250 && $monthly_basic_pay < 14750) {
        return 725;
    } elseif ($monthly_basic_pay >= 14750 && $monthly_basic_pay < 15250) {
        return 750;
    } elseif ($monthly_basic_pay >= 15250 && $monthly_basic_pay < 15750) {
        return 775;
    } elseif ($monthly_basic_pay >= 15750 && $monthly_basic_pay < 16250) {
        return 800;
    } elseif ($monthly_basic_pay >= 16250 && $monthly_basic_pay < 16750) {
        return 825;
    } elseif ($monthly_basic_pay >= 16750 && $monthly_basic_pay < 17250) {
        return 850;
    } elseif ($monthly_basic_pay >= 17250 && $monthly_basic_pay < 17750) {
        return 875;
    } elseif ($monthly_basic_pay >= 17750 && $monthly_basic_pay < 18250) {
        return 900;
    } elseif ($monthly_basic_pay >= 18250 && $monthly_basic_pay < 18750) {
        return 925;
    } elseif ($monthly_basic_pay >= 18750 && $monthly_basic_pay < 19250) {
        return 950;
    } elseif ($monthly_basic_pay >= 19250 && $monthly_basic_pay < 19750) {
        return 975;
    } elseif ($monthly_basic_pay >= 19750 && $monthly_basic_pay < 20250) {
        return 1000;
    } else {
        // For amounts 20250 and above, continue adding 25 for each 500 range
        $base_amount = 20250;
        $base_deduction = 1000;
        $range_size = 500;
        $additional_ranges = ceil(($monthly_basic_pay - $base_amount) / $range_size);
        return $base_deduction + ($additional_ranges * 25);
    }
}

function calculateTaxDeduction($monthly_basic_pay) {
    if ($monthly_basic_pay < 10417) {
        return 0;
    } elseif ($monthly_basic_pay > 10417 && $monthly_basic_pay <= 16666) {
        return (0 + (0.15 * $monthly_basic_pay));
    } elseif ($monthly_basic_pay > 16666 && $monthly_basic_pay <= 33332) {
        return (937.50 + (0.20 * $monthly_basic_pay));
    } elseif ($monthly_basic_pay > 33332 && $monthly_basic_pay <= 83332) {
        return (4270.70 + (0.25 * $monthly_basic_pay));
    } elseif ($monthly_basic_pay > 83332 && $monthly_basic_pay <= 333332) {
        return (16770.70 + (0.30 * $monthly_basic_pay));
    } elseif ($monthly_basic_pay >= 333333) {
        return (91770.70 + (0.35 * $monthly_basic_pay));
    }
}

function calculatePhilHealthDeduction($monthly_basic_pay) {
    // PhilHealth deduction is 5% of basic pay divided by 2
    return ($monthly_basic_pay * 0.05) / 2;
}

function calculatePagIBIGDeduction() {
    // Pag-IBIG deduction is always 200
    return 200;
}


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_period':
                $period_name = trim($_POST['period_name']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                
                $stmt = $conn->prepare("INSERT INTO pay_periods (period_name, start_date, end_date) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $period_name, $start_date, $end_date);
                
                if ($stmt->execute()) {
                    $message = 'Pay period created successfully.';
                } else {
                    $error = 'Error creating pay period: ' . $stmt->error;
                }
                break;
                
            case 'approve_payroll':
                if (isset($_POST['approve_payroll_id'])) {
                    $payroll_id = intval($_POST['approve_payroll_id']);
                    
                    $stmt = $conn->prepare("UPDATE payroll_records SET status = 'Approved' WHERE id = ? AND status = 'Draft'");
                    $stmt->bind_param("i", $payroll_id);
                    
                    if ($stmt->execute()) {
                        $message = 'Payroll record approved successfully.';
                    } else {
                        $error = 'Error approving payroll record: ' . $stmt->error;
                    }
                } else {
                    $error = 'Invalid payroll ID.';
                }
                break;
                
            case 'delete_payroll':
                if (isset($_POST['delete_payroll_id'])) {
                    $payroll_id = intval($_POST['delete_payroll_id']);
                    
                    $stmt = $conn->prepare("DELETE FROM payroll_records WHERE id = ?");
                    $stmt->bind_param("i", $payroll_id);
                    
                    if ($stmt->execute()) {
                        $message = 'Payroll record deleted successfully.';
                    } else {
                        $error = 'Error deleting payroll record: ' . $stmt->error;
                    }
                } else {
                    $error = 'Invalid payroll ID.';
                }
                break;
                
            case 'delete_pay_period':
                if (isset($_POST['delete_pay_period_id'])) {
                    $pay_period_id = intval($_POST['delete_pay_period_id']);
                    
                    // Start transaction to ensure both operations succeed or fail together
                    $conn->begin_transaction();
                    
                    try {
                        // First delete associated payroll records
                        $stmt1 = $conn->prepare("DELETE FROM payroll_records WHERE pay_period_id = ?");
                        $stmt1->bind_param("i", $pay_period_id);
                        $stmt1->execute();
                        
                        // Then delete the pay period
                        $stmt2 = $conn->prepare("DELETE FROM pay_periods WHERE id = ?");
                        $stmt2->bind_param("i", $pay_period_id);
                        $stmt2->execute();
                        
                        // Commit the transaction
                        $conn->commit();
                        $message = 'Pay period and associated payroll records deleted successfully.';
                    } catch (Exception $e) {
                        // Roll back the transaction if something failed
                        $conn->rollback();
                        $error = 'Error deleting pay period: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid pay period ID.';
                }
                break;
                
            case 'mark_paid':
                if (isset($_POST['mark_paid_id'])) {
                    $payroll_id = intval($_POST['mark_paid_id']);
                    
                    // Get the pay period ID for this payroll record
                    $get_period_stmt = $conn->prepare("SELECT pay_period_id FROM payroll_records WHERE id = ?");
                    $get_period_stmt->bind_param("i", $payroll_id);
                    $get_period_stmt->execute();
                    $period_result = $get_period_stmt->get_result();
                    
                    if ($period_data = $period_result->fetch_assoc()) {
                        $pay_period_id = $period_data['pay_period_id'];
                        
                        // Mark the payroll record as paid
                        $stmt = $conn->prepare("UPDATE payroll_records SET status = 'Paid', payment_date = CURRENT_DATE() WHERE id = ?");
                        $stmt->bind_param("i", $payroll_id);
                        
                        if ($stmt->execute()) {
                            $message = 'Payroll record marked as paid successfully.';
                            
                            // Check if all payroll records for this pay period are paid
                            $check_all_paid = $conn->prepare("SELECT COUNT(*) as total, 
                                                             SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid 
                                                             FROM payroll_records 
                                                             WHERE pay_period_id = ?");
                            $check_all_paid->bind_param("i", $pay_period_id);
                            $check_all_paid->execute();
                            $paid_result = $check_all_paid->get_result()->fetch_assoc();
                            
                            // If all records are paid, update pay period status to Completed
                            if ($paid_result['total'] > 0 && $paid_result['total'] == $paid_result['paid']) {
                                $update_period = $conn->prepare("UPDATE pay_periods SET status = 'Completed' WHERE id = ?");
                                $update_period->bind_param("i", $pay_period_id);
                                $update_period->execute();
                            }
                        } else {
                            $error = 'Error marking payroll record as paid: ' . $stmt->error;
                        }
                    } else {
                        $error = 'Could not find pay period for this payroll record.';
                    }
                } else {
                    $error = 'Invalid payroll ID.';
                }
                break;
                
            case 'calculate_payroll':
                $pay_period_id = intval($_POST['pay_period_id'] ?? 0);
                $employee_id = intval($_POST['employee_id'] ?? 0);
                $days_worked = floatval($_POST['days_worked'] ?? 0);
                $late_minutes = floatval($_POST['late_minutes'] ?? 0);
                $overtime_entries_json = isset($_POST['overtime_entries_json']) ? $_POST['overtime_entries_json'] : '[]';
                $overtime_entries = json_decode($overtime_entries_json, true);
                error_log("DEBUG: Raw overtime JSON received: " . $overtime_entries_json);
                error_log("DEBUG: Decoded overtime entries: " . print_r($overtime_entries, true));
                error_log("DEBUG: JSON decode error: " . json_last_error_msg());
                $allowances = floatval($_POST['allowances'] ?? 0);
                $additional_payment = floatval($_POST['additional_payment'] ?? 0);
                $sss_deduction = floatval($_POST['sss_deduction'] ?? 0);
                $philhealth_deduction = floatval($_POST['philhealth_deduction'] ?? 0);
                $pagibig_deduction = floatval($_POST['pagibig_deduction'] ?? 0);
                $tax_deduction = floatval($_POST['tax_deduction'] ?? 0);
                $other_deductions = floatval($_POST['other_deductions'] ?? 0);
                $loans_advances = floatval($_POST['loans_advances'] ?? 0);
                $sss_loan = floatval($_POST['sss_loan'] ?? 0);
                $hdmf_loan = floatval($_POST['hdmf_loan'] ?? 0);
                $calamity_loan = floatval($_POST['calamity_loan'] ?? 0);
                $multipurpose_loan = floatval($_POST['multipurpose_loan'] ?? 0);
                
                // Calculate total overtime pay from entries
                $overtime_pay = 0;
                foreach ($overtime_entries as $entry) {
                    $overtime_pay += floatval($entry['amount']);
                }
                
                // Validate required fields
                if ($pay_period_id <= 0 || $employee_id <= 0) {
                    $error = 'Please select both employee and pay period.';
                    break;
                }
                
                // Get employee information
                $emp_stmt = $conn->prepare("SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) as full_name FROM employees e WHERE e.id = ?");
                $emp_stmt->bind_param("i", $employee_id);
                $emp_stmt->execute();
                $employee_result = $emp_stmt->get_result();
                
                if ($employee_result->num_rows === 0) {
                    $error = 'Selected employee not found.';
                    break;
                }
                
                $employee = $employee_result->fetch_assoc();
                $daily_rate = $employee['daily_rate'];
                
                // Get pay period information
                $period_stmt = $conn->prepare("SELECT period_name, start_date, end_date FROM pay_periods WHERE id = ?");
                $period_stmt->bind_param("i", $pay_period_id);
                $period_stmt->execute();
                $period_result = $period_stmt->get_result();
                
                if ($period_result->num_rows === 0) {
                    $error = 'Selected pay period not found.';
                    break;
                }
                
                $pay_period = $period_result->fetch_assoc();
                
                // Check if payroll record already exists
                $check_stmt = $conn->prepare("SELECT id, status FROM payroll_records WHERE employee_id = ? AND pay_period_id = ?");
                $check_stmt->bind_param("ii", $employee_id, $pay_period_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $is_update = false;
                $existing_record = null;
                
                if ($check_result->num_rows > 0) {
                    $existing_record = $check_result->fetch_assoc();
                    $is_update = true;
                    
                    // Check if the record is already approved or paid
                    if ($existing_record['status'] === 'Approved' || $existing_record['status'] === 'Paid') {
                        $error = "Payroll for {$employee['full_name']} in period '{$pay_period['period_name']}' has already been {$existing_record['status']}. Cannot modify approved/paid records.";
                        break;
                    }
                }
                
                // Calculate payroll components
                // Process days worked entries JSON to calculate accurate basic pay
                $days_worked_entries_json = isset($_POST['days_worked_entries_json']) ? $_POST['days_worked_entries_json'] : '[]';
                error_log("DEBUG: Raw days worked JSON received: " . $days_worked_entries_json);
                $days_worked_entries = json_decode($days_worked_entries_json, true);
                error_log("DEBUG: Decoded days worked entries: " . print_r($days_worked_entries, true));
                error_log("DEBUG: JSON decode error for days worked: " . json_last_error_msg());
                
                // Process overtime entries JSON to calculate accurate overtime pay
                $overtime_entries_json = isset($_POST['overtime_entries_json']) ? $_POST['overtime_entries_json'] : '[]';
                $overtime_entries = json_decode($overtime_entries_json, true);
                
                $basic_pay = 0;
                $total_days_worked = 0;
                if (!empty($days_worked_entries) && is_array($days_worked_entries)) {
                    // Calculate basic pay and total days from days worked entries (matches frontend logic)
                    foreach ($days_worked_entries as $entry) {
                        $days = floatval($entry['days'] ?? 0);
                        $rate = floatval($entry['rate'] ?? $daily_rate);
                        $basic_pay += $days * $rate;
                        $total_days_worked += $days;
                    }
                    // Update days_worked with the calculated total
                    $days_worked = $total_days_worked;
                } else {
                    // Fallback to simple calculation if no entries provided
                    $basic_pay = $daily_rate * $days_worked;
                }
                
                $basic_pay = round($basic_pay, 2) + $additional_payment;
                
                // Calculate overtime pay from JSON entries
                $overtime_pay = 0;
                $total_overtime_hours = 0;
                if (!empty($overtime_entries) && is_array($overtime_entries)) {
                    error_log("DEBUG: Processing " . count($overtime_entries) . " overtime entries for calculation");
                    foreach ($overtime_entries as $entry) {
                        $hours = floatval($entry['hours'] ?? 0);
                        $rate = floatval($entry['rate'] ?? 0);
                        $amount = floatval($entry['amount'] ?? 0);
                        error_log("DEBUG: Entry - Hours: $hours, Rate: $rate, Amount: $amount");
                        $overtime_pay += $amount;
                        $total_overtime_hours += $hours;
                    }
                    error_log("DEBUG: Total calculated overtime_pay: $overtime_pay");
                } else {
                    error_log("DEBUG: No overtime entries found for calculation - overtime_pay will be 0");
                }
                $overtime_pay = round($overtime_pay, 2);
                error_log("DEBUG: Final overtime_pay after rounding: $overtime_pay");
                
                // Calculate late deduction: (late time / 8) / 60 * daily rate
                $late_deduction = ($late_minutes / 8) / 60 * $daily_rate;
                
                // Check if deductions should be applied
                $apply_deductions = isset($_POST['apply_deductions']) && $_POST['apply_deductions'] == '1';
                
                if ($apply_deductions) {
                    // Calculate monthly basic pay for automatic deductions
                    // Convert daily rate to monthly equivalent (22 working days per month)
                    $monthly_basic_pay = $daily_rate * $days_worked;
                    
                    // Check if this is a month-end period (30th) and combine with mid-month period if exists
                    if (strpos($pay_period['period_name'], '30th') !== false || 
                        strpos($pay_period['period_name'], '28th') !== false || 
                        strpos($pay_period['period_name'], '29th') !== false || 
                        strpos($pay_period['period_name'], '31st') !== false) {
                        
                        // Extract month and year from period name
                        $period_parts = explode(' ', $pay_period['period_name']);
                        if (count($period_parts) >= 2) {
                            $month = $period_parts[0]; // e.g., "January"
                            
                            // Look for a corresponding 15th period in the same month
                            $mid_month_period = $month . ' 15th';
                            
                            // Query for payroll in the mid-month period for this employee
                            $mid_month_stmt = $conn->prepare("SELECT pr.basic_pay FROM payroll_records pr 
                                                JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                                                WHERE pr.employee_id = ? AND pp.period_name LIKE ?");
                            $search_period = $mid_month_period . '%'; // Add wildcard to match potential year suffix
                            $mid_month_stmt->bind_param("is", $employee_id, $search_period);
                            $mid_month_stmt->execute();
                            $mid_month_result = $mid_month_stmt->get_result();
                            
                            if ($mid_month_row = $mid_month_result->fetch_assoc()) {
                                // Add the mid-month basic pay to the current monthly basic pay for deduction calculations
                                $monthly_basic_pay += $mid_month_row['basic_pay'];
                            }
                        }
                    }
                    
                    // Calculate automatic deductions based on monthly equivalent
                    $sss_deduction = calculateSSSDeduction($monthly_basic_pay);
                    $philhealth_deduction = calculatePhilHealthDeduction($monthly_basic_pay);
                    $pagibig_deduction = calculatePagIBIGDeduction();
                    $tax_deduction = calculateTaxDeduction($monthly_basic_pay);
                } else {
                    // Set all deductions to 0 if not applying deductions
                    $sss_deduction = 0;
                    $philhealth_deduction = 0;
                    $pagibig_deduction = 0;
                    $tax_deduction = 0;
                }
                
                $total_deductions = $sss_deduction + $philhealth_deduction + $pagibig_deduction + $tax_deduction + $other_deductions + $loans_advances + $sss_loan + $hdmf_loan + $calamity_loan + $multipurpose_loan + $late_deduction;
                $net_pay = $basic_pay + $overtime_pay + $allowances - $total_deductions;
                $thirteenth_month_pay = $basic_pay / 12;
                
                try {
                    // Start transaction to handle payroll record and overtime entries
                    $conn->begin_transaction();
                    
                    if ($is_update) {
                        // Update existing record
                        $stmt = $conn->prepare("UPDATE payroll_records SET days_worked = ?, late_minutes = ?, basic_pay = ?, overtime_pay = ?, allowances = ?, additional_payment = ?, sss_deduction = ?, philhealth_deduction = ?, pagibig_deduction = ?, tax_deduction = ?, other_deductions = ?, loans_advances = ?, sss_loan = ?, hdmf_loan = ?, calamity_loan = ?, multipurpose_loan = ?, late_deduction = ?, total_deductions = ?, net_pay = ?, thirteenth_month_pay = ?, updated_at = CURRENT_TIMESTAMP WHERE employee_id = ? AND pay_period_id = ?");
                        if ($stmt === false) {
                            throw new Exception('Database error: ' . $conn->error);
                        }
                        error_log("DEBUG: Updating payroll_records with overtime_pay: $overtime_pay");
                        if (!$stmt->bind_param("ddddddddddddddddddddii", $days_worked, $late_minutes, $basic_pay, $overtime_pay, $allowances, $additional_payment, $sss_deduction, $philhealth_deduction, $pagibig_deduction, $tax_deduction, $other_deductions, $loans_advances, $sss_loan, $hdmf_loan, $calamity_loan, $multipurpose_loan, $late_deduction, $total_deductions, $net_pay, $thirteenth_month_pay, $employee_id, $pay_period_id)) {
                            throw new Exception('Parameter binding error: ' . $stmt->error);
                        }
                        
                        // Get the payroll record ID
                        $payroll_id = $existing_record['id'];
                        
                        // Delete existing overtime entries
                        $delete_overtime = $conn->prepare("DELETE FROM overtime_entries WHERE payroll_record_id = ?");
                        $delete_overtime->bind_param("i", $payroll_id);
                        $delete_overtime->execute();
                    } else {
                        // Insert new record
                        $stmt = $conn->prepare("INSERT INTO payroll_records (employee_id, pay_period_id, days_worked, late_minutes, basic_pay, overtime_pay, allowances, additional_payment, sss_deduction, philhealth_deduction, pagibig_deduction, tax_deduction, other_deductions, loans_advances, sss_loan, hdmf_loan, calamity_loan, multipurpose_loan, late_deduction, total_deductions, net_pay, thirteenth_month_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt === false) {
                            throw new Exception('Database error: ' . $conn->error);
                        }
                        if (!$stmt->bind_param("iidddddddddddddddddddd", $employee_id, $pay_period_id, $days_worked, $late_minutes, $basic_pay, $overtime_pay, $allowances, $additional_payment, $sss_deduction, $philhealth_deduction, $pagibig_deduction, $tax_deduction, $other_deductions, $loans_advances, $sss_loan, $hdmf_loan, $calamity_loan, $multipurpose_loan, $late_deduction, $total_deductions, $net_pay, $thirteenth_month_pay)) {
                            throw new Exception('Parameter binding error: ' . $stmt->error);
                        }
                    }
                    
                    if ($stmt->execute()) {
                        // Get the payroll record ID for inserting overtime entries
                        if (!$is_update) {
                            $payroll_id = $conn->insert_id;
                        }
                        
                        // Insert overtime entries
                        error_log("DEBUG: Overtime entries check - Count: " . count($overtime_entries));
                        error_log("DEBUG: Overtime entries data: " . print_r($overtime_entries, true));
                        
                        if (!empty($overtime_entries)) {
                            error_log("DEBUG: Preparing overtime insertion for payroll_id: " . $payroll_id);
                            $overtime_stmt = $conn->prepare("INSERT INTO overtime_entries (payroll_record_id, overtime_hours, overtime_rate, overtime_pay, overtime_type) VALUES (?, ?, ?, ?, ?)");
                            
                            if ($overtime_stmt === false) {
                                error_log("DEBUG: Failed to prepare overtime statement: " . $conn->error);
                            } else {
                                foreach ($overtime_entries as $entry) {
                                    $hours = floatval($entry['hours']);
                                    $rate = floatval($entry['rate']);
                                    $amount = floatval($entry['amount']);
                                    $type = $entry['type'];
                                    
                                    error_log("DEBUG: Raw entry data: " . print_r($entry, true));
                                    error_log("DEBUG: Inserting overtime - Hours: $hours, Rate: $rate, Amount: $amount, Type: $type");
                                    
                                    if ($overtime_stmt->bind_param("iddds", $payroll_id, $hours, $rate, $amount, $type)) {
                                        if ($overtime_stmt->execute()) {
                                            error_log("DEBUG: Overtime entry inserted successfully");
                                        } else {
                                            error_log("DEBUG: Failed to execute overtime insertion: " . $overtime_stmt->error);
                                        }
                                    } else {
                                        error_log("DEBUG: Failed to bind overtime parameters: " . $overtime_stmt->error);
                                    }
                                }
                            }
                        } else {
                            error_log("DEBUG: No overtime entries to insert (empty array)");
                        }
                        
                        // Commit the transaction
                        $conn->commit();
                        
                        if ($is_update) {
                            $message = "Payroll record updated successfully for {$employee['full_name']} in period '{$pay_period['period_name']}'.";                        } else {
                            $message = "Payroll calculated and saved successfully for {$employee['full_name']} in period '{$pay_period['period_name']}'.";                        }
                        
                        // Automatic status change: Update period status based on current state
                        $status_check = $conn->prepare("SELECT status FROM pay_periods WHERE id = ?");
                        $status_check->bind_param("i", $pay_period_id);
                        $status_check->execute();
                        $status_result = $status_check->get_result();
                        if ($status_row = $status_result->fetch_assoc()) {
                            $current_status = $status_row['status'];
                            
                            // If period is Draft, set to Processing
                            if ($current_status === 'Draft') {
                                $update_period = $conn->prepare("UPDATE pay_periods SET status = 'Processing' WHERE id = ?");
                                $update_period->bind_param("i", $pay_period_id);
                                $update_period->execute();
                            }
                            // If period is Completed, reset to Processing since there are now new/pending payrolls
                            elseif ($current_status === 'Completed') {
                                $update_period = $conn->prepare("UPDATE pay_periods SET status = 'Processing' WHERE id = ?");
                                $update_period->bind_param("i", $pay_period_id);
                                $update_period->execute();
                                
                                // Add a note to the success message
                                $message .= " Period status has been reset to 'Processing' due to new payroll calculation.";
                            }
                        }
                    } else {
                        // Rollback the transaction if there's an error
                        $conn->rollback();
                        throw new Exception('Execution error: ' . $stmt->error);
                    }
                    
                } catch (Exception $e) {
                    // Check if it's a duplicate key error
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'unique_employee_period') !== false) {
                        $error = "Payroll for {$employee['full_name']} in period '{$pay_period['period_name']}' already exists. Please use the update option or delete the existing record first.";
                    } else {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'approve_payroll':
                $payroll_id = intval($_POST['payroll_id']);
                $stmt = $conn->prepare("UPDATE payroll_records SET status = 'Approved' WHERE id = ?");
                $stmt->bind_param("i", $payroll_id);
                
                if ($stmt->execute()) {
                    $message = 'Payroll approved successfully.';
                } else {
                    $error = 'Error approving payroll: ' . $stmt->error;
                }
                break;

            case 'delete_payroll':
                $payroll_id = intval($_POST['payroll_id']);
                $stmt = $conn->prepare("DELETE FROM payroll_records WHERE id = ?");
                $stmt->bind_param("i", $payroll_id);
                if ($stmt->execute()) {
                    $message = 'Payroll record deleted successfully.';
                } else {
                    $error = 'Error deleting payroll record: ' . $stmt->error;
                }
                break;

            case 'delete_pay_period':
                $pay_period_id = intval($_POST['pay_period_id']);
                
                // First, check if the pay period exists and get its details
                $check_stmt = $conn->prepare("SELECT period_name, status FROM pay_periods WHERE id = ?");
                $check_stmt->bind_param("i", $pay_period_id);
                $check_stmt->execute();
                $period_result = $check_stmt->get_result();
                
                if ($period_result->num_rows === 0) {
                    $error = 'Pay period not found.';
                    break;
                }
                
                $period = $period_result->fetch_assoc();
                
                // Check if there are any payroll records for this period
                $records_stmt = $conn->prepare("SELECT COUNT(*) as count FROM payroll_records WHERE pay_period_id = ?");
                $records_stmt->bind_param("i", $pay_period_id);
                $records_stmt->execute();
                $records_result = $records_stmt->get_result();
                $records_count = $records_result->fetch_assoc()['count'];
                
                // Check if there are any reports for this period
                $reports_stmt = $conn->prepare("SELECT COUNT(*) as count FROM payroll_reports WHERE pay_period_id = ?");
                $reports_stmt->bind_param("i", $pay_period_id);
                $reports_stmt->execute();
                $reports_result = $reports_stmt->get_result();
                $reports_count = $reports_result->fetch_assoc()['count'];
                
                try {
                    $stmt = $conn->prepare("DELETE FROM pay_periods WHERE id = ?");
                    $stmt->bind_param("i", $pay_period_id);
                    
                    if ($stmt->execute()) {
                        $message = "Pay period '{$period['period_name']}' deleted successfully.";
                        if ($records_count > 0) {
                            $message .= " {$records_count} payroll record(s) were also deleted.";
                        }
                        if ($reports_count > 0) {
                            $message .= " {$reports_count} report(s) were also deleted.";
                        }
                    } else {
                        $error = 'Error deleting pay period: ' . $stmt->error;
                    }
                } catch (Exception $e) {
                    $error = 'Error deleting pay period: ' . $e->getMessage();
                }
                break;

            // Add a case for marking payroll as Paid (if not present)
            case 'mark_paid':
                $payroll_id = intval($_POST['payroll_id']);
                // Set payroll record to Paid
                $stmt = $conn->prepare("UPDATE payroll_records SET status = 'Paid' WHERE id = ?");
                $stmt->bind_param("i", $payroll_id);
                if ($stmt->execute()) {
                    // Get pay_period_id for this payroll record
                    $period_stmt = $conn->prepare("SELECT pay_period_id FROM payroll_records WHERE id = ?");
                    $period_stmt->bind_param("i", $payroll_id);
                    $period_stmt->execute();
                    $period_result = $period_stmt->get_result();
                    if ($period_row = $period_result->fetch_assoc()) {
                        $pay_period_id = $period_row['pay_period_id'];
                        // Check if all payroll records for this period are Paid
                        $check_all = $conn->prepare("SELECT COUNT(*) as total, SUM(status = 'Paid') as paid_count FROM payroll_records WHERE pay_period_id = ?");
                        $check_all->bind_param("i", $pay_period_id);
                        $check_all->execute();
                        $all_result = $check_all->get_result();
                        if ($all_row = $all_result->fetch_assoc()) {
                            if ($all_row['total'] > 0 && $all_row['total'] == $all_row['paid_count']) {
                                // Set pay period to Completed
                                $update_period = $conn->prepare("UPDATE pay_periods SET status = 'Completed' WHERE id = ?");
                                $update_period->bind_param("i", $pay_period_id);
                                $update_period->execute();
                            }
                        }
                    }
                    $message = 'Payroll marked as paid.';
                } else {
                    $error = 'Error marking payroll as paid: ' . $stmt->error;
                }
                break;
        }
    }
}

// Get pay periods
$pay_periods = $conn->query("SELECT * FROM pay_periods ORDER BY start_date DESC");

// Get employees
$employees = $conn->query("SELECT * FROM employees ORDER BY last_name, first_name");

// Get payroll records with duplicate detection
$payroll_records = $conn->query("
    SELECT pr.*, e.first_name, e.last_name, e.employee_number, pp.period_name,
           COUNT(*) OVER (PARTITION BY pr.employee_id, pr.pay_period_id) as record_count
    FROM payroll_records pr 
    JOIN employees e ON pr.employee_id = e.id 
    JOIN pay_periods pp ON pr.pay_period_id = pp.id 
    ORDER BY pr.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - MOR Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-calculator me-2"></i>
                        Payroll Management
                    </h2>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPeriodModal">
                            <i class="fas fa-plus me-2"></i>Create Pay Period
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Pay Periods -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar me-2"></i>Pay Periods
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Period Name</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($period = $pay_periods->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($period['period_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($period['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($period['end_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $period['status'] === 'Completed' ? 'success' : 'warning'; ?>">
                                                <?php echo htmlspecialchars($period['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary calculate-payroll-btn" data-id="<?php echo $period['id']; ?>" data-name="<?php echo htmlspecialchars($period['period_name']); ?>">
                                                <i class="fas fa-calculator me-1"></i>Calculate Payroll
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-pay-period-btn" data-id="<?php echo $period['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Payroll Records -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Payroll Records
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Pay Period</th>
                                        <th>Basic Pay</th>
                                        <th>Overtime</th>
                                        <th>Allowances</th>
                                        <th>Deductions</th>
                                        <th>Net Pay</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($record = $payroll_records->fetch_assoc()): ?>
                                    <tr class="<?php echo $record['record_count'] > 1 ? 'table-warning' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['employee_number']); ?></small>
                                            <?php if ($record['record_count'] > 1): ?>
                                            <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Duplicate Record (<?php echo $record['record_count']; ?> entries)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['period_name']); ?></td>
                                        <td>₱<?php echo number_format($record['basic_pay'], 2); ?></td>
                                        <td>₱<?php echo number_format($record['overtime_pay'], 2); ?></td>
                                        <td>₱<?php echo number_format($record['allowances'], 2); ?></td>
                                        <td>₱<?php echo number_format($record['total_deductions'], 2); ?></td>
                                        <td><strong>₱<?php echo number_format($record['net_pay'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $record['status'] === 'Paid' ? 'success' : ($record['status'] === 'Approved' ? 'warning' : 'secondary'); ?>">
                                                <?php echo htmlspecialchars($record['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-payroll-btn" data-id="<?php echo $record['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($record['status'] === 'Draft'): ?>
                                            <button class="btn btn-sm btn-success approve-payroll-btn" data-id="<?php echo $record['id']; ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($record['status'] === 'Approved'): ?>
                                            <button class="btn btn-sm btn-success mark-paid-btn" data-id="<?php echo $record['id']; ?>">
                                                <i class="fas fa-money-bill-wave"></i> Mark Paid
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger delete-payroll-btn" data-id="<?php echo $record['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Pay Period Modal -->
    <div class="modal fade" id="createPeriodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-plus me-2"></i>Create Pay Period
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_period">
                        <div class="mb-3">
                            <label for="period_name" class="form-label">Period Name *</label>
                            <select class="form-select" id="period_name" name="period_name" required>
                                <option value="">Select Period</option>
                                <option value="January 15th">January 15th</option>
                                <option value="January 30th">January 30th</option>
                                <option value="February 15th">February 15th</option>
                                <option value="February 28th">February 28th</option>
                                <option value="March 15th">March 15th</option>
                                <option value="March 30th">March 30th</option>
                                <option value="April 15th">April 15th</option>
                                <option value="April 30th">April 30th</option>
                                <option value="May 15th">May 15th</option>
                                <option value="May 30th">May 30th</option>
                                <option value="June 15th">June 15th</option>
                                <option value="June 30th">June 30th</option>
                                <option value="July 15th">July 15th</option>
                                <option value="July 30th">July 30th</option>
                                <option value="August 15th">August 15th</option>
                                <option value="August 30th">August 30th</option>
                                <option value="September 15th">September 15th</option>
                                <option value="September 30th">September 30th</option>
                                <option value="October 15th">October 15th</option>
                                <option value="October 30th">October 30th</option>
                                <option value="November 15th">November 15th</option>
                                <option value="November 30th">November 30th</option>
                                <option value="December 15th">December 15th</option>
                                <option value="December 30th">December 30th</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date *</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Period
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Calculate Payroll Modal -->
    <div class="modal fade" id="calculatePayrollModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calculator me-2"></i>Calculate Payroll
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="payrollForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="calculate_payroll">
                        <input type="hidden" name="pay_period_id" id="pay_period_id">
                        
                        <!-- Warning for existing payroll records -->
                        <div id="existing_payroll_warning" style="display: none;"></div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Employee *</label>
                                    <select class="form-select" id="employee_id" name="employee_id" required onchange="loadEmployeeRate(); document.getElementById('deduction_info').innerHTML = '';">
                                        <option value="">Select Employee</option>
                                        <?php 
                                        $employees->data_seek(0);
                                        while ($employee = $employees->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $employee['id']; ?>" data-rate="<?php echo $employee['daily_rate']; ?>" data-rate-type="<?php echo $employee['rate_type']; ?>">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Daily Rate</label>
                                    <div class="form-control-plaintext" id="daily_rate_display">₱0.00</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Days Worked Entries</label>
                                    <div id="days-worked-entries-container">
                                        <div class="days-worked-entry mb-2">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <input type="number" step="0.01" class="form-control days-worked-count" placeholder="Days" value="0">
                                                </div>
                                                <div class="col-md-7">
                                                    <select class="form-select days-worked-type">
                                                        <option value="regular_day">Regular Day</option>
                                                        <option value="rest_day">Rest Day</option>
                                                        <option value="rest_day_special_holiday">Rest Day and Special Holiday</option>
                                                        <option value="regular_holiday_ordinary_day">Regular Holiday - Ordinary Day</option>
                                                        <option value="rest_day_regular_holiday">Rest Day and Regular Holiday</option>
                                                        <option value="special_holiday_ordinary_day">Special Holiday- Ordinary Day</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-1 d-flex align-items-center">
                                                    <button type="button" class="btn btn-sm btn-danger remove-days-worked" style="display:none;"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-primary add-days-worked" id="add-daysworked-btn">
                                            <i class="fas fa-plus me-1"></i>Add Days Worked
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <table class="table table-sm table-bordered" id="days-worked-summary-table" style="display:none;">
                                            <thead>
                                                <tr>
                                                    <th>Days</th>
                                                    <th>Amount</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="days-worked-summary-body">
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- Hidden fields to store data for form submission -->
                                    <input type="hidden" id="days_worked_entries_json" name="days_worked_entries_json" value="[]">
                                    <input type="hidden" id="overtime_entries_json" name="overtime_entries_json" value="[]">
                                </div> 
                                <div class="mb-3">
                                    <label for="late_minutes" class="form-label">Late (Minutes)</label>
                                    <input type="number" step="0.01" class="form-control" id="late_minutes" name="late_minutes" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Overtime Entries</label>
                                    <div id="overtime-entries-container">
                                        <div class="overtime-entry mb-2">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <input type="number" step="0.01" class="form-control overtime-hours" placeholder="Hours" value="0">
                                                </div>
                                                <div class="col-md-7">
                                                    <select class="form-select overtime-type">
                                                        <option value="0">No Overtime</option>
                                                        <option value="ordinary_day_ot">Ordinary Day OT</option>
                                                        <option value="rest_day_special_holiday_ot">Rest Day/Special Holiday OT</option>
                                                        <option value="rest_day_and_special_holiday_ot">Rest Day and Special Holiday OT</option>
                                                        <option value="regular_holiday_ordinary_day_ot">Regular Holiday - Ordinary Day OT</option>
                                                        <option value="rest_day_regular_holiday_ot_special">Rest Day and Regular Holiday OT</option>
                                                        <option value="night_differential_ordinary_day">Night Differential - Ordinary Day</option>
                                                        <option value="night_differential_rest_day">Night Differential - Rest Day</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-1 d-flex align-items-center">
                                                    <button type="button" class="btn btn-sm btn-danger remove-overtime" style="display:none;"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-primary" id="add-overtime-btn">
                                            <i class="fas fa-plus me-1"></i>Add Overtime
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <table class="table table-sm table-bordered" id="overtime-summary-table" style="display:none;">
                                            <thead>
                                                <tr>
                                                    <th>Hours</th>
                                                    <th>Amount</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="overtime-summary-body">
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- Hidden fields to store overtime data for form submission -->
                                    <input type="hidden" id="overtime_entries_json" name="overtime_entries_json" value="[]">
                                </div>
                                <div class="mb-3">
                                    <label for="allowances" class="form-label">Allowances</label>
                                    <input type="number" step="0.01" class="form-control" id="allowances" name="allowances" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <label for="additional_payment" class="form-label">Additional Payment</label>
                                    <input type="number" step="0.01" class="form-control" id="additional_payment" name="additional_payment" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="apply_deductions" name="apply_deductions" value="1" checked onchange="toggleDeductions()">
                                        <label class="form-check-label" for="apply_deductions">
                                            Apply Automatic Deductions
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sss_deduction" class="form-label">SSS Deduction (Auto-calculated)</label>
                                    <input type="number" step="0.01" class="form-control" id="sss_deduction" name="sss_deduction" value="0" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="philhealth_deduction" class="form-label">PhilHealth Deduction (Auto-calculated)</label>
                                    <input type="number" step="0.01" class="form-control" id="philhealth_deduction" name="philhealth_deduction" value="0" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="pagibig_deduction" class="form-label">Pag-IBIG Deduction (Auto-calculated)</label>
                                    <input type="number" step="0.01" class="form-control" id="pagibig_deduction" name="pagibig_deduction" value="0" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="tax_deduction" class="form-label">Tax Deduction (Auto-calculated)</label>
                                    <input type="number" step="0.01" class="form-control" id="tax_deduction" name="tax_deduction" value="0" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="other_deductions" class="form-label">Other Deductions</label>
                                    <input type="number" step="0.01" class="form-control" id="other_deductions" name="other_deductions" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <label for="loans_advances" class="form-label">Loans/Advances</label>
                                    <input type="number" step="0.01" class="form-control" id="loans_advances" name="loans_advances" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <label for="sss_loan" class="form-label">SSS Loan</label>
                                    <input type="number" step="0.01" class="form-control" id="sss_loan" name="sss_loan" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <label for="hdmf_loan" class="form-label">MPII Savings</label>
                                    <input type="number" step="0.01" class="form-control" id="hdmf_loan" name="hdmf_loan" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <label for="calamity_loan" class="form-label">Calamity Loan</label>
                                    <input type="number" step="0.01" class="form-control" id="calamity_loan" name="calamity_loan" value="0" onchange="calculatePayroll()">
                                </div>
                                <div class="mb-3">
                                    <label for="multipurpose_loan" class="form-label">Multi-Purpose Loan</label>
                                    <input type="number" step="0.01" class="form-control" id="multipurpose_loan" name="multipurpose_loan" value="0" onchange="calculatePayroll()">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Deduction Information -->
                        <div id="deduction_info"></div>
                        
                        <!-- Calculation Results -->
                        <div class="card bg-light">
                            <div class="card-header">
                                <h6 class="mb-0">Calculation Results</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Basic Pay:</strong> ₱<span id="basic_pay_result">0.00</span></p>
                                        <p><strong>Overtime Pay:</strong> ₱<span id="overtime_pay_result">0.00</span></p>
                                        <p><strong>Total Deductions:</strong> ₱<span id="total_deductions_result">0.00</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Net Pay:</strong> ₱<span id="net_pay_result">0.00</span></p>
                                        <p><strong>13th Month Pay:</strong> ₱<span id="thirteenth_month_result">0.00</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Payroll
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Payroll Modal -->
    <div class="modal fade" id="approvePayrollModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>Approve Payroll
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve_payroll">
                        <input type="hidden" name="approve_payroll_id" id="approve_payroll_id">
                        <p>Are you sure you want to approve this payroll record? This will mark it as ready for payment.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Payroll Modal -->
    <div class="modal fade" id="deletePayrollModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>Delete Payroll
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_payroll">
                        <input type="hidden" name="delete_payroll_id" id="delete_payroll_id">
                        <p>Are you sure you want to delete this payroll record? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Pay Period Modal -->
    <div class="modal fade" id="deletePayPeriodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>Delete Pay Period
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_pay_period">
                        <input type="hidden" name="delete_pay_period_id" id="delete_pay_period_id">
                        <p>Are you sure you want to delete this pay period? This will also delete all payroll records associated with this period.</p>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>Warning: This action cannot be undone!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Mark Paid Modal -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave me-2"></i>Mark as Paid
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="mark_paid_id" id="mark_paid_id">
                        <p>Are you sure you want to mark this payroll record as paid? This indicates that payment has been processed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Mark as Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and buttons.js are now loaded in header.php -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        // openCalculatePayrollModal function moved to buttons.js
        
        function loadEmployeeRate() {
            const employeeSelect = document.getElementById('employee_id');
            const selectedOption = employeeSelect.options[employeeSelect.selectedIndex];
            const dailyRate = selectedOption.getAttribute('data-rate');
            const rateType = selectedOption.getAttribute('data-rate-type');
            
            if (dailyRate) {
                let rateDisplay = '₱' + parseFloat(dailyRate).toFixed(2);
                if (rateType === 'Monthly') {
                    // Show both monthly and daily rate for monthly employees
                    const monthlyRate = parseFloat(dailyRate) * 22; // 22 working days per month
                    rateDisplay = '₱' + monthlyRate.toFixed(2) + ' monthly (₱' + parseFloat(dailyRate).toFixed(2) + ' daily)';
                } else {
                    rateDisplay = '₱' + parseFloat(dailyRate).toFixed(2) + ' daily';
                }
                document.getElementById('daily_rate_display').textContent = rateDisplay;
            }
            
            // Check for existing payroll record
            checkExistingPayroll();
            
            // Auto-calculate deductions when employee is selected
            calculatePayroll();
        }
        
        function checkExistingPayroll() {
            const employeeId = document.getElementById('employee_id').value;
            const payPeriodId = document.getElementById('pay_period_id').value;
            
            if (employeeId && payPeriodId) {
                fetch('payroll_check_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'employee_id=' + employeeId + '&pay_period_id=' + payPeriodId
                })
                .then(response => response.json())
                .then(data => {
                    const warningDiv = document.getElementById('existing_payroll_warning');
                    if (data.exists) {
                        warningDiv.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> A payroll record already exists for this employee in the selected pay period.
                                <br><small>Status: ${data.status}</small>
                                <br><small>You can update the existing record or delete it first.</small>
                            </div>
                        `;
                        warningDiv.style.display = 'block';
                    } else {
                        warningDiv.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error checking existing payroll:', error);
                });
            }
        }
        
        // JavaScript versions of the deduction calculation functions
        function calculateSSSDeductionJS(monthlyBasicPay) {
            if (monthlyBasicPay < 5250) {
                return 250;
            } else if (monthlyBasicPay >= 5250 && monthlyBasicPay <= 5750) {
                return 275;
            } else if (monthlyBasicPay >= 5750 && monthlyBasicPay <= 6250) {
                return 300;
            } else if (monthlyBasicPay >= 6250 && monthlyBasicPay <= 6750) {
                return 325;
            } else if (monthlyBasicPay >= 6750 && monthlyBasicPay <= 7250) {
                return 350;
            } else if (monthlyBasicPay >= 7250 && monthlyBasicPay <= 7750) {
                return 375;
            } else if (monthlyBasicPay >= 7750 && monthlyBasicPay <= 8250) {
                return 400;
            } else if (monthlyBasicPay >= 8250 && monthlyBasicPay <= 8750) {
                return 425;
            } else if (monthlyBasicPay >= 8750 && monthlyBasicPay <= 9250) {
                return 450;
            } else if (monthlyBasicPay >= 9250 && monthlyBasicPay <= 9750) {
                return 475;
            } else if (monthlyBasicPay >= 9750 && monthlyBasicPay <= 10250) {
                return 500;
            } else if (monthlyBasicPay >= 10250 && monthlyBasicPay <= 10750) {
                return 525;
            } else if (monthlyBasicPay >= 10750 && monthlyBasicPay <= 11250) {
                return 550;
            } else if (monthlyBasicPay >= 11250 && monthlyBasicPay <= 11750) {
                return 575;
            } else if (monthlyBasicPay >= 11750 && monthlyBasicPay <= 12250) {
                return 600;
            } else if (monthlyBasicPay >= 12250 && monthlyBasicPay <= 12750) {
                return 625;
            } else if (monthlyBasicPay >= 12750 && monthlyBasicPay <= 13250) {
                return 650;
            } else if (monthlyBasicPay >= 13250 && monthlyBasicPay <= 13750) {
                return 675;
            } else if (monthlyBasicPay >= 13750 && monthlyBasicPay <= 14250) {
                return 700;
            } else if (monthlyBasicPay >= 14250 && monthlyBasicPay <= 14750) {
                return 725;
            } else if (monthlyBasicPay >= 14750 && monthlyBasicPay <= 15250) {
                return 750;
            } else if (monthlyBasicPay >= 15250 && monthlyBasicPay <= 15750) {
                return 775;
            } else if (monthlyBasicPay >= 15750 && monthlyBasicPay <= 16250) {
                return 800;
            } else if (monthlyBasicPay >= 16250 && monthlyBasicPay <= 16750) {
                return 825;
            } else if (monthlyBasicPay >= 16750 && monthlyBasicPay <= 17250) {
                return 850;
            } else if (monthlyBasicPay >= 17250 && monthlyBasicPay <= 17750) {
                return 875;
            } else if (monthlyBasicPay >= 17750 && monthlyBasicPay <= 18250) {
                return 900;
            } else if (monthlyBasicPay >= 18250 && monthlyBasicPay <= 18750) {
                return 925;
            } else if (monthlyBasicPay >= 18750 && monthlyBasicPay <= 19250) {
                return 950;
            } else if (monthlyBasicPay >= 19250 && monthlyBasicPay <= 19750) {
                return 975;
            } else if (monthlyBasicPay >= 19750 && monthlyBasicPay <= 20250) {
                return 1000;
            } else {
                // For amounts 20250 and above, continue adding 25 for each 500 range
                const baseAmount = 20250;
                const baseDeduction = 1000;
                const rangeSize = 500;
                const additionalRanges = Math.ceil((monthlyBasicPay - baseAmount) / rangeSize);
                return baseDeduction + (additionalRanges * 25);
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

        function calculateTaxDeductionJS(monthlyBasicPay) {
            // Ensure we're working with a valid number
            monthlyBasicPay = parseFloat(monthlyBasicPay) || 0;
            
            if (monthlyBasicPay < 10417) {
                return 0;
            } else if (monthlyBasicPay >= 10417 && monthlyBasicPay <= 16666) {
                return (0 + (0.15 * (monthlyBasicPay)));
            } else if (monthlyBasicPay > 16666 && monthlyBasicPay <= 33332) {
                return (937.50 + (0.20 * monthlyBasicPay));
            } else if (monthlyBasicPay > 33332 && monthlyBasicPay <= 83332) {
                return (4270.70 + (0.25 * monthlyBasicPay));
            } else if (monthlyBasicPay > 83332 && monthlyBasicPay <= 333332) {
                return (16770.70 + (0.30 * monthlyBasicPay));
            } else if (monthlyBasicPay > 333332) {
                return (91770.70 + (0.35 * monthlyBasicPay));
            }
            return 0; // Default return for any edge cases
        }

        function toggleDeductions() {
            const applyDeductions = document.getElementById('apply_deductions').checked;
            const deductionFields = ['sss_deduction', 'philhealth_deduction', 'pagibig_deduction', 'tax_deduction'];
            
            deductionFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (applyDeductions) {
                    field.style.backgroundColor = '#f8f9fa';
                    field.style.color = '#495057';
                } else {
                    field.style.backgroundColor = '#e9ecef';
                    field.style.color = '#6c757d';
                }
            });
            
            // Recalculate payroll when toggle changes
            calculatePayroll();
        }

        // Add event listeners for overtime entries
        document.addEventListener('DOMContentLoaded', function() {
            // Check if jQuery is loaded
            if (typeof jQuery !== 'undefined') {
                console.log('jQuery is loaded!');
            } else {
                console.error('jQuery is NOT loaded!');
            }
            
            // Add overtime entry button
            document.getElementById('add-overtime-btn').addEventListener('click', function() {
                addOvertimeEntry();
            });
            
            // Add days worked entry button
            document.querySelector('.add-days-worked').addEventListener('click', function() {
                addDaysWorkedEntry();
            });
            
            // Add direct event listeners to key input fields
            const daysWorkedInput = document.getElementById('days_worked');
            if (daysWorkedInput) {
                daysWorkedInput.addEventListener('input', function() {
                    console.log('Days worked changed to:', this.value);
                    calculatePayroll();
                    updateDaysWorkedSummary();
                });
            }
            
            const employeeSelect = document.getElementById('employee_id');
            if (employeeSelect) {
                employeeSelect.addEventListener('change', function() {
                    console.log('Employee changed to:', this.options[this.selectedIndex]?.text);
                    loadEmployeeRate();
                });
            }
            
            // Initial calculation
            calculatePayroll();
            
            // Add event listeners to the first overtime entry
            setupOvertimeEntryListeners(document.querySelector('.overtime-entry'));
            
            // Add form submission handler to collect days worked entries
            const payrollForm = document.getElementById('payrollForm');
            if (payrollForm) {
                payrollForm.addEventListener('submit', function(e) {
                    // Always prevent default submission
                    e.preventDefault();
                    
                    console.log('=== FORM SUBMISSION DEBUG ===');
                    
                    // Collect data immediately and synchronously
                    collectDaysWorkedEntries();
                    collectOvertimeEntries();
                    
                    // Force a small delay to ensure DOM updates
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            // Get the final values
                            const overtimeField = document.getElementById('overtime_entries_json');
                            const daysWorkedField = document.getElementById('days_worked_entries_json');
                            
                            console.log('Submitting with overtime data:', overtimeField?.value);
                            console.log('Submitting with days worked data:', daysWorkedField?.value);
                            
                            // Create form data manually to ensure proper encoding
                            const formData = new FormData();
                            
                            // Add all form fields manually
                            const formElements = payrollForm.elements;
                            for (let i = 0; i < formElements.length; i++) {
                                const element = formElements[i];
                                if (element.name && element.type !== 'submit') {
                                    if (element.name === 'overtime_entries_json') {
                                        formData.append(element.name, overtimeField?.value || '[]');
                                        console.log('Added overtime_entries_json:', overtimeField?.value || '[]');
                                    } else if (element.name === 'days_worked_entries_json') {
                                        formData.append(element.name, daysWorkedField?.value || '[]');
                                        console.log('Added days_worked_entries_json:', daysWorkedField?.value || '[]');
                                    } else if (element.type === 'checkbox' || element.type === 'radio') {
                                        if (element.checked) {
                                            formData.append(element.name, element.value);
                                        }
                                    } else {
                                        formData.append(element.name, element.value);
                                    }
                                }
                            }
                            
                            // Debug: Log all FormData entries
                            console.log('FormData contents:');
                            for (let [key, value] of formData.entries()) {
                                console.log(key + ':', value);
                            }
                            
                            // Submit via fetch
                            const formAction = payrollForm.getAttribute('action') || window.location.href;
                            console.log('Form action URL:', formAction);
                            
                            fetch(formAction, {
                                method: 'POST',
                                body: formData
                            }).then(response => {
                                if (response.ok) {
                                    // Redirect to avoid duplicate submission
                                    window.location.href = window.location.href.split('?')[0];
                                } else {
                                    console.error('Form submission failed');
                                    alert('Form submission failed. Please try again.');
                                }
                            }).catch(error => {
                                console.error('Form submission error:', error);
                                alert('Form submission error. Please try again.');
                            });
                        });
                    });
                });
            }
            
            // Add event listeners to the first days worked entry
            setupDaysWorkedEntryListeners(document.querySelector('.days-worked-entry'));
            
            // Initialize summaries
            updateOvertimeSummary();
            updateDaysWorkedSummary();
        });
        
        function setupOvertimeEntryListeners(entryElement) {
            const hoursInput = entryElement.querySelector('.overtime-hours');
            const typeSelect = entryElement.querySelector('.overtime-type');
            const removeButton = entryElement.querySelector('.remove-overtime');
            
            hoursInput.addEventListener('change', function() {
                calculatePayroll();
                updateOvertimeSummary();
            });
            
            typeSelect.addEventListener('change', function() {
                calculatePayroll();
                updateOvertimeSummary();
            });
            
            removeButton.addEventListener('click', function() {
                entryElement.remove();
                calculatePayroll();
                updateOvertimeSummary();
                
                // Show/hide remove buttons based on number of entries
                toggleRemoveButtons();
            });
        }
        
        function addOvertimeEntry() {
            const container = document.getElementById('overtime-entries-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'overtime-entry mb-2';
            newEntry.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <input type="number" step="0.01" class="form-control overtime-hours" placeholder="Hours" value="0">
                    </div>
                    <div class="col-md-7">
                        <select class="form-select overtime-type">
                            <option value="0">No Overtime</option>
                            <option value="ordinary_day_ot">Ordinary Day OT</option>
                            <option value="rest_day_special_holiday_ot">Rest Day/Special Holiday OT</option>
                            <option value="rest_day_and_special_holiday_ot">Rest Day and Special Holiday OT</option>
                            <option value="regular_holiday_ordinary_day_ot">Regular Holiday - Ordinary Day OT</option>
                            <option value="rest_day_regular_holiday_ot_special">Rest Day and Regular Holiday OT</option>
                            <option value="night_differential_ordinary_day">Night Differential - Ordinary Day</option>
                            <option value="night_differential_rest_day">Night Differential - Rest Day</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-danger remove-overtime"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            `;
            
            container.appendChild(newEntry);
            
            // Add event listeners to the new entry
            setupOvertimeEntryListeners(newEntry);
            
            // Show/hide remove buttons based on number of entries
            toggleRemoveButtons();
            
            // Update calculations
            calculatePayroll();
            updateOvertimeSummary();
        }
        
        function toggleRemoveButtons() {
            const entries = document.querySelectorAll('.overtime-entry');
            const removeButtons = document.querySelectorAll('.remove-overtime');
            
            // Show remove buttons only if there's more than one entry
            if (entries.length > 1) {
                removeButtons.forEach(button => {
                    button.style.display = 'block';
                });
            } else {
                removeButtons.forEach(button => {
                    button.style.display = 'none';
                });
            }
            
            // Do the same for days worked entries
            const daysEntries = document.querySelectorAll('.days-worked-entry');
            const daysRemoveButtons = document.querySelectorAll('.remove-days-worked');
            
            // Show remove buttons only if there's more than one entry
            if (daysEntries.length > 1) {
                daysRemoveButtons.forEach(button => {
                    button.style.display = 'block';
                });
            } else {
                daysRemoveButtons.forEach(button => {
                    button.style.display = 'none';
                });
            }
        }
        
        function updateOvertimeSummary() {
            const entries = document.querySelectorAll('.overtime-entry');
            const summaryTable = document.getElementById('overtime-summary-table');
            const summaryBody = document.getElementById('overtime-summary-body');
            const overtimeEntriesJson = document.getElementById('overtime_entries_json');
            const dailyRate = parseFloat(document.getElementById('employee_id').options[document.getElementById('employee_id').selectedIndex].getAttribute('data-rate')) || 0;
            
            // Clear the summary table
            summaryBody.innerHTML = '';
            
            // Prepare the JSON data
            const overtimeData = [];
            
            // Check if there are any valid overtime entries
            let hasValidEntries = false;
            
            entries.forEach((entry, index) => {
                const hours = parseFloat(entry.querySelector('.overtime-hours').value) || 0;
                const overtimeType = entry.querySelector('.overtime-type').value;
                const typeText = entry.querySelector('.overtime-type option:checked').text;
                
                // Calculate rate based on daily rate and overtime type
                let rate = 0;
                if (overtimeType !== '0') {
                    switch(overtimeType) {
                        case 'ordinary_day_ot':
                            rate = [(dailyRate / 8) * 1.25];
                            break;
                        case 'rest_day_special_holiday_ot':
                            rate = [(dailyRate / 8) * 1.69];
                            break;
                        case 'rest_day_and_special_holiday_ot':
                            rate = [(dailyRate / 8) * 1.95];
                            break;
                        case 'regular_holiday_ordinary_day_ot':
                            rate = [(dailyRate / 8) * 2.6];
                            break;
                        case 'rest_day_regular_holiday_ot':
                            rate = [(dailyRate / 8) * 1.3];
                            break;
                        case 'rest_day_regular_holiday_ot_special':
                            rate = (((dailyRate / 8) * 2.6) * 1.3);
                            break;
                        case 'night_differential_ordinary_day':
                            rate = [(dailyRate / 8) * 0.1];
                            break;
                        case 'night_differential_rest_day':
                            rate = [(dailyRate / 8) * 1.3] * 1.1;
                            break;
                        default:
                            // For backward compatibility with numeric values
                            rate = parseFloat(overtimeType) || 0;
                    }
                }
                
                // Skip entries with 0 hours or 0 rate
                if (hours === 0 || rate === 0) return;
                
                hasValidEntries = true;
                const amount = hours * rate;
                
                // Add to the summary table
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${hours}</td>
                    <td>₱${amount.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeOvertimeEntry(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                summaryBody.appendChild(row);
                
                // Add to the JSON data
                overtimeData.push({
                    hours: hours,
                    rate: rate,
                    type: typeText,
                    overtime_type: overtimeType,
                    amount: amount
                });
            });
            
            // Show/hide the summary table
            summaryTable.style.display = hasValidEntries ? 'table' : 'none';
            
            // Note: JSON field is managed by collectOvertimeEntries() function
            // overtimeEntriesJson.value = JSON.stringify(overtimeData);
        }
        
        function removeOvertimeEntry(index) {
            const entries = document.querySelectorAll('.overtime-entry');
            if (index < entries.length) {
                entries[index].remove();
                toggleRemoveButtons();
                calculatePayroll();
                updateOvertimeSummary();
            }
        }
        
        function setupDaysWorkedEntryListeners(entryElement) {
            const daysInput = entryElement.querySelector('.days-worked-count');
            const typeSelect = entryElement.querySelector('.days-worked-type');
            const removeButton = entryElement.querySelector('.remove-days-worked');
            
            if (daysInput) {
                daysInput.addEventListener('change', function() {
                    calculatePayroll();
                    updateDaysWorkedSummary();
                });
            }
            
            if (typeSelect) {
                typeSelect.addEventListener('change', function() {
                    calculatePayroll();
                    updateDaysWorkedSummary();
                });
            }
            
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    entryElement.remove();
                    calculatePayroll();
                    updateDaysWorkedSummary();
                    toggleRemoveButtons();
                });
            }
        }
        
        function addDaysWorkedEntry() {
            const container = document.getElementById('days-worked-entries-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'days-worked-entry mb-2';
            newEntry.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <input type="number" step="0.01" class="form-control days-worked-count" placeholder="Days" value="0">
                    </div>
                    <div class="col-md-7">
                        <select class="form-select days-worked-type">
                            <option value="regular_day">Regular Day</option>
                            <option value="rest_day">Rest Day</option>
                            <option value="rest_day_special_holiday">Rest Day and Special Holiday</option>
                            <option value="regular_holiday_ordinary_day">Regular Holiday - Ordinary Day</option>
                            <option value="rest_day_regular_holiday">Rest Day and Regular Holiday</option>
                            <option value="special_holiday_ordinary_day">Special Holiday- Ordinary Day</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-danger remove-days-worked"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            `;
            
            container.appendChild(newEntry);
            
            // Add event listeners to the new entry
            setupDaysWorkedEntryListeners(newEntry);
            
            // Show/hide remove buttons based on number of entries
            toggleRemoveButtons();
            
            // Update calculations
            calculatePayroll();
        }

        // Function to collect days worked entries and convert to JSON
        function collectDaysWorkedEntries() {
            const entries = document.querySelectorAll('.days-worked-entry');
            const daysWorkedData = [];
            
            // Get daily rate
            const employeeSelect = document.getElementById('employee_id');
            let dailyRate = 0;
            if (employeeSelect && employeeSelect.selectedIndex >= 0) {
                dailyRate = parseFloat(employeeSelect.options[employeeSelect.selectedIndex].getAttribute('data-rate')) || 0;
            }
            
            entries.forEach(function(entry) {
                const daysInput = entry.querySelector('.days-worked-count');
                const typeSelect = entry.querySelector('.days-worked-type');
                
                if (daysInput && typeSelect) {
                    const days = parseFloat(daysInput.value) || 0;
                    const type = typeSelect.value;
                    
                    if (days > 0) {
                        // Calculate rate based on type
                        let rate = dailyRate;
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
                        
                        daysWorkedData.push({
                            days: days,
                            type: type,
                            rate: rate,
                            amount: days * rate
                        });
                    }
                }
            });
            
            // Update the hidden JSON field
            const jsonField = document.getElementById('days_worked_entries_json');
            if (jsonField) {
                jsonField.value = JSON.stringify(daysWorkedData);
                console.log('Days worked entries collected:', daysWorkedData);
            }
        }

        // Function to collect overtime entries and convert to JSON
        function collectOvertimeEntries() {
            console.log('=== COLLECT OVERTIME ENTRIES DEBUG ===');
            const entries = document.querySelectorAll('.overtime-entry');
            console.log('Found overtime entries:', entries.length);
            const overtimeData = [];
            
            // Get daily rate for calculating overtime rates
            const employeeSelect = document.getElementById('employee_id');
            let dailyRate = 0;
            if (employeeSelect && employeeSelect.selectedIndex >= 0) {
                dailyRate = parseFloat(employeeSelect.options[employeeSelect.selectedIndex].getAttribute('data-rate')) || 0;
            }
            console.log('Daily rate:', dailyRate);
            
            entries.forEach(function(entry) {
                const hoursInput = entry.querySelector('.overtime-hours');
                const typeSelect = entry.querySelector('.overtime-type');
                
                if (hoursInput && typeSelect) {
                    const hours = parseFloat(hoursInput.value) || 0;
                    const type = typeSelect.value;
                    
                    console.log(`Processing entry: Hours=${hours}, Type=${type}`);
                    
                    if (hours > 0 && type !== '0') {
                        // Calculate rate based on overtime type
                        let rate = 0;
                        const hourlyRate = dailyRate / 8; // Convert daily rate to hourly
                        
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
                                rate = hourlyRate * 1.25; // Default to ordinary OT
                        }
                        
                        const amount = hours * rate;
                        console.log(`Adding to overtimeData: Hours=${hours}, Rate=${rate}, Amount=${amount}, Type=${type}`);
                        
                        overtimeData.push({
                            hours: hours,
                            rate: rate,
                            type: type,
                            amount: amount
                        });
                    } else {
                        console.log(`Skipping entry: Hours=${hours}, Type=${type} (invalid)`);
                    }
                }
            });
            
            // Update the hidden JSON field
            const jsonField = document.getElementById('overtime_entries_json');
            if (jsonField) {
                jsonField.value = JSON.stringify(overtimeData);
                console.log('Overtime entries collected:', overtimeData);
                console.log('Hidden field value set to:', jsonField.value);
                
                // Also log to server via AJAX for debugging
                if (overtimeData.length > 0) {
                    fetch('debug_log.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'message=FRONTEND: Collected ' + overtimeData.length + ' overtime entries: ' + JSON.stringify(overtimeData)
                    });
                } else {
                    fetch('debug_log.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'message=FRONTEND: No overtime entries collected - entries found: ' + entries.length
                    });
                }
            } else {
                console.log('ERROR: overtime_entries_json field not found!');
            }
        }
        
        function updateDaysWorkedSummary() {
            const entries = document.querySelectorAll('.days-worked-entry');
            const summaryTable = document.getElementById('days-worked-summary-table');
            const summaryBody = document.getElementById('days-worked-summary-body');
            const daysWorkedEntriesJson = document.getElementById('days_worked_entries_json');
            
            // Check if essential elements exist
            if (!summaryTable || !summaryBody || !daysWorkedEntriesJson) {
                console.warn('Essential elements for days worked summary not found');
                return;
            }
            
            // Safely get daily rate
            const employeeSelect = document.getElementById('employee_id');
            let dailyRate = 0;
            if (employeeSelect && employeeSelect.selectedIndex >= 0 && employeeSelect.options[employeeSelect.selectedIndex]) {
                dailyRate = parseFloat(employeeSelect.options[employeeSelect.selectedIndex].getAttribute('data-rate')) || 0;
            }
            
            // Clear the summary table
            summaryBody.innerHTML = '';
            
            // Prepare the JSON data
            const daysWorkedData = [];
            
            // Check if there are any valid days worked entries
            let hasValidEntries = false;
            let totalDaysWorked = 0;
            
            entries.forEach((entry, index) => {
                const days = parseFloat(entry.querySelector('.days-worked-count').value) || 0;
                const dayType = entry.querySelector('.days-worked-type').value;
                const typeText = entry.querySelector('.days-worked-type option:checked').text;
                
                // Calculate rate based on daily rate and day type
                let rate = dailyRate;
                if (dayType !== 'regular_day') {
                    switch(dayType) {
                        case 'rest_day':
                            rate = dailyRate * 1.3;
                            break;
                        case 'rest_day_special_holiday':
                            rate = dailyRate * 1.5;
                            break;
                        case 'regular_holiday_ordinary_day':
                            rate = dailyRate * 1;
                            break;
                        case 'rest_day_regular_holiday':
                            rate = (dailyRate * 2) + (dailyRate * 0.3 * 2);
                            break;
                        case 'special_holiday_ordinary_day':
                            rate = dailyRate * 0.3;
                            break;
                        default:
                            rate = dailyRate;
                    }
                }
                
                // Skip entries with 0 days
                if (days === 0) return;
                
                hasValidEntries = true;
                totalDaysWorked += days;
                const amount = days * rate;
                
                // Add to the summary table
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${days}</td>
                    <td>₱${amount.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeDaysWorkedEntry(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                summaryBody.appendChild(row);
                
                // Add to the JSON data
                daysWorkedData.push({
                    days: days,
                    rate: rate,
                    type: typeText,
                    day_type: dayType,
                    amount: amount
                });
            });
            
            // Update the main days_worked field with total
            const daysWorkedField = document.getElementById('days_worked');
            if (daysWorkedField) {
                daysWorkedField.value = totalDaysWorked;
            }
            
            // Show/hide the summary table
            summaryTable.style.display = hasValidEntries ? 'table' : 'none';
            
            // Update the hidden JSON field
            daysWorkedEntriesJson.value = JSON.stringify(daysWorkedData);
        }
        
        function removeDaysWorkedEntry(index) {
            const entries = document.querySelectorAll('.days-worked-entry');
            if (index < entries.length) {
                entries[index].remove();
                toggleRemoveButtons();
                calculatePayroll();
                updateDaysWorkedSummary();
            }
        }
        
        function calculatePayroll() {
            // Helper function to safely get element value
            function getElementValue(id) {
                const element = document.getElementById(id);
                return element ? parseFloat(element.value || 0) : 0;
            }
            
            // Get the employee select element
            const employeeSelect = document.getElementById('employee_id');
            
            // Check if an employee is selected
            if (!employeeSelect || employeeSelect.selectedIndex < 0) {
                console.log('No employee selected');
                return; // Exit if no employee is selected
            }
            
            const selectedOption = employeeSelect.options[employeeSelect.selectedIndex];
            if (!selectedOption) {
                console.log('Selected option not found');
                return; // Exit if selected option is not found
            }
            
            const dailyRate = parseFloat(selectedOption.getAttribute('data-rate')) || 0;
            const daysWorked = getElementValue('days_worked');
            const lateMinutes = getElementValue('late_minutes');
            const allowances = getElementValue('allowances');
            const additionalPayment = getElementValue('additional_payment');
            const otherDeductions = getElementValue('other_deductions');
            const loansAdvances = getElementValue('loans_advances');
            const sssLoan = getElementValue('sss_loan');
            const hdmfLoan = getElementValue('hdmf_loan');
            const calamityLoan = getElementValue('calamity_loan');
            const multipurposeLoan = getElementValue('multipurpose_loan');
            
            console.log('Daily Rate:', dailyRate, 'Days Worked:', daysWorked);
            
            // Calculate late deduction: (late time / 8) / 60 * daily rate
            const lateDeduction = (lateMinutes / 8) / 60 * dailyRate;
            
            // Calculate basic pay from days worked entries
            let basicPay = 0;
            const daysWorkedEntries = document.querySelectorAll('.days-worked-entry');
            
            daysWorkedEntries.forEach(entry => {
                const days = parseFloat(entry.querySelector('.days-worked-count').value) || 0;
                const dayType = entry.querySelector('.days-worked-type').value;
                
                // Calculate rate based on daily rate and day type
                let rate = dailyRate;
                if (dayType !== 'regular_day') {
                    switch(dayType) {
                        case 'rest_day':
                            rate = dailyRate * 1.3;
                            break;
                        case 'rest_day_special_holiday':
                            rate = dailyRate * 1.5;
                            break;
                        case 'regular_holiday_ordinary_day':
                            rate = dailyRate * 1;
                            break;
                        case 'rest_day_regular_holiday':
                            rate = (dailyRate * 2) + (dailyRate * 0.3 * 2);
                            break;
                        case 'special_holiday_ordinary_day':
                            rate = dailyRate * 0.3;
                            break;
                        default:
                            rate = dailyRate;
                    }
                }
                
                basicPay += days * rate;
            });
            
            // Add additional payment
            basicPay = parseFloat(basicPay.toFixed(2)) + additionalPayment;
            
            // Calculate total overtime pay from all entries
            let overtimePay = 0;
            const overtimeEntries = document.querySelectorAll('.overtime-entry');
            
            overtimeEntries.forEach(entry => {
                const hours = parseFloat(entry.querySelector('.overtime-hours').value) || 0;
                const overtimeType = entry.querySelector('.overtime-type').value;
                let rate = 0;
                
                // Calculate rate based on daily rate and overtime type
                if (overtimeType !== '0') {
                    switch(overtimeType) {
                        case 'ordinary_day_ot':
                            rate = [(dailyRate / 8) * 1.25];
                            break;
                        case 'rest_day_special_holiday_ot':
                            rate = [(dailyRate / 8) * 1.69];
                            break;
                        case 'rest_day_and_special_holiday_ot':
                            rate = [(dailyRate / 8) * 1.95];
                            break;
                        case 'regular_holiday_ordinary_day_ot':
                            rate = [(dailyRate / 8) * 2.6];
                            break;
                        case 'rest_day_regular_holiday_ot':
                            rate = [(dailyRate / 8) * 1.3];
                            break;
                        case 'rest_day_regular_holiday_ot_special':
                            rate = (((dailyRate / 8) * 2.6) * 1.3);
                            break;
                        case 'night_differential_ordinary_day':
                            rate = [(dailyRate / 8) * 0.1];
                            break;
                        case 'night_differential_rest_day':
                            rate = [(dailyRate / 8) * 1.3] * 1.1;
                            break;
                        default:
                            rate = 0;
                    }
                }
                
                overtimePay += hours * rate;
            });
            
            // Update the overtime entries JSON
            updateOvertimeSummary();
            
            // Check if deductions should be applied
            const applyDeductionsElement = document.getElementById('apply_deductions');
            const applyDeductions = applyDeductionsElement ? applyDeductionsElement.checked : false;
            
            let sssDeduction = 0;
            let philhealthDeduction = 0;
            let pagibigDeduction = 0;
            let taxDeduction = 0;
            
            if (applyDeductions) {
                // Calculate monthly basic pay for automatic deductions from days worked entries
                let monthlyBasicPay = 0;
                const daysWorkedEntriesForDeductions = document.querySelectorAll('.days-worked-entry');
                
                daysWorkedEntriesForDeductions.forEach(entry => {
                    const days = parseFloat(entry.querySelector('.days-worked-count').value) || 0;
                    const dayType = entry.querySelector('.days-worked-type').value;
                    
                    // Calculate rate based on daily rate and day type
                    let rate = dailyRate;
                    if (dayType !== 'regular_day') {
                        switch(dayType) {
                            case 'rest_day':
                                rate = dailyRate * 1.3;
                                break;
                            case 'rest_day_special_holiday':
                                rate = dailyRate * 1.5;
                                break;
                            case 'regular_holiday_ordinary_day':
                                rate = dailyRate * 1;
                                break;
                            case 'rest_day_regular_holiday':
                                rate = (dailyRate * 2) + (dailyRate * 0.3 * 2);
                                break;
                            case 'special_holiday_ordinary_day':
                                rate = dailyRate * 0.3;
                                break;
                            default:
                                rate = dailyRate;
                        }
                    }
                    
                    monthlyBasicPay += days * rate;
                });
                
                monthlyBasicPay = parseFloat(monthlyBasicPay.toFixed(2));
                
                // Initialize deduction values - we'll calculate them either here or in the AJAX callback
                let deductionsCalculated = false;
                
                // Check if this is a month-end period (30th, 28th, 29th, 31st) and try to find previous mid-month payroll
                const payPeriodElement = document.getElementById('pay_period_id');
                const payPeriodId = payPeriodElement ? payPeriodElement.value : '';
                if (payPeriodId) {
                    // Get the period name from the dropdown
                    const periodSelect = document.getElementById('pay_period_id');
                    let periodName = '';
                    
                    // Check if the select element exists and has selected options
                    if (periodSelect && periodSelect.selectedIndex >= 0 && periodSelect.options[periodSelect.selectedIndex]) {
                        periodName = periodSelect.options[periodSelect.selectedIndex].text.trim();
                    }
                    
                    // Check if this is a month-end period
                    if (periodName && (periodName.includes('30th') || periodName.includes('28th') || 
                                       periodName.includes('29th') || periodName.includes('31st'))) {
                        // Extract month from period name
                        const month = periodName.split(' ')[0]; // e.g., "January"
                        const midMonthPeriod = month + ' 15th';
                        
                        // Make an AJAX call to get the mid-month basic pay
                        const employeeIdElement = document.getElementById('employee_id');
                        const employeeId = employeeIdElement ? employeeIdElement.value : '';
                        
                        // Only proceed if we have an employee selected
                        if (employeeId) {
                            // Show loading message
                            document.getElementById('deduction_info').innerHTML = '<div class="alert alert-info">Loading combined deductions data...</div>';
                            
                            // Make AJAX call to get mid-month payroll data
                            $.ajax({
                                url: 'get_mid_month_pay.php',
                                type: 'POST',
                                data: {
                                    employee_id: employeeId,
                                    mid_month_period: midMonthPeriod
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success && response.basic_pay) {
                                        // Add the mid-month basic pay to the current monthly basic pay for deduction calculations
                                        const midMonthBasicPay = parseFloat(response.basic_pay);
                                        const combinedMonthlyBasicPay = monthlyBasicPay + midMonthBasicPay;
                                        
                                        // Recalculate deductions with combined basic pay
                                        sssDeduction = calculateSSSDeductionJS(combinedMonthlyBasicPay);
                                        philhealthDeduction = calculatePhilHealthDeductionJS(combinedMonthlyBasicPay);
                                        pagibigDeduction = calculatePagIBIGDeductionJS();
                                        taxDeduction = calculateTaxDeductionJS(combinedMonthlyBasicPay);
                                        
                                        // Log the calculated deductions for debugging
                                        console.log('Month-end deductions calculated with combined pay:', {
                                            combinedMonthlyBasicPay,
                                            sssDeduction,
                                            philhealthDeduction,
                                            pagibigDeduction,
                                            taxDeduction
                                        });
                                        
                                        // Update the deduction input fields
                                        document.getElementById('sss_deduction').value = sssDeduction.toFixed(2);
                                        document.getElementById('philhealth_deduction').value = philhealthDeduction.toFixed(2);
                                        document.getElementById('pagibig_deduction').value = pagibigDeduction.toFixed(2);
                                        document.getElementById('tax_deduction').value = taxDeduction.toFixed(2);
                                        
                                        // Update total deductions and net pay
                                        const totalDeductions = sssDeduction + philhealthDeduction + pagibigDeduction + taxDeduction + 
                                                               otherDeductions + loansAdvances + sssLoan + hdmfLoan + calamityLoan + multipurposeLoan + lateDeduction;
                                        const netPay = basicPay + overtimePay + allowances - totalDeductions;
                                        
                                        document.getElementById('total_deductions_result').textContent = totalDeductions.toFixed(2);
                                        document.getElementById('net_pay_result').textContent = netPay.toFixed(2);
                                        
                                        // Mark that deductions have been calculated
                                        deductionsCalculated = true;
                                        
                                        // Show info message about combined deductions
                                        document.getElementById('deduction_info').innerHTML = 
                                            '<div class="alert alert-success">Deductions calculated based on combined basic pay: ' + 
                                            'Current period (' + basicPay.toFixed(2) + ') + Mid-month period (' + midMonthBasicPay.toFixed(2) + 
                                            ') = ' + combinedMonthlyBasicPay.toFixed(2) + '</div>';
                                    } else {
                                        // No mid-month payroll found
                                        document.getElementById('deduction_info').innerHTML = 
                                            '<div class="alert alert-warning">No mid-month payroll found. Deductions calculated based only on current period.</div>';
                                    }
                                },
                                error: function() {
                                    document.getElementById('deduction_info').innerHTML = 
                                        '<div class="alert alert-danger">Error retrieving mid-month payroll data. Deductions calculated based only on current period.</div>';
                                }
                            });
                        }
                    }
                }
                
                // If deductions haven't been calculated by the AJAX call (for non-month-end periods or if AJAX failed),
                // calculate them now based on the current period only
                if (!deductionsCalculated) {
                    sssDeduction = calculateSSSDeductionJS(monthlyBasicPay);
                    philhealthDeduction = calculatePhilHealthDeductionJS(monthlyBasicPay);
                    pagibigDeduction = calculatePagIBIGDeductionJS();
                    taxDeduction = calculateTaxDeductionJS(monthlyBasicPay);
                    
                    // Log the calculated deductions for debugging
                    console.log('Regular deductions calculated with current period pay:', {
                        monthlyBasicPay,
                        sssDeduction,
                        philhealthDeduction,
                        pagibigDeduction,
                        taxDeduction
                    });
                }
            }
            
            // Update the deduction input fields
            document.getElementById('sss_deduction').value = sssDeduction.toFixed(2);
            document.getElementById('philhealth_deduction').value = philhealthDeduction.toFixed(2);
            document.getElementById('pagibig_deduction').value = pagibigDeduction.toFixed(2);
            document.getElementById('tax_deduction').value = taxDeduction.toFixed(2);
            
            const totalDeductions = sssDeduction + philhealthDeduction + pagibigDeduction + taxDeduction + otherDeductions + loansAdvances + sssLoan + hdmfLoan + calamityLoan + multipurposeLoan + lateDeduction;
            const netPay = basicPay + overtimePay + allowances - totalDeductions;
            const thirteenthMonthPay = basicPay / 12;
            
            // Log the values before updating the UI
            console.log('Updating UI with:', {
                basicPay: basicPay.toFixed(2),
                overtimePay: overtimePay.toFixed(2),
                totalDeductions: totalDeductions.toFixed(2),
                netPay: netPay.toFixed(2),
                thirteenthMonthPay: thirteenthMonthPay.toFixed(2)
            });
            
            // Update the UI elements
            const basicPayResult = document.getElementById('basic_pay_result');
            const overtimePayResult = document.getElementById('overtime_pay_result');
            const totalDeductionsResult = document.getElementById('total_deductions_result');
            const netPayResult = document.getElementById('net_pay_result');
            const thirteenthMonthResult = document.getElementById('thirteenth_month_result');
            
            if (basicPayResult) basicPayResult.textContent = basicPay.toFixed(2);
            if (overtimePayResult) overtimePayResult.textContent = overtimePay.toFixed(2);
            if (totalDeductionsResult) totalDeductionsResult.textContent = totalDeductions.toFixed(2);
            if (netPayResult) netPayResult.textContent = netPay.toFixed(2);
            if (thirteenthMonthResult) thirteenthMonthResult.textContent = thirteenthMonthPay.toFixed(2);
        }
        
        // Button functions moved to buttons.js
    </script>
</body>
</html>