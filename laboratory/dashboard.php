<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Laboratory role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'laboratory') {
    header('Location: ../login.php');
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle Update Test Results
if (isset($_POST['update_test_results'])) {
    $test_id = $_POST['test_id'];
    $results = $_POST['results'];
    
    try {
        $stmt = $pdo->prepare("UPDATE laboratory_tests SET results = ?, status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$results, $test_id]);
        
        $success_message = "Test results updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating test results: " . $e->getMessage();
    }
}

// Handle Edit Test Results
if (isset($_POST['edit_test_results'])) {
    $test_id = $_POST['test_id'];
    $results = $_POST['results'];
    
    try {
        $stmt = $pdo->prepare("UPDATE laboratory_tests SET results = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$results, $test_id]);
        
        $success_message = "Test results updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating test results: " . $e->getMessage();
    }
}

// Handle Delete Test
if (isset($_POST['delete_test'])) {
    $test_id = $_POST['test_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM laboratory_tests WHERE id = ?");
        $stmt->execute([$test_id]);
        
        $success_message = "Test deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting test: " . $e->getMessage();
    }
}

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

// Get statistics for dashboard
$total_tests = $pdo->query("SELECT COUNT(*) as total FROM laboratory_tests")->fetch(PDO::FETCH_ASSOC)['total'];
$pending_tests = $pdo->query("SELECT COUNT(*) as total FROM laboratory_tests WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'];
$completed_tests = $pdo->query("SELECT COUNT(*) as total FROM laboratory_tests WHERE status = 'completed'")->fetch(PDO::FETCH_ASSOC)['total'];
$my_tests = $pdo->query("SELECT COUNT(*) as total FROM laboratory_tests WHERE conducted_by = " . $_SESSION['user_id'])->fetch(PDO::FETCH_ASSOC)['total'];

// Get all pending tests
$pending_tests_list = $pdo->query("SELECT lt.*, p.full_name as patient_name, p.card_no, p.age, p.gender, 
                                  u.full_name as doctor_name, cf.symptoms, cf.diagnosis
                           FROM laboratory_tests lt 
                           JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                           JOIN patients p ON cf.patient_id = p.id 
                           JOIN users u ON cf.doctor_id = u.id 
                           WHERE lt.status = 'pending'
                           ORDER BY lt.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get ALL completed tests (not just by this lab tech)
$all_completed_tests = $pdo->query("SELECT lt.*, p.full_name as patient_name, p.card_no, 
                                   u.full_name as doctor_name, lab_user.full_name as lab_technician
                            FROM laboratory_tests lt 
                            JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                            JOIN patients p ON cf.patient_id = p.id 
                            JOIN users u ON cf.doctor_id = u.id 
                            LEFT JOIN users lab_user ON lt.conducted_by = lab_user.id
                            WHERE lt.status = 'completed'
                            ORDER BY lt.updated_at DESC 
                            LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Laboratory Dashboard - Almajyd Dispensary</title>
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
            border: 3px solid #8b5cf6;
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
            background: #8b5cf6;
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
        
        .stat-card.total { border-left-color: #8b5cf6; }
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.completed { border-left-color: #10b981; }
        .stat-card.mine { border-left-color: #3b82f6; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.total .stat-icon { color: #8b5cf6; }
        .stat-card.pending .stat-icon { color: #f59e0b; }
        .stat-card.completed .stat-icon { color: #10b981; }
        .stat-card.mine .stat-icon { color: #3b82f6; }
        
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
            background: #8b5cf6;
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
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
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
            background: #8b5cf6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
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
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
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

        /* Test Cards */
        .tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .test-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .test-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .test-card.pending { border-left-color: #f59e0b; }
        .test-card.completed { border-left-color: #10b981; }
        
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .test-type {
            font-weight: bold;
            color: #1e293b;
            font-size: 1.1rem;
        }
        
        .test-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        
        .test-info {
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
        
        .test-description {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #475569;
        }
        
        .test-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        /* Action Buttons in Table */
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px 25px;
            background: #8b5cf6;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 25px;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
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
            background: #8b5cf6 !important;
            border-color: #8b5cf6 !important;
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
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #e9d5ff; color: #7c3aed; }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 800px;
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
            
            .tests-grid {
                grid-template-columns: 1fr;
            }
            
            .test-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                height: 95vh;
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
                <p>LABORATORY DEPARTMENT</p>
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
                    <small style="font-size: 0.75rem;">Laboratory Technologist</small>
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
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-vial"></i>
                </div>
                <div class="stat-number"><?php echo $total_tests; ?></div>
                <div class="stat-label">Total Tests</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_tests; ?></div>
                <div class="stat-label">Pending Tests</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $completed_tests; ?></div>
                <div class="stat-label">Completed Tests</div>
            </div>
            <div class="stat-card mine">
                <div class="stat-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-number"><?php echo $my_tests; ?></div>
                <div class="stat-label">My Tests</div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="showTab('pending')">
                    <i class="fas fa-clock"></i>
                    Pending Tests (<?php echo $pending_tests; ?>)
                </button>
                <button class="tab" onclick="showTab('completed')">
                    <i class="fas fa-check-circle"></i>
                    All Completed Tests (<?php echo $completed_tests; ?>)
                </button>
                <button class="tab" onclick="showTab('profile')">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </button>
            </div>

            <!-- Pending Tests Tab -->
            <div id="pending-tab" class="tab-content active">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-clock"></i> Pending Laboratory Tests
                </h3>
                
                <?php if (empty($pending_tests_list)): ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-check-circle" style="font-size: 3em; margin-bottom: 15px; color: #10b981;"></i>
                        <h4>No Pending Tests</h4>
                        <p>All laboratory tests have been completed.</p>
                    </div>
                <?php else: ?>
                    <div class="tests-grid">
                        <?php foreach ($pending_tests_list as $test): ?>
                        <div class="test-card pending">
                            <div class="test-header">
                                <div class="test-type"><?php echo htmlspecialchars($test['test_type']); ?></div>
                                <div class="test-status status-pending">Pending</div>
                            </div>
                            
                            <div class="test-info">
                                <div class="info-row">
                                    <span class="info-label">Patient:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($test['patient_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($test['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Age/Gender:</span>
                                    <span class="info-value"><?php echo $test['age'] . ' yrs / ' . ucfirst($test['gender']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Requested by:</span>
                                    <span class="info-value">Dr. <?php echo htmlspecialchars($test['doctor_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Request Date:</span>
                                    <span class="info-value"><?php echo date('M j, Y H:i', strtotime($test['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($test['test_description'])): ?>
                            <div class="test-description">
                                <strong>Test Description:</strong><br>
                                <?php echo htmlspecialchars($test['test_description']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($test['symptoms'])): ?>
                            <div class="test-description">
                                <strong>Patient Symptoms:</strong><br>
                                <?php echo htmlspecialchars($test['symptoms']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                <div class="form-group">
                                    <label class="form-label">Test Results *</label>
                                    <textarea name="results" class="form-textarea" placeholder="Enter test results and findings..." required></textarea>
                                </div>
                                
                                <div class="test-actions">
                                    <button type="submit" name="update_test_results" class="btn btn-success">
                                        <i class="fas fa-check"></i> Submit Results
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed Tests Tab -->
            <div id="completed-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> All Completed Laboratory Tests
                </h3>
                
                <?php if (empty($all_completed_tests)): ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-vial" style="font-size: 3em; margin-bottom: 15px; color: #8b5cf6;"></i>
                        <h4>No Completed Tests</h4>
                        <p>No laboratory tests have been completed yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="completedTestsTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Test Type</th>
                                        <th>Patient</th>
                                        <th>Card No</th>
                                        <th>Doctor</th>
                                        <th>Lab Technician</th>
                                        <th>Results</th>
                                        <th>Completed Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_completed_tests as $test): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($test['test_type']); ?></strong>
                                            <?php if (!empty($test['test_description'])): ?>
                                                <br><small style="color: #64748b;"><?php echo htmlspecialchars($test['test_description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($test['patient_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($test['card_no']); ?></strong></td>
                                        <td>Dr. <?php echo htmlspecialchars($test['doctor_name']); ?></td>
                                        <td>
                                            <?php if (!empty($test['lab_technician'])): ?>
                                                <?php echo htmlspecialchars($test['lab_technician']); ?>
                                            <?php else: ?>
                                                <span style="color: #64748b;">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($test['results'])): ?>
                                                <span class="badge badge-success" title="<?php echo htmlspecialchars($test['results']); ?>">
                                                    <i class="fas fa-check"></i> Results Available
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">No Results</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y H:i', strtotime($test['updated_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-info btn-sm" onclick="editTest(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars(addslashes($test['results'])); ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteTest(<?php echo $test['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
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

    <!-- Edit Test Modal -->
    <div id="editTestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Test Results</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editTestForm" method="POST" action="">
                    <input type="hidden" name="test_id" id="edit_test_id">
                    <div class="form-group">
                        <label class="form-label">Test Results *</label>
                        <textarea name="results" id="edit_results" class="form-textarea" placeholder="Enter test results and findings..." required rows="8"></textarea>
                    </div>
                    
                    <div class="test-actions">
                        <button type="submit" name="edit_test_results" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Results
                        </button>
                        <button type="button" class="btn btn-danger" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteTestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-trash"></i> Delete Test</h3>
                <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; font-size: 1.1rem;">Are you sure you want to delete this test?</p>
                <p style="color: #64748b; margin-bottom: 25px;">This action cannot be undone.</p>
                
                <form id="deleteTestForm" method="POST" action="">
                    <input type="hidden" name="test_id" id="delete_test_id">
                    
                    <div class="test-actions">
                        <button type="submit" name="delete_test" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Yes, Delete Test
                        </button>
                        <button type="button" class="btn btn-primary" onclick="closeDeleteModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
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
            $('#completedTestsTable').DataTable({
                responsive: true,
                paging: true,
                searching: true,
                info: true,
                ordering: true,
                pageLength: 10,
                language: {
                    search: "Search tests:",
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

        // Function to edit test
        function editTest(testId, currentResults) {
            document.getElementById('edit_test_id').value = testId;
            document.getElementById('edit_results').value = currentResults;
            document.getElementById('editTestModal').style.display = 'block';
        }

        // Function to close edit modal
        function closeEditModal() {
            document.getElementById('editTestModal').style.display = 'none';
        }

        // Function to delete test
        function deleteTest(testId) {
            document.getElementById('delete_test_id').value = testId;
            document.getElementById('deleteTestModal').style.display = 'block';
        }

        // Function to close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteTestModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editTestModal');
            const deleteModal = document.getElementById('deleteTestModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
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

        // Auto-refresh pending tests every 30 seconds
        setInterval(function() {
            if (document.getElementById('pending-tab').classList.contains('active')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>