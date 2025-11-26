<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// Get patient ID from URL
$patient_id = $_GET['patient_id'] ?? 0;

if (!$patient_id) {
    die('Invalid patient ID');
}

// Get patient details
$patient_stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$patient_stmt->execute([$patient_id]);
$patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient not found');
}

// Get checking_form_id for the patient
$checking_stmt = $pdo->prepare("SELECT id FROM checking_forms WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
$checking_stmt->execute([$patient_id]);
$checking_form = $checking_stmt->fetch(PDO::FETCH_ASSOC);
$checking_form_id = $checking_form['id'] ?? 0;

// Get prescriptions with their ACTUAL prices from payments table
$prescriptions_stmt = $pdo->prepare("
    SELECT 
        pr.id,
        pr.medicine_name as name,
        pr.dosage,
        pr.frequency,
        pr.duration,
        pr.instructions,
        pr.status,
        pr.created_at,
        COALESCE(
            (SELECT medicine_amount FROM payments 
             WHERE checking_form_id = cf.id AND payment_type = 'medicine_and_lab' 
             ORDER BY id DESC LIMIT 1),
            (SELECT amount FROM payments 
             WHERE checking_form_id = cf.id AND payment_type = 'medicine' 
             ORDER BY id DESC LIMIT 1),
            0
        ) as total_medicine_amount,
        (SELECT COUNT(*) FROM prescriptions pr2 
         WHERE pr2.checking_form_id = cf.id AND pr2.status = 'dispensed') as total_prescriptions
    FROM prescriptions pr 
    JOIN checking_forms cf ON pr.checking_form_id = cf.id 
    WHERE cf.patient_id = ? AND pr.status = 'dispensed'
    ORDER BY pr.created_at DESC
");
$prescriptions_stmt->execute([$patient_id]);
$prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lab tests with their ACTUAL prices from payments table
$lab_tests_stmt = $pdo->prepare("
    SELECT 
        lt.id,
        lt.test_type as name,
        lt.results as instructions,
        'completed' as status,
        lt.created_at,
        COALESCE(
            (SELECT lab_amount FROM payments 
             WHERE checking_form_id = cf.id AND payment_type = 'medicine_and_lab' 
             ORDER BY id DESC LIMIT 1),
            (SELECT amount FROM payments 
             WHERE checking_form_id = cf.id AND payment_type = 'lab' 
             ORDER BY id DESC LIMIT 1),
            0
        ) as total_lab_amount,
        (SELECT COUNT(*) FROM laboratory_tests lt2 
         WHERE lt2.checking_form_id = cf.id AND lt2.status = 'completed') as total_lab_tests
    FROM laboratory_tests lt 
    JOIN checking_forms cf ON lt.checking_form_id = cf.id 
    WHERE cf.patient_id = ? AND lt.status = 'completed'
    ORDER BY lt.created_at DESC
");
$lab_tests_stmt->execute([$patient_id]);
$lab_tests = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment details for this patient
$payment_stmt = $pdo->prepare("
    SELECT 
        amount,
        medicine_amount,
        lab_amount,
        payment_type,
        status,
        created_at
    FROM payments 
    WHERE patient_id = ? AND checking_form_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$payment_stmt->execute([$patient_id, $checking_form_id]);
$payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate individual prices based on ACTUAL payment data
$medicine_total = 0;
$lab_total = 0;
$grand_total = 0;

// Process prescriptions with individual pricing
$prescriptions_with_prices = [];
foreach ($prescriptions as $prescription) {
    $price = 0;
    
    if ($payment) {
        if ($payment['payment_type'] === 'medicine_and_lab' && $payment['medicine_amount'] > 0) {
            // For combined payments, divide medicine amount equally among prescriptions
            $price = $prescription['total_prescriptions'] > 0 
                ? $payment['medicine_amount'] / $prescription['total_prescriptions'] 
                : 0;
        } elseif ($payment['payment_type'] === 'medicine' && $payment['amount'] > 0) {
            // For medicine-only payments, divide equally among prescriptions
            $price = $prescription['total_prescriptions'] > 0 
                ? $payment['amount'] / $prescription['total_prescriptions'] 
                : 0;
        }
    }
    
    $prescriptions_with_prices[] = [
        'type' => 'medicine',
        'id' => $prescription['id'],
        'name' => $prescription['name'],
        'dosage' => $prescription['dosage'],
        'frequency' => $prescription['frequency'],
        'duration' => $prescription['duration'],
        'instructions' => $prescription['instructions'],
        'status' => $prescription['status'],
        'price' => $price,
        'created_at' => $prescription['created_at']
    ];
    
    $medicine_total += $price;
}

// Process lab tests with individual pricing
$lab_tests_with_prices = [];
foreach ($lab_tests as $lab_test) {
    $price = 0;
    
    if ($payment) {
        if ($payment['payment_type'] === 'medicine_and_lab' && $payment['lab_amount'] > 0) {
            // For combined payments, divide lab amount equally among lab tests
            $price = $lab_test['total_lab_tests'] > 0 
                ? $payment['lab_amount'] / $lab_test['total_lab_tests'] 
                : 0;
        } elseif ($payment['payment_type'] === 'lab' && $payment['amount'] > 0) {
            // For lab-only payments, divide equally among lab tests
            $price = $lab_test['total_lab_tests'] > 0 
                ? $payment['amount'] / $lab_test['total_lab_tests'] 
                : 0;
        }
    }
    
    $lab_tests_with_prices[] = [
        'type' => 'lab_test',
        'id' => $lab_test['id'],
        'name' => $lab_test['name'],
        'dosage' => '',
        'frequency' => '',
        'duration' => '',
        'instructions' => $lab_test['instructions'],
        'status' => $lab_test['status'],
        'price' => $price,
        'created_at' => $lab_test['created_at']
    ];
    
    $lab_total += $price;
}

// Combine all items
$items = array_merge($prescriptions_with_prices, $lab_tests_with_prices);

// Calculate grand total
if ($payment) {
    if ($payment['payment_type'] === 'medicine_and_lab') {
        $grand_total = $payment['amount'];
        $medicine_total = $payment['medicine_amount'] ?? 0;
        $lab_total = $payment['lab_amount'] ?? 0;
    } else {
        $grand_total = $payment['amount'];
    }
} else {
    $grand_total = $medicine_total + $lab_total;
}

// Count items
$medicine_count = count($prescriptions_with_prices);
$lab_count = count($lab_tests_with_prices);
$total_items = $medicine_count + $lab_count;

// Payment type display
$payment_type_display = 'No Payment Record';
if ($payment) {
    switch ($payment['payment_type']) {
        case 'medicine_and_lab':
            $payment_type_display = 'COMBINED PAYMENT (MEDICINE & LABORATORY)';
            break;
        case 'medicine':
            $payment_type_display = 'MEDICINE PAYMENT ONLY';
            break;
        case 'lab':
            $payment_type_display = 'LABORATORY PAYMENT ONLY';
            break;
        default:
            $payment_type_display = strtoupper(str_replace('_', ' ', $payment['payment_type']));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Report - Almajyd Dispensary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: white;
            color: #333;
            line-height: 1.4;
            font-size: 12px;
        }
        
        .print-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 15px;
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #10b981;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .clinic-info {
            text-align: center;
            flex: 1;
        }
        
        .clinic-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 3px;
        }
        
        .clinic-address {
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
        }
        
        .document-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
            color: #333;
        }
        
        /* Payment Info */
        .payment-info {
            background: #f0f9ff;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #3b82f6;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .payment-type {
            font-weight: bold;
            color: #1e40af;
            font-size: 11px;
        }
        
        .payment-details {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Patient Info */
        .patient-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .info-group {
            margin-bottom: 3px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            font-size: 10px;
        }
        
        .info-value {
            color: #333;
            font-size: 11px;
        }
        
        /* Summary */
        .summary {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .summary-item {
            padding: 6px;
            background: #e8f5e8;
            border-radius: 4px;
            border: 1px solid #10b981;
        }
        
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 2px;
        }
        
        .summary-label {
            font-size: 9px;
            color: #666;
            font-weight: 600;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th {
            background: #2c5aa0;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-size: 10px;
            border: 1px solid #1e4080;
        }
        
        .items-table td {
            padding: 5px 4px;
            border: 1px solid #ddd;
            font-size: 10px;
            vertical-align: top;
        }
        
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .medicine-row {
            background: #f0fdf4 !important;
        }
        
        .lab-row {
            background: #f0f9ff !important;
        }
        
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .type-medicine {
            background: #d1fae5;
            color: #065f46;
        }
        
        .type-lab {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }
        
        .status-dispensed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .price {
            font-weight: bold;
            color: #10b981;
            text-align: right;
        }
        
        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #333;
        }
        
        .total-section {
            margin-bottom: 15px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 3px 0;
        }
        
        .total-label {
            font-weight: bold;
            color: #555;
            font-size: 11px;
        }
        
        .total-amount {
            font-weight: bold;
            color: #10b981;
            font-size: 11px;
        }
        
        .grand-total {
            border-top: 1px solid #ddd;
            padding-top: 8px;
            margin-top: 8px;
            font-size: 12px;
        }
        
        .grand-total .total-label {
            color: #2c5aa0;
            font-size: 12px;
        }
        
        .grand-total .total-amount {
            color: #2c5aa0;
            font-size: 14px;
        }
        
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .signature {
            text-align: center;
        }
        
        .signature-line {
            width: 150px;
            border-bottom: 1px solid #333;
            margin: 0 auto 5px auto;
            padding-top: 20px;
        }
        
        .signature-name {
            font-weight: bold;
            color: #333;
            font-size: 11px;
        }
        
        .signature-title {
            font-size: 9px;
            color: #666;
        }
        
        .print-info {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #eee;
        }
        
        /* Action Buttons */
        .action-buttons {
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            margin: 0 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print {
            background: #10b981;
            color: white;
        }
        
        .btn-back {
            background: #3b82f6;
            color: white;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                font-size: 10pt;
            }
            
            .print-container {
                max-width: 100%;
                padding: 10px;
                margin: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .logo {
                border: 2px solid #10b981 !important;
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 20px 10px;
            color: #666;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        /* Compact layout for instructions */
        .compact-instructions {
            font-size: 9px;
            color: #666;
            line-height: 1.3;
        }
        
        /* Price breakdown */
        .price-breakdown {
            font-size: 9px;
            color: #666;
            font-style: italic;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print Report
        </button>
        <a href="dashboard.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="print-container">
        <!-- Header with Logo -->
        <div class="header">
            <div class="logo-section">
                <div class="logo">
                    <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
                </div>
            </div>
            <div class="clinic-info">
                <div class="clinic-name">ALMAJYD DISPENSARY</div>
                <div class="clinic-address">TOMONDO - ZANZIBAR | Tel: +255 777 567 478</div>
                <div class="document-title">MEDICAL TREATMENT REPORT</div>
            </div>
            <div style="width: 60px;"></div> <!-- Spacer for balance -->
        </div>

        <!-- Payment Information -->
        <?php if ($payment): ?>
        <div class="payment-info">
            <div class="payment-type"><?php echo $payment_type_display; ?></div>
            <div class="payment-details">
                Payment Status: <strong><?php echo strtoupper($payment['status']); ?></strong> | 
                Date: <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Patient Information -->
        <div class="patient-info">
            <div class="info-group">
                <span class="info-label">Patient Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
            </div>
            <div class="info-group">
                <span class="info-label">Card No:</span>
                <span class="info-value"><?php echo htmlspecialchars($patient['card_no']); ?></span>
            </div>
            <div class="info-group">
                <span class="info-label">Age/Gender:</span>
                <span class="info-value"><?php echo $patient['age'] ? $patient['age'] . ' yrs / ' . ucfirst($patient['gender']) : 'N/A'; ?></span>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-item">
                <div class="summary-value"><?php echo $total_items; ?></div>
                <div class="summary-label">Total Items</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $medicine_count; ?></div>
                <div class="summary-label">Medicines</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $lab_count; ?></div>
                <div class="summary-label">Lab Tests</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">TSh <?php echo number_format($medicine_total, 2); ?></div>
                <div class="summary-label">Medicines Total</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">TSh <?php echo number_format($lab_total, 2); ?></div>
                <div class="summary-label">Lab Tests Total</div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">Type</th>
                    <th width="25%">Item Name</th>
                    <th width="15%">Specifications</th>
                    <th width="30%">Details/Results</th>
                    <th width="10%">Status</th>
                    <th width="15%">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-file-medical-alt"></i><br>
                            No medical items found for this patient
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr class="<?php echo $item['type'] == 'medicine' ? 'medicine-row' : 'lab-row'; ?>">
                            <!-- Type -->
                            <td>
                                <span class="type-badge type-<?php echo $item['type']; ?>">
                                    <?php echo $item['type'] == 'medicine' ? 'MED' : 'LAB'; ?>
                                </span>
                            </td>
                            
                            <!-- Item Name -->
                            <td>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            </td>
                            
                            <!-- Specifications -->
                            <td>
                                <?php if ($item['type'] == 'medicine'): ?>
                                    <?php if (!empty($item['dosage'])): ?>
                                        <strong>Dose:</strong> <?php echo htmlspecialchars($item['dosage']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($item['frequency'])): ?>
                                        <strong>Freq:</strong> <?php echo htmlspecialchars($item['frequency']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($item['duration'])): ?>
                                        <strong>Dur:</strong> <?php echo htmlspecialchars($item['duration']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em>Laboratory Test</em>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Details/Results -->
                            <td class="compact-instructions">
                                <?php if (!empty($item['instructions'])): ?>
                                    <?php 
                                    $instructions = $item['instructions'];
                                    if (strlen($instructions) > 80) {
                                        $instructions = substr($instructions, 0, 80) . '...';
                                    }
                                    echo htmlspecialchars($instructions); 
                                    ?>
                                <?php else: ?>
                                    <em>No additional details</em>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Status -->
                            <td>
                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                    <?php echo strtoupper($item['status']); ?>
                                </span>
                            </td>
                            
                            <!-- Price -->
                            <td class="price">
                                <?php if ($item['price'] > 0): ?>
                                    TSh <?php echo number_format($item['price'], 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer">
            <!-- Total Amounts Breakdown -->
            <div class="total-section">
                <?php if ($payment && $payment['payment_type'] === 'medicine_and_lab'): ?>
                    <div class="total-row">
                        <span class="total-label">Medicines Total (<?php echo $medicine_count; ?> items):</span>
                        <span class="total-amount">TSh <?php echo number_format($medicine_total, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Laboratory Tests Total (<?php echo $lab_count; ?> tests):</span>
                        <span class="total-amount">TSh <?php echo number_format($lab_total, 2); ?></span>
                    </div>
                <?php elseif ($payment && $payment['payment_type'] === 'medicine'): ?>
                    <div class="total-row">
                        <span class="total-label">Medicines Total (<?php echo $medicine_count; ?> items):</span>
                        <span class="total-amount">TSh <?php echo number_format($medicine_total, 2); ?></span>
                    </div>
                <?php elseif ($payment && $payment['payment_type'] === 'lab'): ?>
                    <div class="total-row">
                        <span class="total-label">Laboratory Tests Total (<?php echo $lab_count; ?> tests):</span>
                        <span class="total-amount">TSh <?php echo number_format($lab_total, 2); ?></span>
                    </div>
                <?php else: ?>
                    <div class="total-row">
                        <span class="total-label">Medicines Total:</span>
                        <span class="total-amount">TSh <?php echo number_format($medicine_total, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Laboratory Tests Total:</span>
                        <span class="total-amount">TSh <?php echo number_format($lab_total, 2); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="total-row grand-total">
                    <span class="total-label">GRAND TOTAL:</span>
                    <span class="total-amount">TSh <?php echo number_format($grand_total, 2); ?></span>
                </div>

                <?php if ($payment && $payment['payment_type'] === 'medicine_and_lab'): ?>
                    <div class="total-row price-breakdown">
                        <span>Note: Combined payment distributed equally among items</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Signatures -->
            <div class="signatures">
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name">Pharmacy Department</div>
                    <div class="signature-title">ALMAJYD DISPENSARY</div>
                </div>
                
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name">Authorized Signature</div>
                    <div class="signature-title">Verified By</div>
                </div>
            </div>
            
            <!-- Print Info -->
            <div class="print-info">
                Report generated on: <?php echo date('F j, Y \a\t h:i A'); ?> | 
                This is an official medical report from Almajyd Dispensary
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            // Auto-print when page loads (optional)
            // setTimeout(() => { window.print(); }, 500);
        };
    </script>
</body>
</html>