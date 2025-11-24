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

// Get system logs from database
// Note: You need to create an audit_logs table first
try {
    // Check if audit_logs table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'audit_logs'");
    $stmt->execute();
    $table_exists = $stmt->fetch();

    if (!$table_exists) {
        // Create audit_logs table if it doesn't exist
        $pdo->exec("
            CREATE TABLE audit_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                username VARCHAR(100),
                action VARCHAR(255),
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        
        // Insert some sample logs for demonstration
        $sample_logs = [
            [1, 'admin', 'LOGIN', 'User logged into the system', '192.168.1.100', 'Mozilla/5.0...'],
            [2, 'doctor1', 'VIEW_PATIENT', 'Viewed patient record #123', '192.168.1.101', 'Mozilla/5.0...'],
            [1, 'admin', 'CREATE_USER', 'Created new user: nurse1', '192.168.1.100', 'Mozilla/5.0...'],
            [3, 'reception1', 'CREATE_APPOINTMENT', 'Created appointment for patient #456', '192.168.1.102', 'Mozilla/5.0...'],
            [2, 'doctor1', 'UPDATE_PRESCRIPTION', 'Updated prescription for patient #123', '192.168.1.101', 'Mozilla/5.0...'],
            [1, 'admin', 'SYSTEM_BACKUP', 'Performed system backup', '192.168.1.100', 'Mozilla/5.0...'],
            [4, 'lab_tech1', 'UPLOAD_RESULTS', 'Uploaded lab results for test #789', '192.168.1.103', 'Mozilla/5.0...'],
            [1, 'admin', 'VIEW_REPORTS', 'Generated user activity report', '192.168.1.100', 'Mozilla/5.0...']
        ];

        $insert_stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, action, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND() * 30) DAY)
        ");

        foreach ($sample_logs as $log) {
            $insert_stmt->execute($log);
        }
    }

    // Get logs with pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;

    // Get total count
    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs");
    $total_logs = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_logs / $limit);

    // Get logs
    $logs_stmt = $pdo->prepare("
        SELECT al.*, u.full_name, u.role 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $logs_stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $logs_stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $logs_stmt->execute();
    $logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $logs = [];
    $total_logs = 0;
    $total_pages = 1;
    $error = "Error loading system logs: " . $e->getMessage();
}

// Get current date and time
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Reports & System Logs - Almajyd Dispensary</title>
    <!-- DataTables CSS -->
      <link rel="icon" href="../images/logo.jpg">
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid;
        }
        
        .stat-card.total { border-left-color: #3b82f6; }
        .stat-card.logins { border-left-color: #10b981; }
        .stat-card.updates { border-left-color: #f59e0b; }
        .stat-card.creates { border-left-color: #ef4444; }
        
        .stat-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .stat-card.total .stat-icon { color: #3b82f6; }
        .stat-card.logins .stat-icon { color: #10b981; }
        .stat-card.updates .stat-icon { color: #f59e0b; }
        .stat-card.creates .stat-icon { color: #ef4444; }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            margin: 8px 0;
            color: #1e293b;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.85em;
            font-weight: 500;
        }

        /* Table Container */
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
            min-width: 1200px;
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

        /* Action Type Colors */
        .action-login { background: #d1fae5; color: #065f46; }
        .action-create { background: #dbeafe; color: #1e40af; }
        .action-update { background: #fef3c7; color: #92400e; }
        .action-delete { background: #fee2e2; color: #991b1b; }
        .action-view { background: #e0e7ff; color: #3730a3; }
        .action-system { background: #f3f4f6; color: #6b7280; }

        /* Export Button */
        .export-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            background: #059669;
            transform: translateY(-2px);
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-responsive table {
                min-width: 1000px;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive table {
                min-width: 900px;
            }
        }

        @media (max-width: 360px) {
            .table-responsive table {
                min-width: 800px;
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
                <a href="user_roles.php" class="step">
                    3
                    <div class="step-label">Permissions</div>
                </a>
                <div class="spacer"></div>
                <a href="user_reports.php" class="step active">
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
                <h2><i class="fas fa-chart-bar"></i> System Logs & User Reports</h2>
                <p>Monitor user activities and system events</p>
            </div>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_logs); ?></div>
                <div class="stat-label">Total Logs</div>
            </div>
            <div class="stat-card logins">
                <div class="stat-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $login_count = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE action LIKE '%LOGIN%'")->fetch(PDO::FETCH_ASSOC)['count'];
                    echo number_format($login_count);
                    ?>
                </div>
                <div class="stat-label">User Logins</div>
            </div>
            <div class="stat-card updates">
                <div class="stat-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $update_count = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE action LIKE '%UPDATE%'")->fetch(PDO::FETCH_ASSOC)['count'];
                    echo number_format($update_count);
                    ?>
                </div>
                <div class="stat-label">Data Updates</div>
            </div>
            <div class="stat-card creates">
                <div class="stat-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $create_count = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE action LIKE '%CREATE%'")->fetch(PDO::FETCH_ASSOC)['count'];
                    echo number_format($create_count);
                    ?>
                </div>
                <div class="stat-label">New Records</div>
            </div>
        </div>

        <!-- System Logs Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-clipboard-list"></i> System Activity Logs</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="export-btn" onclick="exportToExcel()">
                        <i class="fas fa-file-export"></i>
                        Export to Excel
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="logsTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): 
                            // Determine badge class based on action type
                            $action_class = 'badge-secondary';
                            if (strpos($log['action'], 'LOGIN') !== false) $action_class = 'action-login';
                            elseif (strpos($log['action'], 'CREATE') !== false) $action_class = 'action-create';
                            elseif (strpos($log['action'], 'UPDATE') !== false) $action_class = 'action-update';
                            elseif (strpos($log['action'], 'DELETE') !== false) $action_class = 'action-delete';
                            elseif (strpos($log['action'], 'VIEW') !== false) $action_class = 'action-view';
                            elseif (strpos($log['action'], 'SYSTEM') !== false) $action_class = 'action-system';
                        ?>
                        <tr>
                            <td><strong>#<?php echo $log['id']; ?></strong></td>
                            <td><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                    <?php if ($log['full_name']): ?>
                                    <br><small><?php echo htmlspecialchars($log['full_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo $log['role'] ? ucfirst($log['role']) : 'N/A'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $action_class; ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                            <td>
                                <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                            </td>
                            <td>
                                <small title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                    <?php 
                                    $ua = $log['user_agent'];
                                    if (strlen($ua) > 30) {
                                        echo htmlspecialchars(substr($ua, 0, 30)) . '...';
                                    } else {
                                        echo htmlspecialchars($ua);
                                    }
                                    ?>
                                </small>
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
            $('#logsTable').DataTable({
                responsive: true,
                language: {
                    search: "Search logs:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ logs",
                    infoEmpty: "Showing 0 to 0 of 0 logs",
                    infoFiltered: "(filtered from _MAX_ total logs)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { responsivePriority: 1, targets: 2 }, // User
                    { responsivePriority: 2, targets: 4 }, // Action
                    { responsivePriority: 3, targets: 5 }, // Description
                    { responsivePriority: 4, targets: 1 }, // Timestamp
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

        // Export to Excel function
        function exportToExcel() {
            // Simple table export (you can enhance this with a proper library)
            const table = document.getElementById('logsTable');
            const html = table.outerHTML;
            const url = 'data:application/vnd.ms-excel,' + escape(html);
            window.open(url, '_blank');
        }

        // Auto-refresh logs every 30 seconds
        setInterval(function() {
            // You can implement auto-refresh here if needed
            // location.reload();
        }, 30000);
    </script>
</body>
</html>