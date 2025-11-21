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

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
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

    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($export_type === 'excel') {
        exportToExcel($patients);
    } elseif ($export_type === 'pdf') {
        exportToPDF($patients);
    }
    exit;
}

// Function to export to Excel
function exportToExcel($patients) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="patient_data_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<!--[if gte mso 9]>';
    echo '<xml>';
    echo '<x:ExcelWorkbook>';
    echo '<x:ExcelWorksheets>';
    echo '<x:ExcelWorksheet>';
    echo '<x:Name>Patient Data</x:Name>';
    echo '<x:WorksheetOptions>';
    echo '<x:DisplayGridlines/>';
    echo '</x:WorksheetOptions>';
    echo '</x:ExcelWorksheet>';
    echo '</x:ExcelWorksheets>';
    echo '</x:ExcelWorkbook>';
    echo '</xml>';
    echo '<![endif]-->';
    echo '</head>';
    echo '<body>';
    
    echo '<table border="1">';
    echo '<tr><th colspan="9" style="background:#f0f0f0; font-size:16px; padding:10px;">ALMAJYD DISPENSARY - PATIENT DATA</th></tr>';
    echo '<tr><th colspan="9" style="background:#f8f8f8; padding:5px;">Generated on: ' . date('F j, Y h:i A') . '</th></tr>';
    echo '<tr style="background:#e0e0e0;">';
    echo '<th>Card No</th>';
    echo '<th>Full Name</th>';
    echo '<th>Age</th>';
    echo '<th>Gender</th>';
    echo '<th>Weight (kg)</th>';
    echo '<th>Phone</th>';
    echo '<th>Address</th>';
    echo '<th>Registered By</th>';
    echo '<th>Registration Date</th>';
    echo '</tr>';
    
    foreach ($patients as $patient) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($patient['card_no']) . '</td>';
        echo '<td>' . htmlspecialchars($patient['full_name']) . '</td>';
        echo '<td>' . ($patient['age'] ?: 'N/A') . '</td>';
        echo '<td>' . ($patient['gender'] ? ucfirst($patient['gender']) : 'N/A') . '</td>';
        echo '<td>' . ($patient['weight'] ?: 'N/A') . '</td>';
        echo '<td>' . ($patient['phone'] ?: 'N/A') . '</td>';
        echo '<td>' . ($patient['address'] ? htmlspecialchars($patient['address']) : 'N/A') . '</td>';
        echo '<td>' . ($patient['created_by_name'] ?: 'System') . '</td>';
        echo '<td>' . date('M j, Y', strtotime($patient['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '<tr><td colspan="9" style="background:#f0f0f0; padding:5px;">Total Patients: ' . count($patients) . '</td></tr>';
    echo '</table>';
    echo '</body></html>';
    exit;
}

// Function to export to PDF (Simple HTML PDF alternative)
function exportToPDF($patients) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="patient_data_' . date('Y-m-d') . '.pdf"');
    header('Cache-Control: max-age=0');
    
    // Simple HTML to PDF conversion using basic styling
    $html = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .header h1 { margin: 0; font-size: 24px; }
            .header .info { font-size: 12px; margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #f0f0f0; padding: 8px; border: 1px solid #000; text-align: left; font-size: 10px; }
            td { padding: 6px; border: 1px solid #000; font-size: 9px; }
            .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>ALMAJYD DISPENSARY</h1>
            <div class="info">TOMONDO - ZANZIBAR</div>
            <div class="info">TEL: +255 777 567 478 / +255 719 053 764</div>
            <div class="info">PATIENT DATA EXPORT - Generated on: ' . date('F j, Y h:i A') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Card No</th>
                    <th>Full Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Weight</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Registered By</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($patients as $patient) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($patient['card_no']) . '</td>
                    <td>' . htmlspecialchars($patient['full_name']) . '</td>
                    <td>' . ($patient['age'] ?: 'N/A') . '</td>
                    <td>' . ($patient['gender'] ? ucfirst($patient['gender']) : 'N/A') . '</td>
                    <td>' . ($patient['weight'] ?: 'N/A') . '</td>
                    <td>' . ($patient['phone'] ?: 'N/A') . '</td>
                    <td>' . ($patient['address'] ? htmlspecialchars(substr($patient['address'], 0, 30)) : 'N/A') . '</td>
                    <td>' . ($patient['created_by_name'] ?: 'System') . '</td>
                    <td>' . date('M j, Y', strtotime($patient['created_at'])) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            Total Patients: ' . count($patients) . ' | ALMAJYD DISPENSARY - Patient Data Export
        </div>
    </body>
    </html>';
    
    // For a proper PDF, you would use a library like TCPDF, Dompdf, or MPDF
    // This is a simple HTML fallback that can be printed as PDF
    echo $html;
    exit;
}

// Handle search and filter for display
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters for display
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

// Execute main query for display
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$total_patients = count($patients);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Patients - Almajyd Dispensary</title>
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

        /* PERSISTENT NAVIGATION STEPS */
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

        /* Export Options */
        .export-options {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .export-title {
            margin-bottom: 20px;
            color: #1e293b;
            font-size: 1.4rem;
        }
        
        .export-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-excel {
            background: #10b981;
            color: white;
        }
        
        .btn-excel:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-pdf {
            background: #ef4444;
            color: white;
        }
        
        .btn-pdf:hover {
            background: #dc2626;
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
            
            .export-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
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
            .user-area {
                flex-direction: column;
                gap: 10px;
            }
            
            .time-display, .user, .logout-btn {
                width: 100%;
                justify-content: center;
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
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-download"></i>
                    Export Patient Data
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Export patient records to Excel or PDF format</p>
            </div>
            <div class="page-actions">
                <a href="view_patients.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i>
                    Back to Patients
                </a>
            </div>
        </div>

        <!-- PERSISTENT NAVIGATION STEPS -->
        <div class="steps-container">
            <h2 class="steps-title">Data Export Control Panel</h2>
            
            <div class="steps">
                <div class="step" onclick="window.location.href='view_patients.php'">
                    1
                    <div class="step-label">View Patients</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="window.location.href='patient_reports.php'">
                    2
                    <div class="step-label">Reports</div>
                </div>
                <div class="spacer"></div>
                <div class="step active">
                    3
                    <div class="step-label">Export Data</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="window.location.href='dashboard.php'">
                    4
                    <div class="step-label">Dashboard</div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div class="content-area" id="content">
                <h2 style="color:#10b981; margin-bottom: 15px;"><i class="fas fa-download"></i> Data Export Center</h2>
                <p>Export patient data in various formats for external use, reporting, and analysis.</p>
                
                <div class="action-grid">
                    <div class="action-card" onclick="document.querySelector('.btn-excel').click()">
                        <h4><i class="fas fa-file-excel"></i> Excel Export</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Spreadsheet format</li>
                            <li><i class="fas fa-check"></i> Data analysis ready</li>
                            <li><i class="fas fa-check"></i> Preserves formatting</li>
                        </ul>
                        <div class="action-buttons">
                            <a href="?export=excel<?php echo buildExportQueryString($search, $gender_filter, $date_from, $date_to); ?>" class="btn btn-excel">
                                <i class="fas fa-download"></i> Download Excel
                            </a>
                        </div>
                    </div>
                    
                    <div class="action-card" onclick="document.querySelector('.btn-pdf').click()">
                        <h4><i class="fas fa-file-pdf"></i> PDF Export</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Professional documents</li>
                            <li><i class="fas fa-check"></i> Print-ready format</li>
                            <li><i class="fas fa-check"></i> Secure sharing</li>
                        </ul>
                        <div class="action-buttons">
                            <a href="?export=pdf<?php echo buildExportQueryString($search, $gender_filter, $date_from, $date_to); ?>" class="btn btn-pdf">
                                <i class="fas fa-download"></i> Download PDF
                            </a>
                        </div>
                    </div>
                    
                    <div class="action-card">
                        <h4><i class="fas fa-filter"></i> Filter Data</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Apply search filters</li>
                            <li><i class="fas fa-check"></i> Select date ranges</li>
                            <li><i class="fas fa-check"></i> Gender-based filtering</li>
                        </ul>
                        <div class="action-buttons">
                            <button class="btn btn-warning" onclick="document.querySelector('.filter-section').scrollIntoView({behavior: 'smooth'})">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="export-options">
            <h2 class="export-title">Quick Export</h2>
            <div class="export-buttons">
                <a href="?export=excel<?php echo buildExportQueryString($search, $gender_filter, $date_from, $date_to); ?>" class="btn btn-excel">
                    <i class="fas fa-file-excel"></i>
                    Export to Excel
                </a>
                <a href="?export=pdf<?php echo buildExportQueryString($search, $gender_filter, $date_from, $date_to); ?>" class="btn btn-pdf">
                    <i class="fas fa-file-pdf"></i>
                    Export to PDF
                </a>
            </div>
            <p style="margin-top: 15px; color: #64748b; font-size: 0.9rem;">
                Total patients to export: <strong><?php echo $total_patients; ?></strong>
            </p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
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
                    <button type="submit" class="btn btn-excel">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                    <a href="patient_export.php" class="btn btn-warning">
                        <i class="fas fa-times"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Patients Table Preview -->
        <div class="table-card">
            <h3>
                <i class="fas fa-list"></i>
                Patient Data Preview
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $patients_data = $patients;
                        if (empty($patients_data)): 
                        ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px; color: #64748b;">
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
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
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
    </script>
</body>
</html>

<?php
// Helper function to build export query string
function buildExportQueryString($search, $gender_filter, $date_from, $date_to) {
    $params = [];
    
    if (!empty($search)) {
        $params[] = 'search=' . urlencode($search);
    }
    
    if (!empty($gender_filter)) {
        $params[] = 'gender=' . urlencode($gender_filter);
    }
    
    if (!empty($date_from)) {
        $params[] = 'date_from=' . urlencode($date_from);
    }
    
    if (!empty($date_to)) {
        $params[] = 'date_to=' . urlencode($date_to);
    }
    
    return $params ? '&' . implode('&', $params) : '';
}
?>