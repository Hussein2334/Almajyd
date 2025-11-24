<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header('Location: ../login.php');
    exit;
}

// Get patient ID from URL
$patient_id = $_GET['id'] ?? 0;

if (!$patient_id) {
    header('Location: view_patients.php');
    exit;
}

// Get patient details
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: view_patients.php');
    exit;
}

// Get patient's checking forms (medical history)
$checking_forms_stmt = $pdo->prepare("SELECT cf.*, u.full_name as doctor_name 
                                    FROM checking_forms cf 
                                    LEFT JOIN users u ON cf.doctor_id = u.id 
                                    WHERE cf.patient_id = ? 
                                    ORDER BY cf.created_at DESC");
$checking_forms_stmt->execute([$patient_id]);
$checking_forms = $checking_forms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient's prescriptions
$prescriptions_stmt = $pdo->prepare("SELECT p.*, cf.id as form_id, u.full_name as doctor_name
                                    FROM prescriptions p 
                                    JOIN checking_forms cf ON p.checking_form_id = cf.id 
                                    LEFT JOIN users u ON cf.doctor_id = u.id 
                                    WHERE cf.patient_id = ? 
                                    ORDER BY p.created_at DESC");
$prescriptions_stmt->execute([$patient_id]);
$prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient's laboratory tests
$lab_tests_stmt = $pdo->prepare("SELECT lt.*, cf.id as form_id, u.full_name as conducted_by_name 
                               FROM laboratory_tests lt 
                               JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                               LEFT JOIN users u ON lt.conducted_by = u.id 
                               WHERE cf.patient_id = ? 
                               ORDER BY lt.created_at DESC");
$lab_tests_stmt->execute([$patient_id]);
$lab_tests = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_checkups = count($checking_forms);
$total_prescriptions = count($prescriptions);
$total_lab_tests = count($lab_tests);

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Almajyd Dispensary</title>
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            color: #1e293b;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Patient Profile Card */
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 30px;
            align-items: start;
        }
        
        .patient-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            box-shadow: 0 8px 25px rgba(59,130,246,0.3);
        }
        
        .patient-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #1e293b;
            font-weight: 600;
        }
        
        .info-value-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.checkups { border-left-color: #3b82f6; }
        .stat-card.prescriptions { border-left-color: #10b981; }
        .stat-card.lab-tests { border-left-color: #f59e0b; }
        .stat-card.registered { border-left-color: #8b5cf6; }
        
        .stat-icon {
            font-size: 1.8em;
            margin-bottom: 8px;
        }
        
        .stat-card.checkups .stat-icon { color: #3b82f6; }
        .stat-card.prescriptions .stat-icon { color: #10b981; }
        .stat-card.lab-tests .stat-icon { color: #f59e0b; }
        .stat-card.registered .stat-icon { color: #8b5cf6; }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            margin: 5px 0;
            color: #1e293b;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.8em;
            font-weight: 500;
        }

        /* Tabs Navigation */
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .tabs-nav {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            overflow-x: auto;
        }
        
        .tab-btn {
            padding: 15px 25px;
            background: none;
            border: none;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn:hover {
            color: #3b82f6;
            background: #f1f5f9;
        }
        
        .tab-btn.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background: white;
        }
        
        .tab-content {
            display: none;
            padding: 0;
        }
        
        .tab-content.active {
            display: block;
        }

        /* Table Card */
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
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

        /* Tables Styling */
        .custom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        .custom-table thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .custom-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .custom-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        .custom-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .custom-table tbody tr:last-child td {
            border-bottom: none;
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
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: #f1f5f9; color: #475569; }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
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

        .btn-info {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-info:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-view {
            background: #3b82f6;
            color: white;
            padding: 4px 8px;
            border: none;
            border-radius: 6px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-view:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .btn-print {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border: none;
            border-radius: 6px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-print:hover {
            background: #059669;
            transform: translateY(-1px);
        }

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
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #d1d5db;
        }

        /* Search and Filter */
        .search-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-actions {
                width: 100%;
                justify-content: center;
            }
            
            .profile-card {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 20px;
            }
            
            .patient-avatar {
                margin: 0 auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.5em;
            }
            
            .tabs-nav {
                flex-direction: column;
            }
            
            .tab-btn {
                justify-content: center;
                border-bottom: 1px solid #e2e8f0;
                border-right: none;
            }
            
            .table-card {
                padding: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .custom-table {
                font-size: 0.8rem;
            }
            
            .custom-table th,
            .custom-table td {
                padding: 8px 10px;
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
                    <small style="font-size: 0.75rem;">Medical Doctor</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-user-injured"></i>
                    Patient Details
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Complete medical profile and history</p>
            </div>
            <div class="page-actions">
                <button onclick="window.location.href='view_patients.php'" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i>
                    Back to Patients
                </button>
                <button onclick="createNewCheckup()" class="btn btn-success">
                    <i class="fas fa-stethoscope"></i>
                    New Checkup
                </button>
                <!-- <button onclick="printPatientSummary()" class="btn btn-info">
                    <i class="fas fa-print"></i>
                    Print Summary
                </button> -->
            </div>
        </div>

        <!-- Patient Profile -->
        <div class="profile-card">
            <div class="patient-avatar">
                <?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?>
            </div>
            <div class="patient-info">
                <div>
                    <div class="info-group">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Card Number</div>
                        <div class="info-value-badge"><?php echo htmlspecialchars($patient['card_no']); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Age</div>
                        <div class="info-value">
                            <?php echo $patient['age'] ? $patient['age'] . ' years' : 'N/A'; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="info-group">
                        <div class="info-label">Gender</div>
                        <div class="info-value">
                            <?php if ($patient['gender']): ?>
                                <span class="badge <?php echo $patient['gender'] == 'male' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo ucfirst($patient['gender']); ?>
                                </span>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Weight</div>
                        <div class="info-value">
                            <?php echo $patient['weight'] ? $patient['weight'] . ' kg' : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo $patient['phone'] ?: 'N/A'; ?></div>
                    </div>
                </div>
                <div>
                    <div class="info-group">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo $patient['address'] ? htmlspecialchars($patient['address']) : 'N/A'; ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Registration Date</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($patient['created_at'])); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Patient ID</div>
                        <div class="info-value-badge">#<?php echo $patient['id']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card checkups">
                <div class="stat-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <div class="stat-number"><?php echo $total_checkups; ?></div>
                <div class="stat-label">Total Checkups</div>
            </div>
            <div class="stat-card prescriptions">
                <div class="stat-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="stat-number"><?php echo $total_prescriptions; ?></div>
                <div class="stat-label">Prescriptions</div>
            </div>
            <div class="stat-card lab-tests">
                <div class="stat-icon">
                    <i class="fas fa-vial"></i>
                </div>
                <div class="stat-number"><?php echo $total_lab_tests; ?></div>
                <div class="stat-label">Lab Tests</div>
            </div>
            <div class="stat-card registered">
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-number">
                    <?php 
                        $days_registered = floor((time() - strtotime($patient['created_at'])) / (60 * 60 * 24));
                        echo $days_registered > 0 ? $days_registered : 1;
                    ?>
                </div>
                <div class="stat-label">Days Registered</div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-btn active" onclick="showTab('checkups')">
                    <i class="fas fa-stethoscope"></i>
                    Medical Checkups
                </button>
                <button class="tab-btn" onclick="showTab('prescriptions')">
                    <i class="fas fa-prescription"></i>
                    Prescriptions
                </button>
                <button class="tab-btn" onclick="showTab('lab-tests')">
                    <i class="fas fa-vial"></i>
                    Laboratory Tests
                </button>
            </div>

            <!-- Checkups Tab -->
            <div id="checkups-tab" class="tab-content active">
                <div class="table-card">
                    <h3>
                        <i class="fas fa-file-medical"></i>
                        Medical Checkup History
                        <small style="font-size: 0.9rem; color: #64748b; font-weight: normal;">
                            (<?php echo $total_checkups; ?> checkups)
                        </small>
                    </h3>

                    <!-- Search Box -->
                    <div class="search-box">
                        <input type="text" id="searchCheckups" class="search-input" placeholder="Search checkups..." onkeyup="searchTable('checkupsTable', this.value)">
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table" id="checkupsTable">
                            <thead>
                                <tr>
                                    <th>Checkup ID</th>
                                    <th>Date</th>
                                    <th>Doctor</th>
                                    <th>Symptoms</th>
                                    <th>Diagnosis</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($checking_forms)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-file-medical"></i>
                                        <div>No medical checkups found</div>
                                        <small style="margin-top: 10px; display: block;">
                                            <button onclick="createNewCheckup()" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Create First Checkup
                                            </button>
                                        </small>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($checking_forms as $checkup): ?>
                                    <tr>
                                        <td><strong>CF-<?php echo $checkup['id']; ?></strong></td>
                                        <td><?php echo date('M j, Y', strtotime($checkup['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($checkup['doctor_name'] ?: 'N/A'); ?></td>
                                        <td>
                                            <?php if ($checkup['symptoms']): ?>
                                                <span title="<?php echo htmlspecialchars($checkup['symptoms']); ?>">
                                                    <?php echo htmlspecialchars(substr($checkup['symptoms'], 0, 50)) . (strlen($checkup['symptoms']) > 50 ? '...' : ''); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">No symptoms recorded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($checkup['diagnosis']): ?>
                                                <span title="<?php echo htmlspecialchars($checkup['diagnosis']); ?>">
                                                    <?php echo htmlspecialchars(substr($checkup['diagnosis'], 0, 50)) . (strlen($checkup['diagnosis']) > 50 ? '...' : ''); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">No diagnosis</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-success"><?php echo ucfirst($checkup['status']); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-view" onclick="viewCheckupDetails(<?php echo $checkup['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn-print" onclick="printCheckup(<?php echo $checkup['id']; ?>)">
                                                    <i class="fas fa-print"></i> Print
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Prescriptions Tab -->
            <div id="prescriptions-tab" class="tab-content">
                <div class="table-card">
                    <h3>
                        <i class="fas fa-prescription"></i>
                        Prescription History
                        <small style="font-size: 0.9rem; color: #64748b; font-weight: normal;">
                            (<?php echo $total_prescriptions; ?> prescriptions)
                        </small>
                    </h3>

                    <!-- Search Box -->
                    <div class="search-box">
                        <input type="text" id="searchPrescriptions" class="search-input" placeholder="Search prescriptions..." onkeyup="searchTable('prescriptionsTable', this.value)">
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table" id="prescriptionsTable">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Duration</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prescriptions)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-prescription"></i>
                                        <div>No prescriptions found</div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($prescription['medicine_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($prescription['dosage'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['frequency'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['duration'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['doctor_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($prescription['created_at'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $prescription['status'] == 'dispensed' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Lab Tests Tab -->
            <div id="lab-tests-tab" class="tab-content">
                <div class="table-card">
                    <h3>
                        <i class="fas fa-vial"></i>
                        Laboratory Test History
                        <small style="font-size: 0.9rem; color: #64748b; font-weight: normal;">
                            (<?php echo $total_lab_tests; ?> tests)
                        </small>
                    </h3>

                    <!-- Search Box -->
                    <div class="search-box">
                        <input type="text" id="searchLabTests" class="search-input" placeholder="Search lab tests..." onkeyup="searchTable('labTestsTable', this.value)">
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table" id="labTestsTable">
                            <thead>
                                <tr>
                                    <th>Test Type</th>
                                    <th>Description</th>
                                    <th>Results</th>
                                    <th>Conducted By</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lab_tests)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fas fa-vial"></i>
                                        <div>No laboratory tests found</div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($lab_tests as $test): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($test['test_type']); ?></strong></td>
                                        <td>
                                            <?php if ($test['test_description']): ?>
                                                <span title="<?php echo htmlspecialchars($test['test_description']); ?>">
                                                    <?php echo htmlspecialchars(substr($test['test_description'], 0, 50)) . (strlen($test['test_description']) > 50 ? '...' : ''); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($test['results']): ?>
                                                <span title="<?php echo htmlspecialchars($test['results']); ?>">
                                                    <?php echo htmlspecialchars(substr($test['results'], 0, 50)) . (strlen($test['results']) > 50 ? '...' : ''); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">Pending results</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($test['conducted_by_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($test['created_at'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $test['status'] == 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo ucfirst($test['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Simple table search function
        function searchTable(tableId, searchText) {
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            const search = searchText.toLowerCase();
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    if (cellText.toLowerCase().indexOf(search) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }

        // Tab navigation function
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Create new checkup
        function createNewCheckup() {
            window.location.href = 'dashboard.php?action=checkup&patient_id=<?php echo $patient_id; ?>';
        }

        // View checkup details
        function viewCheckupDetails(checkupId) {
            // You can implement a modal or redirect to checkup details page
            alert('View checkup details for ID: ' + checkupId + '\nThis feature can be implemented with a modal or separate page.');
        }

        // Print functions
        function printPatientSummary() {
            window.open('print_patient_summary.php?id=<?php echo $patient_id; ?>', '_blank');
        }

        function printCheckup(checkupId) {
            window.open('print_checkup.php?id=' + checkupId, '_blank');
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