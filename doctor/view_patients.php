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

// Handle search and filters
$search = $_GET['search'] ?? '';
$gender = $_GET['gender'] ?? '';
$status = $_GET['status'] ?? '';

// Build query with filters
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM checking_forms WHERE patient_id = p.id) as checkup_count,
          (SELECT COUNT(*) FROM prescriptions pr JOIN checking_forms cf ON pr.checking_form_id = cf.id WHERE cf.patient_id = p.id) as prescription_count,
          (SELECT COUNT(*) FROM laboratory_tests lt JOIN checking_forms cf ON lt.checking_form_id = cf.id WHERE cf.patient_id = p.id) as lab_count
          FROM patients p 
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.card_no LIKE ? OR p.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($gender)) {
    $query .= " AND p.gender = ?";
    $params[] = $gender;
}

$query .= " ORDER BY p.created_at DESC";

// Get all patients
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for statistics
$total_patients = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch(PDO::FETCH_ASSOC)['total'];
$male_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE gender = 'male'")->fetch(PDO::FETCH_ASSOC)['total'];
$female_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE gender = 'female'")->fetch(PDO::FETCH_ASSOC)['total'];
$today_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patients - Almajyd Dispensary</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
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
        
        .stat-card.total { border-left-color: #3b82f6; }
        .stat-card.male { border-left-color: #0ea5e9; }
        .stat-card.female { border-left-color: #ec4899; }
        .stat-card.today { border-left-color: #10b981; }
        
        .stat-icon {
            font-size: 1.8em;
            margin-bottom: 8px;
        }
        
        .stat-card.total .stat-icon { color: #3b82f6; }
        .stat-card.male .stat-icon { color: #0ea5e9; }
        .stat-card.female .stat-icon { color: #ec4899; }
        .stat-card.today .stat-icon { color: #10b981; }
        
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

        /* Filter Card */
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .filter-title {
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.85rem;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

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

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
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
        
        .btn-medical {
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
        
        .btn-medical:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .btn-prescription {
            background: #8b5cf6;
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
        
        .btn-prescription:hover {
            background: #7c3aed;
            transform: translateY(-1px);
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

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 1000px;
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
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .table-card {
                padding: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
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
                    <i class="fas fa-users"></i>
                    View All Patients
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Manage and view patient records and medical history</p>
            </div>
            <div class="page-actions">
                <button onclick="window.location.href='dashboard.php'" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </button>
                <button onclick="exportToExcel()" class="btn btn-success">
                    <i class="fas fa-file-excel"></i>
                    Export to Excel
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card male">
                <div class="stat-icon">
                    <i class="fas fa-male"></i>
                </div>
                <div class="stat-number"><?php echo $male_patients; ?></div>
                <div class="stat-label">Male Patients</div>
            </div>
            <div class="stat-card female">
                <div class="stat-icon">
                    <i class="fas fa-female"></i>
                </div>
                <div class="stat-number"><?php echo $female_patients; ?></div>
                <div class="stat-label">Female Patients</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_patients; ?></div>
                <div class="stat-label">Today's Patients</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Patients
            </h3>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Search Patients</label>
                    <input type="text" name="search" class="form-input" placeholder="Search by name, card number, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All Genders</option>
                        <option value="male" <?php echo $gender == 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo $gender == 'female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Actions</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <button type="button" onclick="clearFilters()" class="btn btn-warning">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Patients Table -->
        <div class="table-card">
            <h3>
                <i class="fas fa-list"></i>
                Patients List
                <small style="font-size: 0.9rem; color: #64748b; font-weight: normal;">
                    (Showing <?php echo count($patients); ?> patients)
                </small>
            </h3>

            <div class="table-responsive">
                <table class="table table-striped table-hover" id="patientsTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Card No</th>
                            <th>Full Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Weight</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Checkups</th>
                            <th>Prescriptions</th>
                            <th>Lab Tests</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 30px; color: #64748b;">
                                <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #d1d5db;"></i>
                                No patients found matching your criteria.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($patient['card_no']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                </td>
                                <td>
                                    <?php if ($patient['age']): ?>
                                        <span class="badge badge-secondary"><?php echo $patient['age']; ?> years</span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['gender']): ?>
                                        <span class="badge <?php echo $patient['gender'] == 'male' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo ucfirst($patient['gender']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['weight']): ?>
                                        <span class="badge badge-secondary"><?php echo $patient['weight']; ?> kg</span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['phone']): ?>
                                        <?php echo htmlspecialchars($patient['phone']); ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['address']): ?>
                                        <span title="<?php echo htmlspecialchars($patient['address']); ?>">
                                            <?php echo htmlspecialchars(substr($patient['address'], 0, 30)) . (strlen($patient['address']) > 30 ? '...' : ''); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo $patient['checkup_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $patient['prescription_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-warning"><?php echo $patient['lab_count']; ?></span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($patient['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewPatient(<?php echo $patient['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn-medical" onclick="viewMedicalHistory(<?php echo $patient['id']; ?>)">
                                            <i class="fas fa-file-medical"></i> Medical
                                        </button>
                                        <button class="btn-prescription" onclick="createCheckup(<?php echo $patient['id']; ?>)">
                                            <i class="fas fa-stethoscope"></i> Checkup
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

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <script>
        // Initialize DataTable with enhanced features
        $(document).ready(function() {
            $('#patientsTable').DataTable({
                responsive: true,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                language: {
                    search: "Search within results:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ patients",
                    infoEmpty: "Showing 0 to 0 of 0 patients",
                    infoFiltered: "(filtered from _MAX_ total patients)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                pageLength: 25,
                order: [[10, 'desc']] // Sort by registration date descending
            });
        });

        // Clear filters function
        function clearFilters() {
            window.location.href = 'view_patients.php';
        }

        // Export to Excel function
        function exportToExcel() {
            // Create a simple CSV export
            let csv = [];
            let headers = [];
            
            // Get table headers
            $('#patientsTable thead th').each(function() {
                if ($(this).text() !== 'Actions') {
                    headers.push($(this).text());
                }
            });
            csv.push(headers.join(','));
            
            // Get table data
            $('#patientsTable tbody tr').each(function() {
                let row = [];
                $(this).find('td').each(function(index) {
                    if (index !== 11) { // Skip actions column
                        let text = $(this).text().trim();
                        // Remove badge text and get clean data
                        text = text.replace(/years|kg|N\/A/g, '').trim();
                        row.push('"' + text + '"');
                    }
                });
                csv.push(row.join(','));
            });
            
            // Download CSV file
            let csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "patients_list_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // View patient details
        function viewPatient(patientId) {
            window.location.href = 'patient_details.php?id=' + patientId;
        }

        // View medical history
        function viewMedicalHistory(patientId) {
            window.location.href = 'medical_history.php?patient_id=' + patientId;
        }

        // Create new checkup for patient
        function createCheckup(patientId) {
            window.location.href = 'dashboard.php?action=checkup&patient_id=' + patientId;
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

        // Auto-refresh page every 5 minutes to get latest data
        setTimeout(() => {
            // location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>