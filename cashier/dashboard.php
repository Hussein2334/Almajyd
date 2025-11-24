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

// Handle Update Profile
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        // Update basic profile info
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']]);
        
        // Update password if provided
        if (!empty($current_password) && !empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match!";
            } else {
                // Verify current password
                $user_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $user_stmt->execute([$_SESSION['user_id']]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pwd_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $pwd_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    $success_message = "Profile and password updated successfully!";
                } else {
                    $error_message = "Current password is incorrect!";
                }
            }
        } else {
            $success_message = "Profile updated successfully!";
        }
        
        // Update session
        $_SESSION['full_name'] = $full_name;
        
    } catch (PDOException $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Handle Add Price and Generate Payment
if (isset($_POST['add_price'])) {
    $prescription_id = $_POST['prescription_id'];
    $price = $_POST['price'] ?? 0;
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_status = $_POST['payment_status'] ?? 'pending';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get patient_id and checking_form_id from prescription
        $prescription_stmt = $pdo->prepare("
            SELECT cf.patient_id, pr.checking_form_id 
            FROM prescriptions pr 
            JOIN checking_forms cf ON pr.checking_form_id = cf.id 
            WHERE pr.id = ?
        ");
        $prescription_stmt->execute([$prescription_id]);
        $prescription_data = $prescription_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prescription_data) {
            $patient_id = $prescription_data['patient_id'];
            $checking_form_id = $prescription_data['checking_form_id'];
            
            // Create payment record
            $payment_stmt = $pdo->prepare("
                INSERT INTO payments (patient_id, checking_form_id, amount, payment_type, status, processed_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $payment_stmt->execute([
                $patient_id, 
                $checking_form_id, 
                $price, 
                $payment_method, 
                $payment_status, 
                $_SESSION['user_id']
            ]);
            
            $payment_id = $pdo->lastInsertId();
            
            // Update prescription to mark as priced (using cashier_id column if exists, otherwise just update)
            try {
                $update_stmt = $pdo->prepare("UPDATE prescriptions SET cashier_id = ? WHERE id = ?");
                $update_stmt->execute([$_SESSION['user_id'], $prescription_id]);
            } catch (PDOException $e) {
                // If cashier_id column doesn't exist, just continue
                error_log("Cashier_id column may not exist: " . $e->getMessage());
            }
            
            $pdo->commit();
            $success_message = "Price added and payment record created successfully!";
        } else {
            throw new Exception("Prescription data not found");
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error adding price: " . $e->getMessage();
    }
}

// Handle Complete Payment
if (isset($_POST['complete_payment'])) {
    $payment_id = $_POST['payment_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE payments SET status = 'paid' WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        $success_message = "Payment marked as completed successfully!";
        
        // Refresh page to update status
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $error_message = "Error completing payment: " . $e->getMessage();
    }
}

// Get statistics for dashboard
$total_patients = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch(PDO::FETCH_ASSOC)['total'];
$today_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];
$total_prescriptions = $pdo->query("SELECT COUNT(*) as total FROM prescriptions")->fetch(PDO::FETCH_ASSOC)['total'];

// Get unpriced prescriptions (dispensed but no payment record)
$unpriced_prescriptions = $pdo->query("
    SELECT COUNT(*) as total 
    FROM prescriptions pr 
    WHERE pr.status = 'dispensed' 
    AND NOT EXISTS (
        SELECT 1 FROM payments p 
        JOIN checking_forms cf ON p.checking_form_id = cf.id 
        WHERE cf.id = pr.checking_form_id
    )
")->fetch(PDO::FETCH_ASSOC)['total'];

// Get pending payments
$pending_payments = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'];

// Get completed payments
$completed_payments = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'];

// Get unpriced prescriptions list (dispensed but no payment record) - FIXED QUERY
$unpriced_prescriptions_list = $pdo->query("
    SELECT 
        pr.*, 
        p.full_name as patient_name, 
        p.card_no, 
        p.age, 
        p.gender,
        u.full_name as doctor_name,
        cf.diagnosis,
        cf.id as checking_form_id
    FROM prescriptions pr 
    JOIN checking_forms cf ON pr.checking_form_id = cf.id 
    JOIN patients p ON cf.patient_id = p.id 
    JOIN users u ON cf.doctor_id = u.id 
    WHERE pr.status = 'dispensed' 
    AND NOT EXISTS (
        SELECT 1 FROM payments pmt 
        WHERE pmt.checking_form_id = cf.id
    )
    ORDER BY pr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get pending payments list - FIXED QUERY
$pending_payments_list = $pdo->query("
    SELECT 
        pmt.*,
        p.full_name as patient_name, 
        p.card_no, 
        p.age, 
        p.gender,
        pr.medicine_name,
        u.full_name as doctor_name,
        cf.diagnosis
    FROM payments pmt
    JOIN patients p ON pmt.patient_id = p.id
    JOIN checking_forms cf ON pmt.checking_form_id = cf.id
    JOIN prescriptions pr ON cf.id = pr.checking_form_id
    JOIN users u ON cf.doctor_id = u.id
    WHERE pmt.status = 'pending'
    ORDER BY pmt.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get completed payments list - FIXED QUERY
$completed_payments_list = $pdo->query("
    SELECT 
        pmt.*,
        p.full_name as patient_name, 
        p.card_no, 
        p.age, 
        p.gender,
        pr.medicine_name,
        u.full_name as doctor_name,
        c.full_name as cashier_name,
        cf.diagnosis
    FROM payments pmt
    JOIN patients p ON pmt.patient_id = p.id
    JOIN checking_forms cf ON pmt.checking_form_id = cf.id
    JOIN prescriptions pr ON cf.id = pr.checking_form_id
    JOIN users u ON cf.doctor_id = u.id
    LEFT JOIN users c ON pmt.processed_by = c.id
    WHERE pmt.status = 'paid'
    ORDER BY pmt.created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments (invoices) - FIXED QUERY
$recent_invoices = $pdo->query("
    SELECT 
        pmt.*,
        pr.medicine_name,
        p.full_name as patient_name,
        p.card_no,
        u.full_name as cashier_name
    FROM payments pmt
    JOIN patients p ON pmt.patient_id = p.id
    JOIN checking_forms cf ON pmt.checking_form_id = cf.id
    JOIN prescriptions pr ON cf.id = pr.checking_form_id
    JOIN users u ON pmt.processed_by = u.id
    ORDER BY pmt.created_at DESC
    LIMIT 15
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
            border: 3px solid #10b981;
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
            background: linear-gradient(135deg, #10b981, #059669);
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
            background: #10b981;
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
        
        .stat-card.patients { border-left-color: #3b82f6; }
        .stat-card.today { border-left-color: #10b981; }
        .stat-card.total-rx { border-left-color: #8b5cf6; }
        .stat-card.unpriced { border-left-color: #10b981; }
        .stat-card.pending-payment { border-left-color: #ef4444; }
        .stat-card.completed-payment { border-left-color: #10b981; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.patients .stat-icon { color: #3b82f6; }
        .stat-card.today .stat-icon { color: #10b981; }
        .stat-card.total-rx .stat-icon { color: #8b5cf6; }
        .stat-card.unpriced .stat-icon { color: #10b981; }
        .stat-card.pending-payment .stat-icon { color: #ef4444; }
        .stat-card.completed-payment .stat-icon { color: #10b981; }
        
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
            background: #10b981;
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
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
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
            background: #3b82f6;
            color: white;
        }
        
        .btn-info:hover {
            background: #2563eb;
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

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .card.prescription { border-left-color: #10b981; }
        .card.payment { border-left-color: #ef4444; }
        .card.completed { border-left-color: #10b981; }
        .card.invoice { border-left-color: #3b82f6; }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-weight: bold;
            color: #1e293b;
            font-size: 1.1rem;
        }
        
        .card-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-unpriced { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fee2e2; color: #991b1b; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-dispensed { background: #dbeafe; color: #1e40af; }
        
        .card-info {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
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
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #475569;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Price Form */
        .price-form {
            background: #ecfdf5;
            border: 2px solid #d1fae5;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .price-form .form-group {
            margin-bottom: 15px;
        }
        
        .price-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .currency-symbol {
            font-weight: bold;
            color: #065f46;
        }
        
        .price-input {
            flex: 1;
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
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
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
            background: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
        }

        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #d1fae5; color: #065f46; }

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
            color: #10b981;
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
            
            .price-input-group {
                flex-direction: column;
                align-items: stretch;
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
            <div class="stat-card patients">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_patients; ?></div>
                <div class="stat-label">Today's Patients</div>
            </div>
            <div class="stat-card total-rx">
                <div class="stat-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="stat-number"><?php echo $total_prescriptions; ?></div>
                <div class="stat-label">Total Prescriptions</div>
            </div>
            <div class="stat-card unpriced">
                <div class="stat-icon">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="stat-number"><?php echo $unpriced_prescriptions; ?></div>
                <div class="stat-label">Unpriced Prescriptions</div>
            </div>
            <div class="stat-card pending-payment">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_payments; ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
            <div class="stat-card completed-payment">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $completed_payments; ?></div>
                <div class="stat-label">Completed Payments</div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="showTab('unpriced')">
                    <i class="fas fa-tag"></i>
                    Add Prices (<?php echo $unpriced_prescriptions; ?>)
                </button>
                <button class="tab" onclick="showTab('pending')">
                    <i class="fas fa-clock"></i>
                    Pending Payments (<?php echo $pending_payments; ?>)
                </button>
                <button class="tab" onclick="showTab('completed')">
                    <i class="fas fa-check-circle"></i>
                    Completed Payments (<?php echo $completed_payments; ?>)
                </button>
                <button class="tab" onclick="showTab('invoices')">
                    <i class="fas fa-receipt"></i>
                    Recent Invoices
                </button>
                <button class="tab" onclick="showTab('profile')">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </button>
            </div>

            <!-- Unpriced Prescriptions Tab -->
            <div id="unpriced-tab" class="tab-content active">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-tag"></i> Add Prices to Dispensed Prescriptions
                </h3>
                
                <?php if (empty($unpriced_prescriptions_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4>No Unpriced Prescriptions</h4>
                        <p>All dispensed prescriptions have been priced.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($unpriced_prescriptions_list as $prescription): ?>
                        <div class="card prescription">
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($prescription['medicine_name']); ?></div>
                                <div class="card-status status-unpriced">Needs Pricing</div>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <span class="info-label">Patient:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($prescription['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Age/Gender:</span>
                                    <span class="info-value"><?php echo $prescription['age'] . ' yrs / ' . ucfirst($prescription['gender']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Prescribed by:</span>
                                    <span class="info-value">Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Dispensed Date:</span>
                                    <span class="info-value"><?php echo date('M j, Y H:i', strtotime($prescription['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="card-description">
                                <strong>Medication Details:</strong><br>
                                <strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage'] ?? 'Not specified'); ?><br>
                                <strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency'] ?? 'Not specified'); ?><br>
                                <strong>Duration:</strong> <?php echo htmlspecialchars($prescription['duration'] ?? 'Not specified'); ?>
                            </div>
                            
                            <?php if (!empty($prescription['diagnosis'])): ?>
                            <div class="card-description">
                                <strong>Diagnosis:</strong><br>
                                <?php echo htmlspecialchars($prescription['diagnosis']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="price-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label">Enter Price (TZS)</label>
                                        <div class="price-input-group">
                                            <span class="currency-symbol">TZS</span>
                                            <input type="number" name="price" class="form-input price-input" placeholder="0.00" min="0" step="0.01" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Payment Method</label>
                                        <select name="payment_method" class="form-select" required>
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="insurance">Insurance</option>
                                            <option value="mobile">Mobile Money</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Payment Status</label>
                                        <select name="payment_status" class="form-select" required>
                                            <option value="pending">Pending Payment</option>
                                            <option value="paid">Paid Now</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="add_price" class="btn btn-success">
                                        <i class="fas fa-save"></i> Save Price & Generate Payment
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Payments Tab -->
            <div id="pending-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-clock"></i> Pending Payments
                </h3>
                
                <?php if (empty($pending_payments_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4>No Pending Payments</h4>
                        <p>All payments have been completed.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($pending_payments_list as $payment): ?>
                        <div class="card payment">
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($payment['medicine_name']); ?></div>
                                <div class="card-status status-pending">Payment Pending</div>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <span class="info-label">Patient:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($payment['patient_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($payment['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Amount:</span>
                                    <span class="info-value" style="color: #ef4444; font-size: 1.1em;">
                                        TZS <?php echo number_format($payment['amount'], 2); ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Payment Method:</span>
                                    <span class="info-value"><?php echo ucfirst($payment['payment_type']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Payment ID:</span>
                                    <span class="info-value">PMT-<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                </div>
                            </div>
                            
                            <div class="card-actions">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <button type="submit" name="complete_payment" class="btn btn-success">
                                        <i class="fas fa-check"></i> Mark as Paid
                                    </button>
                                </form>
                                <button class="btn btn-primary" onclick="printInvoice(<?php echo $payment['id']; ?>)">
                                    <i class="fas fa-print"></i> Print Invoice
                                </button>
                                <button class="btn btn-info" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed Payments Tab -->
            <div id="completed-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> Completed Payments
                </h3>
                
                <?php if (empty($completed_payments_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h4>No Completed Payments</h4>
                        <p>No payments have been completed yet.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($completed_payments_list as $payment): ?>
                        <div class="card completed">
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($payment['medicine_name']); ?></div>
                                <div class="card-status status-paid">Paid</div>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <span class="info-label">Patient:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($payment['patient_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($payment['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Amount Paid:</span>
                                    <span class="info-value" style="color: #10b981; font-size: 1.1em;">
                                        TZS <?php echo number_format($payment['amount'], 2); ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Payment Method:</span>
                                    <span class="info-value"><?php echo ucfirst($payment['payment_type']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Processed by:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($payment['cashier_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Paid Date:</span>
                                    <span class="info-value"><?php echo date('M j, Y H:i', strtotime($payment['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="card-actions">
                                <button class="btn btn-primary" onclick="printInvoice(<?php echo $payment['id']; ?>)">
                                    <i class="fas fa-print"></i> Print Invoice
                                </button>
                                <button class="btn btn-info" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Invoices Tab -->
            <div id="invoices-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-receipt"></i> Recent Payments/Invoices
                </h3>
                
                <?php if (empty($recent_invoices)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <h4>No Payments Found</h4>
                        <p>No payments have been processed yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="invoicesTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Payment #</th>
                                        <th>Patient</th>
                                        <th>Card No</th>
                                        <th>Medicine</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Cashier</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_invoices as $invoice): ?>
                                    <tr>
                                        <td><strong>PMT-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars($invoice['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['card_no']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['medicine_name']); ?></td>
                                        <td style="font-weight: bold; color: #10b981;">
                                            TZS <?php echo number_format($invoice['amount'], 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo ucfirst($invoice['payment_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $invoice['status'] == 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['cashier_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($invoice['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-primary btn-sm" onclick="printInvoice(<?php echo $invoice['id']; ?>)">
                                                    <i class="fas fa-print"></i> Print
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

            <!-- Profile Settings Tab -->
            <div id="profile-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </h3>
                
                <div class="form-grid">
                    <div class="form-card">
                        <h4><i class="fas fa-user-edit"></i> Personal Information</h4>
                        <form method="POST" action="">
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
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <div class="form-card">
                        <h4><i class="fas fa-key"></i> Change Password</h4>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-input" placeholder="Enter current password">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-input" placeholder="Enter new password">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
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
            $('#invoicesTable').DataTable({
                responsive: true,
                paging: true,
                searching: true,
                info: true,
                ordering: true,
                pageLength: 10,
                order: [[8, 'desc']],
                language: {
                    search: "Search payments:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
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

        // Function to print invoice
        function printInvoice(paymentId) {
            window.open('print_invoice.php?id=' + paymentId, '_blank');
        }

        // Function to view payment details
        function viewPaymentDetails(paymentId) {
            alert('Viewing payment details: ' + paymentId);
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

        // Auto-focus on price input when card is displayed
        document.addEventListener('DOMContentLoaded', function() {
            const priceInputs = document.querySelectorAll('.price-input');
            priceInputs.forEach(input => {
                input.focus();
            });
        });
    </script>
</body>
</html>