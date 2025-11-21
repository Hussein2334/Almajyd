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

// Add new user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $district = trim($_POST['district']);
        $region = trim($_POST['region']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, full_name, phone, district, region) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $role, $full_name, $phone, $district, $region]);
            
            $message = "User added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "Username already exists!";
            } else {
                $message = "Error adding user: " . $e->getMessage();
            }
            $message_type = "error";
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $district = trim($_POST['district']);
        $region = trim($_POST['region']);
        $status = $_POST['status'];
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, email = ?, role = ?, full_name = ?, phone = ?, district = ?, region = ?, status = ? WHERE id = ?");
            $stmt->execute([$username, $password, $email, $role, $full_name, $phone, $district, $region, $status, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, full_name = ?, phone = ?, district = ?, region = ?, status = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $full_name, $phone, $district, $region, $status, $user_id]);
        }
        
        $message = "User updated successfully!";
        $message_type = "success";
    }
}

// Handle GET actions (activate/deactivate/delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    if ($_GET['action'] == 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "User activated successfully!";
        $message_type = "success";
    } elseif ($_GET['action'] == 'deactivate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "User deactivated successfully!";
        $message_type = "success";
    } elseif ($_GET['action'] == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            $message = "User deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Cannot delete admin users!";
            $message_type = "error";
        }
    }
}

// Get user data for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get current date and time
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Almajyd Dispensary</title>
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

        /* Form Container */
        .form-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .form-container h3 {
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #10b981;
            background: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
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
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        /* Users Table Container */
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
            background: #3b82f6;
            color: white;
        }
        
        .btn-info:hover {
            background: #2563eb;
        }

        /* Edit Form Styling */
        .edit-form-container {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 2px solid #f59e0b;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .edit-form-container h3 {
            color: #92400e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
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
            
            .form-grid {
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
            
            .form-container,
            .edit-form-container,
            .table-container {
                padding: 15px;
            }
            
            .table-responsive {
                font-size: 0.8rem;
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
                <a href="manage_users.php" class="step active">
                    2
                    <div class="step-label">Users</div>
                </a>
                <div class="spacer"></div>
                <a href="../view_patients.php" class="step">
                    3
                    <div class="step-label">Patients</div>
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
                <h2><i class="fas fa-users-cog"></i> User Management</h2>
                <p>Add, edit, and manage system users and their permissions</p>
            </div>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Message Alerts -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'error'; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Edit User Form (Shows when editing) -->
        <?php if ($edit_user): ?>
        <div class="edit-form-container">
            <h3><i class="fas fa-edit"></i> Edit User: <?php echo htmlspecialchars($edit_user['full_name']); ?></h3>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <input type="hidden" name="update_user" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_username">Username *</label>
                        <input type="text" id="edit_username" name="username" class="form-control" required 
                               value="<?php echo htmlspecialchars($edit_user['username']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">New Password (Leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password" class="form-control" 
                               placeholder="Enter new password">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email Address</label>
                        <input type="email" id="edit_email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_role">Role *</label>
                        <select id="edit_role" name="role" class="form-control" required>
                            <option value="doctor" <?php echo $edit_user['role'] == 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="receptionist" <?php echo $edit_user['role'] == 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                            <option value="laboratory" <?php echo $edit_user['role'] == 'laboratory' ? 'selected' : ''; ?>>Laboratory Technician</option>
                            <option value="pharmacy" <?php echo $edit_user['role'] == 'pharmacy' ? 'selected' : ''; ?>>Pharmacy Staff</option>
                            <option value="cashier" <?php echo $edit_user['role'] == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                            <?php if ($edit_user['role'] == 'admin'): ?>
                            <option value="admin" selected>Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_full_name">Full Name *</label>
                        <input type="text" id="edit_full_name" name="full_name" class="form-control" required 
                               value="<?php echo htmlspecialchars($edit_user['full_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_phone">Phone Number</label>
                        <input type="tel" id="edit_phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_district">District</label>
                        <input type="text" id="edit_district" name="district" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['district']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_region">Region</label>
                        <input type="text" id="edit_region" name="region" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['region']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status *</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="active" <?php echo $edit_user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $edit_user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update User
                    </button>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel Edit
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Add User Form -->
        <div class="form-container">
            <h3><i class="fas fa-user-plus"></i> Add New User</h3>
            <form method="POST" id="addUserForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" class="form-control" required 
                               placeholder="Enter username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" class="form-control" required 
                               placeholder="Enter password">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="user@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="doctor">Doctor</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="laboratory">Laboratory Technician</option>
                            <option value="pharmacy">Pharmacy Staff</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required 
                               placeholder="Enter full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               placeholder="+255 XXX XXX XXX">
                    </div>
                    
                    <div class="form-group">
                        <label for="district">District</label>
                        <input type="text" id="district" name="district" class="form-control" 
                               placeholder="Enter district">
                    </div>
                    
                    <div class="form-group">
                        <label for="region">Region</label>
                        <input type="text" id="region" name="region" class="form-control" 
                               placeholder="Enter region">
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Add User
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Reset Form
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table with DataTables -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-users"></i> System Users (<?php echo count($users); ?>)</h3>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="usersTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
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
                            <td><?php echo htmlspecialchars($user['email'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                            <td>
                                <?php 
                                $location = [];
                                if ($user['district']) $location[] = $user['district'];
                                if ($user['region']) $location[] = $user['region'];
                                echo $location ? implode(', ', $location) : 'N/A';
                                ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['status'] == 'active'): ?>
                                        <a href="?action=deactivate&id=<?php echo $user['id']; ?>" 
                                           class="btn-sm btn-warning" 
                                           onclick="return confirm('Deactivate this user?')">
                                            <i class="fas fa-pause"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=activate&id=<?php echo $user['id']; ?>" 
                                           class="btn-sm btn-success"
                                           onclick="return confirm('Activate this user?')">
                                            <i class="fas fa-play"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['role'] != 'admin'): ?>
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                           class="btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-sm btn-secondary" style="cursor: not-allowed; opacity: 0.6;">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <a href="?edit=<?php echo $user['id']; ?>" class="btn-sm btn-info">
                                        <i class="fas fa-edit"></i>
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
                    { responsivePriority: 1, targets: 1 }, // Username
                    { responsivePriority: 2, targets: 2 }, // Full Name
                    { responsivePriority: 3, targets: 3 }, // Role
                    { responsivePriority: 4, targets: 9 }, // Actions
                    { orderable: false, targets: 9 } // Actions column not sortable
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

        // Form validation
        document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                e.preventDefault();
                return false;
            }
            return true;
        });

        // Edit form validation
        document.getElementById('editUserForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('edit_password').value;
            if (password && password.length < 6) {
                alert('Password must be at least 6 characters long!');
                e.preventDefault();
                return false;
            }
            return true;
        });

        // Auto-fill form based on role (for add form)
        document.getElementById('role')?.addEventListener('change', function() {
            const role = this.value;
            const phoneField = document.getElementById('phone');
            const districtField = document.getElementById('district');
            const regionField = document.getElementById('region');
            
            // Set default phone based on role
            switch(role) {
                case 'doctor':
                    phoneField.placeholder = '+255 777 123 456';
                    break;
                case 'receptionist':
                    phoneField.placeholder = '+255 777 234 567';
                    break;
                case 'laboratory':
                    phoneField.placeholder = '+255 777 345 678';
                    break;
                case 'pharmacy':
                    phoneField.placeholder = '+255 777 456 789';
                    break;
                case 'cashier':
                    phoneField.placeholder = '+255 777 567 890';
                    break;
                default:
                    phoneField.placeholder = '+255 XXX XXX XXX';
            }
            
            // Set default location to Tomondo, Zanzibar
            if (!districtField.value) {
                districtField.value = 'Tomondo';
            }
            if (!regionField.value) {
                regionField.value = 'Zanzibar';
            }
        });

        // Scroll to edit form when editing
        <?php if ($edit_user): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.edit-form-container').scrollIntoView({
                behavior: 'smooth'
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>