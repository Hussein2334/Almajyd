<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Build query with filters
$query = "SELECT 
            r.*, 
            p.full_name as patient_name,
            p.card_no,
            p.age,
            p.gender,
            p.phone,
            pt.amount as payment_amount,
            pt.payment_method,
            u.full_name as cashier_name,
            doc.full_name as doctor_name,
            cf.diagnosis,
            (SELECT GROUP_CONCAT(CONCAT(pr.medicine_name, ' (', pr.dosage, ')') SEPARATOR ', ') 
             FROM prescriptions pr 
             WHERE pr.checking_form_id = pt.checking_form_id AND pr.status = 'dispensed') as medications
          FROM receipts r
          JOIN payments pt ON r.payment_id = pt.id
          JOIN patients p ON pt.patient_id = p.id
          LEFT JOIN users u ON r.issued_by = u.id
          LEFT JOIN checking_forms cf ON pt.checking_form_id = cf.id
          LEFT JOIN users doc ON cf.doctor_id = doc.id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (r.receipt_number LIKE ? OR p.full_name LIKE ? OR p.card_no LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from)) {
    $query .= " AND DATE(r.issued_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(r.issued_at) <= ?";
    $params[] = $date_to;
}

if (!empty($payment_method)) {
    $query .= " AND pt.payment_method = ?";
    $params[] = $payment_method;
}

$query .= " ORDER BY r.issued_at DESC";

// Execute main query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count and statistics
$total_receipts = count($receipts);
$total_amount = array_sum(array_column($receipts, 'total_amount'));
$today_receipts = $pdo->query("SELECT COUNT(*) as total FROM receipts WHERE DATE(issued_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];
$today_amount = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM receipts WHERE DATE(issued_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipts - Almajyd Dispensary</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
        
        .stat-card.receipts { border-left-color: #ef4444; }
        .stat-card.amount { border-left-color: #10b981; }
        .stat-card.today { border-left-color: #3b82f6; }
        .stat-card.today-amount { border-left-color: #f59e0b; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.receipts .stat-icon { color: #ef4444; }
        .stat-card.amount .stat-icon { color: #10b981; }
        .stat-card.today .stat-icon { color: #3b82f6; }
        .stat-card.today-amount .stat-icon { color: #f59e0b; }
        
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
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

        /* DataTables Styling */
        .dataTables_wrapper {
            margin-top: 15px;
        }
        
        .dataTables_length,
        .dataTables_filter {
            margin-bottom: 15px;
        }
        
        .dataTables_length select,
        .dataTables_filter input {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .dataTables_info {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            margin: 0 2px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #475569;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        /* Table Styling */
        table.dataTable {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        
        table.dataTable thead th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.85rem;
        }
        
        table.dataTable tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        
        table.dataTable tbody tr:hover {
            background: #f8fafc;
        }

        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }

        /* Action buttons in table */
        .table-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Print All Receipts Styles */
        .print-all-container {
            display: none;
        }
        
        .print-receipt {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto 20px auto;
            background: white;
            padding: 15px;
            border: 1px solid #000;
            font-size: 12px;
            line-height: 1.3;
            page-break-after: always;
            font-family: 'Courier New', monospace;
        }
        
        .print-receipt:last-child {
            page-break-after: auto;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        
        .receipt-header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        
        .receipt-header p {
            font-size: 10px;
            margin: 2px 0;
        }
        
        .receipt-logo {
            text-align: center;
            margin-bottom: 5px;
        }
        
        .receipt-logo img {
            height: 50px;
            width: auto;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #ccc;
        }
        
        .receipt-section {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #000;
        }
        
        .receipt-section-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
            background: #f0f0f0;
            padding: 3px;
        }
        
        .receipt-total {
            border-top: 2px solid #000;
            padding-top: 8px;
            margin-top: 8px;
            font-weight: bold;
            font-size: 13px;
        }
        
        .barcode {
            text-align: center;
            margin: 10px 0;
            padding: 5px;
        }
        
        .thank-you {
            text-align: center;
            font-style: italic;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #000;
        }
        
        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 8px;
            color: #666;
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-all-container,
            .print-all-container * {
                visibility: visible;
            }
            
            .print-all-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
            }
            
            .print-receipt {
                margin: 0 auto;
                box-shadow: none;
                border: 1px solid #000;
            }
            
            .no-print {
                display: none !important;
            }
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
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
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/logo.jpg">
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

    <div class="main no-print">

        <!-- Quick Statistics -->
        <div class="stats-grid">
            <div class="stat-card receipts">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-number"><?php echo $total_receipts; ?></div>
                <div class="stat-label">Total Receipts</div>
            </div>
            <div class="stat-card amount">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($total_amount, 2); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_receipts; ?></div>
                <div class="stat-label">Today's Receipts</div>
            </div>
            <div class="stat-card today-amount">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($today_amount, 2); ?></div>
                <div class="stat-label">Today's Amount</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <button type="button" onclick="printAllReceipts()" class="btn btn-warning">
                <i class="fas fa-print"></i>
                Print All Receipts
            </button>
            <button type="button" onclick="exportToExcel()" class="btn btn-success">
                <i class="fas fa-file-excel"></i>
                Export to Excel
            </button>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search Receipts</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by receipt no, patient name, card no..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Payment Method</label>
                        <select name="payment_method" class="filter-input">
                            <option value="">All Methods</option>
                            <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="card" <?php echo $payment_method == 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="mobile" <?php echo $payment_method == 'mobile' ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="insurance" <?php echo $payment_method == 'insurance' ? 'selected' : ''; ?>>Insurance</option>
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
                    <a href="print_receipts.php" class="btn btn-primary">
                        <i class="fas fa-times"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Receipts Table with DataTables -->
        <div class="table-card">
            <h3>
                <i class="fas fa-receipt"></i>
                All Receipts
                <small style="font-size: 0.9rem; color: #64748b; font-weight: normal;">
                    (Total: <?php echo $total_receipts; ?> receipts)
                </small>
            </h3>

            <div class="table-responsive">
                <table id="receiptsTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Patient Name</th>
                            <th>Card No</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Cashier</th>
                            <th>Date Issued</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $receipts_data = $receipts;
                        if (!empty($receipts_data)): 
                            foreach ($receipts_data as $receipt): 
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($receipt['receipt_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($receipt['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($receipt['card_no']); ?></td>
                            <td><strong>TSh <?php echo number_format($receipt['total_amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge <?php 
                                    echo $receipt['payment_method'] == 'cash' ? 'badge-success' : 
                                         ($receipt['payment_method'] == 'card' ? 'badge-info' : 'badge-warning'); 
                                ?>">
                                    <?php echo ucfirst($receipt['payment_method']); ?>
                                </span>
                            </td>
                            <td><?php echo $receipt['cashier_name'] ?: 'N/A'; ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($receipt['issued_at'])); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn btn-warning btn-sm" onclick="printSingleReceipt(<?php echo $receipt['payment_id']; ?>)">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Print All Receipts Container -->
    <div class="print-all-container" id="printAllContainer">
        <?php foreach ($receipts_data as $receipt): ?>
        <div class="print-receipt">
            <!-- Clinic Header with Logo -->
            <div class="receipt-logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="receipt-header">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>Quality Healthcare Services</p>
                <p>Tel: +255 777 567 478 | Email: amrykassim@gmail.com</p>
                <p>Tomondo - Zanzibar</p>
                <p><strong>OFFICIAL RECEIPT</strong></p>
            </div>

            <!-- Receipt Details -->
            <div class="receipt-item">
                <span>Receipt No:</span>
                <span><strong><?php echo htmlspecialchars($receipt['receipt_number']); ?></strong></span>
            </div>
            <div class="receipt-item">
                <span>Date & Time:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($receipt['issued_at'])); ?></span>
            </div>

            <!-- Patient Information -->
            <div class="receipt-section">
                <div class="receipt-section-title">PATIENT INFORMATION</div>
                <div class="receipt-item">
                    <span>Name:</span>
                    <span><?php echo htmlspecialchars($receipt['patient_name']); ?></span>
                </div>
                <div class="receipt-item">
                    <span>Card No:</span>
                    <span><?php echo htmlspecialchars($receipt['card_no']); ?></span>
                </div>
                <div class="receipt-item">
                    <span>Age/Gender:</span>
                    <span><?php echo $receipt['age'] . ' yrs / ' . ucfirst($receipt['gender']); ?></span>
                </div>
                <div class="receipt-item">
                    <span>Phone:</span>
                    <span><?php echo $receipt['phone'] ?: 'N/A'; ?></span>
                </div>
                <div class="receipt-item">
                    <span>Doctor:</span>
                    <span>Dr. <?php echo htmlspecialchars($receipt['doctor_name']); ?></span>
                </div>
            </div>

            <!-- Medical Information -->
            <?php if (!empty($receipt['diagnosis'])): ?>
            <div class="receipt-section">
                <div class="receipt-section-title">MEDICAL INFORMATION</div>
                <div class="receipt-item" style="flex-direction: column; align-items: flex-start;">
                    <div><strong>Diagnosis:</strong></div>
                    <div style="margin-top: 3px;"><?php echo htmlspecialchars($receipt['diagnosis']); ?></div>
                </div>
                
                <?php if (!empty($receipt['medications'])): ?>
                <div class="receipt-item" style="flex-direction: column; align-items: flex-start; margin-top: 5px;">
                    <div><strong>Medications:</strong></div>
                    <div style="margin-top: 3px; font-size: 11px;"><?php echo htmlspecialchars($receipt['medications']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Payment Breakdown -->
            <div class="receipt-section">
                <div class="receipt-section-title">PAYMENT BREAKDOWN</div>
                
                <div class="receipt-item">
                    <span>Total Amount:</span>
                    <span>TSh <?php echo number_format($receipt['total_amount'], 2); ?></span>
                </div>
                
                <div class="receipt-item">
                    <span>Amount Paid:</span>
                    <span>TSh <?php echo number_format($receipt['amount_paid'], 2); ?></span>
                </div>
                
                <?php if ($receipt['change_amount'] > 0): ?>
                <div class="receipt-item">
                    <span>Change Amount:</span>
                    <span>TSh <?php echo number_format($receipt['change_amount'], 2); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="receipt-item">
                    <span>Payment Method:</span>
                    <span style="text-transform: uppercase;"><?php echo $receipt['payment_method']; ?></span>
                </div>
            </div>

            <!-- Barcode Area -->
            <div class="barcode">
                <div style="border: 1px solid #000; padding: 5px; display: inline-block;">
                    [BARCODE AREA]
                </div>
                <div style="font-size: 10px; margin-top: 3px;">
                    <?php echo $receipt['receipt_number']; ?>
                </div>
            </div>

            <!-- Thank You Message -->
            <div class="thank-you">
                Thank you for choosing Almajyd Dispensary!
            </div>

            <!-- Footer -->
            <div class="footer">
                <div>Issued by: <?php echo htmlspecialchars($receipt['cashier_name']); ?></div>
                <div>Date: <?php echo date('d/m/Y H:i', strtotime($receipt['issued_at'])); ?></div>
                <div style="margin-top: 5px;">
                    *** This is a computer generated receipt ***
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#receiptsTable').DataTable({
                "pageLength": 25,
                "responsive": true,
                "dom": 'Bfrtip',
                "buttons": [
                    'copy', 'csv', 'excel'
                ],
                "language": {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ receipts",
                    "infoEmpty": "Showing 0 to 0 of 0 receipts",
                    "paginate": {
                        "previous": "Previous",
                        "next": "Next"
                    }
                },
                "order": [[6, 'desc']] // Sort by date issued descending
            });
        });

        // Function to print single receipt
        function printSingleReceipt(paymentId) {
            window.open('receipt.php?payment_id=' + paymentId, '_blank');
        }

        // Function to print all receipts
        function printAllReceipts() {
            const printContainer = document.getElementById('printAllContainer');
            if (printContainer.children.length === 0) {
                alert('No receipts to print!');
                return;
            }

            // Show the print container
            printContainer.style.display = 'block';
            
            // Print the receipts
            window.print();
            
            // Hide the print container after printing
            setTimeout(() => {
                printContainer.style.display = 'none';
            }, 1000);
        }

        // Function to export to Excel
        function exportToExcel() {
            // Trigger DataTable export to Excel
            $('.buttons-excel').click();
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