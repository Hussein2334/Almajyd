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

// Handle Update Profile
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
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
// Total Patients
$total_patients = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch(PDO::FETCH_ASSOC)['total'];

// Today's Patients
$today_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];

// Total Revenue Statistics
$revenue_stats = $pdo->query("
    SELECT 
        COALESCE(SUM(p.consultation_fee), 0) as total_consultation,
        COALESCE(SUM(pr.medicine_price), 0) as total_medicine,
        COALESCE(SUM(lt.lab_price), 0) as total_lab,
        (COALESCE(SUM(p.consultation_fee), 0) + COALESCE(SUM(pr.medicine_price), 0) + COALESCE(SUM(lt.lab_price), 0)) as total_revenue
    FROM patients p
    LEFT JOIN checking_forms cf ON p.id = cf.patient_id
    LEFT JOIN prescriptions pr ON cf.id = pr.checking_form_id
    LEFT JOIN laboratory_tests lt ON cf.id = lt.checking_form_id
")->fetch(PDO::FETCH_ASSOC);

// Today's Revenue
$today_revenue = $pdo->query("
    SELECT 
        COALESCE(SUM(p.consultation_fee), 0) as total_consultation,
        COALESCE(SUM(pr.medicine_price), 0) as total_medicine,
        COALESCE(SUM(lt.lab_price), 0) as total_lab,
        (COALESCE(SUM(p.consultation_fee), 0) + COALESCE(SUM(pr.medicine_price), 0) + COALESCE(SUM(lt.lab_price), 0)) as total_revenue
    FROM patients p
    LEFT JOIN checking_forms cf ON p.id = cf.patient_id
    LEFT JOIN prescriptions pr ON cf.id = pr.checking_form_id
    LEFT JOIN laboratory_tests lt ON cf.id = lt.checking_form_id
    WHERE DATE(p.created_at) = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

// Get all patients with their payment details
$patients_stmt = $pdo->prepare("
    SELECT 
        p.*,
        cf.id as checking_form_id,
        cf.diagnosis,
        cf.created_at as visit_date,
        doc.full_name as doctor_name,
        (SELECT COUNT(*) FROM prescriptions pr WHERE pr.checking_form_id = cf.id) as prescription_count,
        (SELECT COUNT(*) FROM laboratory_tests lt WHERE lt.checking_form_id = cf.id) as lab_test_count,
        (SELECT SUM(medicine_price) FROM prescriptions pr WHERE pr.checking_form_id = cf.id) as total_medicine_cost,
        (SELECT SUM(lab_price) FROM laboratory_tests lt WHERE lt.checking_form_id = cf.id) as total_lab_cost,
        (p.consultation_fee + 
         COALESCE((SELECT SUM(medicine_price) FROM prescriptions pr WHERE pr.checking_form_id = cf.id), 0) + 
         COALESCE((SELECT SUM(lab_price) FROM laboratory_tests lt WHERE lt.checking_form_id = cf.id), 0)
        ) as total_cost
    FROM patients p
    LEFT JOIN checking_forms cf ON p.id = cf.patient_id
    LEFT JOIN users doc ON cf.doctor_id = doc.id
    ORDER BY p.created_at DESC
");
$patients_stmt->execute();
$all_patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user data
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Almajyd Dispensary</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 160px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-card.patients { border-left-color: #10b981; }
        .stat-card.today { border-left-color: #3b82f6; }
        .stat-card.consultation { border-left-color: #f59e0b; }
        .stat-card.medicine { border-left-color: #ef4444; }
        .stat-card.lab { border-left-color: #8b5cf6; }
        .stat-card.revenue { border-left-color: #059669; }
        
        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 12px;
        }
        
        .stat-card.patients .stat-icon { color: #10b981; }
        .stat-card.today .stat-icon { color: #3b82f6; }
        .stat-card.consultation .stat-icon { color: #f59e0b; }
        .stat-card.medicine .stat-icon { color: #ef4444; }
        .stat-card.lab .stat-icon { color: #8b5cf6; }
        .stat-card.revenue .stat-icon { color: #059669; }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            margin: 8px 0;
            color: #1e293b;
            line-height: 1;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9em;
            font-weight: 500;
            line-height: 1.3;
        }

        /* Content Tabs */
        .tabs-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 25px;
            background: none;
            border: none;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        
        .tab:hover {
            color: #3b82f6;
            background: #f0f9ff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }

        /* Table Styling */
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-card h3 {
            margin-bottom: 20px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }

        /* DataTables Styling */
        .dataTables_wrapper {
            margin-top: 15px;
        }
        
        .dataTables_length,
        .dataTables_filter {
            margin-bottom: 15px;
        }
        
        .dataTables_length select,
        .dataTables_filter input {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .dataTables_info {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            margin: 0 2px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #475569;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* Table Styling */
        table.dataTable {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        
        table.dataTable thead th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.85rem;
        }
        
        table.dataTable tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        
        table.dataTable tbody tr:hover {
            background: #f8fafc;
        }

        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-primary { background: #dbeafe; color: #1e40af; }

        /* Action buttons in table */
        .table-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Profile Form */
        .profile-form {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
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
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Revenue Breakdown */
        .revenue-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .revenue-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid;
        }
        
        .revenue-item.consultation { border-left-color: #f59e0b; }
        .revenue-item.medicine { border-left-color: #ef4444; }
        .revenue-item.lab { border-left-color: #8b5cf6; }
        .revenue-item.total { border-left-color: #059669; }
        
        .revenue-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .revenue-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1e293b;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .table-card {
                padding: 15px;
            }
            
            .table-actions {
                flex-direction: column;
            }
            
            .revenue-breakdown {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/logo.jpg">
</head>
<body>

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>CASHIER DEPARTMENT - TOMONDO ZANZIBAR</p>
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
                    <small style="font-size: 0.75rem;">Cashier Department</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

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
            
            <div class="stat-card consultation">
                <div class="stat-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <div class="stat-number">TZS <?php echo number_format($revenue_stats['total_consultation'], 2); ?></div>
                <div class="stat-label">Consultation Revenue</div>
            </div>
            
            <div class="stat-card medicine">
                <div class="stat-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <div class="stat-number">TZS <?php echo number_format($revenue_stats['total_medicine'], 2); ?></div>
                <div class="stat-label">Medicine Revenue</div>
            </div>
            
            <div class="stat-card lab">
                <div class="stat-icon">
                    <i class="fas fa-vial"></i>
                </div>
                <div class="stat-number">TZS <?php echo number_format($revenue_stats['total_lab'], 2); ?></div>
                <div class="stat-label">Laboratory Revenue</div>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number">TZS <?php echo number_format($revenue_stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="cashier_receipts.php" class="btn btn-primary">
                <i class="fas fa-receipt"></i>
                Manage Receipts
            </a>
            <button class="btn btn-success" onclick="showTab('revenue')">
                <i class="fas fa-chart-bar"></i>
                View Revenue Report
            </button>
            <button class="btn btn-warning" onclick="showTab('profile')">
                <i class="fas fa-user-cog"></i>
                Profile Settings
            </button>
        </div>

        <!-- Content Tabs -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="showTab('patients')">
                    <i class="fas fa-users"></i> All Patients
                </button>
                <button class="tab" onclick="showTab('revenue')">
                    <i class="fas fa-chart-line"></i> Revenue Report
                </button>
                <button class="tab" onclick="showTab('profile')">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </button>
            </div>

            <!-- Patients Tab -->
            <div id="patients" class="tab-content active">
                <h3>
                    <i class="fas fa-users"></i>
                    All Patients & Payment Records
                </h3>

                <div class="table-responsive">
                    <table id="patientsTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Card No</th>
                                <th>Patient Name</th>
                                <th>Phone</th>
                                <th>Consultation</th>
                                <th>Medicine</th>
                                <th>Laboratory</th>
                                <th>Total Cost</th>
                                <th>Last Visit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_patients as $patient): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($patient['card_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge badge-warning">
                                        TZS <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-danger">
                                        TZS <?php echo number_format($patient['total_medicine_cost'] ?? 0, 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        TZS <?php echo number_format($patient['total_lab_cost'] ?? 0, 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong>TZS <?php echo number_format($patient['total_cost'] ?? 0, 2); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    if ($patient['visit_date']) {
                                        echo date('M j, Y', strtotime($patient['visit_date']));
                                    } else {
                                        echo 'No visits';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <!-- <button class="btn btn-primary btn-sm" onclick="viewPatientDetails(<?php echo $patient['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button> -->
                                        <?php if ($patient['checking_form_id']): ?>
                                        <a href="cashier_receipts.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Revenue Report Tab -->
            <div id="revenue" class="tab-content">
                <h3>
                    <i class="fas fa-chart-line"></i>
                    Revenue Report
                </h3>

                <!-- Today's Revenue -->
                <div class="table-card">
                    <h4><i class="fas fa-calendar-day"></i> Today's Revenue</h4>
                    <div class="revenue-breakdown">
                        <div class="revenue-item consultation">
                            <div class="revenue-label">Consultation</div>
                            <div class="revenue-amount">TZS <?php echo number_format($today_revenue['total_consultation'], 2); ?></div>
                        </div>
                        <div class="revenue-item medicine">
                            <div class="revenue-label">Medicine</div>
                            <div class="revenue-amount">TZS <?php echo number_format($today_revenue['total_medicine'], 2); ?></div>
                        </div>
                        <div class="revenue-item lab">
                            <div class="revenue-label">Laboratory</div>
                            <div class="revenue-amount">TZS <?php echo number_format($today_revenue['total_lab'], 2); ?></div>
                        </div>
                        <div class="revenue-item total">
                            <div class="revenue-label">Total Today</div>
                            <div class="revenue-amount">TZS <?php echo number_format($today_revenue['total_revenue'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="table-card">
                    <h4><i class="fas fa-chart-bar"></i> Total Revenue</h4>
                    <div class="revenue-breakdown">
                        <div class="revenue-item consultation">
                            <div class="revenue-label">Consultation</div>
                            <div class="revenue-amount">TZS <?php echo number_format($revenue_stats['total_consultation'], 2); ?></div>
                        </div>
                        <div class="revenue-item medicine">
                            <div class="revenue-label">Medicine</div>
                            <div class="revenue-amount">TZS <?php echo number_format($revenue_stats['total_medicine'], 2); ?></div>
                        </div>
                        <div class="revenue-item lab">
                            <div class="revenue-label">Laboratory</div>
                            <div class="revenue-amount">TZS <?php echo number_format($revenue_stats['total_lab'], 2); ?></div>
                        </div>
                        <div class="revenue-item total">
                            <div class="revenue-label">Grand Total</div>
                            <div class="revenue-amount">TZS <?php echo number_format($revenue_stats['total_revenue'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Revenue by Patient -->
                <div class="table-card">
                    <h4><i class="fas fa-list"></i> Detailed Patient Payments</h4>
                    <div class="table-responsive">
                        <table id="revenueTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Card No</th>
                                    <th>Consultation</th>
                                    <th>Medicine</th>
                                    <th>Laboratory</th>
                                    <th>Total Paid</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_patients as $patient): ?>
                                <?php if ($patient['total_cost'] > 0): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($patient['card_no']); ?></strong></td>
                                    <td>TZS <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?></td>
                                    <td>TZS <?php echo number_format($patient['total_medicine_cost'] ?? 0, 2); ?></td>
                                    <td>TZS <?php echo number_format($patient['total_lab_cost'] ?? 0, 2); ?></td>
                                    <td><strong>TZS <?php echo number_format($patient['total_cost'] ?? 0, 2); ?></strong></td>
                                    <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Profile Settings Tab -->
            <div id="profile" class="tab-content">
                <h3>
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </h3>
                
                <div class="profile-form">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" placeholder="Enter email address">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" placeholder="Enter phone number">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" placeholder="Enter current password to change password">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" placeholder="Enter new password">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-sync"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#patientsTable').DataTable({
                "pageLength": 25,
                "responsive": true,
                "order": [[7, 'desc']],
                "language": {
                    "search": "Search patients:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ patients",
                    "infoEmpty": "Showing 0 to 0 of 0 patients",
                    "paginate": {
                        "previous": "Previous",
                        "next": "Next"
                    }
                }
            });

            $('#revenueTable').DataTable({
                "pageLength": 25,
                "responsive": true,
                "order": [[5, 'desc']],
                "language": {
                    "search": "Search revenue:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ records",
                    "infoEmpty": "Showing 0 to 0 of 0 records",
                    "paginate": {
                        "previous": "Previous",
                        "next": "Next"
                    }
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
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Function to view patient details
        function viewPatientDetails(patientId) {
            // You can implement a modal or redirect to patient details page
            alert('View patient details for ID: ' + patientId);
            // window.open('patient_details.php?id=' + patientId, '_blank');
        }

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
    </script>
</body>
</html>