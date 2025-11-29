<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Cashier role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'cashier') {
    header('Location: ../login.php');
    exit;
}

// Handle receipt generation
if (isset($_POST['generate_receipt'])) {
    $patient_id = $_POST['patient_id'];
    $payment_method = $_POST['payment_method'];
    $amount_paid = $_POST['amount_paid'];
    
    try {
        // Get patient details and costs
        $patient_stmt = $pdo->prepare("
            SELECT 
                p.*,
                cf.id as checking_form_id,
                (p.consultation_fee + 
                 COALESCE((SELECT SUM(medicine_price) FROM prescriptions pr WHERE pr.checking_form_id = cf.id), 0) + 
                 COALESCE((SELECT SUM(lab_price) FROM laboratory_tests lt WHERE lt.checking_form_id = cf.id), 0)
                ) as total_cost
            FROM patients p
            LEFT JOIN checking_forms cf ON p.id = cf.patient_id
            WHERE p.id = ?
        ");
        $patient_stmt->execute([$patient_id]);
        $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            die("Patient not found");
        }
        
        $total_cost = $patient['total_cost'];
        $change_amount = $amount_paid - $total_cost;
        
        // Create payment record
        $payment_stmt = $pdo->prepare("INSERT INTO payments (patient_id, checking_form_id, amount, payment_method, status) VALUES (?, ?, ?, ?, 'completed')");
        $payment_stmt->execute([$patient_id, $patient['checking_form_id'], $total_cost, $payment_method]);
        $payment_id = $pdo->lastInsertId();
        
        // Generate receipt number
        $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad($payment_id, 4, '0', STR_PAD_LEFT);
        
        // Create receipt
        $receipt_stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_number, total_amount, amount_paid, change_amount, issued_by) VALUES (?, ?, ?, ?, ?, ?)");
        $receipt_stmt->execute([$payment_id, $receipt_number, $total_cost, $amount_paid, $change_amount, $_SESSION['user_id']]);
        
        $success_message = "Receipt generated successfully! Receipt No: " . $receipt_number;
        
    } catch (PDOException $e) {
        $error_message = "Error generating receipt: " . $e->getMessage();
    }
}

// Get all patients for receipt generation
$patients_stmt = $pdo->prepare("
    SELECT 
        p.*,
        cf.id as checking_form_id,
        (p.consultation_fee + 
         COALESCE((SELECT SUM(medicine_price) FROM prescriptions pr WHERE pr.checking_form_id = cf.id), 0) + 
         COALESCE((SELECT SUM(lab_price) FROM laboratory_tests lt WHERE lt.checking_form_id = cf.id), 0)
        ) as total_cost,
        p.consultation_fee,
        COALESCE((SELECT SUM(medicine_price) FROM prescriptions pr WHERE pr.checking_form_id = cf.id), 0) as medicine_cost,
        COALESCE((SELECT SUM(lab_price) FROM laboratory_tests lt WHERE lt.checking_form_id = cf.id), 0) as lab_cost
    FROM patients p
    LEFT JOIN checking_forms cf ON p.id = cf.patient_id
    WHERE cf.id IS NOT NULL
    ORDER BY p.created_at DESC
");
$patients_stmt->execute();
$patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all receipts
$receipts_stmt = $pdo->prepare("
    SELECT 
        r.*,
        p.full_name as patient_name,
        p.card_no,
        pt.payment_method,
        u.full_name as cashier_name
    FROM receipts r
    JOIN payments pt ON r.payment_id = pt.id
    JOIN patients p ON pt.patient_id = p.id
    LEFT JOIN users u ON r.issued_by = u.id
    ORDER BY r.issued_at DESC
");
$receipts_stmt->execute();
$receipts = $receipts_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Receipts - Almajyd Dispensary</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Table Card */
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
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

        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-input:disabled {
            background-color: #f8fafc;
            color: #6b7280;
            cursor: not-allowed;
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
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Cost Breakdown */
        .cost-breakdown {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .cost-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: #1e293b;
        }
        
        .cost-label {
            color: #64748b;
        }
        
        .cost-amount {
            font-weight: 600;
            color: #1e293b;
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
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
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
        .badge-primary { background: #dbeafe; color: #1e40af; }

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
            
            .form-row {
                grid-template-columns: 1fr;
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

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>CASHIER DEPARTMENT - TOMONDO ZANZIBAR</p>
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
                    <small style="font-size: 0.75rem;">Cashier Department</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Generate Receipt Form -->
        <div class="table-card">
            <h3><i class="fas fa-receipt"></i> Generate New Receipt</h3>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="receiptForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Select Patient *</label>
                        <select name="patient_id" id="patientSelect" class="form-select" required onchange="updateCostBreakdown()">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        data-total-cost="<?php echo $patient['total_cost']; ?>"
                                        data-consultation="<?php echo $patient['consultation_fee']; ?>"
                                        data-medicine="<?php echo $patient['medicine_cost']; ?>"
                                        data-lab="<?php echo $patient['lab_cost']; ?>">
                                    <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['card_no'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mobile">Mobile Money</option>
                            <option value="insurance">Insurance</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Amount Paid (TZS) *</label>
                        <input type="number" name="amount_paid" id="amountPaid" class="form-input" step="0.01" min="0" required 
                               placeholder="Enter amount paid" oninput="calculateChange()">
                    </div>
                </div>

                <!-- Cost Breakdown (Automatically filled) -->
                <div class="cost-breakdown" id="costBreakdown" style="display: none;">
                    <h4 style="margin-bottom: 15px; color: #374151;">
                        <i class="fas fa-money-bill-wave"></i> Cost Breakdown
                    </h4>
                    <div class="cost-item">
                        <span class="cost-label">Consultation Fee:</span>
                        <span class="cost-amount" id="consultationCost">TZS 0.00</span>
                    </div>
                    <div class="cost-item">
                        <span class="cost-label">Medicine Cost:</span>
                        <span class="cost-amount" id="medicineCost">TZS 0.00</span>
                    </div>
                    <div class="cost-item">
                        <span class="cost-label">Laboratory Cost:</span>
                        <span class="cost-amount" id="labCost">TZS 0.00</span>
                    </div>
                    <div class="cost-item">
                        <span class="cost-label">Total Cost:</span>
                        <span class="cost-amount" id="totalCost">TZS 0.00</span>
                    </div>
                </div>

                <!-- Change Calculation -->
                <div class="cost-breakdown" id="changeCalculation" style="display: none; background: #f0fdf4; border-color: #10b981;">
                    <h4 style="margin-bottom: 15px; color: #065f46;">
                        <i class="fas fa-calculator"></i> Payment Calculation
                    </h4>
                    <div class="cost-item">
                        <span class="cost-label">Total Cost:</span>
                        <span class="cost-amount" id="displayTotalCost">TZS 0.00</span>
                    </div>
                    <div class="cost-item">
                        <span class="cost-label">Amount Paid:</span>
                        <span class="cost-amount" id="displayAmountPaid">TZS 0.00</span>
                    </div>
                    <div class="cost-item">
                        <span class="cost-label">Change Amount:</span>
                        <span class="cost-amount" id="changeAmount" style="color: #059669; font-weight: bold;">TZS 0.00</span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <button type="submit" name="generate_receipt" class="btn btn-primary" id="generateBtn" disabled>
                            <i class="fas fa-print"></i> Generate Receipt
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- All Receipts Table -->
        <div class="table-card">
            <h3><i class="fas fa-list"></i> All Receipts</h3>
            
            <div class="table-responsive">
                <table id="receiptsTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Patient Name</th>
                            <th>Card No</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Change</th>
                            <th>Payment Method</th>
                            <th>Cashier</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($receipt['receipt_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($receipt['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($receipt['card_no']); ?></td>
                            <td><strong>TZS <?php echo number_format($receipt['total_amount'], 2); ?></strong></td>
                            <td>TZS <?php echo number_format($receipt['amount_paid'], 2); ?></td>
                            <td>TZS <?php echo number_format($receipt['change_amount'], 2); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo ucfirst($receipt['payment_method']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($receipt['cashier_name']); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($receipt['issued_at'])); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn btn-warning btn-sm" onclick="printReceipt(<?php echo $receipt['payment_id']; ?>)">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DataTables Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#receiptsTable').DataTable({
                "pageLength": 25,
                "responsive": true,
                "order": [[8, 'desc']],
                "language": {
                    "search": "Search receipts:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ receipts",
                    "infoEmpty": "Showing 0 to 0 of 0 receipts",
                    "paginate": {
                        "previous": "Previous",
                        "next": "Next"
                    }
                }
            });
        });

        // Function to update cost breakdown when patient is selected
        function updateCostBreakdown() {
            const patientSelect = document.getElementById('patientSelect');
            const selectedOption = patientSelect.options[patientSelect.selectedIndex];
            const costBreakdown = document.getElementById('costBreakdown');
            const changeCalculation = document.getElementById('changeCalculation');
            const generateBtn = document.getElementById('generateBtn');

            if (selectedOption.value !== '') {
                // Get cost data from data attributes
                const consultationCost = parseFloat(selectedOption.getAttribute('data-consultation')) || 0;
                const medicineCost = parseFloat(selectedOption.getAttribute('data-medicine')) || 0;
                const labCost = parseFloat(selectedOption.getAttribute('data-lab')) || 0;
                const totalCost = parseFloat(selectedOption.getAttribute('data-total-cost')) || 0;

                // Update cost breakdown display
                document.getElementById('consultationCost').textContent = 'TZS ' + consultationCost.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('medicineCost').textContent = 'TZS ' + medicineCost.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('labCost').textContent = 'TZS ' + labCost.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('totalCost').textContent = 'TZS ' + totalCost.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // Update change calculation
                document.getElementById('displayTotalCost').textContent = 'TZS ' + totalCost.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // Show the cost breakdown and change calculation
                costBreakdown.style.display = 'block';
                changeCalculation.style.display = 'block';

                // Enable the generate button
                generateBtn.disabled = false;

                // Auto-fill amount paid with total cost
                document.getElementById('amountPaid').value = totalCost.toFixed(2);
                
                // Calculate change
                calculateChange();

            } else {
                // Hide cost breakdown and disable button if no patient selected
                costBreakdown.style.display = 'none';
                changeCalculation.style.display = 'none';
                generateBtn.disabled = true;
            }
        }

        // Function to calculate change amount
        function calculateChange() {
            const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
            const totalCost = parseFloat(document.getElementById('totalCost').textContent.replace('TZS ', '').replace(/,/g, '')) || 0;
            const changeAmount = amountPaid - totalCost;

            // Update display
            document.getElementById('displayAmountPaid').textContent = 'TZS ' + amountPaid.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            document.getElementById('changeAmount').textContent = 'TZS ' + changeAmount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Change color based on whether change is positive or negative
            if (changeAmount >= 0) {
                document.getElementById('changeAmount').style.color = '#059669';
            } else {
                document.getElementById('changeAmount').style.color = '#dc2626';
            }
        }

        // Function to print receipt
        function printReceipt(paymentId) {
            window.open('print_receipt.php?payment_id=' + paymentId, '_blank');
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