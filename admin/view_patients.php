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

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$query = "SELECT p.*, u.full_name as created_by_name 
          FROM patients p 
          LEFT JOIN users u ON p.created_by = u.id 
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.card_no LIKE ? OR p.full_name LIKE ? OR p.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($gender_filter)) {
    $query .= " AND p.gender = ?";
    $params[] = $gender_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY p.created_at DESC";

// Execute main query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$total_patients = count($patients);

// Calculate statistics
$male_count = 0;
$female_count = 0;
$today_count = 0;
$today = date('Y-m-d');

foreach ($patients as $patient) {
    if ($patient['gender'] == 'male') $male_count++;
    if ($patient['gender'] == 'female') $female_count++;
    if (date('Y-m-d', strtotime($patient['created_at'])) == $today) $today_count++;
}

// Function to get patient treatment history
function getPatientTreatmentHistory($pdo, $patient_id) {
    $query = "
        SELECT 
            cf.*,
            u.full_name as doctor_name,
            p.medicine_name,
            p.dosage,
            p.frequency,
            p.duration,
            p.instructions
        FROM checking_forms cf
        LEFT JOIN users u ON cf.doctor_id = u.id
        LEFT JOIN prescriptions p ON cf.id = p.checking_form_id
        WHERE cf.patient_id = ?
        ORDER BY cf.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$patient_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patients - Almajyd Dispensary</title>
     <link rel="icon" href="../images/logo.jpg">
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
        .stat-card.male { border-left-color: #3b82f6; }
        .stat-card.female { border-left-color: #10b981; }
        .stat-card.today { border-left-color: #f59e0b; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.patients .stat-icon { color: #ef4444; }
        .stat-card.male .stat-icon { color: #3b82f6; }
        .stat-card.female .stat-icon { color: #10b981; }
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

        /* Clickable Process Steps */
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

        /* Buttons */
        .btn {
            padding: 10px 20px;
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

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
        }
        
        .filter-input {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
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

        /* Simple Table Styling */
        .simple-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .simple-table th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.85rem;
        }
        
        .simple-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        
        .simple-table tr:hover {
            background: #f8fafc;
        }
        
        .simple-table tr:last-child td {
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

        /* PRINT FORM STYLING - WITH LOGO AND PATIENT HISTORY */
        .print-form-container {
            display: none;
        }
        
        .print-form {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: white;
            font-family: Arial, sans-serif;
            color: black;
            line-height: 1.4;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 8mm;
            border-bottom: 1px solid #000;
            padding-bottom: 4mm;
        }
        
        .print-logo {
            text-align: center;
            margin-bottom: 3mm;
        }
        
        .print-logo img {
            height: 25mm;
            width: auto;
            display: block;
            margin: 0 auto;
        }
        
        .print-header h1 {
            font-size: 24pt;
            font-weight: bold;
            margin-bottom: 3mm;
            color: black;
            text-transform: uppercase;
        }
        
        .print-header .clinic-info {
            font-size: 11pt;
            color: black;
            line-height: 1.6;
        }
        
        .print-divider {
            border-top: 1px solid #000;
            margin: 4mm 0;
        }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6mm;
            margin-bottom: 8mm;
        }
        
        .info-section {
            margin-bottom: 4mm;
        }
        
        .info-label {
            font-weight: bold;
            margin-bottom: 2mm;
            font-size: 11pt;
        }
        
        .info-value {
            font-size: 11pt;
            padding: 2mm 0;
            border-bottom: 1px dashed #666;
            min-height: 7mm;
        }
        
        .treatment-history {
            margin: 6mm 0;
        }
        
        .treatment-history h3 {
            font-size: 12pt;
            margin-bottom: 3mm;
            border-bottom: 1px solid #000;
            padding-bottom: 1mm;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
            border: 1px solid #000;
        }
        
        .history-table th {
            background: #f0f0f0;
            padding: 3mm;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
            font-size: 9pt;
        }
        
        .history-table td {
            padding: 3mm;
            border: 1px solid #000;
            font-size: 9pt;
            vertical-align: top;
        }
        
        .treatment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6mm;
            border: 1px solid #000;
        }
        
        .treatment-table th {
            background: #f0f0f0;
            padding: 3mm;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
            font-size: 10pt;
        }
        
        .treatment-table td {
            padding: 3mm;
            border: 1px solid #000;
            font-size: 10pt;
            height: 20mm;
            vertical-align: top;
        }
        
        .signature-section {
            margin-top: 15mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 60mm;
            text-align: center;
            padding-top: 2mm;
            font-size: 10pt;
        }
        
        .print-footer {
            text-align: center;
            margin-top: 8mm;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 3mm;
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-form-container,
            .print-form-container * {
                visibility: visible;
            }
            
            .print-form {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                min-height: 100%;
                margin: 0;
                padding: 20mm;
                box-shadow: none;
            }
            
            .no-print {
                display: none !important;
            }
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
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                justify-content: center;
            }
            
            .table-card {
                padding: 15px;
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

    <div class="topbar no-print">
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
                <span id="currentTime"><?php echo date('h:i A'); ?></span>
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
        <div class="stats-grid no-print">
            <div class="stat-card patients">
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
                <div class="stat-number"><?php echo $male_count; ?></div>
                <div class="stat-label">Male Patients</div>
            </div>
            <div class="stat-card female">
                <div class="stat-icon">
                    <i class="fas fa-female"></i>
                </div>
                <div class="stat-number"><?php echo $female_count; ?></div>
                <div class="stat-label">Female Patients</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_count; ?></div>
                <div class="stat-label">Today's Patients</div>
            </div>
        </div>

        <!-- Clickable Process Steps -->
        <div class="steps-container no-print">
            <h2 class="steps-title">Patient Management Control Panel</h2>
            
            <div class="steps">
                <div class="step active" onclick="showStep(1)">
                    1
                    <div class="step-label">Overview</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(2)">
                    2
                    <div class="step-label">Search & Filter</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(3)">
                    3
                    <div class="step-label">Reports</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(4)">
                    4
                    <div class="step-label">Export</div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div class="content-area" id="content">
                <h2 style="color:#10b981; margin-bottom: 15px;">Welcome to Patient Management</h2>
                <p>Click on the numbers above to manage different aspects of patient records.</p>
                
                <div class="action-grid">
                    <div class="action-card" onclick="showStep(1)">
                        <h4><i class="fas fa-chart-bar"></i> Patient Overview</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> View all patient records</li>
                            <li><i class="fas fa-check"></i> Monitor registration trends</li>
                            <li><i class="fas fa-check"></i> Track patient demographics</li>
                        </ul>
                    </div>
                    <div class="action-card" onclick="showStep(2)">
                        <h4><i class="fas fa-search"></i> Advanced Search</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Search by name or card number</li>
                            <li><i class="fas fa-check"></i> Filter by gender and date</li>
                            <li><i class="fas fa-check"></i> Quick patient lookup</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search Patients</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by name, card no, phone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Gender</label>
                        <select name="gender" class="filter-input">
                            <option value="">All Genders</option>
                            <option value="male" <?php echo $gender_filter == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $gender_filter == 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                    <a href="view_patients.php" class="btn btn-danger">
                        <i class="fas fa-times"></i>
                        Clear Filters
                    </a>
                    <button type="button" onclick="printAllPatientForms()" class="btn btn-warning">
                        <i class="fas fa-print"></i>
                        Print All Patient Forms
                    </button>
                </div>
            </form>
        </div>

        <!-- Patients Table -->
        <div class="table-card no-print">
            <h3>
                <i class="fas fa-list"></i>
                All Patients
                <small style="font-size: 0.9rem; color: #64748b; font-weight: normal;">
                    (Total: <?php echo $total_patients; ?> patients)
                </small>
            </h3>

            <div class="table-responsive">
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Card No</th>
                            <th>Full Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Weight (kg)</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Registered By</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $patients_data = $patients;
                        if (empty($patients_data)): 
                        ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 20px; color: #64748b;">
                                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                No patients found matching your criteria.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($patients_data as $patient): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($patient['card_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                <td><?php echo $patient['age'] ?: 'N/A'; ?></td>
                                <td>
                                    <span class="badge <?php echo $patient['gender'] == 'male' ? 'badge-info' : 'badge-warning'; ?>">
                                        <?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'N/A'; ?>
                                    </span>
                                </td>
                                <td><?php echo $patient['weight'] ?: 'N/A'; ?></td>
                                <td><?php echo $patient['phone'] ?: 'N/A'; ?></td>
                                <td><?php echo $patient['address'] ? htmlspecialchars(substr($patient['address'], 0, 30)) . (strlen($patient['address']) > 30 ? '...' : '') : 'N/A'; ?></td>
                                <td><?php echo $patient['created_by_name'] ?: 'System'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="printSingleForm('<?php echo $patient['card_no']; ?>')">
                                        <i class="fas fa-print"></i> Print Form
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PRINT FORM SECTION - WITH LOGO AND PATIENT HISTORY -->
        <div class="print-form-container" id="printForms">
            <?php foreach ($patients_data as $patient): 
                $treatment_history = getPatientTreatmentHistory($pdo, $patient['id']);
            ?>
            <div class="print-form" id="form-<?php echo $patient['card_no']; ?>">
                <div class="print-header">
                    <!-- Logo Section -->
                    <div class="print-logo">
                        <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
                    </div>
                    
                    <h1>ALMAJYD DISPENSARY</h1>
                    <div class="clinic-info">
                        TEL: +255 777 567 478 / +255 719 053 764<br>
                        EMAIL: amrykassim@gmail.com<br>
                        TOMONDO - ZANZIBAR
                    </div>
                </div>
                
                <div class="print-divider"></div>
                
                <div class="patient-info-grid">
                    <div class="info-section">
                        <div class="info-label">Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">Date:</div>
                        <div class="info-value"><?php echo date('d/m/Y'); ?></div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">Address:</div>
                        <div class="info-value"><?php echo $patient['address'] ? htmlspecialchars($patient['address']) : ''; ?></div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">Age:</div>
                        <div class="info-value"><?php echo $patient['age'] ?: ''; ?></div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">Card No:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['card_no']); ?></div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">Weight:</div>
                        <div class="info-value"><?php echo $patient['weight'] ? $patient['weight'] . ' kg' : ''; ?></div>
                    </div>
                </div>
                
                <!-- Treatment History Section -->
                <?php if (!empty($treatment_history)): ?>
                <div class="treatment-history">
                    <h3>PATIENT TREATMENT HISTORY</h3>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Symptoms</th>
                                <th>Diagnosis</th>
                                <th>Medicine</th>
                                <th>Dosage</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treatment_history as $history): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($history['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($history['doctor_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($history['symptoms'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($history['diagnosis'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($history['medicine_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($history['dosage'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($history['duration'] ?: 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="print-divider"></div>
                
                <h3 style="margin: 6mm 0 3mm 0; font-size: 12pt;">CURRENT TREATMENT</h3>
                <table class="treatment-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Diagnosis</th>
                            <th>Treatment</th>
                            <th>Medication</th>
                            <th>Doctor's Signature</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Signature Section -->
                <div class="signature-section">
                    <div class="signature-line">
                        Prepared By:<br>
                        <strong><?php echo $_SESSION['full_name']; ?></strong><br>
                        Administrator
                    </div>
                    <div class="signature-line">
                        Date: <?php echo date('F j, Y'); ?><br>
                        <strong>Doctor's Signature</strong><br>
                        &nbsp;
                    </div>
                </div>
                
                <div class="print-footer">
                    Patient Medical Record - ALMAJYD DISPENSARY - Generated on: <?php echo date('F j, Y'); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script>
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
                    <h2 style="color:#10b981; margin-bottom: 15px;"><i class="fas fa-chart-bar"></i> Patient Overview</h2>
                    <p>Get a comprehensive view of all patient records and statistics.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                        </button>
                        <button class="btn btn-success" onclick="printAllPatientForms()">
                            <i class="fas fa-print"></i> Print All Patient Forms
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='view_patients.php'">
                            <h4><i class="fas fa-list"></i> View All Patients</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Complete patient database</li>
                                <li><i class="fas fa-check"></i> Real-time statistics</li>
                                <li><i class="fas fa-check"></i> Demographic analysis</li>
                            </ul>
                        </div>
                        
                        <div class="action-card" onclick="showStep(2)">
                            <h4><i class="fas fa-search"></i> Advanced Search</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Filter by multiple criteria</li>
                                <li><i class="fas fa-check"></i> Quick patient lookup</li>
                                <li><i class="fas fa-check"></i> Date range filtering</li>
                            </ul>
                        </div>
                        
                        <div class="action-card" onclick="showStep(3)">
                            <h4><i class="fas fa-chart-pie"></i> Analytics</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Gender distribution</li>
                                <li><i class="fas fa-check"></i> Registration trends</li>
                                <li><i class="fas fa-check"></i> Monthly reports</li>
                            </ul>
                        </div>
                    </div>
                `,
                2: `
                    <h2 style="color:#3b82f6; margin-bottom: 15px;"><i class="fas fa-search"></i> Search & Filter Patients</h2>
                    <p>Use advanced search and filtering options to find specific patients.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-user-circle"></i> Search by Name</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Full name search</li>
                                <li><i class="fas fa-check"></i> Partial name matching</li>
                                <li><i class="fas fa-check"></i> Case-insensitive</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-id-card"></i> Search by Card Number</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Exact card number</li>
                                <li><i class="fas fa-check"></i> Quick patient identification</li>
                                <li><i class="fas fa-check"></i> Unique identifier search</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-filter"></i> Advanced Filtering</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Gender-based filter</li>
                                <li><i class="fas fa-check"></i> Date range selection</li>
                                <li><i class="fas fa-check"></i> Combined criteria</li>
                            </ul>
                        </div>
                    </div>
                `,
                3: `
                    <h2 style="color:#f59e0b; margin-bottom: 15px;"><i class="fas fa-chart-pie"></i> Reports & Analytics</h2>
                    <p>Generate comprehensive reports and analyze patient data.</p>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="printAllPatientForms()">
                            <h4><i class="fas fa-print"></i> Print Patient Forms</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Individual patient forms</li>
                                <li><i class="fas fa-check"></i> Medical record format</li>
                                <li><i class="fas fa-check"></i> Professional design</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-warning" onclick="printAllPatientForms()">
                                    <i class="fas fa-print"></i> Print All Forms
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-chart-line"></i> Statistical Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Gender distribution</li>
                                <li><i class="fas fa-check"></i> Monthly registrations</li>
                                <li><i class="fas fa-check"></i> Age group analysis</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-calendar-alt"></i> Time-based Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Daily registrations</li>
                                <li><i class="fas fa-check"></i> Weekly summaries</li>
                                <li><i class="fas fa-check"></i> Monthly trends</li>
                            </ul>
                        </div>
                    </div>
                `,
                4: `
                    <h2 style="color:#8b5cf6; margin-bottom: 15px;"><i class="fas fa-download"></i> Data Export</h2>
                    <p>Export patient data in various formats for external use.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-file-excel"></i> Excel Export</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Spreadsheet format</li>
                                <li><i class="fas fa-check"></i> Data analysis ready</li>
                                <li><i class="fas fa-check"></i> Preserves formatting</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-file-pdf"></i> PDF Export</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Professional documents</li>
                                <li><i class="fas fa-check"></i> Print-ready format</li>
                                <li><i class="fas fa-check"></i> Secure sharing</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-copy"></i> Copy Data</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Quick copy to clipboard</li>
                                <li><i class="fas fa-check"></i> Paste into other apps</li>
                                <li><i class="fas fa-check"></i> Instant data transfer</li>
                            </ul>
                        </div>
                    </div>
                `
            };
            
            content.innerHTML = stepsContent[num] || stepsContent[1];
        }

        // Function to print all patient forms
        function printAllPatientForms() {
            const printForms = document.getElementById('printForms');
            printForms.style.display = 'block';
            
            // Hide all forms first, then show them one by one for printing
            const allForms = document.querySelectorAll('.print-form');
            allForms.forEach(form => {
                form.style.display = 'none';
            });
            
            // Print each form on a separate page
            let currentIndex = 0;
            
            function printNextForm() {
                if (currentIndex < allForms.length) {
                    // Hide all forms
                    allForms.forEach(form => {
                        form.style.display = 'none';
                    });
                    
                    // Show current form
                    allForms[currentIndex].style.display = 'block';
                    
                    // Print current form
                    window.print();
                    
                    currentIndex++;
                    
                    // Wait a bit before printing next form
                    if (currentIndex < allForms.length) {
                        setTimeout(printNextForm, 500);
                    } else {
                        // Reset after all forms are printed
                        setTimeout(() => {
                            allForms.forEach(form => {
                                form.style.display = 'block';
                            });
                            printForms.style.display = 'none';
                        }, 500);
                    }
                }
            }
            
            // Start printing
            printNextForm();
        }

        // Function to print single patient form
        function printSingleForm(cardNo) {
            const printForms = document.getElementById('printForms');
            const allForms = document.querySelectorAll('.print-form');
            
            // Hide all forms
            allForms.forEach(form => {
                form.style.display = 'none';
            });
            
            // Show only the selected form
            const singleForm = document.getElementById('form-' + cardNo);
            if (singleForm) {
                singleForm.style.display = 'block';
                printForms.style.display = 'block';
                
                // Print the form
                window.print();
                
                // Reset after printing
                setTimeout(() => {
                    allForms.forEach(form => {
                        form.style.display = 'block';
                    });
                    printForms.style.display = 'none';
                }, 500);
            }
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