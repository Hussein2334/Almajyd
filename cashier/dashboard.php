<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Cashier role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'cashier') {
    header('Location: ../login.php');
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle Update Profile Information
if (isset($_POST['update_profile_info'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate inputs
    if (empty($full_name)) {
        $error_message = "Full name is required!";
    } else {
        try {
            // Update basic profile info
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']]);
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            
            $success_message = "Profile updated successfully!";
            
        } catch (PDOException $e) {
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle Change Password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate password fields
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long!";
    } else {
        try {
            // Verify current password
            $user_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $user_stmt->execute([$_SESSION['user_id']]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pwd_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pwd_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Current password is incorrect!";
            }
            
        } catch (PDOException $e) {
            $error_message = "Error changing password: " . $e->getMessage();
        }
    }
}

// Handle Payment Processing
if (isset($_POST['process_payment'])) {
    $payment_id = $_POST['payment_id'];
    $payment_method = $_POST['payment_method'];
    $amount_paid = $_POST['amount_paid'];
    $transaction_id = $_POST['transaction_id'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Get payment details with consultation fee
        $payment_stmt = $pdo->prepare("
            SELECT p.*, pt.consultation_fee 
            FROM payments p 
            JOIN patients pt ON p.patient_id = pt.id 
            WHERE p.id = ?
        ");
        $payment_stmt->execute([$payment_id]);
        $payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            $consultation_fee = $payment['consultation_fee'] ?? 0;
            $total_amount = $payment['amount'] + $consultation_fee;
            
            // Check if paid amount matches
            if (floatval($amount_paid) >= floatval($total_amount)) {
                // Update payment status
                $update_stmt = $pdo->prepare("
                    UPDATE payments 
                    SET status = 'paid', 
                        payment_method = ?,
                        amount_paid = ?,
                        transaction_id = ?,
                        notes = CONCAT(COALESCE(notes, ''), ' | PAID: ', ?),
                        paid_at = NOW(),
                        processed_by = ?
                    WHERE id = ?
                ");
                $update_stmt->execute([
                    $payment_method,
                    $amount_paid,
                    $transaction_id,
                    $notes,
                    $_SESSION['user_id'],
                    $payment_id
                ]);
                
                // Create receipt
                $receipt_stmt = $pdo->prepare("
                    INSERT INTO receipts (payment_id, receipt_number, total_amount, amount_paid, change_amount, issued_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $receipt_number = 'RCP' . date('Ymd') . str_pad($payment_id, 4, '0', STR_PAD_LEFT);
                $change_amount = floatval($amount_paid) - floatval($total_amount);
                
                $receipt_stmt->execute([
                    $payment_id,
                    $receipt_number,
                    $total_amount,
                    $amount_paid,
                    $change_amount,
                    $_SESSION['user_id']
                ]);
                
                $success_message = "Payment processed successfully! Receipt #: " . $receipt_number;
                
                if ($change_amount > 0) {
                    $success_message .= "<br>Change Amount: TSh " . number_format($change_amount, 2);
                }
                
            } else {
                $error_message = "Paid amount is less than total amount!";
            }
        } else {
            $error_message = "Payment record not found!";
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error processing payment: " . $e->getMessage();
    }
}

// Handle Cancel Payment
if (isset($_POST['cancel_payment'])) {
    $payment_id = $_POST['payment_id'];
    $cancellation_reason = $_POST['cancellation_reason'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'cancelled', 
                notes = CONCAT(COALESCE(notes, ''), ' | CANCELLED: ', ?),
                processed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$cancellation_reason, $_SESSION['user_id'], $payment_id]);
        
        $success_message = "Payment cancelled successfully!";
        
    } catch (PDOException $e) {
        $error_message = "Error cancelling payment: " . $e->getMessage();
    }
}

// Handle Refund Payment
if (isset($_POST['refund_payment'])) {
    $payment_id = $_POST['payment_id'];
    $refund_amount = $_POST['refund_amount'];
    $refund_reason = $_POST['refund_reason'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Get payment details
        $payment_stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $payment_stmt->execute([$payment_id]);
        $payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment && $payment['status'] == 'paid') {
            // Create refund record
            $refund_stmt = $pdo->prepare("
                INSERT INTO refunds (payment_id, refund_amount, refund_reason, processed_by)
                VALUES (?, ?, ?, ?)
            ");
            $refund_stmt->execute([
                $payment_id,
                $refund_amount,
                $refund_reason,
                $_SESSION['user_id']
            ]);
            
            // Update payment status to refunded
            $update_stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'refunded',
                    notes = CONCAT(COALESCE(notes, ''), ' | REFUNDED: ', ?),
                    processed_by = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$refund_reason, $_SESSION['user_id'], $payment_id]);
            
            $success_message = "Refund processed successfully! Refund Amount: TSh " . number_format($refund_amount, 2);
            
        } else {
            $error_message = "Payment not found or not eligible for refund!";
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error processing refund: " . $e->getMessage();
    }
}

// Handle Edit Payment Amount
if (isset($_POST['edit_payment_amount'])) {
    $payment_id = $_POST['payment_id'];
    $new_medicine_amount = $_POST['new_medicine_amount'];
    $new_lab_amount = $_POST['new_lab_amount'];
    $reason = $_POST['reason'] ?? '';
    
    try {
        $new_total_amount = $new_medicine_amount + $new_lab_amount;
        
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET amount = ?, 
                medicine_amount = ?, 
                lab_amount = ?,
                notes = CONCAT(COALESCE(notes, ''), ' | AMOUNT ADJUSTED: ', ?),
                processed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $new_total_amount,
            $new_medicine_amount,
            $new_lab_amount,
            $reason,
            $_SESSION['user_id'],
            $payment_id
        ]);
        
        $success_message = "Payment amount updated successfully!";
        
    } catch (PDOException $e) {
        $error_message = "Error updating payment amount: " . $e->getMessage();
    }
}

// Get statistics for dashboard
$total_payments = $pdo->query("SELECT COUNT(*) as total FROM payments")->fetch(PDO::FETCH_ASSOC)['total'];
$pending_payments = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'];
$completed_payments = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'];
$cancelled_payments = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'cancelled'")->fetch(PDO::FETCH_ASSOC)['total'];

// Get revenue statistics
$total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'];
$pending_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'];
$today_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(created_at) = CURDATE() AND status = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'];
$today_payments = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE DATE(created_at) = CURDATE() AND status = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'];

// Get pending payments with patient details
$pending_payments_list = $pdo->query("
    SELECT 
        p.*,
        pt.full_name as patient_name,
        pt.card_no,
        pt.age,
        pt.gender,
        pt.phone,
        pt.consultation_fee,
        u.full_name as doctor_name,
        cf.diagnosis,
        cf.symptoms,
        (SELECT GROUP_CONCAT(CONCAT(pr.medicine_name, ' (', pr.dosage, ')') SEPARATOR ', ') 
         FROM prescriptions pr 
         WHERE pr.checking_form_id = p.checking_form_id AND pr.status = 'dispensed') as medications,
        (SELECT GROUP_CONCAT(CONCAT(lt.test_type, ': ', lt.results) SEPARATOR ' | ') 
         FROM laboratory_tests lt 
         WHERE lt.checking_form_id = p.checking_form_id AND lt.status = 'completed') as lab_tests
    FROM payments p
    JOIN patients pt ON p.patient_id = pt.id
    JOIN checking_forms cf ON p.checking_form_id = cf.id
    JOIN users u ON cf.doctor_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get completed payments for today
$today_completed_payments = $pdo->query("
    SELECT 
        p.*,
        pt.full_name as patient_name,
        pt.card_no,
        pt.consultation_fee,
        r.receipt_number,
        p.payment_method,
        p.amount_paid,
        p.paid_at
    FROM payments p
    JOIN patients pt ON p.patient_id = pt.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    WHERE DATE(p.paid_at) = CURDATE() AND p.status = 'paid'
    ORDER BY p.paid_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get all payments for management
$all_payments = $pdo->query("
    SELECT 
        p.*,
        pt.full_name as patient_name,
        pt.card_no,
        pt.consultation_fee,
        r.receipt_number,
        u.full_name as processed_by_name
    FROM payments p
    JOIN patients pt ON p.patient_id = pt.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    LEFT JOIN users u ON p.processed_by = u.id
    ORDER BY p.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Get current user data
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');

// Safe variable access for form values
$current_full_name = htmlspecialchars($current_user['full_name'] ?? '');
$current_email = htmlspecialchars($current_user['email'] ?? '');
$current_phone = htmlspecialchars($current_user['phone'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Almajyd Dispensary</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
            color: #334155;
        }
        
        /* Topbar - WITH LOGO */
        .topbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 3px solid #3b82f6;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .clinic-info h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .clinic-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        
        .user-area {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .time-display {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f1f5f9;
            padding: 8px 15px;
            border-radius: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Main Content */
        .main { 
            padding: 25px; 
            max-width: 1400px; 
            margin: 0 auto; 
        }

        /* Messages - IMPROVED ALERT SYSTEM */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 10px;
            font-weight: 500;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-left: 4px solid;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-card.payments { border-left-color: #3b82f6; }
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.completed { border-left-color: #10b981; }
        .stat-card.cancelled { border-left-color: #ef4444; }
        .stat-card.revenue { border-left-color: #10b981; }
        .stat-card.pending-revenue { border-left-color: #f59e0b; }
        .stat-card.today-revenue { border-left-color: #3b82f6; }
        .stat-card.today-payments { border-left-color: #8b5cf6; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.payments .stat-icon { color: #3b82f6; }
        .stat-card.pending .stat-icon { color: #f59e0b; }
        .stat-card.completed .stat-icon { color: #10b981; }
        .stat-card.cancelled .stat-icon { color: #ef4444; }
        .stat-card.revenue .stat-icon { color: #10b981; }
        .stat-card.pending-revenue .stat-icon { color: #f59e0b; }
        .stat-card.today-revenue .stat-icon { color: #3b82f6; }
        .stat-card.today-payments .stat-icon { color: #8b5cf6; }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            margin: 8px 0;
            color: #1e293b;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.85em;
            font-weight: 500;
        }

        /* Tabs Navigation */
        .tabs-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: #f8fafc;
            border: none;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab:hover {
            background: #e2e8f0;
            color: #475569;
        }
        
        .tab.active {
            background: #3b82f6;
            color: white;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 20px;
        }
        
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .form-card h4 {
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: #06b6d4;
            color: white;
        }
        
        .btn-info:hover {
            background: #0891b2;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(700px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .card.pending { border-left-color: #f59e0b; }
        .card.completed { border-left-color: #10b981; }
        .card.cancelled { border-left-color: #ef4444; }
        .card.patient { border-left-color: #3b82f6; }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-weight: bold;
            color: #1e293b;
            font-size: 1.2rem;
        }
        
        .card-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-refunded { background: #f3e8ff; color: #7c3aed; }
        
        .card-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .info-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .card-description {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #475569;
            line-height: 1.5;
        }
        
        .amount-section {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .amount-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: #1e40af;
            border-top: 2px solid #3b82f6;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .card-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        /* Payment Form */
        .payment-form {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            border-color: #3b82f6;
        }
        
        .payment-method.selected {
            border-color: #3b82f6;
            background: #dbeafe;
        }
        
        .payment-method input[type="radio"] {
            margin: 0;
        }

        /* Tables */
        .table-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-card h3 {
            margin-bottom: 15px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        /* DataTables Custom Styling */
        .dataTables_wrapper {
            font-size: 0.85rem;
        }
        
        .dataTables_filter input {
            border: 2px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
            font-size: 0.85rem !important;
            margin-bottom: 15px !important;
        }
        
        .dataTables_length select {
            border: 2px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            font-size: 0.85rem !important;
            margin-bottom: 15px !important;
        }
        
        .dataTables_info {
            color: #6b7280 !important;
            font-size: 0.8rem !important;
            padding: 10px 0 !important;
        }
        
        .dataTables_paginate .paginate_button {
            border: 1px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            margin: 0 2px !important;
            font-size: 0.8rem !important;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #3b82f6 !important;
            border-color: #3b82f6 !important;
            color: white !important;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-purple { background: #f3e8ff; color: #7c3aed; }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 800px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #3b82f6;
            opacity: 0.7;
        }
        
        .empty-state h4 {
            color: #475569;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #64748b;
        }
        
        .close:hover {
            color: #374151;
        }

        /* MOBILE RESPONSIVE DESIGN */
        @media (max-width: 768px) {
            .topbar {
                padding: 12px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .logo-section {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
            
            .user-area {
                width: 100%;
                justify-content: space-between;
            }
            
            .main {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.8em;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-radius: 8px;
                margin-bottom: 5px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .card-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .user-area {
                flex-direction: column;
                gap: 10px;
            }
            
            .time-display, .user, .logout-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* AJAX Form Submission Styles */
        .form-submitting {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .btn-loading {
            position: relative;
            color: transparent !important;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-right-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/logo.jpg">
</head>
<body>

    <!-- Improved Alert System -->
    <?php if ($success_message): ?>
    <div class="alert-container">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <span><?php echo $success_message; ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert-container">
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> 
            <span><?php echo $error_message; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>CASHIER DEPARTMENT</p>
            </div>
        </div>
        
        <div class="user-area">
            <div class="time-display">
                <i class="fas fa-clock"></i>
                <span id="currentTime"><?php echo $current_time; ?></span>
            </div>
            <div class="user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight:600; font-size: 0.9rem;"><?php echo $_SESSION['full_name']; ?></div>
                    <small style="font-size: 0.75rem;">Cashier</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Quick Statistics -->
        <div class="stats-grid">
            <div class="stat-card payments">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo $total_payments; ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_payments; ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $completed_payments; ?></div>
                <div class="stat-label">Completed Payments</div>
            </div>
            <div class="stat-card cancelled">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $cancelled_payments; ?></div>
                <div class="stat-label">Cancelled Payments</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($total_revenue, 0); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card pending-revenue">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($pending_revenue, 0); ?></div>
                <div class="stat-label">Pending Revenue</div>
            </div>
            <div class="stat-card today-revenue">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($today_revenue, 0); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            <div class="stat-card today-payments">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-number"><?php echo $today_payments; ?></div>
                <div class="stat-label">Today's Payments</div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="showTab('pending')">
                    <i class="fas fa-clock"></i>
                    Pending Payments (<?php echo count($pending_payments_list); ?>)
                </button>
                <button class="tab" onclick="showTab('today')">
                    <i class="fas fa-calendar-day"></i>
                    Today's Payments (<?php echo count($today_completed_payments); ?>)
                </button>
                <button class="tab" onclick="showTab('all')">
                    <i class="fas fa-list"></i>
                    All Payments (<?php echo count($all_payments); ?>)
                </button>
                <button class="tab" onclick="showTab('print')">
                    <i class="fas fa-print"></i>
                    Print Receipt
                </button>
                <button class="tab" onclick="showTab('profile')">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </button>
            </div>

            <!-- Pending Payments Tab -->
            <div id="pending-tab" class="tab-content active">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-clock"></i> Pending Payments - Verify & Process
                </h3>
                
                <?php if (empty($pending_payments_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4>No Pending Payments</h4>
                        <p>All payments have been processed successfully.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($pending_payments_list as $payment): 
                            $consultation_fee = $payment['consultation_fee'] ?? 0;
                            $total_amount = $payment['amount'] + $consultation_fee;
                        ?>
                        <div class="card pending">
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($payment['patient_name']); ?></div>
                                <div class="card-status status-pending">
                                    Pending Payment
                                </div>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($payment['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Age/Gender:</span>
                                    <span class="info-value"><?php echo $payment['age'] . ' yrs / ' . ucfirst($payment['gender']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Phone:</span>
                                    <span class="info-value"><?php echo $payment['phone'] ?: 'N/A'; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Doctor:</span>
                                    <span class="info-value">Dr. <?php echo htmlspecialchars($payment['doctor_name']); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($payment['diagnosis'])): ?>
                            <div class="card-description">
                                <strong>Diagnosis:</strong><br>
                                <?php echo htmlspecialchars($payment['diagnosis']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($payment['symptoms'])): ?>
                            <div class="card-description">
                                <strong>Symptoms:</strong><br>
                                <?php echo htmlspecialchars($payment['symptoms']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($payment['medications'])): ?>
                            <div class="card-description">
                                <strong>Medications:</strong><br>
                                <?php echo htmlspecialchars($payment['medications']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($payment['lab_tests'])): ?>
                            <div class="card-description">
                                <strong>Lab Tests:</strong><br>
                                <?php echo htmlspecialchars($payment['lab_tests']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Amount Section - UPDATED TO INCLUDE CONSULTATION FEE -->
                            <div class="amount-section">
                                <h5 style="margin-bottom: 15px; color: #1e40af;">
                                    <i class="fas fa-money-bill"></i> Payment Summary
                                </h5>
                                <div class="amount-row">
                                    <span>Consultation Fee:</span>
                                    <span>TSh <?php echo number_format($consultation_fee, 2); ?></span>
                                </div>
                                <div class="amount-row">
                                    <span>Medicine Amount:</span>
                                    <span>TSh <?php echo number_format($payment['medicine_amount'], 2); ?></span>
                                </div>
                                <div class="amount-row">
                                    <span>Lab Amount:</span>
                                    <span>TSh <?php echo number_format($payment['lab_amount'], 2); ?></span>
                                </div>
                                <div class="amount-row amount-total">
                                    <span>Total Amount:</span>
                                    <span>TSh <?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>
                            
                            <!-- Payment Processing Form -->
                            <div class="payment-form">
                                <h5 style="margin-bottom: 15px; color: #1e293b;">
                                    <i class="fas fa-credit-card"></i> Process Payment
                                </h5>
                                
                                <form method="POST" action="" id="paymentForm_<?php echo $payment['id']; ?>" class="ajax-form">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label">Payment Method *</label>
                                        <div class="payment-methods">
                                            <label class="payment-method" onclick="selectPaymentMethod(this, <?php echo $payment['id']; ?>)">
                                                <input type="radio" name="payment_method" value="cash" required>
                                                <i class="fas fa-money-bill-wave"></i>
                                                <span>Cash</span>
                                            </label>
                                            <label class="payment-method" onclick="selectPaymentMethod(this, <?php echo $payment['id']; ?>)">
                                                <input type="radio" name="payment_method" value="card" required>
                                                <i class="fas fa-credit-card"></i>
                                                <span>Card</span>
                                            </label>
                                            <label class="payment-method" onclick="selectPaymentMethod(this, <?php echo $payment['id']; ?>)">
                                                <input type="radio" name="payment_method" value="mobile" required>
                                                <i class="fas fa-mobile-alt"></i>
                                                <span>Mobile Money</span>
                                            </label>
                                            <label class="payment-method" onclick="selectPaymentMethod(this, <?php echo $payment['id']; ?>)">
                                                <input type="radio" name="payment_method" value="bank" required>
                                                <i class="fas fa-university"></i>
                                                <span>Bank Transfer</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Amount Paid (TSh) *</label>
                                            <input type="number" name="amount_paid" class="form-input" 
                                                min="<?php echo $total_amount; ?>" 
                                                step="0.01" 
                                                value="<?php echo $total_amount; ?>" 
                                                required
                                                onchange="calculateChange(<?php echo $payment['id']; ?>, <?php echo $total_amount; ?>)">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Transaction ID (Optional)</label>
                                            <input type="text" name="transaction_id" class="form-input" placeholder="e.g., TXN123456">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Notes (Optional)</label>
                                        <textarea name="notes" class="form-textarea" placeholder="Additional notes..."></textarea>
                                    </div>
                                    
                                    <div id="changeDisplay_<?php echo $payment['id']; ?>" class="amount-row" style="display: none; background: #d1fae5; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                                        <span><strong>Change Amount:</strong></span>
                                        <span id="changeAmount_<?php echo $payment['id']; ?>" style="color: #065f46; font-weight: bold;"></span>
                                    </div>
                                    
                                    <div class="card-actions">
                                        <button type="submit" name="process_payment" class="btn btn-success">
                                            <i class="fas fa-check-circle"></i> Process Payment
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick="openEditAmountModal(<?php echo $payment['id']; ?>, <?php echo $payment['medicine_amount']; ?>, <?php echo $payment['lab_amount']; ?>)">
                                            <i class="fas fa-edit"></i> Edit Amount
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="openCancelModal(<?php echo $payment['id']; ?>)">
                                            <i class="fas fa-times"></i> Cancel Payment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Today's Payments Tab -->
            <div id="today-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-calendar-day"></i> Today's Completed Payments
                </h3>
                
                <?php if (empty($today_completed_payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h4>No Payments Today</h4>
                        <p>No payments have been processed today.</p>
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="todayPaymentsTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Patient</th>
                                        <th>Total Amount</th>
                                        <th>Amount Paid</th>
                                        <th>Payment Method</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_completed_payments as $payment): 
                                        $total_amount = $payment['amount'] + ($payment['consultation_fee'] ?? 0);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $payment['receipt_number'] ?? 'N/A'; ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['patient_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($payment['card_no']); ?></small>
                                        </td>
                                        <td>TSh <?php echo number_format($total_amount, 2); ?></td>
                                        <td>TSh <?php echo number_format($payment['amount_paid'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo ucfirst($payment['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('H:i', strtotime($payment['paid_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="receipt.php?payment_id=<?php echo $payment['id']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-print"></i> Print
                                                </a>
                                                <button class="btn btn-warning btn-sm" onclick="openRefundModal(<?php echo $payment['id']; ?>, <?php echo $payment['amount_paid']; ?>)">
                                                    <i class="fas fa-undo"></i> Refund
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Payments Tab -->
            <div id="all-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-list"></i> All Payments Management
                </h3>
                
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="allPaymentsTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Consultation</th>
                                    <th>Medicine</th>
                                    <th>Lab</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Processed By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_payments as $payment): 
                                    $total_amount = $payment['amount'] + ($payment['consultation_fee'] ?? 0);
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $payment['id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['patient_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($payment['card_no']); ?></small>
                                    </td>
                                    <td>TSh <?php echo number_format($payment['consultation_fee'] ?? 0, 2); ?></td>
                                    <td>TSh <?php echo number_format($payment['medicine_amount'], 2); ?></td>
                                    <td>TSh <?php echo number_format($payment['lab_amount'], 2); ?></td>
                                    <td><strong>TSh <?php echo number_format($total_amount, 2); ?></strong></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $payment['status'] == 'paid' ? 'badge-success' : 
                                                 ($payment['status'] == 'pending' ? 'badge-warning' : 
                                                 ($payment['status'] == 'cancelled' ? 'badge-danger' : 'badge-purple')); 
                                        ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($payment['payment_method']): ?>
                                        <span class="badge badge-info">
                                            <?php echo ucfirst($payment['payment_method']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <?php echo $payment['processed_by_name'] ? htmlspecialchars($payment['processed_by_name']) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($payment['status'] == 'pending'): ?>
                                                <button class="btn btn-success btn-sm" onclick="processPayment(<?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-check"></i> Process
                                                </button>
                                            <?php elseif ($payment['status'] == 'paid'): ?>
                                                <a href="receipt.php?payment_id=<?php echo $payment['id']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-print"></i> Print
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn btn-info btn-sm" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Print Receipt Tab -->
            <div id="print-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-print"></i> Print Receipt
                </h3>
                
                <div class="form-card">
                    <h4><i class="fas fa-search"></i> Search Payment for Receipt</h4>
                    <form method="GET" action="receipt.php" target="_blank">
                        <div class="form-group">
                            <label class="form-label">Payment ID *</label>
                            <input type="number" name="payment_id" class="form-input" placeholder="Enter payment ID" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-print"></i> View & Print Receipt
                        </button>
                    </form>
                </div>

                <!-- Quick Access to Recent Payments -->
                <div class="form-card" style="margin-top: 20px;">
                    <h4><i class="fas fa-history"></i> Recent Payments</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Patient</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $recent_payments = array_slice($all_payments, 0, 10);
                                foreach ($recent_payments as $payment): 
                                    $total_amount = $payment['amount'] + ($payment['consultation_fee'] ?? 0);
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $payment['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['patient_name']); ?></td>
                                    <td>TSh <?php echo number_format($total_amount, 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <a href="receipt.php?payment_id=<?php echo $payment['id']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Profile Settings Tab - FIXED VERSION -->
            <div id="profile-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </h3>
                
                <div class="form-grid">
                    <div class="form-card">
                        <h4><i class="fas fa-user-edit"></i> Personal Information</h4>
                        <form method="POST" action="" id="profileForm">
                            <input type="hidden" name="update_profile_info" value="1">
                            
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-input" value="<?php echo $current_full_name; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" value="<?php echo $current_email; ?>" placeholder="Enter email address">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" value="<?php echo $current_phone; ?>" placeholder="Enter phone number">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <div class="form-card">
                        <h4><i class="fas fa-key"></i> Change Password</h4>
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="form-group">
                                <label class="form-label">Current Password *</label>
                                <input type="password" name="current_password" class="form-input" placeholder="Enter current password" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">New Password *</label>
                                <input type="password" name="new_password" class="form-input" placeholder="Enter new password" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Edit Amount Modal -->
    <div id="editAmountModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editAmountModal')">&times;</span>
            <h3><i class="fas fa-edit"></i> Edit Payment Amount</h3>
            <form method="POST" action="" id="editAmountForm" class="ajax-form">
                <input type="hidden" name="payment_id" id="edit_payment_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Medicine Amount (TSh)</label>
                        <input type="number" name="new_medicine_amount" id="edit_medicine_amount" class="form-input" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Lab Amount (TSh)</label>
                        <input type="number" name="new_lab_amount" id="edit_lab_amount" class="form-input" min="0" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason for Edit</label>
                    <input type="text" name="reason" class="form-input" placeholder="Reason for amount adjustment" required>
                </div>
                
                <div class="amount-row" style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <span><strong>New Total Amount:</strong></span>
                    <span id="new_total_amount" style="color: #1e40af; font-weight: bold; font-size: 1.1rem;">TSh 0.00</span>
                </div>
                
                <div class="card-actions">
                    <button type="submit" name="edit_payment_amount" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Amount
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editAmountModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Payment Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('cancelModal')">&times;</span>
            <h3><i class="fas fa-times-circle"></i> Cancel Payment</h3>
            <form method="POST" action="" id="cancelForm" class="ajax-form">
                <input type="hidden" name="payment_id" id="cancel_payment_id">
                
                <div class="form-group">
                    <label class="form-label">Cancellation Reason *</label>
                    <textarea name="cancellation_reason" class="form-textarea" placeholder="Please provide a reason for cancellation..." required></textarea>
                </div>
                
                <div class="card-actions">
                    <button type="submit" name="cancel_payment" class="btn btn-danger">
                        <i class="fas fa-times"></i> Confirm Cancellation
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cancelModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Refund Modal -->
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('refundModal')">&times;</span>
            <h3><i class="fas fa-undo"></i> Process Refund</h3>
            <form method="POST" action="" id="refundForm" class="ajax-form">
                <input type="hidden" name="payment_id" id="refund_payment_id">
                
                <div class="form-group">
                    <label class="form-label">Refund Amount (TSh) *</label>
                    <input type="number" name="refund_amount" id="refund_amount" class="form-input" min="0.01" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Refund Reason *</label>
                    <textarea name="refund_reason" class="form-textarea" placeholder="Please provide a reason for refund..." required></textarea>
                </div>
                
                <div class="card-actions">
                    <button type="submit" name="refund_payment" class="btn btn-warning">
                        <i class="fas fa-undo"></i> Process Refund
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('refundModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#todayPaymentsTable').DataTable({
                responsive: true,
                paging: true,
                searching: true,
                info: true,
                ordering: true,
                pageLength: 10,
                language: {
                    search: "Search payments:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                },
                order: [[5, 'desc']]
            });

            $('#allPaymentsTable').DataTable({
                responsive: true,
                paging: true,
                searching: true,
                info: true,
                ordering: true,
                pageLength: 10,
                language: {
                    search: "Search payments:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                },
                order: [[0, 'desc']]
            });

            // AJAX Form Submission for payment forms
            $('.ajax-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var submitBtn = form.find('button[type="submit"]');
                var originalText = submitBtn.html();
                
                // Show loading state
                form.addClass('form-submitting');
                submitBtn.addClass('btn-loading').prop('disabled', true);
                
                // Submit form via AJAX
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        // Reload the page to show updated data
                        location.reload();
                    },
                    error: function() {
                        alert('Error occurred. Please try again.');
                        form.removeClass('form-submitting');
                        submitBtn.removeClass('btn-loading').prop('disabled', false).html(originalText);
                    }
                });
            });

            // Regular form submission for profile forms (non-AJAX)
            $('#profileForm, #passwordForm').on('submit', function(e) {
                var form = $(this);
                var submitBtn = form.find('button[type="submit"]');
                var originalText = submitBtn.html();
                
                // Show loading state
                form.addClass('form-submitting');
                submitBtn.addClass('btn-loading').prop('disabled', true);
                
                // Allow regular form submission for profile updates
                // The page will reload with the updated data
            });
        });

        // Function to show tabs
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Function to select payment method
        function selectPaymentMethod(element, paymentId) {
            // Remove selected class from all payment methods in this form
            const form = element.closest('form');
            form.querySelectorAll('.payment-method').forEach(method => {
                method.classList.remove('selected');
            });
            
            // Add selected class to clicked method
            element.classList.add('selected');
            
            // Check the radio button
            const radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
        }

        // Function to calculate change
        function calculateChange(paymentId, totalAmount) {
            const form = document.getElementById('paymentForm_' + paymentId);
            const amountPaid = parseFloat(form.querySelector('input[name="amount_paid"]').value) || 0;
            
            const changeDisplay = document.getElementById('changeDisplay_' + paymentId);
            const changeAmount = document.getElementById('changeAmount_' + paymentId);
            
            if (amountPaid > totalAmount) {
                const change = amountPaid - totalAmount;
                changeAmount.textContent = 'TSh ' + change.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                changeDisplay.style.display = 'flex';
            } else {
                changeDisplay.style.display = 'none';
            }
        }

        // Function to open edit amount modal
        function openEditAmountModal(paymentId, medicineAmount, labAmount) {
            document.getElementById('edit_payment_id').value = paymentId;
            document.getElementById('edit_medicine_amount').value = medicineAmount;
            document.getElementById('edit_lab_amount').value = labAmount;
            updateNewTotalAmount();
            document.getElementById('editAmountModal').style.display = 'block';
        }

        // Function to update new total amount in edit modal
        function updateNewTotalAmount() {
            const medicineAmount = parseFloat(document.getElementById('edit_medicine_amount').value) || 0;
            const labAmount = parseFloat(document.getElementById('edit_lab_amount').value) || 0;
            const totalAmount = medicineAmount + labAmount;
            
            document.getElementById('new_total_amount').textContent = 'TSh ' + totalAmount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Function to open cancel modal
        function openCancelModal(paymentId) {
            document.getElementById('cancel_payment_id').value = paymentId;
            document.getElementById('cancelModal').style.display = 'block';
        }

        // Function to open refund modal
        function openRefundModal(paymentId, maxAmount) {
            document.getElementById('refund_payment_id').value = paymentId;
            document.getElementById('refund_amount').value = maxAmount;
            document.getElementById('refund_amount').max = maxAmount;
            document.getElementById('refundModal').style.display = 'block';
        }

        // Function to close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Function to process payment (redirect to pending tab)
        function processPayment(paymentId) {
            showTab('pending');
            // Scroll to the specific payment card
            setTimeout(() => {
                const element = document.getElementById('paymentForm_' + paymentId);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        }

        // Function to view payment details
        function viewPaymentDetails(paymentId) {
            alert('Viewing payment details for ID: ' + paymentId);
            // You can implement a detailed view modal here
        }

        // Auto-remove alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Update time every minute
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Initial time update
        updateTime();
        setInterval(updateTime, 60000);

        // Event listeners for edit amount modal
        document.getElementById('edit_medicine_amount').addEventListener('input', updateNewTotalAmount);
        document.getElementById('edit_lab_amount').addEventListener('input', updateNewTotalAmount);

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize change calculations for all pending payments
            <?php foreach ($pending_payments_list as $payment): 
                $consultation_fee = $payment['consultation_fee'] ?? 0;
                $total_amount = $payment['amount'] + $consultation_fee;
            ?>
            calculateChange(<?php echo $payment['id']; ?>, <?php echo $total_amount; ?>);
            <?php endforeach; ?>
        });
    </script>
</body>
</html>