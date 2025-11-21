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

// Get registration statistics
$registration_stats_query = "
    SELECT 
        COUNT(*) as total_patients,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_patients,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_patients,
        AVG(age) as avg_age,
        MIN(created_at) as first_registration,
        MAX(created_at) as last_registration
    FROM patients 
    WHERE DATE(created_at) BETWEEN ? AND ?
";

$stmt = $pdo->prepare($registration_stats_query);
$stmt->execute([$start_date, $end_date]);
$registration_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly registration trends
$monthly_trends_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as registrations,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female
    FROM patients 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";

$monthly_trends = $pdo->query($monthly_trends_query)->fetchAll(PDO::FETCH_ASSOC);
$monthly_trends = array_reverse($monthly_trends); // Reverse to show oldest first

// Get daily registration trends for current month
$daily_trends_query = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as registrations
    FROM patients 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
";

$stmt = $pdo->prepare($daily_trends_query);
$stmt->execute([$start_date, $end_date]);
$daily_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get treatment history statistics
$treatment_stats_query = "
    SELECT 
        COUNT(*) as total_visits,
        COUNT(DISTINCT patient_id) as unique_patients,
        AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as avg_treatment_days
    FROM checking_forms 
    WHERE status = 'completed' 
    AND DATE(created_at) BETWEEN ? AND ?
";

$stmt = $pdo->prepare($treatment_stats_query);
$stmt->execute([$start_date, $end_date]);
$treatment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get common diagnoses
$common_diagnoses_query = "
    SELECT 
        diagnosis,
        COUNT(*) as frequency
    FROM checking_forms 
    WHERE diagnosis IS NOT NULL 
    AND diagnosis != ''
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY diagnosis 
    ORDER BY frequency DESC 
    LIMIT 10
";

$stmt = $pdo->prepare($common_diagnoses_query);
$stmt->execute([$start_date, $end_date]);
$common_diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly summaries
$monthly_summary_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_patients,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_patients,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_patients,
        AVG(age) as avg_age
    FROM patients 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
";

$monthly_summaries = $pdo->query($monthly_summary_query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate growth percentages
$current_month_count = $registration_stats['total_patients'];
$previous_month = date('Y-m', strtotime('-1 month'));
$previous_month_query = "SELECT COUNT(*) as count FROM patients WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
$stmt = $pdo->prepare($previous_month_query);
$stmt->execute([$previous_month]);
$previous_month_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$growth_percentage = $previous_month_count > 0 ? 
    (($current_month_count - $previous_month_count) / $previous_month_count) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Reports - Almajyd Dispensary</title>
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
        
        .stat-card.registrations { border-left-color: #3b82f6; }
        .stat-card.growth { border-left-color: #10b981; }
        .stat-card.gender { border-left-color: #ef4444; }
        .stat-card.age { border-left-color: #f59e0b; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.registrations .stat-icon { color: #3b82f6; }
        .stat-card.growth .stat-icon { color: #10b981; }
        .stat-card.gender .stat-icon { color: #ef4444; }
        .stat-card.age .stat-icon { color: #f59e0b; }
        
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

        .growth-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .growth-positive {
            color: #10b981;
        }

        .growth-negative {
            color: #ef4444;
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
        
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }

        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .table-card, .chart-card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .main { padding: 0 !important; }
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

        <!-- Page Header -->
        <div class="page-header no-print">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-chart-bar"></i>
                    Patient Reports & Analytics
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Comprehensive patient statistics and treatment analysis</p>
            </div>
            <div class="page-actions">
                <a href="dashboard.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <button onclick="window.print()" class="btn btn-success">
                    <i class="fas fa-print"></i>
                    Print Report
                </button>
            </div>
        </div>

        <!-- Clickable Process Steps with ACTIONS -->
        <div class="steps-container">
            <h2 class="steps-title">Reports & Analytics Control Panel</h2>
            
            <div class="steps">
                <div class="step active" onclick="showStep(1)">
                    1
                    <div class="step-label">Overview</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(2)">
                    2
                    <div class="step-label">Registration Stats</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(3)">
                    3
                    <div class="step-label">Treatment History</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(4)">
                    4
                    <div class="step-label">Monthly Reports</div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div class="content-area" id="content">
                <h2 style="color:#10b981; margin-bottom: 15px;">Welcome to Reports & Analytics</h2>
                <p>Click on the numbers above to explore different aspects of patient data analysis.</p>
                
                <div class="action-grid">
                    <div class="action-card" onclick="showStep(2)">
                        <h4><i class="fas fa-user-plus"></i> Registration Statistics</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Patient registration trends</li>
                            <li><i class="fas fa-check"></i> Gender distribution analysis</li>
                            <li><i class="fas fa-check"></i> Growth rate calculations</li>
                        </ul>
                    </div>
                    <div class="action-card" onclick="showStep(3)">
                        <h4><i class="fas fa-stethoscope"></i> Treatment History</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Treatment completion rates</li>
                            <li><i class="fas fa-check"></i> Common diagnoses analysis</li>
                            <li><i class="fas fa-check"></i> Treatment duration statistics</li>
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
                    <a href="patient_reports.php" class="btn btn-warning">
                        <i class="fas fa-sync"></i>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Registration Statistics -->
        <div class="stats-grid">
            <div class="stat-card registrations">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-number"><?php echo $registration_stats['total_patients']; ?></div>
                <div class="stat-label">Total Registrations</div>
                <div class="growth-indicator <?php echo $growth_percentage >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $growth_percentage >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs(round($growth_percentage, 1)); ?>% from previous month
                </div>
            </div>
            <div class="stat-card gender">
                <div class="stat-icon">
                    <i class="fas fa-venus-mars"></i>
                </div>
                <div class="stat-number">
                    <?php echo $registration_stats['male_patients'] . ' / ' . $registration_stats['female_patients']; ?>
                </div>
                <div class="stat-label">Male / Female Ratio</div>
            </div>
            <div class="stat-card age">
                <div class="stat-icon">
                    <i class="fas fa-birthday-cake"></i>
                </div>
                <div class="stat-number"><?php echo round($registration_stats['avg_age'], 1); ?></div>
                <div class="stat-label">Average Age</div>
            </div>
            <div class="stat-card growth">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number"><?php echo $treatment_stats['total_visits'] ?? 0; ?></div>
                <div class="stat-label">Treatment Visits</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Monthly Registration Trends -->
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Registration Trends</h3>
                <div class="chart-container">
                    <canvas id="monthlyTrendsChart"></canvas>
                </div>
            </div>

            <!-- Gender Distribution -->
            <div class="chart-card">
                <h3><i class="fas fa-venus-mars"></i> Gender Distribution</h3>
                <div class="chart-container">
                    <canvas id="genderDistributionChart"></canvas>
                </div>
            </div>

            <!-- Common Diagnoses -->
            <div class="chart-card">
                <h3><i class="fas fa-stethoscope"></i> Common Diagnoses</h3>
                <div class="chart-container">
                    <canvas id="diagnosesChart"></canvas>
                </div>
            </div>

            <!-- Treatment Statistics -->
            <div class="chart-card">
                <h3><i class="fas fa-heartbeat"></i> Treatment Statistics</h3>
                <div class="chart-container">
                    <canvas id="treatmentStatsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Summaries Table -->
        <div class="table-card">
            <h3><i class="fas fa-calendar-alt"></i> Monthly Summaries (Last 12 Months)</h3>
            <div class="table-responsive">
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Patients</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th>Average Age</th>
                            <th>Growth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_summaries as $summary): ?>
                        <tr>
                            <td><strong><?php echo date('M Y', strtotime($summary['month'] . '-01')); ?></strong></td>
                            <td><?php echo $summary['total_patients']; ?></td>
                            <td>
                                <span class="badge badge-primary">
                                    <?php echo $summary['male_patients']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?php echo $summary['female_patients']; ?>
                                </span>
                            </td>
                            <td><?php echo round($summary['avg_age'], 1); ?></td>
                            <td>
                                <?php 
                                // Calculate growth (simplified)
                                $growth = $summary['total_patients'] > 0 ? '📈' : '➡️';
                                echo $growth;
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Treatment History -->
        <div class="table-card">
            <h3><i class="fas fa-history"></i> Treatment History Summary</h3>
            <div class="table-responsive">
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Total Treatment Visits</strong></td>
                            <td><?php echo $treatment_stats['total_visits'] ?? 0; ?></td>
                            <td>Number of completed treatment sessions</td>
                        </tr>
                        <tr>
                            <td><strong>Unique Patients Treated</strong></td>
                            <td><?php echo $treatment_stats['unique_patients'] ?? 0; ?></td>
                            <td>Number of distinct patients receiving treatment</td>
                        </tr>
                        <tr>
                            <td><strong>Average Treatment Duration</strong></td>
                            <td><?php echo round($treatment_stats['avg_treatment_days'] ?? 0, 1); ?> days</td>
                            <td>Average time from diagnosis to completion</td>
                        </tr>
                    </tbody>
                </table>
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
                    <h2 style="color:#10b981; margin-bottom: 15px;"><i class="fas fa-chart-bar"></i> Reports Overview</h2>
                    <p>Access comprehensive analytics and insights about patient data and treatment patterns.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                        </button>
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Full Report
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="showStep(2)">
                            <h4><i class="fas fa-user-plus"></i> Registration Analytics</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Patient registration trends</li>
                                <li><i class="fas fa-check"></i> Demographic analysis</li>
                                <li><i class="fas fa-check"></i> Growth rate monitoring</li>
                            </ul>
                        </div>
                        
                        <div class="action-card" onclick="showStep(3)">
                            <h4><i class="fas fa-stethoscope"></i> Treatment Analysis</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Treatment completion rates</li>
                                <li><i class="fas fa-check"></i> Diagnosis frequency</li>
                                <li><i class="fas fa-check"></i> Treatment duration stats</li>
                            </ul>
                        </div>
                        
                        <div class="action-card" onclick="showStep(4)">
                            <h4><i class="fas fa-calendar-alt"></i> Monthly Reports</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Monthly performance</li>
                                <li><i class="fas fa-check"></i> Comparative analysis</li>
                                <li><i class="fas fa-check"></i> Trend identification</li>
                            </ul>
                        </div>
                    </div>
                `,
                2: `
                    <h2 style="color:#3b82f6; margin-bottom: 15px;"><i class="fas fa-user-plus"></i> Registration Statistics</h2>
                    <p>Analyze patient registration patterns, demographic distribution, and growth trends.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-chart-line"></i> Registration Trends</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Monthly registration patterns</li>
                                <li><i class="fas fa-check"></i> Seasonal variations</li>
                                <li><i class="fas fa-check"></i> Growth rate analysis</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-venus-mars"></i> Demographic Analysis</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Gender distribution</li>
                                <li><i class="fas fa-check"></i> Age group analysis</li>
                                <li><i class="fas fa-check"></i> Geographic patterns</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-calendar-day"></i> Daily Registration Patterns</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Peak registration days</li>
                                <li><i class="fas fa-check"></i> Time-based analysis</li>
                                <li><i class="fas fa-check"></i> Capacity planning</li>
                            </ul>
                        </div>
                    </div>
                `,
                3: `
                    <h2 style="color:#f59e0b; margin-bottom: 15px;"><i class="fas fa-stethoscope"></i> Treatment History Analysis</h2>
                    <p>Examine treatment patterns, common diagnoses, and healthcare service utilization.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-heartbeat"></i> Treatment Completion</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Treatment success rates</li>
                                <li><i class="fas fa-check"></i> Average treatment duration</li>
                                <li><i class="fas fa-check"></i> Follow-up patterns</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-diagnoses"></i> Common Diagnoses</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Most frequent conditions</li>
                                <li><i class="fas fa-check"></i> Seasonal illness patterns</li>
                                <li><i class="fas fa-check"></i> Treatment effectiveness</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-user-md"></i> Healthcare Utilization</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Service frequency</li>
                                <li><i class="fas fa-check"></i> Resource allocation</li>
                                <li><i class="fas fa-check"></i> Patient retention</li>
                            </ul>
                        </div>
                    </div>
                `,
                4: `
                    <h2 style="color:#8b5cf6; margin-bottom: 15px;"><i class="fas fa-calendar-alt"></i> Monthly Reports & Summaries</h2>
                    <p>Comprehensive monthly performance reports and comparative analysis.</p>
                    
                    <div class="action-grid">
                        <div class="action-card">
                            <h4><i class="fas fa-chart-pie"></i> Monthly Performance</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Monthly registration totals</li>
                                <li><i class="fas fa-check"></i> Treatment completion rates</li>
                                <li><i class="fas fa-check"></i> Service utilization trends</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-chart-bar"></i> Comparative Analysis</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Month-over-month growth</li>
                                <li><i class="fas fa-check"></i> Year-over-year comparison</li>
                                <li><i class="fas fa-check"></i> Seasonal performance</li>
                            </ul>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-trending-up"></i> Trend Identification</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Long-term patterns</li>
                                <li><i class="fas fa-check"></i> Predictive analytics</li>
                                <li><i class="fas fa-check"></i> Strategic planning</li>
                            </ul>
                        </div>
                    </div>
                `
            };
            
            content.innerHTML = stepsContent[num] || stepsContent[1];
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
            // Monthly Trends Chart
            const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_trends, 'month')); ?>,
                    datasets: [{
                        label: 'Total Registrations',
                        data: <?php echo json_encode(array_column($monthly_trends, 'registrations')); ?>,
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

            // Gender Distribution Chart
            const genderCtx = document.getElementById('genderDistributionChart').getContext('2d');
            new Chart(genderCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Male', 'Female'],
                    datasets: [{
                        data: [
                            <?php echo $registration_stats['male_patients']; ?>,
                            <?php echo $registration_stats['female_patients']; ?>
                        ],
                        backgroundColor: ['#3b82f6', '#ec4899'],
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

            // Common Diagnoses Chart
            const diagnosesCtx = document.getElementById('diagnosesChart').getContext('2d');
            new Chart(diagnosesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($common_diagnoses, 'diagnosis')); ?>,
                    datasets: [{
                        label: 'Frequency',
                        data: <?php echo json_encode(array_column($common_diagnoses, 'frequency')); ?>,
                        backgroundColor: '#10b981',
                        borderColor: '#059669',
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
                            beginAtZero: true
                        }
                    }
                }
            });

            // Treatment Statistics Chart
            const treatmentCtx = document.getElementById('treatmentStatsChart').getContext('2d');
            new Chart(treatmentCtx, {
                type: 'pie',
                data: {
                    labels: ['Completed Treatments', 'Unique Patients'],
                    datasets: [{
                        data: [
                            <?php echo $treatment_stats['total_visits'] ?? 0; ?>,
                            <?php echo $treatment_stats['unique_patients'] ?? 0; ?>
                        ],
                        backgroundColor: ['#f59e0b', '#ef4444'],
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

        // Auto-print if print parameter is set
        <?php if (isset($_GET['print'])): ?>
        window.onload = function() {
            window.print();
        }
        <?php endif; ?>
    </script>
</body>
</html>