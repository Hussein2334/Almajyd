<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle form actions
$message = '';
$message_type = '';

// Update user permissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_permissions'])) {
    $user_id = $_POST['user_id'];
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    
    // Convert permissions array to JSON string
    $permissions_json = json_encode($permissions);
    
    try {
        // Check if permissions column exists, if not alter table
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'permissions'");
        $stmt->execute();
        $column_exists = $stmt->fetch();
        
        if (!$column_exists) {
            // Add permissions column
            $pdo->exec("ALTER TABLE users ADD COLUMN permissions TEXT AFTER status");
        }
        
        $stmt = $pdo->prepare("UPDATE users SET permissions = ? WHERE id = ?");
        $stmt->execute([$permissions_json, $user_id]);
        
        $message = "User permissions updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating permissions: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing permissions
$edit_user = null;
if (isset($_GET['edit_permissions'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit_permissions']]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Decode permissions if they exist
    if ($edit_user && isset($edit_user['permissions']) && $edit_user['permissions']) {
        $edit_user['permissions_array'] = json_decode($edit_user['permissions'], true);
    } else {
        $edit_user['permissions_array'] = [];
    }
}

// Define available permissions for each role
$role_permissions = [
    'doctor' => [
        'patients' => ['view', 'create', 'edit', 'delete'],
        'appointments' => ['view', 'create', 'edit', 'delete'],
        'prescriptions' => ['view', 'create', 'edit', 'delete'],
        'lab_tests' => ['view', 'create', 'edit'],
        'medical_records' => ['view', 'create', 'edit'],
        'reports' => ['view']
    ],
    'receptionist' => [
        'patients' => ['view', 'create', 'edit'],
        'appointments' => ['view', 'create', 'edit', 'delete'],
        'billing' => ['view', 'create'],
        'reports' => ['view']
    ],
    'laboratory' => [
        'lab_tests' => ['view', 'create', 'edit', 'delete'],
        'patients' => ['view'],
        'reports' => ['view']
    ],
    'pharmacy' => [
        'prescriptions' => ['view', 'dispense'],
        'inventory' => ['view', 'manage'],
        'patients' => ['view'],
        'reports' => ['view']
    ],
    'cashier' => [
        'billing' => ['view', 'create', 'edit', 'delete'],
        'payments' => ['view', 'process'],
        'patients' => ['view'],
        'reports' => ['view']
    ],
    'admin' => [
        'all' => ['full_access']
    ]
];

// Get current date and time
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles & Permissions - Almajyd Dispensary</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        /* Topbar */
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

        /* Navigation Steps */
        .nav-steps {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid #e2e8f0;
        }
        
        .steps-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e2e8f0;
            border-radius: 3px;
            z-index: 1;
        }
        
        .step {
            width: 50px;
            height: 50px;
            background: white;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            color: #64748b;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            text-decoration: none;
        }
        
        .step:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .step.active {
            border-color: #10b981;
            color: white;
            background: #10b981;
            box-shadow: 0 0 15px rgba(16,185,129,0.4);
        }
        
        .step-label {
            position: absolute;
            top: 100%;
            margin-top: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
        }
        
        .spacer { 
            flex-grow: 1; 
            min-width: 30px; 
        }

        /* Main Content */
        .main { 
            padding: 25px; 
            max-width: 1400px; 
            margin: 0 auto; 
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title h2 {
            font-size: 1.8rem;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .back-btn {
            background: #6b7280;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Message Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Permissions Form */
        .permissions-form {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .permissions-form h3 {
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .permission-category {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #0ea5e9;
        }
        
        .permission-category h4 {
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .permission-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .permission-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 6px;
            transition: background 0.3s ease;
        }
        
        .permission-option:hover {
            background: #e2e8f0;
        }
        
        .permission-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0ea5e9;
        }
        
        .permission-label {
            font-size: 0.9rem;
            color: #374151;
            cursor: pointer;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Users Table */
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-header h3 {
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 15px;
        }
        
        .table-responsive table {
            min-width: 1000px;
        }

        /* DataTables Custom Styling */
        .dataTables_wrapper {
            font-size: 0.85rem;
            width: 100%;
        }
        
        .dataTables_wrapper .dataTables_filter {
            float: none !important;
            text-align: center !important;
            margin-bottom: 15px !important;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
            font-size: 0.85rem !important;
        }
        
        .dataTables_wrapper .dataTables_length {
            float: none !important;
            text-align: center !important;
            margin-bottom: 15px !important;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            font-size: 0.85rem !important;
        }
        
        .dataTables_wrapper .dataTables_info {
            color: #6b7280 !important;
            font-size: 0.8rem !important;
            padding: 10px 0 !important;
            text-align: center !important;
        }
        
        .dataTables_wrapper .dataTables_paginate {
            text-align: center !important;
            margin-top: 15px !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 1px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            margin: 0 2px !important;
            font-size: 0.8rem !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-info {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .badge-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.7rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 30px;
            justify-content: center;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-info {
            background: #0ea5e9;
            color: white;
        }
        
        .btn-info:hover {
            background: #0284c7;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        /* User Info Header */
        .user-info-header {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .user-info-content {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .user-avatar-large {
            width: 60px;
            height: 60px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .user-details h4 {
            margin: 0;
            font-size: 1.2rem;
            color: #1e293b;
        }
        
        .user-details p {
            margin: 5px 0 0 0;
            color: #64748b;
            font-size: 0.9rem;
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
            
            .nav-steps {
                padding: 15px 20px;
            }
            
            .steps::before {
                display: none;
            }
            
            .steps {
                justify-content: space-around;
            }
            
            .spacer {
                display: none;
            }
            
            .step {
                width: 45px;
                height: 45px;
                font-size: 16px;
            }
            
            .step-label {
                font-size: 10px;
            }
            
            .main {
                padding: 15px;
            }
            
            .permissions-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-responsive table {
                min-width: 900px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-sm {
                width: 100%;
                justify-content: center;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .user-area {
                flex-direction: column;
                gap: 10px;
            }
            
            .time-display, .user, .logout-btn {
                width: 100%;
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .back-btn {
                width: 100%;
                justify-content: center;
            }
            
            .steps {
                gap: 5px;
            }
            
            .step {
                width: 40px;
                height: 40px;
                font-size: 14px;
            }
            
            .step-label {
                font-size: 9px;
            }
            
            .permission-category {
                padding: 15px;
            }
            
            .user-info-content {
                flex-direction: column;
                text-align: center;
            }
            
            .table-responsive table {
                min-width: 800px;
            }
        }

        @media (max-width: 360px) {
            .table-responsive table {
                min-width: 700px;
            }
            
            .action-buttons .btn-sm {
                padding: 4px 8px;
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>TOMONDO - ZANZIBAR</p>
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
                    <small style="font-size: 0.75rem;">Administrator</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <!-- PERSISTENT NAVIGATION STEPS -->
    <div class="nav-steps">
        <div class="steps-container">
            <div class="steps">
                <a href="dashboard.php" class="step">
                    1
                    <div class="step-label">Dashboard</div>
                </a>
                <div class="spacer"></div>
                <a href="manage_users.php" class="step">
                    2
                    <div class="step-label">Users</div>
                </a>
                <div class="spacer"></div>
                <a href="user_roles.php" class="step active">
                    3
                    <div class="step-label">Permissions</div>
                </a>
                <div class="spacer"></div>
                <a href="reports.php" class="step">
                    4
                    <div class="step-label">Reports</div>
                </a>
                <div class="spacer"></div>
                <a href="system_settings.php" class="step">
                    5
                    <div class="step-label">System</div>
                </a>
            </div>
        </div>
    </div>

    <div class="main">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h2><i class="fas fa-user-shield"></i> User Roles & Permissions</h2>
                <p>Manage user permissions and access control</p>
            </div>
            <a href="manage_users.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Users
            </a>
        </div>

        <!-- Message Alerts -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'error'; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Edit User Permissions Form -->
        <?php if ($edit_user): ?>
        <div class="user-info-header">
            <div class="user-info-content">
                <div class="user-avatar-large">
                    <?php echo strtoupper(substr($edit_user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($edit_user['full_name']); ?></h4>
                    <p><?php echo ucfirst($edit_user['role']); ?> • <?php echo ucfirst($edit_user['status']); ?></p>
                </div>
            </div>
        </div>

        <div class="permissions-form">
            <h3><i class="fas fa-edit"></i> Manage Permissions</h3>
            <form method="POST" id="editPermissionsForm">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <input type="hidden" name="update_permissions" value="1">
                
                <div class="permissions-grid">
                    <?php 
                    $user_role = $edit_user['role'];
                    if (isset($role_permissions[$user_role])): 
                        foreach ($role_permissions[$user_role] as $category => $actions): 
                    ?>
                    <div class="permission-category">
                        <h4>
                            <i class="fas fa-<?php 
                                switch($category) {
                                    case 'patients': echo 'user-injured'; break;
                                    case 'appointments': echo 'calendar-check'; break;
                                    case 'prescriptions': echo 'prescription'; break;
                                    case 'lab_tests': echo 'vial'; break;
                                    case 'medical_records': echo 'file-medical'; break;
                                    case 'billing': echo 'money-bill-wave'; break;
                                    case 'payments': echo 'credit-card'; break;
                                    case 'inventory': echo 'boxes'; break;
                                    case 'reports': echo 'chart-bar'; break;
                                    case 'all': echo 'shield-alt'; break;
                                    default: echo 'cog';
                                }
                            ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $category)); ?>
                        </h4>
                        <div class="permission-options">
                            <?php foreach ($actions as $action): ?>
                            <div class="permission-option">
                                <input type="checkbox" 
                                       id="perm_<?php echo $category . '_' . $action; ?>" 
                                       name="permissions[<?php echo $category; ?>][]" 
                                       value="<?php echo $action; ?>"
                                       <?php 
                                       // Safely check if permission is set
                                       $has_permission = false;
                                       if (isset($edit_user['permissions_array'][$category]) && is_array($edit_user['permissions_array'][$category])) {
                                           $has_permission = in_array($action, $edit_user['permissions_array'][$category]);
                                       }
                                       echo $has_permission ? 'checked' : ''; 
                                       ?>>
                                <label for="perm_<?php echo $category . '_' . $action; ?>" class="permission-label">
                                    <?php echo ucfirst($action); ?> access
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                    <div class="permission-category">
                        <h4><i class="fas fa-exclamation-triangle"></i> No Permissions Defined</h4>
                        <p>No permission templates defined for the role: <?php echo $user_role; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Permissions
                    </button>
                    <a href="user_roles.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Users Table with DataTables -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-users"></i> System Users</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <span class="badge badge-primary">Total: <?php echo count($users); ?></span>
                    <span class="badge badge-success">Active: <?php echo count(array_filter($users, fn($u) => $u['status'] == 'active')); ?></span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="usersTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Permissions</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            // Safely get permissions count
                            $permission_count = 0;
                            if (isset($user['permissions']) && $user['permissions']) {
                                $user_permissions = json_decode($user['permissions'], true);
                                if ($user_permissions) {
                                    foreach ($user_permissions as $perms) {
                                        $permission_count += is_array($perms) ? count($perms) : 0;
                                    }
                                }
                            }
                            
                            // Safely get last login
                            $last_login = 'Never';
                            if (isset($user['last_login']) && $user['last_login']) {
                                $last_login = date('M j, Y', strtotime($user['last_login']));
                            }
                        ?>
                        <tr>
                            <td><strong>#<?php echo $user['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td>
                                <span class="badge 
                                    <?php 
                                    switch($user['role']) {
                                        case 'admin': echo 'badge-primary'; break;
                                        case 'doctor': echo 'badge-success'; break;
                                        case 'receptionist': echo 'badge-info'; break;
                                        case 'laboratory': echo 'badge-warning'; break;
                                        case 'pharmacy': echo 'badge-secondary'; break;
                                        case 'cashier': echo 'badge-danger'; break;
                                        default: echo 'badge-secondary';
                                    }
                                    ?>">
                                    <i class="fas fa-<?php 
                                        switch($user['role']) {
                                            case 'admin': echo 'user-shield'; break;
                                            case 'doctor': echo 'user-md'; break;
                                            case 'receptionist': echo 'user-tie'; break;
                                            case 'laboratory': echo 'vial'; break;
                                            case 'pharmacy': echo 'pills'; break;
                                            case 'cashier': echo 'cash-register'; break;
                                            default: echo 'user';
                                        }
                                    ?>"></i>
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo $permission_count; ?> perms
                                </span>
                            </td>
                            <td><?php echo $last_login; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?edit_permissions=<?php echo $user['id']; ?>" class="btn-sm btn-info">
                                        <i class="fas fa-shield-alt"></i>
                                        Permissions
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#usersTable').DataTable({
                responsive: true,
                language: {
                    search: "Search users:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                    infoEmpty: "Showing 0 to 0 of 0 users",
                    infoFiltered: "(filtered from _MAX_ total users)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                pageLength: 10,
                order: [[0, 'desc']],
                columnDefs: [
                    { responsivePriority: 1, targets: 2 }, // Full Name
                    { responsivePriority: 2, targets: 7 }, // Actions
                    { responsivePriority: 3, targets: 3 }, // Role
                    { responsivePriority: 4, targets: 4 }, // Status
                    { orderable: false, targets: 7 } // Actions column not sortable
                ]
            });
        });

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

        // Scroll to permissions form when editing
        <?php if ($edit_user): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.user-info-header').scrollIntoView({
                behavior: 'smooth'
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>