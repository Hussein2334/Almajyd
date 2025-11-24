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

// Set default date range (current month)
$current_month = date('Y-m');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get system statistics - USING ONLY EXISTING TABLES
$system_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM patients) as total_patients,
        (SELECT COUNT(*) FROM users WHERE role = 'doctor') as total_doctors,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
        (SELECT COUNT(*) FROM checking_forms) as total_consultations,
        (SELECT COUNT(*) FROM prescriptions) as total_prescriptions,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()) as today_patients,
        (SELECT COUNT(*) FROM checking_forms WHERE DATE(created_at) = CURDATE()) as today_consultations,
        (SELECT COUNT(*) FROM prescriptions WHERE DATE(created_at) = CURDATE()) as today_prescriptions
";

$system_stats = $pdo->query($system_stats_query)->fetch(PDO::FETCH_ASSOC);

// Get recent user activity from existing tables
$recent_activity_query = "
    (SELECT 
        u.full_name,
        u.role,
        'Patient Registration' as activity_type,
        CONCAT('Registered patient: ', p.full_name) as description,
        p.created_at
    FROM patients p
    LEFT JOIN users u ON p.created_by = u.id
    ORDER BY p.created_at DESC
    LIMIT 10)
    
    UNION ALL
    
    (SELECT 
        u.full_name,
        u.role,
        'Consultation' as activity_type,
        CONCAT('Consultation for patient ID: ', cf.patient_id) as description,
        cf.created_at
    FROM checking_forms cf
    LEFT JOIN users u ON cf.doctor_id = u.id
    ORDER BY cf.created_at DESC
    LIMIT 10)
    
    UNION ALL
    
    (SELECT 
        u.full_name,
        u.role,
        'Prescription' as activity_type,
        CONCAT('Prescribed: ', p.medicine_name) as description,
        p.created_at
    FROM prescriptions p
    LEFT JOIN checking_forms cf ON p.checking_form_id = cf.id
    LEFT JOIN users u ON cf.doctor_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 10)
    
    ORDER BY created_at DESC
    LIMIT 30
";

$recent_activity = $pdo->query($recent_activity_query)->fetchAll(PDO::FETCH_ASSOC);

// Get system usage statistics
$usage_stats_query = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as daily_registrations
    FROM patients 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
";

$stmt = $pdo->prepare($usage_stats_query);
$stmt->execute([$start_date, $end_date]);
$usage_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get database size information
$db_size_query = "
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
        table_rows
    FROM information_schema.TABLES 
    WHERE table_schema = ?
    ORDER BY (data_length + index_length) DESC
";

$db_name = 'hospital_management'; // Replace with your database name
$stmt = $pdo->prepare($db_size_query);
$stmt->execute([$db_name]);
$db_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get peak usage hours from existing data
$peak_hours_query = "
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as activity_count
    FROM patients 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY HOUR(created_at)
    ORDER BY activity_count DESC
    LIMIT 10
";

$stmt = $pdo->prepare($peak_hours_query);
$stmt->execute([$start_date, $end_date]);
$peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate system health metrics
$total_db_size = array_sum(array_column($db_tables, 'size_mb'));
$total_records = array_sum(array_column($db_tables, 'table_rows'));

// Get table growth statistics
$growth_stats_query = "
    SELECT 
        'patients' as table_name,
        COUNT(*) as record_count,
        MAX(created_at) as last_update
    FROM patients
    
    UNION ALL
    
    SELECT 
        'checking_forms' as table_name,
        COUNT(*) as record_count,
        MAX(created_at) as last_update
    FROM checking_forms
    
    UNION ALL
    
    SELECT 
        'prescriptions' as table_name,
        COUNT(*) as record_count,
        MAX(created_at) as last_update
    FROM prescriptions
    
    UNION ALL
    
    SELECT 
        'users' as table_name,
        COUNT(*) as record_count,
        MAX(created_at) as last_update
    FROM users
";

$growth_stats = $pdo->query($growth_stats_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Almajyd Dispensary</title>
     <link rel="icon" href="../images/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* PRINT FORM STYLING - WITH LOGO AND SIGNATURE */
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
        
        .report-period {
            text-align: center;
            margin: 4mm 0;
            font-size: 12pt;
            font-weight: bold;
        }
        
        .stats-grid-print {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6mm;
            margin: 6mm 0;
        }
        
        .stat-item-print {
            margin-bottom: 4mm;
        }
        
        .stat-label-print {
            font-weight: bold;
            margin-bottom: 2mm;
            font-size: 11pt;
            border-bottom: 1px solid #666;
            padding-bottom: 1mm;
        }
        
        .stat-value-print {
            font-size: 11pt;
            padding: 2mm 0;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6mm 0;
            border: 1px solid #000;
        }
        
        .summary-table th {
            background: #f0f0f0;
            padding: 3mm;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
            font-size: 10pt;
        }
        
        .summary-table td {
            padding: 3mm;
            border: 1px solid #000;
            font-size: 10pt;
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

        /* Regular Styles (Hidden in Print) */
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

        /* Date Filter */
        .date-filter {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
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

        /* Stats Grid */
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
        .stat-card.doctors { border-left-color: #10b981; }
        .stat-card.consultations { border-left-color: #f59e0b; }
        .stat-card.prescriptions { border-left-color: #ef4444; }
        .stat-card.users { border-left-color: #8b5cf6; }
        .stat-card.database { border-left-color: #06b6d4; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.patients .stat-icon { color: #3b82f6; }
        .stat-card.doctors .stat-icon { color: #10b981; }
        .stat-card.consultations .stat-icon { color: #f59e0b; }
        .stat-card.prescriptions .stat-icon { color: #ef4444; }
        .stat-card.users .stat-icon { color: #8b5cf6; }
        .stat-card.database .stat-icon { color: #06b6d4; }
        
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

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .chart-card h3 {
            margin-bottom: 20px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Tables */
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
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

        /* System Health Indicators */
        .health-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .health-excellent { background: #d1fae5; color: #065f46; }
        .health-good { background: #fef3c7; color: #92400e; }
        .health-warning { background: #fed7aa; color: #9a3412; }
        .health-critical { background: #fecaca; color: #991b1b; }

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
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                padding: 15px;
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
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
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
                    <small style="font-size: 0.75rem;">System Administrator</small>
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
        <div class="page-header no-print">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-server"></i>
                    System Reports & Analytics
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Comprehensive system performance and usage analytics</p>
            </div>
            <div class="page-actions">
                <a href="dashboard.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <button onclick="printReport()" class="btn btn-success">
                    <i class="fas fa-print"></i>
                    Print Report
                </button>
            </div>
        </div>

        <!-- Clickable Process Steps with ACTIONS -->
        <div class="steps-container no-print">
            <h2 class="steps-title">System Analytics Control Panel</h2>
            
            <div class="steps">
                <div class="step active" onclick="showStep(1)">
                    1
                    <div class="step-label">Overview</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(2)">
                    2
                    <div class="step-label">System Health</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(3)">
                    3
                    <div class="step-label">Activity Logs</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(4)">
                    4
                    <div class="step-label">Database Info</div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div class="content-area" id="content">
                <h2 style="color:#10b981; margin-bottom: 15px;">Welcome to System Analytics</h2>
                <p>Click on the numbers above to explore different aspects of system performance and usage.</p>
                
                <div class="action-grid">
                    <div class="action-card" onclick="showStep(2)">
                        <h4><i class="fas fa-heartbeat"></i> System Health</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Database performance metrics</li>
                            <li><i class="fas fa-check"></i> Resource utilization</li>
                            <li><i class="fas fa-check"></i> Error rate monitoring</li>
                        </ul>
                    </div>
                    <div class="action-card" onclick="showStep(3)">
                        <h4><i class="fas fa-list-alt"></i> Activity Monitoring</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> User activity tracking</li>
                            <li><i class="fas fa-check"></i> Security event logs</li>
                            <li><i class="fas fa-check"></i> Peak usage analysis</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="date-filter no-print">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label">Start Date</label>
                    <input type="date" name="start_date" class="filter-input" value="<?php echo $start_date; ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">End Date</label>
                    <input type="date" name="end_date" class="filter-input" value="<?php echo $end_date; ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Apply Filter
                    </button>
                </div>
                <div class="filter-group">
                    <a href="system_reports.php" class="btn btn-warning">
                        <i class="fas fa-sync"></i>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Regular Content (Hidden in Print) -->
        <div class="no-print">
            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card patients">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['total_patients']; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-card doctors">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['total_doctors']; ?></div>
                    <div class="stat-label">Doctors</div>
                </div>
                <div class="stat-card consultations">
                    <div class="stat-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['total_consultations']; ?></div>
                    <div class="stat-label">Consultations</div>
                </div>
                <div class="stat-card prescriptions">
                    <div class="stat-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['total_prescriptions']; ?></div>
                    <div class="stat-label">Prescriptions</div>
                </div>
                <div class="stat-card users">
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['total_users']; ?></div>
                    <div class="stat-label">System Users</div>
                </div>
                <div class="stat-card database">
                    <div class="stat-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-number"><?php echo round($total_db_size, 1); ?> MB</div>
                    <div class="stat-label">Database Size</div>
                </div>
            </div>

            <!-- Today's Activity -->
            <div class="stats-grid">
                <div class="stat-card patients">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['today_patients']; ?></div>
                    <div class="stat-label">Today's Patients</div>
                </div>
                <div class="stat-card consultations">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['today_consultations']; ?></div>
                    <div class="stat-label">Today's Consultations</div>
                </div>
                <div class="stat-card prescriptions">
                    <div class="stat-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="stat-number"><?php echo $system_stats['today_prescriptions']; ?></div>
                    <div class="stat-label">Today's Prescriptions</div>
                </div>
                <div class="stat-card database">
                    <div class="stat-icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_records; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Database Table Sizes -->
                <div class="chart-card">
                    <h3><i class="fas fa-database"></i> Database Table Sizes</h3>
                    <div class="chart-container">
                        <canvas id="databaseSizesChart"></canvas>
                    </div>
                </div>

                <!-- Peak Usage Hours -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-bar"></i> Peak System Usage Hours</h3>
                    <div class="chart-container">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>

                <!-- Daily Activity Trends -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-line"></i> Daily Registration Trends</h3>
                    <div class="chart-container">
                        <canvas id="dailyActivityChart"></canvas>
                    </div>
                </div>

                <!-- Table Growth Statistics -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-pie"></i> Table Record Distribution</h3>
                    <div class="chart-container">
                        <canvas id="tableDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Database Tables Information -->
            <div class="table-card">
                <h3><i class="fas fa-table"></i> Database Tables Information</h3>
                <div class="table-responsive">
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Size (MB)</th>
                                <th>Records</th>
                                <th>Health Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db_tables as $table): 
                                $health_status = '';
                                $health_class = '';
                                
                                if ($table['size_mb'] < 1) {
                                    $health_status = 'Excellent';
                                    $health_class = 'health-excellent';
                                } elseif ($table['size_mb'] < 10) {
                                    $health_status = 'Good';
                                    $health_class = 'health-good';
                                } elseif ($table['size_mb'] < 50) {
                                    $health_status = 'Warning';
                                    $health_class = 'health-warning';
                                } else {
                                    $health_status = 'Critical';
                                    $health_class = 'health-critical';
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo $table['table_name']; ?></strong></td>
                                <td><?php echo $table['size_mb']; ?> MB</td>
                                <td><?php echo number_format($table['table_rows']); ?></td>
                                <td>
                                    <span class="health-indicator <?php echo $health_class; ?>">
                                        <?php echo $health_status; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent System Activity -->
            <div class="table-card">
                <h3><i class="fas fa-list-alt"></i> Recent System Activity</h3>
                <div class="table-responsive">
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Activity Type</th>
                                <th>Description</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_activity)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #64748b;">
                                    No recent activity available.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($activity['full_name'] ?: 'System'); ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo $activity['role'] == 'admin' ? 'badge-danger' : ($activity['role'] == 'doctor' ? 'badge-info' : 'badge-warning'); ?>">
                                            <?php echo ucfirst($activity['role'] ?: 'System'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['activity_type']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table Growth Statistics -->
            <div class="table-card">
                <h3><i class="fas fa-chart-line"></i> Table Growth Statistics</h3>
                <div class="table-responsive">
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Record Count</th>
                                <th>Last Update</th>
                                <th>Growth Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($growth_stats as $growth): 
                                $growth_status = $growth['record_count'] > 1000 ? 'High' : ($growth['record_count'] > 100 ? 'Medium' : 'Low');
                            ?>
                            <tr>
                                <td><strong><?php echo ucfirst($growth['table_name']); ?></strong></td>
                                <td><?php echo number_format($growth['record_count']); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($growth['last_update'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $growth_status == 'High' ? 'badge-danger' : ($growth_status == 'Medium' ? 'badge-warning' : 'badge-info'); ?>">
                                        <?php echo $growth_status; ?> Growth
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Peak Usage Hours -->
            <div class="table-card">
                <h3><i class="fas fa-chart-bar"></i> Peak Registration Hours</h3>
                <div class="table-responsive">
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Hour</th>
                                <th>Registration Count</th>
                                <th>Time Period</th>
                                <th>Usage Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($peak_hours)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #64748b;">
                                    No peak usage data available.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($peak_hours as $hour): 
                                    $time_period = $hour['hour'] < 12 ? 'AM' : 'PM';
                                    $display_hour = $hour['hour'] % 12 ?: 12;
                                    $usage_level = '';
                                    
                                    if ($hour['activity_count'] >= 10) {
                                        $usage_level = 'Very High';
                                    } elseif ($hour['activity_count'] >= 5) {
                                        $usage_level = 'High';
                                    } elseif ($hour['activity_count'] >= 2) {
                                        $usage_level = 'Medium';
                                    } else {
                                        $usage_level = 'Low';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo $display_hour . ':00 ' . $time_period; ?></strong></td>
                                    <td><?php echo $hour['activity_count']; ?> registrations</td>
                                    <td><?php echo $display_hour . ':00 - ' . $display_hour . ':59 ' . $time_period; ?></td>
                                    <td>
                                        <span class="badge <?php echo $usage_level == 'Very High' ? 'badge-danger' : ($usage_level == 'High' ? 'badge-warning' : 'badge-info'); ?>">
                                            <?php echo $usage_level; ?>
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

        <!-- PRINT FORM SECTION - WITH LOGO AND SIGNATURE -->
        <div class="print-form-container" id="printForms">
            <div class="print-form">
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
                
                <div class="report-period">
                    SYSTEM PERFORMANCE REPORT - <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?>
                </div>
                
                <div class="print-divider"></div>
                
                <div class="stats-grid-print">
                    <div class="stat-item-print">
                        <div class="stat-label-print">Total Patients in System</div>
                        <div class="stat-value-print"><?php echo $system_stats['total_patients']; ?> patients</div>
                    </div>
                    <div class="stat-item-print">
                        <div class="stat-label-print">Registered Doctors</div>
                        <div class="stat-value-print"><?php echo $system_stats['total_doctors']; ?> doctors</div>
                    </div>
                    <div class="stat-item-print">
                        <div class="stat-label-print">Total Consultations</div>
                        <div class="stat-value-print"><?php echo $system_stats['total_consultations']; ?> consultations</div>
                    </div>
                    <div class="stat-item-print">
                        <div class="stat-label-print">Total Prescriptions</div>
                        <div class="stat-value-print"><?php echo $system_stats['total_prescriptions']; ?> prescriptions</div>
                    </div>
                    <div class="stat-item-print">
                        <div class="stat-label-print">System Users</div>
                        <div class="stat-value-print"><?php echo $system_stats['total_users']; ?> users</div>
                    </div>
                    <div class="stat-item-print">
                        <div class="stat-label-print">Database Size</div>
                        <div class="stat-value-print"><?php echo round($total_db_size, 2); ?> MB</div>
                    </div>
                </div>
                
                <div class="print-divider"></div>
                
                <h3 style="margin: 6mm 0 3mm 0; font-size: 12pt;">Database Tables Information</h3>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Size (MB)</th>
                            <th>Records</th>
                            <th>Health Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db_tables as $table): 
                            $health_status = $table['size_mb'] < 10 ? 'Good' : ($table['size_mb'] < 50 ? 'Warning' : 'Critical');
                        ?>
                        <tr>
                            <td><?php echo $table['table_name']; ?></td>
                            <td><?php echo $table['size_mb']; ?> MB</td>
                            <td><?php echo number_format($table['table_rows']); ?></td>
                            <td><?php echo $health_status; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="print-divider"></div>
                
                <h3 style="margin: 6mm 0 3mm 0; font-size: 12pt;">System Usage Summary</h3>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Today</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Patient Registrations</td>
                            <td><?php echo $system_stats['today_patients']; ?></td>
                            <td><?php echo $system_stats['total_patients']; ?></td>
                            <td>Active</td>
                        </tr>
                        <tr>
                            <td>Consultations</td>
                            <td><?php echo $system_stats['today_consultations']; ?></td>
                            <td><?php echo $system_stats['total_consultations']; ?></td>
                            <td>Active</td>
                        </tr>
                        <tr>
                            <td>Prescriptions</td>
                            <td><?php echo $system_stats['today_prescriptions']; ?></td>
                            <td><?php echo $system_stats['total_prescriptions']; ?></td>
                            <td>Active</td>
                        </tr>
                        <tr>
                            <td>Database Records</td>
                            <td>N/A</td>
                            <td><?php echo number_format($total_records); ?></td>
                            <td>Stable</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Signature Section -->
                <div class="signature-section">
                    <div class="signature-line">
                        Prepared By:<br>
                        <strong><?php echo $_SESSION['full_name']; ?></strong><br>
                        System Administrator
                    </div>
                    <div class="signature-line">
                        Date: <?php echo date('F j, Y'); ?><br>
                        <strong>Signature</strong><br>
                        &nbsp;
                    </div>
                </div>
                
                <div class="print-footer">
                    System Performance Report - ALMAJYD DISPENSARY - Generated on: <?php echo date('F j, Y'); ?>
                </div>
            </div>
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
                    <h2 style="color:#10b981; margin-bottom: 15px;"><i class="fas fa-server"></i> System Reports Overview</h2>
                    <p>Access comprehensive system analytics and performance monitoring tools.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                        </button>
                        <button class="btn btn-success" onclick="printReport()">
                            <i class="fas fa-print"></i> Print System Report
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="showStep(2)">
                            <h4><i class="fas fa-heartbeat"></i> System Health</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Database performance metrics</li>
                                <li><i class="fas fa-check"></i> Resource utilization</li>
                                <li><i class="fas fa-check"></i> Error rate monitoring</li>
                            </ul>
                        </div>
                        
                        <div class="action-card" onclick="showStep(3)">
                            <h4><i class="fas fa-list-alt"></i> Activity Monitoring</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> User activity tracking</li>
                                <li><i class="fas fa-check"></i> Security event logs</li>
                                <li><i class="fas fa-check"></i> Peak usage analysis</li>
                            </ul>
                        </div>
                        
                        <div class="action-card" onclick="showStep(4)">
                            <h4><i class="fas fa-database"></i> Database Management</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Table size analysis</li>
                                <li><i class="fas fa-check"></i> Storage optimization</li>
                                <li><i class="fas fa-check"></i> Backup status</li>
                            </ul>
                        </div>
                    </div>
                `,
                2: `
                    <h2 style="color:#3b82f6; margin-bottom: 15px;"><i class="fas fa-heartbeat"></i> System Health Monitoring</h2>
                    <p>Monitor system performance, resource utilization, and health metrics.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-database"></i> Database Performance</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Table size monitoring</li>
                                <li><i class="fas fa-check"></i> Query performance</li>
                                <li><i class="fas fa-check"></i> Connection health</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-memory"></i> Resource Utilization</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Memory usage tracking</li>
                                <li><i class="fas fa-check"></i> CPU performance</li>
                                <li><i class="fas fa-check"></i> Storage capacity</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-bug"></i> Error Monitoring</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Error rate analysis</li>
                                <li><i class="fas fa-check"></i> Exception tracking</li>
                                <li><i class="fas fa-check"></i> Debug information</li>
                            </ul>
                        </div>
                    </div>
                `,
                3: `
                    <h2 style="color:#f59e0b; margin-bottom: 15px;"><i class="fas fa-list-alt"></i> Activity & Security Monitoring</h2>
                    <p>Track user activities, security events, and system usage patterns.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-user-clock"></i> User Activity Logs</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Login/logout tracking</li>
                                <li><i class="fas fa-check"></i> Page access monitoring</li>
                                <li><i class="fas fa-check"></i> Action auditing</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-shield-alt"></i> Security Events</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Failed login attempts</li>
                                <li><i class="fas fa-check"></i> Access violations</li>
                                <li><i class="fas fa-check"></i> Security alerts</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-chart-bar"></i> Usage Patterns</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Peak usage hours</li>
                                <li><i class="fas fa-check"></i> Feature utilization</li>
                                <li><i class="fas fa-check"></i> Performance trends</li>
                            </ul>
                        </div>
                    </div>
                `,
                4: `
                    <h2 style="color:#8b5cf6; margin-bottom: 15px;"><i class="fas fa-database"></i> Database Management & Analytics</h2>
                    <p>Manage database performance, storage, and optimization strategies.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-hdd"></i> Storage Analysis</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Table size distribution</li>
                                <li><i class="fas fa-check"></i> Growth trends</li>
                                <li><i class="fas fa-check"></i> Optimization opportunities</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-backup"></i> Backup Management</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Backup schedules</li>
                                <li><i class="fas fa-check"></i> Recovery procedures</li>
                                <li><i class="fas fa-check"></i> Storage management</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-wrench"></i> Maintenance Tools</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Optimization scripts</li>
                                <li><i class="fas fa-check"></i> Cleanup procedures</li>
                                <li><i class="fas fa-check"></i> Performance tuning</li>
                            </ul>
                        </div>
                    </div>
                `
            };
            
            content.innerHTML = stepsContent[num] || stepsContent[1];
        }

        // Function to print report in the desired format
        function printReport() {
            const printForms = document.getElementById('printForms');
            printForms.style.display = 'block';
            
            // Print after a short delay to ensure content is visible
            setTimeout(() => {
                window.print();
                // Hide print forms after printing
                setTimeout(() => {
                    printForms.style.display = 'none';
                }, 500);
            }, 500);
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

        updateTime();
        setInterval(updateTime, 60000);

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Database Sizes Chart
            const dbSizesCtx = document.getElementById('databaseSizesChart').getContext('2d');
            new Chart(dbSizesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($db_tables, 'table_name')); ?>,
                    datasets: [{
                        label: 'Size (MB)',
                        data: <?php echo json_encode(array_column($db_tables, 'size_mb')); ?>,
                        backgroundColor: '#3b82f6',
                        borderColor: '#1d4ed8',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Size (MB)'
                            }
                        }
                    }
                }
            });

            // Peak Hours Chart
            const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
            new Chart(peakHoursCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($hour) { 
                        $display_hour = $hour['hour'] % 12 ?: 12;
                        $period = $hour['hour'] < 12 ? 'AM' : 'PM';
                        return $display_hour + ':00 ' + $period;
                    }, $peak_hours)); ?>,
                    datasets: [{
                        label: 'Registration Count',
                        data: <?php echo json_encode(array_column($peak_hours, 'activity_count')); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });

            // Daily Activity Chart
            const dailyActivityCtx = document.getElementById('dailyActivityChart').getContext('2d');
            new Chart(dailyActivityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($usage_stats, 'date')); ?>,
                    datasets: [{
                        label: 'Daily Registrations',
                        data: <?php echo json_encode(array_column($usage_stats, 'daily_registrations')); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });

            // Table Distribution Chart
            const tableDistributionCtx = document.getElementById('tableDistributionChart').getContext('2d');
            new Chart(tableDistributionCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($growth_stats, 'table_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($growth_stats, 'record_count')); ?>,
                        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>