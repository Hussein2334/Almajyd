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

// Get statistics for dashboard
$total_patients = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch(PDO::FETCH_ASSOC)['total'];
$total_doctors = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'active'")->fetch(PDO::FETCH_ASSOC)['total'];
$total_staff = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND status = 'active'")->fetch(PDO::FETCH_ASSOC)['total'];
$today_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent patients
$recent_patients = $pdo->query("SELECT p.*, u.full_name as created_by_name 
                               FROM patients p 
                               LEFT JOIN users u ON p.created_by = u.id 
                               ORDER BY p.created_at DESC 
                               LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get recent users
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Almajyd Dispensary</title>
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
        
        .stat-card.patients { border-left-color: #ef4444; }
        .stat-card.doctors { border-left-color: #3b82f6; }
        .stat-card.staff { border-left-color: #10b981; }
        .stat-card.today { border-left-color: #f59e0b; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.patients .stat-icon { color: #ef4444; }
        .stat-card.doctors .stat-icon { color: #3b82f6; }
        .stat-card.staff .stat-icon { color: #10b981; }
        .stat-card.today .stat-icon { color: #f59e0b; }
        
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

        /* Clickable Steps - IMPROVED WITH ACTIONS */
        .steps-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .steps-title {
            text-align: center;
            margin-bottom: 25px;
            color: #1e293b;
            font-size: 1.4rem;
        }
        
        .steps {
            display: flex;
            align-items: center;
            margin: 30px 0;
            position: relative;
            justify-content: center;
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
            width: 60px;
            height: 60px;
            background: white;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            color: #64748b;
            z-index: 2;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .step:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
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
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
        }
        
        .spacer { 
            flex-grow: 1; 
            min-width: 30px; 
        }

        /* Content area with ACTIONS */
        .content-area {
            margin-top: 25px;
            padding: 25px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px dashed #e2e8f0;
            min-height: 350px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .action-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #10b981;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .action-card h4 {
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-card h4 i {
            color: #10b981;
        }
        
        .action-list {
            list-style: none;
            margin-bottom: 15px;
        }
        
        .action-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-list li:last-child {
            border-bottom: none;
        }
        
        .action-list li i {
            color: #10b981;
            font-size: 0.9em;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
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

        /* Recent Activity Tables */
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        
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
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        /* Print Button */
        .print-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 600px;
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
                width: 50px;
                height: 50px;
                font-size: 16px;
            }
            
            .step-label {
                font-size: 10px;
            }
            
            .content-area {
                padding: 20px 15px;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .tables-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .table-card {
                padding: 15px;
            }
            
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: center !important;
                float: none !important;
                margin-bottom: 10px !important;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <div class="stat-card doctors">
                <div class="stat-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="stat-number"><?php echo $total_doctors; ?></div>
                <div class="stat-label">Active Doctors</div>
            </div>
            <div class="stat-card staff">
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-number"><?php echo $total_staff; ?></div>
                <div class="stat-label">Total Staff</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_patients; ?></div>
                <div class="stat-label">Today's Patients</div>
            </div>
        </div>

        <!-- Clickable Process Steps with ACTIONS -->
        <div class="steps-container">
            <h2 class="steps-title">Hospital Management Control Panel</h2>
            
            <div class="steps">
                <div class="step active" onclick="showStep(1)">
                    1
                    <div class="step-label">Users</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(2)">
                    2
                    <div class="step-label">Patients</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(3)">
                    3
                    <div class="step-label">Medical</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(4)">
                    4
                    <div class="step-label">Reports</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(5)">
                    5
                    <div class="step-label">System</div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div class="content-area" id="content">
                <h2 style="color:#10b981; margin-bottom: 15px;">Welcome to Admin Control Panel</h2>
                <p>Click on the numbers above to manage different sections of the hospital system.</p>
                
                <div class="action-grid">
                    <div class="action-card" onclick="showStep(1)">
                        <h4><i class="fas fa-users-cog"></i> User Management</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Add new staff members</li>
                            <li><i class="fas fa-check"></i> Manage user permissions</li>
                            <li><i class="fas fa-check"></i> View staff activity</li>
                        </ul>
                    </div>
                    <div class="action-card" onclick="showStep(2)">
                        <h4><i class="fas fa-user-injured"></i> Patient Management</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> View all patients</li>
                            <li><i class="fas fa-check"></i> Patient records</li>
                            <li><i class="fas fa-check"></i> Registration reports</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Tables -->
        <div class="tables-grid">
            <div class="table-card">
                <h3><i class="fas fa-user-injured"></i> Recent Patients</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="patientsTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Card No</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Phone</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_patients as $patient): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($patient['card_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                <td><?php echo $patient['age'] ?: 'N/A'; ?></td>
                                <td><?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'N/A'; ?></td>
                                <td><?php echo $patient['phone'] ?: 'N/A'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-card">
                <h3><i class="fas fa-user-plus"></i> Recent Users</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="usersTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['email'] ?: 'N/A'; ?></td>
                                <td>
                                    <span class="badge <?php echo $user['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
        // Initialize DataTables for both tables
        $(document).ready(function() {
            $('#patientsTable').DataTable({
                responsive: true,
                paging: false,
                searching: true,
                info: false,
                ordering: true,
                language: {
                    search: "Search patients:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });

            $('#usersTable').DataTable({
                responsive: true,
                paging: false,
                searching: true,
                info: false,
                ordering: true,
                language: {
                    search: "Search users:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        });

        // Function to show step content with ACTIONS
        function showStep(num) {
            // Remove active class from all steps
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            // Add active to clicked step
            event.target.classList.add('active');

            // Change content based on step
            const content = document.getElementById('content');
            
            const stepsContent = {
                1: `
                    <h2 style="color:#10b981; margin-bottom: 15px;"><i class="fas fa-users-cog"></i> User Management</h2>
                    <p>Manage all system users, their roles and permissions.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="window.location.href='manage_users.php'">
                            <i class="fas fa-plus"></i> Add New User
                        </button>
                        <button class="btn btn-success" onclick="printUserReport()">
                            <i class="fas fa-print"></i> Print User Report
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='manage_users.php'">
                            <h4><i class="fas fa-user-plus"></i> Add New User</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Create doctor accounts</li>
                                <li><i class="fas fa-check"></i> Add reception staff</li>
                                <li><i class="fas fa-check"></i> Setup laboratory technicians</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="window.location.href='manage_users.php'">
                                    <i class="fas fa-plus"></i> Add User
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='user_roles.php'">
                            <h4><i class="fas fa-user-shield"></i> Manage Roles</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Change user permissions</li>
                                <li><i class="fas fa-check"></i> Assign department access</li>
                                <li><i class="fas fa-check"></i> Update user status</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="window.location.href='user_roles.php'">
                                    <i class="fas fa-cog"></i> Manage Roles
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='user_reports.php'">
                            <h4><i class="fas fa-chart-bar"></i> User Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Staff activity logs</li>
                                <li><i class="fas fa-check"></i> User performance</li>
                                <li><i class="fas fa-check"></i> Login history</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-warning" onclick="window.location.href='user_reports.php'">
                                    <i class="fas fa-chart-line"></i> View Reports
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                2: `
                    <h2 style="color:#3b82f6; margin-bottom: 15px;"><i class="fas fa-user-injured"></i> Patient Management</h2>
                    <p>Manage patient records, registrations and medical history.</p>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='view_patients.php'">
                            <h4><i class="fas fa-list"></i> View All Patients</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Browse patient database</li>
                                <li><i class="fas fa-check"></i> Search patient records</li>
                                <li><i class="fas fa-check"></i> Filter by date/status</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="window.location.href='view_patients.php'">
                                    <i class="fas fa-list"></i> View Patients
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='patient_reports.php'">
                            <h4><i class="fas fa-file-medical"></i> Patient Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Registration statistics</li>
                                <li><i class="fas fa-check"></i> Treatment history</li>
                                <li><i class="fas fa-check"></i> Monthly summaries</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="window.location.href='patient_reports.php'">
                                    <i class="fas fa-chart-pie"></i> Generate Reports
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='patient_export.php'">
                            <h4><i class="fas fa-download"></i> Data Export</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Export to Excel</li>
                                <li><i class="fas fa-check"></i> PDF reports</li>
                                <li><i class="fas fa-check"></i> Backup data</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-warning" onclick="window.location.href='patient_export.php'">
                                    <i class="fas fa-file-export"></i> Export Data
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                3: `
                    <h2 style="color:#f59e0b; margin-bottom: 15px;"><i class="fas fa-stethoscope"></i> Medical Management</h2>
                    <p>Oversee medical operations, treatments and laboratory processes.</p>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='medical_records.php'">
                            <h4><i class="fas fa-file-medical-alt"></i> Medical Records</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> View treatment history</li>
                                <li><i class="fas fa-check"></i> Doctor assignments</li>
                                <li><i class="fas fa-check"></i> Prescription tracking</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="window.location.href='medical_records.php'">
                                    <i class="fas fa-folder-open"></i> View Records
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='lab_management.php'">
                            <h4><i class="fas fa-vial"></i> Laboratory Management</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Test results monitoring</li>
                                <li><i class="fas fa-check"></i> Lab technician assignments</li>
                                <li><i class="fas fa-check"></i> Quality control</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="window.location.href='lab_management.php'">
                                    <i class="fas fa-microscope"></i> Manage Lab
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='pharmacy_management.php'">
                            <h4><i class="fas fa-pills"></i> Pharmacy Control</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Medication inventory</li>
                                <li><i class="fas fa-check"></i> Prescription tracking</li>
                                <li><i class="fas fa-check"></i> Stock management</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-warning" onclick="window.location.href='pharmacy_management.php'">
                                    <i class="fas fa-capsules"></i> Pharmacy Control
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                4: `
                    <h2 style="color:#8b5cf6; margin-bottom: 15px;"><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
                    <p>Generate comprehensive reports and analyze system data.</p>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='financial_reports.php'">
                            <h4><i class="fas fa-money-bill-wave"></i> Financial Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Revenue analysis</li>
                                <li><i class="fas fa-check"></i> Payment tracking</li>
                                <li><i class="fas fa-check"></i> Expense reports</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="window.location.href='financial_reports.php'">
                                    <i class="fas fa-chart-line"></i> Financial Reports
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='performance_reports.php'">
                            <h4><i class="fas fa-tachometer-alt"></i> Performance Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Staff performance</li>
                                <li><i class="fas fa-check"></i> Department efficiency</li>
                                <li><i class="fas fa-check"></i> Service quality</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="window.location.href='performance_reports.php'">
                                    <i class="fas fa-chart-pie"></i> Performance
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='custom_reports.php'">
                            <h4><i class="fas fa-cog"></i> Custom Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Create custom queries</li>
                                <li><i class="fas fa-check"></i> Export any data</li>
                                <li><i class="fas fa-check"></i> Schedule reports</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-warning" onclick="window.location.href='custom_reports.php'">
                                    <i class="fas fa-magic"></i> Custom Reports
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                5: `
                    <h2 style="color:#ef4444; margin-bottom: 15px;"><i class="fas fa-cogs"></i> System Administration</h2>
                    <p>Configure system settings, backups and maintenance.</p>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='system_settings.php'">
                            <h4><i class="fas fa-sliders-h"></i> System Settings</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> General configuration</li>
                                <li><i class="fas fa-check"></i> Email settings</li>
                                <li><i class="fas fa-check"></i> Security options</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="window.location.href='system_settings.php'">
                                    <i class="fas fa-cog"></i> System Settings
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='backup_management.php'">
                            <h4><i class="fas fa-database"></i> Backup & Restore</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Database backups</li>
                                <li><i class="fas fa-check"></i> System restore points</li>
                                <li><i class="fas fa-check"></i> Data export/import</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="window.location.href='backup_management.php'">
                                    <i class="fas fa-save"></i> Backup System
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='user_reports.php'">
                            <h4><i class="fas fa-clipboard-list"></i> System Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> System activity overview</li>
                                <li><i class="fas fa-check"></i> User activity tracking</li>
                                <li><i class="fas fa-check"></i> Performance monitoring</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-warning" onclick="window.location.href='user_reports.php'">
                                    <i class="fas fa-chart-bar"></i> View Reports
                                </button>
                            </div>
                        </div>
                    </div>
                `
            };
            
            content.innerHTML = stepsContent[num] || stepsContent[1];
        }

        // Print user report function
        function printUserReport() {
            window.open('user_reports.php?print=true', '_blank');
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