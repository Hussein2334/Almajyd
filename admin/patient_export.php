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
    <title>Patient Data Table - Almajyd Dispensary</title>
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

        .btn-info {
            background: #3b82f6;
            color: white;
        }
        
        .btn-info:hover {
            background: #2563eb;
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

        /* Table Actions */
        .table-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-info {
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Data Table Styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        .data-table th {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border: none;
            position: sticky;
            top: 0;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            background: white;
        }
        
        .data-table tr:hover {
            background: #f8fafc;
        }
        
        .data-table tr:nth-child(even) {
            background: #fafdfb;
        }
        
        .data-table tr:nth-child(even):hover {
            background: #f1faf7;
        }

        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-primary { background: #e0e7ff; color: #3730a3; }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .table-responsive table {
            min-width: 1000px;
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
            color: #cbd5e1;
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
            
            .table-actions {
                flex-direction: column;
                align-items: flex-start;
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
                    <i class="fas fa-table"></i>
                    Patient Data Table
                </h1>
                <p style="color: #64748b; margin-top: 5px;">View and export complete patient records</p>
            </div>
            <div class="page-actions">
                <a href="view_patients.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i>
                    Back to Patients
                </a>
            </div>
        </div>

        <!-- Export Options -->
        <div class="export-options">
            <h2 class="export-title">Export Patient Data</h2>
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
                Total patients: <strong><?php echo $total_patients; ?></strong> | 
                Filtered results will be exported
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

        <!-- Patients Data Table -->
        <div class="table-card">
            <div class="table-actions">
                <h3 style="margin: 0;">
                    <i class="fas fa-users"></i>
                    Patient Records
                </h3>
                <div class="table-info">
                    Showing: <strong><?php echo $total_patients; ?></strong> patients
                    <?php if ($search || $gender_filter || $date_from || $date_to): ?>
                        <span class="badge badge-primary" style="margin-left: 10px;">
                            <i class="fas fa-filter"></i> Filtered
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
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
                        <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>No patients found</h4>
                                    <p>No patient records match your current filters.</p>
                                </div>
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
                                        <span class="badge badge-info"><?php echo $patient['age']; ?> years</span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['gender']): ?>
                                        <span class="badge <?php echo $patient['gender'] == 'male' ? 'badge-primary' : 'badge-warning'; ?>">
                                            <i class="fas fa-<?php echo $patient['gender'] == 'male' ? 'male' : 'female'; ?>"></i>
                                            <?php echo ucfirst($patient['gender']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['weight']): ?>
                                        <span class="badge badge-success"><?php echo $patient['weight']; ?> kg</span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($patient['phone']); ?>" style="color: #10b981; text-decoration: none;">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($patient['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['address']): ?>
                                        <span title="<?php echo htmlspecialchars($patient['address']); ?>">
                                            <?php 
                                            $address = $patient['address'];
                                            echo htmlspecialchars(strlen($address) > 30 ? substr($address, 0, 30) . '...' : $address); 
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['created_by_name']): ?>
                                        <span class="badge badge-info"><?php echo $patient['created_by_name']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color: #64748b;">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y', strtotime($patient['created_at'])); ?>
                                    </small>
                                </td>
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

        // Add some interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Add click effect to table rows
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                    setTimeout(() => {
                        this.style.transform = '';
                        this.style.boxShadow = '';
                    }, 200);
                });
            });
        });
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